<?php

declare(strict_types=1);

namespace App\Commands;

use App\Concerns\WithJsonOutput;
use App\Enums\ExitCode;
use App\Services\CaddyfileGenerator;
use App\Services\ConfigManager;
use App\Services\McpClient;
use App\Services\ReverbBroadcaster;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use LaravelZero\Framework\Commands\Command;

final class ProjectCreateCommand extends Command
{
    use WithJsonOutput;

    protected $signature = 'project:create
        {repo? : GitHub repository (user/repo format) or clone URL}
        {--name= : Project name (defaults to repo name)}
        {--slug= : URL-friendly slug (defaults to name)}
        {--integrate : Enable full integration (Orchestrator + VK + Linear)}
        {--template= : Template repository (user/repo format)}
        {--linear-team= : Linear team ID for project creation}
        {--visibility=private : Repository visibility (private/public)}
        {--path= : Override default project path}
        {--json : Output as JSON}';

    protected $description = 'Create a new project (standalone by default, --integrate for full integration)';

    public function handle(
        ConfigManager $config,
        CaddyfileGenerator $caddy,
        McpClient $mcp,
        ReverbBroadcaster $reverb,
    ): int {
        /** @var string|null $repo */
        $repo = $this->argument('repo');
        $integrate = (bool) $this->option('integrate');

        // Interactive mode if TTY and no repo provided
        if (! $repo && $this->input->isInteractive()) {
            /** @var string $repo */
            $repo = $this->ask('GitHub repository (user/repo or clone URL)');
        }

        if (! $repo) {
            return $this->failWithMessage('Repository is required');
        }

        // Parse repo format
        $isCloneUrl = str_starts_with($repo, 'git@') || str_starts_with($repo, 'https://');
        $repoName = $isCloneUrl ? basename($repo, '.git') : basename($repo);

        /** @var string|null $nameOption */
        $nameOption = $this->option('name');
        $name = $nameOption ?? $repoName;

        /** @var string|null $slugOption */
        $slugOption = $this->option('slug');
        $slug = $slugOption ?? Str::slug($name);

        /** @var string|null $pathOption */
        $pathOption = $this->option('path');
        $paths = $config->get('paths', ['~/projects']);
        $basePath = $pathOption ?? ($paths[0] ?? '~/projects');
        $localPath = $this->expandPath("{$basePath}/{$repoName}");

        // Check if path exists
        if (is_dir($localPath)) {
            if ($this->input->isInteractive()) {
                /** @var string $choice */
                $choice = $this->choice(
                    "Directory already exists: {$localPath}",
                    ['Add integrations (if --integrate)', 'Cancel'],
                    1
                );
                if ($choice === 'Cancel') {
                    return $this->failWithMessage('Cancelled by user');
                }
                if ($integrate && ! $isCloneUrl) {
                    return $this->addIntegrations($mcp, $name, $slug, $repo, $localPath);
                }
            }

            return $this->failWithMessage("Directory already exists: {$localPath}");
        }

        // Channel for real-time updates (predictable based on slug)
        $channelId = "project-create-{$slug}";

        try {
            if ($integrate) {
                return $this->createIntegrated($mcp, $reverb, $caddy, $channelId, $name, $slug, $repo, $localPath);
            } else {
                return $this->createStandalone($reverb, $caddy, $channelId, $slug, $repo, $localPath, $isCloneUrl);
            }
        } catch (\Throwable $e) {
            $reverb->broadcast($channelId, 'error', ['message' => $e->getMessage()]);
            $this->logFailure($e);

            return $this->failWithMessage($e->getMessage());
        }
    }

    private function createIntegrated(
        McpClient $mcp,
        ReverbBroadcaster $reverb,
        CaddyfileGenerator $caddy,
        string $channelId,
        string $name,
        string $slug,
        string $repo,
        string $localPath,
    ): int {
        if (! $mcp->isConfigured()) {
            return $this->failWithMessage(
                'Orchestrator not configured. Use standalone mode (without --integrate) or configure orchestrator.url'
            );
        }

        $reverb->broadcast($channelId, 'status', ['step' => 'orchestrator', 'message' => 'Creating project...']);

        // Call Orchestrator MCP
        /** @var array{meta?: array{repository_url?: string, project_id?: mixed, integrations?: array<string, mixed>, status?: string}} $result */
        $result = $mcp->callTool('create-project', [
            'name' => $name,
            'slug' => $slug,
            'github_repo' => $repo,
            'github_visibility' => $this->option('visibility'),
            'template_repo' => $this->option('template'),
            'linear_team_id' => $this->option('linear-team'),
            'local_path' => $localPath,
        ]);

        $meta = $result['meta'] ?? [];
        $repositoryUrl = $meta['repository_url'] ?? "git@github.com:{$repo}.git";

        // Broadcast integration results
        /** @var array<string, array{status?: string, error?: string|null}> $integrations */
        $integrations = $meta['integrations'] ?? [];
        foreach ($integrations as $integration => $status) {
            $reverb->broadcast($channelId, 'integration', [
                'name' => $integration,
                'status' => $status['status'] ?? 'unknown',
                'error' => $status['error'] ?? null,
            ]);
        }

        // Clone and setup
        $reverb->broadcast($channelId, 'status', ['step' => 'clone', 'message' => 'Cloning repository...']);
        $this->cloneAndSetup($repositoryUrl, $localPath);

        // Regenerate and reload Caddy
        $reverb->broadcast($channelId, 'status', ['step' => 'caddy', 'message' => 'Configuring web server...']);
        $caddy->generate();
        $caddy->reload();

        $reverb->broadcast($channelId, 'complete', ['project' => $meta]);

        return $this->outputJsonSuccess([
            'channel_id' => $channelId,
            'name' => $name,
            'slug' => $slug,
            'local_path' => $localPath,
            'site_url' => "https://{$slug}.test",
            'orchestrator_id' => $meta['project_id'] ?? null,
            'integrations' => $integrations,
            'status' => $meta['status'] ?? 'ready',
        ]);
    }

