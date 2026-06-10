<?php

use App\Enums\TeamPermission;
use App\Enums\TeamRole;

test('tenant control roles include settings and audit permissions', function () {
    expect(TeamRole::Owner->permissions())->toContain(TeamPermission::ManageSettings)
        ->and(TeamRole::Owner->permissions())->toContain(TeamPermission::ViewAuditEvents)
        ->and(TeamRole::Admin->permissions())->toContain(TeamPermission::ManageSettings)
        ->and(TeamRole::Admin->permissions())->toContain(TeamPermission::ManageFarmOperations)
        ->and(TeamRole::Admin->permissions())->toContain(TeamPermission::ViewFinance)
        ->and(TeamRole::Admin->permissions())->toContain(TeamPermission::ManageFinance)
        ->and(TeamRole::Admin->permissions())->not->toContain(TeamPermission::ViewAuditEvents)
        ->and(TeamRole::Member->permissions())->toBe([TeamPermission::ViewFarmOperations]);
});
