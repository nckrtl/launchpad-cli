<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Services\ConfigManager;
use App\Services\DockerManager;
use App\Services\PhpManager;
use App\Services\SiteScanner;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
final class StatusTool extends Tool
{
    protected string $name = 'orbit_status';

    protected string $description = 'Get Orbit service status including running containers, sites count, TLD, and default PHP version';

    public function __construct(
        protected DockerManager $dockerManager,
        protected ConfigManager $configManager,
        protected SiteScanner $siteScanner,
        protected PhpManager $phpManager,
    ) {}

    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    public function handle(Request $request): ResponseFactory
    {
        $isUsingFpm = $this->isUsingFpm();
        $architecture = $isUsingFpm ? 'php-fpm' : 'frankenphp';

        $services = [];

        // Core services (always Docker containers)
        $coreServices = ['dns', 'caddy', 'postgres', 'redis', 'mailpit'];
        foreach ($coreServices as $service) {
            $container = 'orbit-'.$service;
            $services[$service] = [
                'status' => $this->dockerManager->isRunning($container) ? 'running' : 'stopped',
                'container' => $container,
            ];
        }

        // PHP services - depends on architecture
        if ($isUsingFpm) {
            // PHP-FPM on host - check if any FPM processes are running
            $phpVersions = ['8.3', '8.4', '8.5'];
            $runningPhpCount = 0;
            foreach ($phpVersions as $version) {
                if ($this->phpManager->isRunning($version)) {
                    $runningPhpCount++;
                }
            }
            $services['php-fpm'] = [
                'status' => $runningPhpCount > 0 ? 'running' : 'stopped',
                'type' => 'host-process',
                'versions_running' => $runningPhpCount,
            ];
        } else {
            // FrankenPHP Docker containers
            $phpVersions = ['83', '84', '85'];
            foreach ($phpVersions as $version) {
                $container = "orbit-php-{$version}";
                $services["php-{$version}"] = [
                    'status' => $this->dockerManager->isRunning($container) ? 'running' : 'stopped',
                    'container' => $container,
                ];
            }
        }

        // Optional services
        if ($this->configManager->isServiceEnabled('reverb')) {
            $services['reverb'] = [
                'status' => $this->dockerManager->isRunning('orbit-reverb') ? 'running' : 'stopped',
                'container' => 'orbit-reverb',
            ];
        }

        if ($this->configManager->isServiceEnabled('horizon')) {
            $services['horizon'] = [
                'status' => $this->dockerManager->isRunning('orbit-horizon') ? 'running' : 'stopped',
                'container' => 'orbit-horizon',
            ];
        }

        $runningCount = count(array_filter($services, fn ($s) => $s['status'] === 'running'));
        $totalCount = count($services);
        $running = $runningCount === $totalCount;

        $sites = $this->siteScanner->scanSites();

        return Response::structured([
            'running' => $running,
            'architecture' => $architecture,
            'services' => $services,
            'services_running' => $runningCount,
            'services_total' => $totalCount,
            'sites_count' => count($sites),
            'config_path' => $this->configManager->getConfigPath(),
            'tld' => $this->configManager->getTld(),
            'default_php_version' => $this->configManager->getDefaultPhpVersion(),
        ]);
    }

    private function isUsingFpm(): bool
    {
        // Check if any FPM socket exists
        $versions = ['8.2', '8.3', '8.4', '8.5'];
        foreach ($versions as $version) {
            if (file_exists($this->phpManager->getSocketPath($version))) {
                return true;
            }
        }

        return false;
    }
}
