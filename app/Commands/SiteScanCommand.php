<?php

declare(strict_types=1);

namespace App\Commands;

use App\Concerns\WithJsonOutput;
use App\Enums\ExitCode;
use App\Services\ConfigManager;
use Illuminate\Support\Facades\Process;
use LaravelZero\Framework\Commands\Command;

final class SiteScanCommand extends Command
{
    use WithJsonOutput;

    protected $signature = 'site:scan
        {path? : Specific path to scan (defaults to configured paths)}
        {--depth=2 : Maximum directory depth to scan}
        {--json : Output as JSON}';

    protected $description = 'Scan for existing sites (git repositories) in configured paths';

    public function handle(ConfigManager $config): int
    {
        /** @var string|null $specificPath */
        $specificPath = $this->argument('path');
        $depth = (int) $this->option('depth');

        $pathsToScan = [];

        if ($specificPath) {
            $pathsToScan[] = $this->expandPath($specificPath);
        } else {
            $configPaths = $config->get('paths', []);
            foreach ($configPaths as $p) {
                $pathsToScan[] = $this->expandPath($p);
            }
        }

        if (empty($pathsToScan)) {
            return $this->failWithMessage('No paths configured to scan');
        }

        $sites = [];

        foreach ($pathsToScan as $basePath) {
            if (! is_dir($basePath)) {
                continue;
            }

            $found = $this->scanDirectory($basePath, $depth);
            $sites = array_merge($sites, $found);
        }

        // Sort by name
        usort($sites, fn ($a, $b) => strcasecmp((string) $a['name'], (string) $b['name']));

        if ($this->wantsJson()) {
            $this->outputJsonSuccess([
                'sites' => $sites,
                'count' => count($sites),
            ]);
        } else {
            if (empty($sites)) {
                $this->info('No sites found.');
            } else {
                $this->info('Found '.count($sites).' site(s):');
                $this->newLine();

                foreach ($sites as $site) {
                    $this->line("  <info>{$site['name']}</info>");
                    $this->line("    Path: {$site['path']}");
                    if ($site['github_url']) {
                        $this->line("    GitHub: {$site['github_url']}");
                    }
                    $this->newLine();
                }
            }
        }

        return ExitCode::Success->value;
    }

    private function scanDirectory(string $basePath, int $maxDepth, int $currentDepth = 0): array
    {
        $sites = [];

        if ($currentDepth > $maxDepth) {
            return $sites;
        }

        $entries = @scandir($basePath);
        if ($entries === false) {
            return $sites;
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $fullPath = "{$basePath}/{$entry}";

            if (! is_dir($fullPath)) {
                continue;
            }

            // Check if this is a git repository
            if (is_dir("{$fullPath}/.git")) {
                $site = $this->extractSiteInfo($fullPath);
                if ($site) {
                    $sites[] = $site;
                }

                // Don't recurse into git repos
                continue;
            }

            // Recurse into subdirectories (but skip common non-project dirs)
            if (! in_array($entry, ['node_modules', 'vendor', '.cache', '.config', '.local'])) {
                $subSites = $this->scanDirectory($fullPath, $maxDepth, $currentDepth + 1);
                $sites = array_merge($sites, $subSites);
            }
        }

        return $sites;
    }

    private function extractSiteInfo(string $path): array
    {
        $name = basename($path);
        $githubUrl = null;

        // Try to get GitHub URL from git remote
        $result = Process::path($path)->timeout(5)->run('git remote get-url origin 2>/dev/null');
        if ($result->successful()) {
            $remoteUrl = trim($result->output());
            $githubUrl = $this->normalizeGithubUrl($remoteUrl);
        }

        // Detect project type
        $type = 'unknown';
        if (file_exists("{$path}/artisan")) {
            $type = 'laravel';
        } elseif (file_exists("{$path}/composer.json")) {
            $type = 'php';
        } elseif (file_exists("{$path}/package.json")) {
            $type = 'node';
        }

        return [
            'name' => $name,
            'path' => $path,
            'github_url' => $githubUrl,
            'type' => $type,
        ];
    }

    private function normalizeGithubUrl(?string $remoteUrl): ?string
    {
        if (! $remoteUrl) {
            return null;
        }

        // Convert SSH URL to HTTPS
        if (preg_match('/^git@github\.com:(.+)\.git$/', $remoteUrl, $matches)) {
            return 'https://github.com/'.$matches[1];
        }

        // Clean up HTTPS URL
        if (preg_match('/^https:\/\/github\.com\/(.+?)(\.git)?$/', $remoteUrl, $matches)) {
            return 'https://github.com/'.rtrim($matches[1], '.git');
        }

        return null;
    }

    private function expandPath(string $path): string
    {
        if (str_starts_with($path, '~/')) {
            return $_SERVER['HOME'].substr($path, 1);
        }

        return $path;
    }

    private function failWithMessage(string $message): int
    {
        if ($this->wantsJson()) {
            $this->outputJsonError($message);
        } else {
            $this->error($message);
        }

        return ExitCode::GeneralError->value;
    }

    private function wantsJson(): bool
    {
        return (bool) $this->option('json') || ! $this->input->isInteractive();
    }
}
