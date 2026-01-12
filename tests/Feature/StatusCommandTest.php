<?php

use App\Services\ConfigManager;
use App\Services\DockerManager;
use App\Services\PhpManager;
use App\Services\SiteScanner;

beforeEach(function () {
    $this->configManager = Mockery::mock(ConfigManager::class);
    $this->dockerManager = Mockery::mock(DockerManager::class);
    $this->siteScanner = Mockery::mock(SiteScanner::class);
    $this->phpManager = Mockery::mock(PhpManager::class);

    $this->app->instance(ConfigManager::class, $this->configManager);
    $this->app->instance(DockerManager::class, $this->dockerManager);
    $this->app->instance(SiteScanner::class, $this->siteScanner);
    $this->app->instance(PhpManager::class, $this->phpManager);
});

it('shows status with all services running', function () {
    $this->dockerManager->shouldReceive('getAllStatuses')->andReturn([
        'dns' => ['running' => true, 'health' => 'healthy', 'container' => 'launchpad-dns'],
        'php' => ['running' => true, 'health' => 'healthy', 'container' => 'launchpad-php'],
        'caddy' => ['running' => true, 'health' => 'healthy', 'container' => 'launchpad-caddy'],
        'postgres' => ['running' => true, 'health' => 'healthy', 'container' => 'launchpad-postgres'],
        'redis' => ['running' => true, 'health' => 'healthy', 'container' => 'launchpad-redis'],
        'mailpit' => ['running' => true, 'health' => 'healthy', 'container' => 'launchpad-mailpit'],
        'horizon' => ['running' => true, 'health' => null, 'container' => 'launchpad-horizon'],
    ]);
    $this->siteScanner->shouldReceive('scan')->andReturn([
        ['name' => 'mysite', 'domain' => 'mysite.test', 'path' => '/path/to/mysite', 'php_version' => '8.3', 'has_custom_php' => false],
    ]);
    $this->configManager->shouldReceive('getConfigPath')->andReturn('/home/user/.config/launchpad');
    $this->configManager->shouldReceive('getTld')->andReturn('test');
    $this->configManager->shouldReceive('getDefaultPhpVersion')->andReturn('8.3');
    $this->phpManager->shouldReceive('getSocketPath')->andReturn('/tmp/php-fpm.sock');

    $this->artisan('status')
        ->expectsOutputToContain('Launchpad is running')
        ->assertExitCode(0);
});

it('shows status with all services stopped', function () {
    $this->dockerManager->shouldReceive('getAllStatuses')->andReturn([
        'dns' => ['running' => false, 'health' => null, 'container' => 'launchpad-dns'],
        'php' => ['running' => false, 'health' => null, 'container' => 'launchpad-php'],
        'caddy' => ['running' => false, 'health' => null, 'container' => 'launchpad-caddy'],
        'postgres' => ['running' => false, 'health' => null, 'container' => 'launchpad-postgres'],
        'redis' => ['running' => false, 'health' => null, 'container' => 'launchpad-redis'],
        'mailpit' => ['running' => false, 'health' => null, 'container' => 'launchpad-mailpit'],
        'horizon' => ['running' => false, 'health' => null, 'container' => 'launchpad-horizon'],
    ]);
    $this->siteScanner->shouldReceive('scan')->andReturn([]);
    $this->configManager->shouldReceive('getConfigPath')->andReturn('/home/user/.config/launchpad');
    $this->configManager->shouldReceive('getTld')->andReturn('test');
    $this->configManager->shouldReceive('getDefaultPhpVersion')->andReturn('8.3');
    $this->phpManager->shouldReceive('getSocketPath')->andReturn(false);

    $this->artisan('status')
        ->expectsOutputToContain('Launchpad is stopped')
        ->assertExitCode(0);
});

it('outputs json when --json flag is used', function () {
    $this->dockerManager->shouldReceive('getAllStatuses')->andReturn([
        'dns' => ['running' => true, 'health' => 'healthy', 'container' => 'launchpad-dns'],
        'php' => ['running' => true, 'health' => 'healthy', 'container' => 'launchpad-php'],
        'caddy' => ['running' => true, 'health' => 'healthy', 'container' => 'launchpad-caddy'],
        'postgres' => ['running' => true, 'health' => 'healthy', 'container' => 'launchpad-postgres'],
        'redis' => ['running' => true, 'health' => 'healthy', 'container' => 'launchpad-redis'],
        'mailpit' => ['running' => true, 'health' => 'healthy', 'container' => 'launchpad-mailpit'],
        'horizon' => ['running' => true, 'health' => null, 'container' => 'launchpad-horizon'],
    ]);
    $this->siteScanner->shouldReceive('scan')->andReturn([]);
    $this->configManager->shouldReceive('getConfigPath')->andReturn('/home/user/.config/launchpad');
    $this->configManager->shouldReceive('getTld')->andReturn('test');
    $this->configManager->shouldReceive('getDefaultPhpVersion')->andReturn('8.3');
    $this->phpManager->shouldReceive('getSocketPath')->andReturn('/tmp/php-fpm.sock');

    $this->artisan('status --json')
        ->assertExitCode(0);
});
