<?php

namespace App\Enums;

enum WorkspacePlan: string
{
    case Starter = 'starter';
    case Pro = 'pro';
    case Investor = 'investor';

    public function label(): string
    {
        return match ($this) {
            self::Starter => 'Starter (Free)',
            self::Pro => 'Pro Farm',
            self::Investor => 'Investor / Pilot',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Starter => '1 farm, up to 2 email team members, daily operations, stock & health logs.',
            self::Pro => 'Unlimited farms and team members, plus SMS phone login invitations.',
            self::Investor => 'Full platform including Investor Portal, approvals, statements, and AI summaries.',
        };
    }

    public function maxFarms(): ?int
    {
        return match ($this) {
            self::Starter => 1,
            self::Pro, self::Investor => null,
        };
    }

    public function maxMembers(): ?int
    {
        return match ($this) {
            self::Starter => 2,
            self::Pro, self::Investor => null,
        };
    }

    public function allowsPhoneInvitations(): bool
    {
        return match ($this) {
            self::Starter => false,
            self::Pro, self::Investor => true,
        };
    }

    public function allowsAiSummaries(): bool
    {
        return match ($this) {
            self::Starter, self::Pro => false,
            self::Investor => true,
        };
    }

    public function allowsInvestorMode(): bool
    {
        return match ($this) {
            self::Starter, self::Pro => false,
            self::Investor => true,
        };
    }

    /**
     * Build a structured plan summary for frontend props.
     *
     * @return array{
     *     key: string,
     *     label: string,
     *     description: string,
     *     maxFarms: int|null,
     *     maxMembers: int|null,
     *     allowsPhoneInvitations: bool,
     *     allowsAiSummaries: bool,
     *     allowsInvestorMode: bool,
     * }
     */
    public function summary(): array
    {
        return [
            'key' => $this->value,
            'label' => $this->label(),
            'description' => $this->description(),
            'maxFarms' => $this->maxFarms(),
            'maxMembers' => $this->maxMembers(),
            'allowsPhoneInvitations' => $this->allowsPhoneInvitations(),
            'allowsAiSummaries' => $this->allowsAiSummaries(),
            'allowsInvestorMode' => $this->allowsInvestorMode(),
        ];
    }

    /**
     * Get selectable workspace plan options for Platform Admin.
     *
     * @return list<array{value: string, label: string, description: string}>
     */
    public static function options(): array
    {
        return array_map(
            static fn (self $plan): array => [
                'value' => $plan->value,
                'label' => $plan->label(),
                'description' => $plan->description(),
            ],
            self::cases(),
        );
    }
}
