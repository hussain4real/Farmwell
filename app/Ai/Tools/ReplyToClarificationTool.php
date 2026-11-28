<?php

namespace App\Ai\Tools;

use App\Actions\Investors\ReplyToClarification;
use App\Ai\Tools\Concerns\InteractsWithFarmwellContext;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Validation\ValidationException;
use Laravel\Ai\Approvals\Approval;
use Laravel\Ai\Concerns\InteractsWithApprovals;
use Laravel\Ai\Contracts\Approvable;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class ReplyToClarificationTool implements Approvable, Tool
{
    use InteractsWithApprovals;
    use InteractsWithFarmwellContext;

    public function description(): string
    {
        return 'Post the farm team\'s reply directly on a request awaiting investor clarification. Use ListPendingApprovalsTool to read the decision comment and identify the request.';
    }

    protected function needsApproval(Request $request): Approval
    {
        $id = $request->integer('approval_request_id');

        return Approval::required("Send clarification reply on request #{$id}");
    }

    public function handle(Request $request): string
    {
        if (! ReplyToClarification::canReply($this->user, $this->team)) {
            return $this->encodeResult(['error' => 'You do not have permission to reply to clarification requests.']);
        }

        try {
            $data = $request->validate(ReplyToClarification::rules() + ['approval_request_id' => ['required', 'integer', 'min:1']]);
            $id = (int) $data['approval_request_id'];
            unset($data['approval_request_id']);

            app(ReplyToClarification::class)->handle($this->team, $this->user, $id, $data['body']);
        } catch (ValidationException $exception) {
            return $this->encodeResult(['error' => $exception->getMessage(), 'fields' => $exception->errors()]);
        } catch (ModelNotFoundException) {
            return $this->encodeResult(['error' => 'Record not found in this workspace.']);
        }

        return $this->encodeResult(['replied' => true, 'approval_request_id' => $id]);
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'approval_request_id' => $schema->integer()->required(),
            'body' => $schema->string()->description('The exact reply to send to the investor.')->required(),
        ];
    }
}
