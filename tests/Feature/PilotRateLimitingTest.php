<?php

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Route;

test('pilot sensitive routes use the appropriate rate limit', function (string $route, string $limiter) {
    expect(Route::getRoutes()->getByName($route)->gatherMiddleware())->toContain('throttle:'.$limiter);
})->with([
    ['teams.invitations.store', 'invitations'],
    ['investors.approvals.decisions.store', 'approval-decisions'],
    ['ai-summaries.store', 'ai-generation'],
    ...array_map(fn ($prefix) => [$prefix.'.store', 'report-generation'], ['activity-exports', 'finance.budget-exports', 'harvests.exports', 'harvests.sales.exports', 'harvests.distributions.exports', 'investor-statements']),
    ...array_map(fn ($prefix) => [$prefix.'.download', 'report-downloads'], ['activity-exports', 'finance.budget-exports', 'harvests.exports', 'harvests.sales.exports', 'harvests.distributions.exports', 'investor-statements']),
]);

test('sensitive request limits are enforced separately for each user and action', function (string $name, int $limit) {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach([$user->id => ['role' => TeamRole::Owner->value], $other->id => ['role' => TeamRole::Admin->value]]);
    $user->switchTeam($team);
    $other->switchTeam($team);

    $url = match ($name) {
        'invitations' => route('teams.invitations.store', $team),
        'approval-decisions' => route('investors.approvals.decisions.store', [$team, 999999]),
        'report-generation' => route('activity-exports.store', $team),
        'report-downloads' => route('activity-exports.download', [$team, 999999]),
        'ai-generation' => route('ai-summaries.store', $team),
    };
    $method = $name === 'report-downloads' ? 'get' : 'post';

    $this->freezeTime();
    $this->actingAs($user);
    for ($attempt = 1; $attempt <= $limit; $attempt++) {
        expect($this->$method($url)->getStatusCode())->toBeLessThan(500)->not->toBe(429);
    }
    $this->$method($url)->assertTooManyRequests()->assertHeader('Retry-After', '60');
    expect($this->actingAs($other)->$method($url)->getStatusCode())->not->toBe(429);
    if ($name !== 'ai-generation') {
        expect($this->actingAs($user)->post(route('ai-summaries.store', $team))->getStatusCode())->not->toBe(429);
    }
    $this->travel(61)->seconds();
    expect($this->actingAs($user)->$method($url)->getStatusCode())->not->toBe(429);
})->with([
    ['invitations', 10], ['approval-decisions', 20], ['report-generation', 10], ['report-downloads', 60], ['ai-generation', 10],
]);
