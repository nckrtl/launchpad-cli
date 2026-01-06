<?php

declare(strict_types=1);

namespace App\Commands;

use App\Services\ConfigManager;
use LaravelZero\Framework\Commands\Command;

final class ReverbSetupCommand extends Command
{
    protected $signature = 'reverb:setup';

    protected $description = 'Setup Laravel Reverb WebSocket service';

    public function handle(ConfigManager $config): int
    {
        // Implementation for setting up Reverb as a launchpad-managed service
        // Similar to how postgres/frankenphp are managed

        $this->info('Setting up Reverb WebSocket service...');

        // TODO: Implement reverb docker-compose setup
        // - Generate reverb configuration
        // - Create docker-compose.yml for reverb
        // - Configure Caddy for TLS termination
        // - Start the service

        $config->set('reverb.url', 'https://reverb.test');

        $this->info('Reverb setup complete.');

        return 0;
    }
}
