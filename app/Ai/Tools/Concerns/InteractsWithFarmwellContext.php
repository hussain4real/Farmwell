<?php

namespace App\Ai\Tools\Concerns;

use App\Actions\Audit\RecordAuditEvent;
use App\Models\Team;
use App\Models\User;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

trait InteractsWithFarmwellContext
{
    public function __construct(
        protected Team $team,
        protected User $user,
    ) {}

    protected function allows(string $ability): bool
    {
        return Gate::forUser($this->user)->allows($ability, $this->team);
    }

    protected function parsePositiveMinorAmount(mixed $raw): ?int
    {
        $minor = $this->parseNonNegativeMinorAmount($raw);

        return $minor !== null && $minor > 0 ? $minor : null;
    }

    protected function parseNonNegativeMinorAmount(mixed $raw): ?int
    {
        if (! is_scalar($raw)) {
            return null;
        }

        $normalized = trim((string) $raw);
        if (! preg_match('/^\d+(?:\.\d{1,2})?$/D', $normalized)) {
            return null;
        }

        return Money::toMinorUnit($normalized);
    }

    protected function parseDateString(mixed $raw): ?string
    {
        if (! is_string($raw)) {
            return null;
        }

        $trimmed = trim($raw);
        if (! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $trimmed, $matches)) {
            return null;
        }

        return checkdate((int) $matches[2], (int) $matches[3], (int) $matches[1]) ? $trimmed : null;
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    protected function recordAssistantAudit(string $action, ?Model $subject = null, array $metadata = []): void
    {
        app(RecordAuditEvent::class)->handle(
            team: $this->team,
            actor: $this->user,
            action: 'assistant.'.$action,
            subject: $subject,
            metadata: ['via' => 'farmwell_assistant', ...$metadata],
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function encodeResult(array $payload): string
    {
        return json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }
}
