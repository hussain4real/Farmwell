<?php

namespace App\Http\Controllers;

use App\Actions\Teams\ResolveWorkspacePlan;
use App\Ai\Agents\FarmwellAssistant;
use App\Enums\TeamRole;
use App\Http\Requests\Assistant\ResolveAssistantApprovalsRequest;
use App\Http\Requests\Assistant\SendAssistantMessageRequest;
use App\Models\Membership;
use App\Models\Team;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Laravel\Ai\Approvals\Decision;
use Laravel\Ai\Approvals\Decisions;
use Laravel\Ai\Approvals\PendingApproval;
use Laravel\Ai\Exceptions\ApprovalMismatchException;
use Laravel\Ai\Models\Conversation;
use Laravel\Ai\Models\ConversationMessage;
use Laravel\Ai\Responses\StreamableAgentResponse;
use Laravel\Ai\Storage\DatabaseConversationStore;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class AssistantController extends Controller
{
    public function index(Request $request, Team $currentTeam, ResolveWorkspacePlan $plans, DatabaseConversationStore $store): JsonResponse
    {
        $membership = $this->membershipFor($request, $currentTeam, $plans);
        $user = $request->user();
        assert($user instanceof User);

        $conversations = Conversation::query()
            ->where('participant_type', $membership->getMorphClass())
            ->where('participant_id', $membership->getKey())
            ->latest('updated_at')
            ->limit(15)
            ->get(['id', 'title', 'updated_at']);

        $requestedConversation = $request->query('conversation_id');
        $activeId = is_string($requestedConversation) && $requestedConversation !== ''
            ? $requestedConversation
            : $conversations->first()?->id;

        $messages = [];
        $pendingApprovals = [];

        if (is_string($activeId) && $activeId !== '') {
            $this->verifyOwnership($store, $activeId, $membership);

            $messages = ConversationMessage::query()
                ->where('conversation_id', $activeId)
                ->orderBy('id')
                ->get(['id', 'role', 'content', 'steps', 'created_at'])
                ->map(function (ConversationMessage $message): array {
                    $inputForm = null;
                    $visibleToolCalls = [];

                    foreach ($message->tool_calls as $call) {
                        $name = $call['name'] ?? '';

                        if ($name === 'AskUserInputTool') {
                            $rawResult = $call['result'] ?? null;
                            $decoded = is_string($rawResult) ? json_decode($rawResult, true) : $rawResult;

                            if (
                                is_array($decoded)
                                && ($decoded['ui_type'] ?? null) === 'input_form'
                                && is_array($decoded['fields'] ?? null)
                                && $decoded['fields'] !== []
                            ) {
                                $inputForm = $decoded;
                            }

                            continue;
                        }

                        $visibleToolCalls[] = [
                            'id' => $call['id'] ?? '',
                            'name' => $name,
                            'successful' => empty($call['failed']) && empty($call['denied']),
                            'denied' => ! empty($call['denied']),
                            'answered' => PendingApproval::isAnswered($call),
                        ];
                    }

                    return [
                        'id' => $message->id,
                        'role' => $message->role,
                        'content' => $message->content ?? '',
                        'tool_calls' => $visibleToolCalls,
                        'input_form' => $inputForm,
                    ];
                })
                ->all();

            $pendingApprovals = array_map(
                static fn (PendingApproval $approval): array => $approval->toArray(),
                $store->pendingApprovalsFor($activeId),
            );
        }

        return response()->json([
            'conversations' => $conversations->map(static function (Conversation $conversation): array {
                $updatedAt = $conversation->getAttribute('updated_at');

                return [
                    'id' => $conversation->getAttribute('id'),
                    'title' => $conversation->getAttribute('title'),
                    'updated_at' => $updatedAt instanceof CarbonInterface ? $updatedAt->toIso8601String() : null,
                ];
            })->all(),
            'activeConversationId' => $activeId,
            'messages' => $messages,
            'pendingApprovals' => $pendingApprovals,
            'starterPrompts' => $this->starterPromptsFor($currentTeam, $user),
        ]);
    }

    public function send(
        SendAssistantMessageRequest $request,
        Team $currentTeam,
        ResolveWorkspacePlan $plans,
        DatabaseConversationStore $store,
    ): StreamedResponse|JsonResponse {
        $membership = $this->membershipFor($request, $currentTeam, $plans);
        $user = $request->user();
        assert($user instanceof User);

        $conversationId = $request->validated('conversation_id');
        if (is_string($conversationId) && $conversationId !== '') {
            $this->verifyOwnership($store, $conversationId, $membership);

            $pending = $store->pendingApprovalsFor($conversationId);
            if ($pending !== []) {
                return response()->json([
                    'message' => __('Please approve or decline the pending action before sending a new message.'),
                    'pendingApprovals' => array_map(
                        static fn (PendingApproval $approval): array => $approval->toArray(),
                        $pending,
                    ),
                ], 409);
            }
        } else {
            $conversationId = null;
        }

        $assistant = (new FarmwellAssistant($currentTeam, $user))
            ->continueOrStart($conversationId, as: $membership);

        $stream = $assistant->stream(
            (string) $request->validated('message'),
            model: $this->assistantModel(),
        );

        return $this->streamAgent($stream);
    }

    public function resolveApprovals(
        ResolveAssistantApprovalsRequest $request,
        Team $currentTeam,
        string $conversation,
        ResolveWorkspacePlan $plans,
        DatabaseConversationStore $store,
    ): StreamedResponse {
        $membership = $this->membershipFor($request, $currentTeam, $plans);
        $this->verifyOwnership($store, $conversation, $membership);

        $user = $request->user();
        assert($user instanceof User);

        /** @var array<string, array{action: string, arguments?: array<string, mixed>, reason?: string|null}> $rawDecisions */
        $rawDecisions = $request->validated('decisions');
        $mapped = [];

        foreach ($rawDecisions as $id => $entry) {
            $mapped[$id] = match ($entry['action']) {
                'approve' => Decision::approve(),
                'edit' => Decision::edit($entry['arguments'] ?? []),
                default => Decision::reject($entry['reason'] ?? 'Declined by user.'),
            };
        }

        $stream = (new FarmwellAssistant($currentTeam, $user))
            ->continue($conversation, as: $membership)
            ->stream(Decisions::from($mapped), model: $this->assistantModel());

        return $this->streamAgent($stream);
    }

    public function destroy(
        Request $request,
        Team $currentTeam,
        string $conversation,
        ResolveWorkspacePlan $plans,
        DatabaseConversationStore $store,
    ): Response {
        $membership = $this->membershipFor($request, $currentTeam, $plans);
        $this->verifyOwnership($store, $conversation, $membership);

        ConversationMessage::query()->where('conversation_id', $conversation)->delete();
        Conversation::query()->whereKey($conversation)->delete();

        return response()->noContent();
    }

    private function membershipFor(Request $request, Team $team, ResolveWorkspacePlan $plans): Membership
    {
        abort_if(
            $request->attributes->has('platform_access_team') || ! $plans->handle($team)->allowsAssistant(),
            403,
        );

        $user = $request->user();
        assert($user instanceof User);

        return $team->memberships()->where('user_id', $user->id)->firstOrFail();
    }

    private function verifyOwnership(DatabaseConversationStore $store, string $conversationId, Membership $membership): void
    {
        abort_unless(
            $store->conversationBelongsTo($conversationId, $membership->getMorphClass(), $membership->getKey()),
            404,
        );
    }

    private function assistantModel(): ?string
    {
        $model = config('ai.assistant_model');

        return is_string($model) && $model !== '' ? $model : null;
    }

    private function streamAgent(StreamableAgentResponse $stream): StreamedResponse
    {
        return response()->stream(function () use ($stream): void {
            try {
                foreach ($stream as $event) {
                    echo 'data: '.((string) $event)."\n\n";
                }

                if ($stream->conversationId !== null) {
                    echo 'data: '.json_encode([
                        'type' => 'conversation',
                        'conversation_id' => $stream->conversationId,
                    ], JSON_THROW_ON_ERROR)."\n\n";
                }
            } catch (ApprovalMismatchException $exception) {
                echo 'data: '.json_encode([
                    'type' => 'approval_mismatch',
                    'message' => $exception->getMessage(),
                    'approvals' => $exception->pendingApprovals->values()->toArray(),
                ], JSON_THROW_ON_ERROR)."\n\n";
            } catch (Throwable $exception) {
                report($exception);

                echo 'data: '.json_encode([
                    'type' => 'error',
                    'message' => __('The assistant could not complete this request right now. Please try again.'),
                ], JSON_THROW_ON_ERROR)."\n\n";
            }

            echo "data: [DONE]\n\n";
        }, headers: [
            'Cache-Control' => 'no-cache, no-transform',
            'Content-Type' => 'text/event-stream',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * @return list<string>
     */
    private function starterPromptsFor(Team $team, User $user): array
    {
        $gate = Gate::forUser($user);

        if ($user->teamRole($team) === TeamRole::Investor && $gate->allows('viewInvestorPortal', $team)) {
            return [
                'Summarize my investment balance and recorded funding',
                'Do I have any pending approval requests?',
            ];
        }

        if ($gate->allows('manageFinance', $team)) {
            return [
                'What is our current funding balance and recent spend?',
                'Request a funding release for this farm',
                'Record a new farm expense for today',
            ];
        }

        return [
            'Give me a quick overview of active cycles, stock, and open tasks',
            'Log today\'s daily production metrics',
        ];
    }
}
