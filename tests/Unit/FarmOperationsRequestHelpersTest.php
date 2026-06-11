<?php

use App\Http\Requests\FarmOperations\ConvertWhatsappIntakeRequest;
use App\Http\Requests\FarmOperations\RejectWhatsappIntakeRequest;
use App\Http\Requests\FarmOperations\UpdateFarmTaskStatusRequest;
use App\Models\FarmTask;
use App\Models\Team;
use App\Models\WhatsappIntake;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('farm operations form requests resolve route-bound team intake and task models', function () {
    $team = Team::factory()->create();
    $intake = WhatsappIntake::factory()->create(['team_id' => $team->id]);
    $task = FarmTask::factory()->create(['team_id' => $team->id]);

    $convertRequest = ConvertWhatsappIntakeRequest::create('/', 'POST');
    $convertRequest->setRouteResolver(fn () => farmwellFarmOperationsRequestRouteStub([
        'current_team' => $team,
        'whatsapp_intake' => $intake,
    ]));

    $rejectRequest = RejectWhatsappIntakeRequest::create('/', 'POST');
    $rejectRequest->setRouteResolver(fn () => farmwellFarmOperationsRequestRouteStub([
        'current_team' => $team,
        'whatsapp_intake' => $intake,
    ]));

    $taskStatusRequest = UpdateFarmTaskStatusRequest::create('/', 'PATCH');
    $taskStatusRequest->setRouteResolver(fn () => farmwellFarmOperationsRequestRouteStub([
        'current_team' => $team,
        'farm_task' => $task,
    ]));

    expect($convertRequest->whatsappIntake()->is($intake))->toBeTrue()
        ->and($rejectRequest->team()->is($team))->toBeTrue()
        ->and($rejectRequest->whatsappIntake()->is($intake))->toBeTrue()
        ->and($taskStatusRequest->team()->is($team))->toBeTrue()
        ->and($taskStatusRequest->farmTask()->is($task))->toBeTrue();
});

/**
 * @param  array<string, mixed>  $parameters
 */
function farmwellFarmOperationsRequestRouteStub(array $parameters): object
{
    return new class($parameters)
    {
        /**
         * @param  array<string, mixed>  $parameters
         */
        public function __construct(private array $parameters)
        {
            //
        }

        public function parameter(string $key, mixed $default = null): mixed
        {
            return $this->parameters[$key] ?? $default;
        }
    };
}
