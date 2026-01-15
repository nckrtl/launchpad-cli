<?php

use App\Enums\ExitCode;
use App\Services\DockerManager;

beforeEach(function () {
    $this->dockerManager = Mockery::mock(DockerManager::class);
    $this->app->instance(DockerManager::class, $this->dockerManager);
});

it('stops all services successfully', function () {
    $this->dockerManager->shouldReceive('stop')->times(7)->andReturn(true);

    $this->artisan('stop')
        ->expectsOutputToContain('Orbit stopped')
        ->assertExitCode(0);
});

it('reports failure when a service fails to stop', function () {
    $this->dockerManager->shouldReceive('stop')->andReturn(false);

    $this->artisan('stop')
        ->expectsOutputToContain('Some services failed to stop')
        ->assertExitCode(ExitCode::ServiceFailed->value);
});

it('outputs json when --json flag is used', function () {
    $this->dockerManager->shouldReceive('stop')->times(7)->andReturn(true);

    $this->artisan('stop --json')
        ->assertExitCode(0);
});
