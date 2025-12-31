<?php

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

it('restarts all services by calling stop and start', function () {
    // Stop expectations
    $this->dockerManager->shouldReceive('stop')->times(6)->andReturn(true);

    // Start expectations
    $this->caddyfileGenerator->shouldReceive('generate')->once();
    $this->phpComposeGenerator->shouldReceive('generate')->once();
    $this->dockerManager->shouldReceive('start')->times(6)->andReturn(true);

    $this->artisan('restart')
        ->assertExitCode(0);
});

it('outputs json when --json flag is used', function () {
    $this->dockerManager->shouldReceive('stop')->times(6)->andReturn(true);
    $this->caddyfileGenerator->shouldReceive('generate')->once();
    $this->phpComposeGenerator->shouldReceive('generate')->once();
    $this->dockerManager->shouldReceive('start')->times(6)->andReturn(true);

    $this->artisan('restart --json')
        ->assertExitCode(0);
});
