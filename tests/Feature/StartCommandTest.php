<?php

use App\Enums\ExitCode;
use App\Services\CaddyfileGenerator;
use App\Services\DockerManager;
use App\Services\PhpComposeGenerator;

beforeEach(function () {
    $this->dockerManager = Mockery::mock(DockerManager::class);
    $this->caddyfileGenerator = Mockery::mock(CaddyfileGenerator::class);
    $this->phpComposeGenerator = Mockery::mock(PhpComposeGenerator::class);

    $this->app->instance(DockerManager::class, $this->dockerManager);
    $this->app->instance(CaddyfileGenerator::class, $this->caddyfileGenerator);
    $this->app->instance(PhpComposeGenerator::class, $this->phpComposeGenerator);
});

it('starts all services successfully', function () {
    $this->caddyfileGenerator->shouldReceive('generate')->once();
    $this->phpComposeGenerator->shouldReceive('generate')->once();
    $this->dockerManager->shouldReceive('start')->times(6)->andReturn(true);

    $this->artisan('start')
        ->expectsOutputToContain('Launchpad is running')
        ->assertExitCode(0);
});

it('reports failure when a service fails to start', function () {
    $this->caddyfileGenerator->shouldReceive('generate')->once();
    $this->phpComposeGenerator->shouldReceive('generate')->once();
    $this->dockerManager->shouldReceive('start')->andReturn(false);

    $this->artisan('start')
        ->expectsOutputToContain('Some services failed to start')
        ->assertExitCode(ExitCode::ServiceFailed->value);
});

it('outputs json when --json flag is used', function () {
    $this->caddyfileGenerator->shouldReceive('generate')->once();
    $this->phpComposeGenerator->shouldReceive('generate')->once();
    $this->dockerManager->shouldReceive('start')->times(6)->andReturn(true);

    $this->artisan('start --json')
        ->assertExitCode(0);
});
