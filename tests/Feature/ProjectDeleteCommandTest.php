<?php

use App\Services\ConfigManager;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->configManager = Mockery::mock(ConfigManager::class)->makePartial();
    $this->configManager->shouldReceive('getPaths')->andReturn(['/tmp/projects']);
    $this->configManager->shouldReceive('getTld')->andReturn('test');
    $this->configManager->shouldReceive('getConfigPath')->andReturn('/tmp/.config/launchpad');
    $this->configManager->shouldReceive('getDefaultPhpVersion')->andReturn('8.4');
    $this->configManager->shouldReceive('get')->with('orchestrator.url', 'http://localhost:8000')->andReturn('http://localhost:8000');
    $this->configManager->shouldReceive('get')->with('reverb.url', '')->andReturn('');
    $this->configManager->shouldReceive('get')->with('paths', [])->andReturn(['/tmp/projects']);
    $this->app->instance(ConfigManager::class, $this->configManager);
});

it('deletes project via MCP when given slug with --force', function () {
    Http::fake([
        'localhost:8000/mcp' => Http::response([
            'jsonrpc' => '2.0',
            'result' => [
                'content' => [['text' => '# Project Deleted']],
                'meta' => [
                    'id' => 1,
                    'name' => 'Test Project',
                    'slug' => 'test-project',
                ],
            ],
            'id' => 'test-id',
        ]),
    ]);

    // Just check that the command executes and makes MCP call
    $this->artisan('project:delete', ['slug' => 'test-project', '--force' => true, '--json' => true]);

    Http::assertSent(function ($request) {
        return $request->url() === 'http://localhost:8000/mcp'
            && $request['params']['name'] === 'delete-project';
    });
});

it('handles MCP error response', function () {
    Http::fake([
        'localhost:8000/mcp' => Http::response([
            'jsonrpc' => '2.0',
            'error' => ['message' => 'Project not found'],
            'id' => 'test-id',
        ]),
    ]);

    $this->artisan('project:delete', ['slug' => 'nonexistent', '--force' => true, '--json' => true])
        ->assertFailed();
});

it('handles connection error', function () {
    Http::fake([
        'localhost:8000/mcp' => Http::response(status: 500),
    ]);

    $this->artisan('project:delete', ['slug' => 'test-project', '--force' => true, '--json' => true])
        ->assertFailed();
});
