<?php

namespace App\Commands;

use App\Services\CaddyfileGenerator;
use App\Services\ConfigManager;
use App\Services\SiteScanner;
use LaravelZero\Framework\Commands\Command;

class PhpCommand extends Command
{
    protected $signature = 'php
        {site : The site name to configure}
        {version? : The PHP version to use (8.3, 8.4)}
        {--reset : Reset to default PHP version}';

    protected $description = 'Set PHP version for a site';

    protected array $validVersions = ['8.3', '8.4'];

    public function handle(
        ConfigManager $configManager,
        SiteScanner $siteScanner,
        CaddyfileGenerator $caddyfileGenerator
    ): int {
        $site = $this->argument('site');
        $version = $this->argument('version');
        $reset = $this->option('reset');

        // Verify site exists
        $siteInfo = $siteScanner->findSite($site);
        if (! $siteInfo) {
            $this->error("Site '{$site}' not found.");

            return self::FAILURE;
        }

        if ($reset) {
            $configManager->removeSiteOverride($site);
            $this->info("Reset {$site} to default PHP version ({$configManager->getDefaultPhpVersion()})");
        } else {
            if (! $version) {
                $this->error('Please provide a PHP version or use --reset');

                return self::FAILURE;
            }

            if (! in_array($version, $this->validVersions)) {
                $this->error("Invalid PHP version. Valid versions: ".implode(', ', $this->validVersions));

                return self::FAILURE;
            }

            $configManager->setSitePhpVersion($site, $version);
            $this->info("Set {$site} to PHP {$version}");
        }

        // Regenerate Caddyfile and reload
        $this->task('Regenerating Caddyfile', fn () => $caddyfileGenerator->generate() || true);

        if ($caddyfileGenerator->reload()) {
            $this->info('Caddy reloaded');
        } else {
            $this->warn('Could not reload Caddy. You may need to restart Launchpad.');
        }

        return self::SUCCESS;
    }
}
