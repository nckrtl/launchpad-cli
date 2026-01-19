<?php

declare(strict_types=1);

namespace App\Commands;

use App\Concerns\WithJsonOutput;
use App\Services\ConfigManager;
use App\Services\SiteScanner;
use LaravelZero\Framework\Commands\Command;

final class SiteListCommand extends Command
{
    use WithJsonOutput;

    protected $signature = 'site:list {--json : Output as JSON}';

    protected $description = 'List ALL directories in scan paths as sites';

    public function handle(SiteScanner $siteScanner, ConfigManager $configManager): int
    {
        $sites = $siteScanner->scan();
        $tld = $configManager->getTld();
        $defaultPhp = $configManager->getDefaultPhpVersion();

        if ($this->wantsJson()) {
            return $this->outputJsonSuccess([
                'sites' => $sites,
                'count' => count($sites),
                'tld' => $tld,
                'default_php_version' => $defaultPhp,
            ]);
        }

        if (empty($sites)) {
            $this->warn('No sites found. Add paths to your config.json file.');

            return self::SUCCESS;
        }

        $this->info('Sites:');
        $this->newLine();

        $tableData = [];
        foreach ($sites as $site) {
            $phpDisplay = $site['php_version'];
            if ($site['has_custom_php']) {
                $phpDisplay .= ' (custom)';
            }

            $hasPublic = $site['has_public_folder'] ? 'Yes' : 'No';
            $domain = $site['domain'] ?? '-';

            $tableData[] = [
                $site['name'],
                $hasPublic,
                $domain,
                $phpDisplay,
            ];
        }

        $this->table(['Name', 'Has Public', 'Domain', 'PHP'], $tableData);

        $this->newLine();
        $this->line("TLD: {$tld}");
        $this->line("Default PHP: {$defaultPhp}");
        $this->line('Total: '.count($sites).' sites');

        return self::SUCCESS;
    }
}
