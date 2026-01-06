<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

final class McpClient
{
    private string $baseUrl;

    public function __construct(ConfigManager $config)
    {
        // CLI always calls localhost Orchestrator
        $orchestratorUrl = $config->get('orchestrator.url', 'http://localhost:8000');
        $this->baseUrl = rtrim($orchestratorUrl, '/') . '/mcp';
    }

    public function isConfigured(): bool
    {
        return ! empty($this->baseUrl);
    }

    /**
     * Call an MCP tool. No authentication required.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public function callTool(string $toolName, array $arguments = []): array
    {
        $response = Http::timeout(120)
            ->post($this->baseUrl, [
                'jsonrpc' => '2.0',
                'method' => 'tools/call',
                'params' => [
                    'name' => $toolName,
                    'arguments' => $arguments,
                ],
                'id' => uniqid(),
            ]);

        if (! $response->successful()) {
            throw new RuntimeException(
                'MCP call failed: ' . ($response->json('error.message') ?? $response->body())
            );
        }

        /** @var array<string, mixed> $result */
        $result = $response->json();

        if (isset($result['error'])) {
            /** @var array{message?: string} $error */
            $error = $result['error'];
            throw new RuntimeException('MCP error: ' . ($error['message'] ?? 'Unknown error'));
        }

        /** @var array<string, mixed> */
        return $result['result'] ?? [];
    }
}
