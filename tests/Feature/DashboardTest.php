<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
});

test('guests are redirected to the login page', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    $response = $this
        ->actingAs($user)
        ->get(route('dashboard'));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('farms/Index')
            ->where('stats.farms', 0)
        );
});

test('unverified users are redirected to the email verification prompt', function () {
    $user = User::factory()->unverified()->create();

    $response = $this
        ->actingAs($user)
        ->get(route('dashboard'));

    $response->assertRedirect(route('verification.notice'));
});
