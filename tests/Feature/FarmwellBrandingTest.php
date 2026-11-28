<?php

use Illuminate\Support\Env;
use Inertia\Testing\AssertableInertia as Assert;

test('default application and sender names use Farmwell when branding variables are absent', function () {
    $environment = Env::getRepository();
    $keys = ['APP_NAME', 'MAIL_FROM_NAME', 'LOG_SLACK_USERNAME'];
    $previous = [];

    foreach ($keys as $key) {
        $previous[$key] = $environment->get($key);
        $environment->clear($key);
    }

    try {
        $app = require config_path('app.php');
        $mail = require config_path('mail.php');
        $logging = require config_path('logging.php');

        expect($app['name'])->toBe('Farmwell')
            ->and($mail['from']['name'])->toBe('Farmwell')
            ->and($logging['channels']['slack']['username'])->toBe('Farmwell');

        config(['app.name' => $app['name']]);
        $this->withoutVite()
            ->get(route('login'))
            ->assertOk()
            ->assertSee('<title>Farmwell</title>', false)
            ->assertInertia(fn (Assert $page) => $page->where('name', 'Farmwell'));
    } finally {
        foreach ($previous as $key => $value) {
            if ($value !== null) {
                $environment->set($key, $value);
            }
        }
    }
});
