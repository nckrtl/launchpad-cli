<?php

namespace App\Commands;

use App\Services\CaddyfileGenerator;
use App\Services\DockerManager;
use LaravelZero\Framework\Commands\Command;

class StartCommand extends Command
{
    protected $signature = 'start';

    protected $description = 'Start all Launchpad services';

    public function handle(DockerManager $dockerManager, CaddyfileGenerator $caddyfileGenerator): int
    {
        $this->info('Starting Launchpad...');

        $this->task('Generating Caddyfile', fn () => $caddyfileGenerator->generate() || true);
        $this->task('Starting DNS', fn () => $dockerManager->start('dns'));
        $this->task('Starting PHP containers', fn () => $dockerManager->start('php'));
        $this->task('Starting Caddy', fn () => $dockerManager->start('caddy'));

        $services = ['postgres', 'redis', 'mailpit'];
        foreach ($services as $service) {
            $this->task("Starting {$service}", fn () => $dockerManager->start($service));
        }

        $this->newLine();
        $this->info('Launchpad is running!');

        return self::SUCCESS;
    }
}
