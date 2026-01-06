<?php

declare(strict_types=1);

namespace App\Commands;

use App\Concerns\WithJsonOutput;
use App\Enums\ExitCode;
use App\Services\CaddyfileGenerator;
use App\Services\ConfigManager;
use App\Services\McpClient;
use Illuminate\Support\Facades\Process;
use LaravelZero\Framework\Commands\Command;

final class ProjectDeleteCommand extends Command
{
    use WithJsonOutput;

    protected $signature = 'project:delete
        {slug? : Project slug to delete}
        {--id= : Project ID to delete (alternative to slug)}
        {--force : Skip confirmation prompt}
        {--delete-repo : Also delete the GitHub repository (irreversible)}
        {--json : Output as JSON}';

    protected $description = 'Delete a project and cascade to integrations (Orchestrator + VK + Linear)';

    public function handle(
        ConfigManager $config,
        CaddyfileGenerator $caddy,
        McpClient $mcp,
    ): int {
        /** @var string|null $slug */
        $slug = $this->argument('slug');

        /** @var string|null $id */
        $id = $this->option('id');

        // Interactive mode if TTY and no slug/id provided
        if (! $slug && ! $id && $this->input->isInteractive()) {
            /** @var string $slug */
            $slug = $this->ask('Project slug to delete');
        }

        if (! $slug && ! $id) {
            return $this->failWithMessage('Project slug or --id is required');
        }

        // Check if MCP/Orchestrator is configured
        if (! $mcp->isConfigured()) {
            return $this->failWithMessage(
                'Orchestrator not configured. Cannot delete integrated projects.'
            );
        }

        // Confirmation prompt (unless --force)
        $force = (bool) $this->option('force');
        if (! $force && $this->input->isInteractive()) {
            $confirm = $this->ask(
                "Type the project slug to confirm deletion",
            );
            
            if ($confirm !== $slug && $confirm !== $id) {
                return $this->failWithMessage('Confirmation failed. Deletion cancelled.');
            }
        }

        try {
            // Call Orchestrator MCP to delete project
            $result = $mcp->callTool('delete-project', [
                'slug' => $slug,
                'id' => $id ? (int) $id : null,
                'confirm_slug' => $slug ?? $this->getSlugFromId($mcp, (int) $id),
                'delete_github_repo' => (bool) $this->option('delete-repo'),
            ]);

            $meta = $result['meta'] ?? [];

            // Remove local project directory if it exists
            $localPath = $this->findLocalPath($config, $slug ?? $meta['slug'] ?? null);
            if ($localPath && is_dir($localPath)) {
                if (! $force && $this->input->isInteractive()) {
                    if ($this->confirm("Delete local directory {$localPath}?", false)) {
                        Process::run("rm -rf " . escapeshellarg($localPath));
                        $meta['local_deleted'] = true;
                    }
                }
            }

            // Regenerate Caddy config
            $caddy->generate();
            $caddy->reload();

            return $this->outputJsonSuccess([
                'message' => 'Project deleted successfully',
                'deleted' => $meta,
            ]);

        } catch (\Throwable $e) {
            return $this->failWithMessage($e->getMessage());
        }
    }

    private function getSlugFromId(McpClient $mcp, int $id): string
    {
        // Get project details to retrieve slug for confirmation
        $result = $mcp->callTool('get-project', ['id' => $id]);
        return $result['meta']['slug'] ?? throw new \RuntimeException('Could not retrieve project slug');
    }

    private function findLocalPath(ConfigManager $config, ?string $slug): ?string
    {
        if (! $slug) {
            return null;
        }

        $paths = $config->get('paths', []);
        foreach ($paths as $basePath) {
            $expandedPath = $this->expandPath($basePath);
            $projectPath = "{$expandedPath}/{$slug}";
            if (is_dir($projectPath)) {
                return $projectPath;
            }
        }

        return null;
    }

    private function expandPath(string $path): string
    {
        if (str_starts_with($path, '~/')) {
            return $_SERVER['HOME'] . substr($path, 1);
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
