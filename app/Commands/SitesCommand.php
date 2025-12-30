<?php

namespace App\Commands;

use App\Services\ConfigManager;
use App\Services\SiteScanner;
use LaravelZero\Framework\Commands\Command;

class SitesCommand extends Command
{
    protected $signature = 'sites';

    protected $description = 'List all sites with their PHP versions';

    public function handle(SiteScanner $siteScanner, ConfigManager $configManager): int
    {
        $sites = $siteScanner->scan();

        if (empty($sites)) {
            $this->warn('No sites found. Add paths to your config.json file.');

            return self::SUCCESS;
        }

        $defaultPhp = $configManager->getDefaultPhpVersion();

        $this->info('Sites:');
        $this->newLine();

        $tableData = [];
        foreach ($sites as $site) {
            $phpDisplay = $site['php_version'];
            if ($site['has_custom_php']) {
                $phpDisplay .= ' (custom)';
            } else {
                $phpDisplay .= ' (default)';
            }

            $tableData[] = [
                $site['domain'],
                $phpDisplay,
                $site['path'],
            ];
        }

        $this->table(['Domain', 'PHP Version', 'Path'], $tableData);

        $this->newLine();
        $this->line("Default PHP version: {$defaultPhp}");

        return self::SUCCESS;
    }
}
