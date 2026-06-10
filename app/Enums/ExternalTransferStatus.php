<?php

namespace App\Enums;

enum ExternalTransferStatus: string
{
    case Recorded = 'recorded';
    case ProofAttached = 'proof_attached';
    case PendingReconciliation = 'pending_reconciliation';
    case Reconciled = 'reconciled';
    case Disputed = 'disputed';
    case Voided = 'voided';

    public function label(): string
    {
        return match ($this) {
            self::Recorded => 'Recorded',
            self::ProofAttached => 'Proof attached',
            self::PendingReconciliation => 'Pending reconciliation',
            self::Reconciled => 'Reconciled',
            self::Disputed => 'Disputed',
            self::Voided => 'Voided',
        };
    }

    /**
     * Get options for forms.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $status): array => ['value' => $status->value, 'label' => $status->label()],
            self::cases(),
        );
    }
}