    private function createStandalone(
        ReverbBroadcaster $reverb,
        CaddyfileGenerator $caddy,
        string $channelId,
        string $slug,
        string $repo,
        string $localPath,
        bool $isCloneUrl,
    ): int {
        $cloneUrl = $isCloneUrl ? $repo : "git@github.com:{$repo}.git";

        $reverb->broadcast($channelId, 'status', ['step' => 'clone', 'message' => 'Cloning repository...']);
        $this->cloneAndSetup($cloneUrl, $localPath);

        $reverb->broadcast($channelId, 'status', ['step' => 'caddy', 'message' => 'Configuring web server...']);
        $caddy->generate();
        $caddy->reload();

        $reverb->broadcast($channelId, 'complete', ['local_path' => $localPath]);

        return $this->outputJsonSuccess([
            'channel_id' => $channelId,
            'local_path' => $localPath,
            'site_url' => "https://{$slug}.test",
            'mode' => 'standalone',
        ]);
    }

    private function addIntegrations(McpClient $mcp, string $name, string $slug, string $repo, string $localPath): int
    {
        /** @var array{meta?: array{integrations?: array<string, mixed>}} $result */
        $result = $mcp->callTool('create-project', [
            'name' => $name,
            'slug' => $slug,
            'repository_url' => "git@github.com:{$repo}.git",
            'linear_team_id' => $this->option('linear-team'),
            'local_path' => $localPath,
        ]);

        return $this->outputJsonSuccess([
            'message' => 'Integrations added to existing project',
            'integrations' => $result['meta']['integrations'] ?? [],
        ]);
    }

    private function cloneAndSetup(string $cloneUrl, string $localPath): void
    {
        // Clone
        $result = Process::timeout(300)->run("git clone {$cloneUrl} {$localPath}");
        if (! $result->successful()) {
            throw new \RuntimeException('Git clone failed: ' . $result->errorOutput());
        }

        // Composer
        if (file_exists("{$localPath}/composer.json")) {
            Process::path($localPath)->timeout(600)->run('composer install');
        }

        // NPM
        if (file_exists("{$localPath}/package.json")) {
            Process::path($localPath)->timeout(600)->run('npm install');
        }

        // Environment
        if (file_exists("{$localPath}/.env.example") && ! file_exists("{$localPath}/.env")) {
            copy("{$localPath}/.env.example", "{$localPath}/.env");
            if (file_exists("{$localPath}/artisan")) {
                Process::path($localPath)->run('php artisan key:generate');
            }
        }

        // PHP version detection from composer.json
        $phpVersion = $this->detectPhpVersion($localPath);
        file_put_contents("{$localPath}/.php-version", "{$phpVersion}\n");
    }

    private function detectPhpVersion(string $localPath): string
    {
        $composerPath = "{$localPath}/composer.json";
        if (! file_exists($composerPath)) {
            return '8.4'; // Default
        }

        $content = file_get_contents($composerPath);
        if ($content === false) {
            return '8.4';
        }

        /** @var array{require?: array{php?: string}}|null $composer */
        $composer = json_decode($content, true);
        $phpRequirement = $composer['require']['php'] ?? null;

        if (! $phpRequirement) {
            return '8.4';
        }

        // Parse version constraint (simplified)
        if (preg_match('/(\d+\.\d+)/', $phpRequirement, $matches)) {
            $version = $matches[1];
            // Map to supported versions
            if (version_compare($version, '8.4', '>=')) {
                return '8.4';
            }
            if (version_compare($version, '8.3', '>=')) {
                return '8.3';
            }
            if (version_compare($version, '8.2', '>=')) {
                return '8.3';
            }
            if (version_compare($version, '8.1', '>=')) {
                return '8.3';
            }
        }

        return '8.4';
    }

    private function expandPath(string $path): string
    {
        if (str_starts_with($path, '~/')) {
            return $_SERVER['HOME'] . substr($path, 1);
        }

        return $path;
    }

    private function logFailure(\Throwable $e): void
    {
        $logPath = $_SERVER['HOME'] . '/.local/share/launchpad/logs';
        if (! is_dir($logPath)) {
            mkdir($logPath, 0755, true);
        }
        file_put_contents(
            "{$logPath}/project-create-" . date('Y-m-d-His') . '.log',
            json_encode([
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ], JSON_PRETTY_PRINT)
        );
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
