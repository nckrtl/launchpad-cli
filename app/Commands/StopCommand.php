<?php

namespace App\Commands;

use App\Services\DockerManager;
use LaravelZero\Framework\Commands\Command;

class StopCommand extends Command
{
    protected $signature = 'stop';

    protected $description = 'Stop all Launchpad services';

    public function handle(DockerManager $dockerManager): int
    {
        $this->info('Stopping Launchpad...');

        $this->task('Stopping Caddy', fn () => $dockerManager->stop('caddy'));

        $services = ['mailpit', 'redis', 'postgres'];
        foreach ($services as $service) {
            $this->task("Stopping {$service}", fn () => $dockerManager->stop($service));
        }

        $this->task('Stopping PHP containers', fn () => $dockerManager->stop('php'));
        $this->task('Stopping DNS', fn () => $dockerManager->stop('dns'));

        $this->newLine();
        $this->info('Launchpad stopped.');

        return self::SUCCESS;
    }
}
