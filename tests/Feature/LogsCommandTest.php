<?php

use App\Services\DockerManager;

beforeEach(function () {
    $this->dockerManager = Mockery::mock(DockerManager::class);
    $this->app->instance(DockerManager::class, $this->dockerManager);
});

it('shows logs for a container', function () {
    $this->dockerManager->shouldReceive('logs')
        ->with('launchpad-caddy', true)
        ->once();

    $this->artisan('logs launchpad-caddy')
        ->expectsOutputToContain('Showing logs for launchpad-caddy')
        ->assertExitCode(0);
});

it('can disable follow mode', function () {
    $this->dockerManager->shouldReceive('logs')
        ->with('launchpad-php-83', false)
        ->once();

    $this->artisan('logs launchpad-php-83 --no-follow')
        ->expectsOutputToContain('Showing logs for launchpad-php-83')
        ->assertExitCode(0);
});
