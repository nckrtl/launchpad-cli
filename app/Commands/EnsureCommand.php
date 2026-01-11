<?php

namespace App\Commands;

use App\Concerns\WithJsonOutput;
use App\Services\ConfigManager;
use App\Services\DockerManager;
use Illuminate\Support\Facades\Process;
use LaravelZero\Framework\Commands\Command;

class EnsureCommand extends Command
{
    use WithJsonOutput;

    protected $signature = 'ensure {--json : Output as JSON}';

    protected $description = 'Ensure all Launchpad services are running (containers + Horizon)';

    protected array $requiredContainers = [
        'launchpad-dns',
        'launchpad-caddy',
        'launchpad-redis',
        'launchpad-reverb',
        'launchpad-horizon',
    ];

    public function handle(
        DockerManager $dockerManager,
        ConfigManager $configManager
    ): int {
        $results = [
            'docker' => false,
            'containers' => false,
            'horizon' => false,
        ];

        // 1. Check if Docker is running
        if (! $this->isDockerRunning()) {
            $this->logOrOutput('Docker is not running, skipping...', 'warn');

            return $this->outputResult($results);
        }
        $results['docker'] = true;

        // 2. Ensure containers are running (includes Horizon)
        $allRunning = true;
        foreach ($this->requiredContainers as $container) {
            if (! $dockerManager->isRunning($container)) {
                $allRunning = false;
                break;
            }
        }

        if (! $allRunning) {
            $this->logOrOutput('Starting containers...', 'info');
            $this->call('start');
        }
        $results['containers'] = true;

        // 3. Verify Horizon container is running
        if ($dockerManager->isRunning('launchpad-horizon')) {
            $results['horizon'] = true;
        } else {
            $this->logOrOutput('Horizon container not running, attempting to start...', 'warn');
            if ($dockerManager->start('horizon')) {
                sleep(3);
                $results['horizon'] = $dockerManager->isRunning('launchpad-horizon');
            }
        }

        return $this->outputResult($results);
    }

    protected function isDockerRunning(): bool
    {
        $result = Process::run('docker info');

        return $result->successful();
    }

    protected function logOrOutput(string $message, string $type): void
    {
        if ($this->wantsJson()) {
            return;
        }

        match ($type) {
            'info' => $this->info($message),
            'warn' => $this->warn($message),
            'error' => $this->error($message),
            default => $this->line($message),
        };
    }

    protected function outputResult(array $results): int
    {
        if ($this->wantsJson()) {
            return $this->outputJsonSuccess([
                'docker' => $results['docker'],
                'containers' => $results['containers'],
                'horizon' => $results['horizon'],
                'all_running' => $results['docker'] && $results['containers'] && $results['horizon'],
            ]);
        }

        if ($results['docker'] && $results['containers'] && $results['horizon']) {
            $this->info('All services are running.');

            return self::SUCCESS;
        }

        return self::FAILURE;
    }
}
