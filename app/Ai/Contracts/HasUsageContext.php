<?php

namespace App\Ai\Contracts;

use App\Enums\AiUsageFeature;

interface HasUsageContext
{
    /**
     * Return team, user, feature, and optional metadata for telemetry attribution.
     *
     * @return array{
     *     team_id: ?int,
     *     user_id: ?int,
     *     feature: AiUsageFeature,
     *     metadata?: array<string, mixed>,
     * }
     */
    public function usageContext(): array;
}
