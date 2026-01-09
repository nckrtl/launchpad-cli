<?php

namespace App\Jobs;

use App\Events\ProjectProvisionStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Process;

class CreateProjectJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 1;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 600; // 10 minutes

    public function __construct(
        public string $slug,
        public string $template,
        public string $dbDriver,
        public string $visibility,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $this->broadcast('provisioning');

            // Create GitHub repository
            $this->broadcast('creating_repo');
            $this->runCli("repo:create {$this->slug} --visibility={$this->visibility}");

            // Clone the repository
            $this->broadcast('cloning');
            $this->runCli("site:clone {$this->slug}");

            // Early Caddy reload for SSL
            $this->runCli('caddy:reload');

            // Set up the project from template
            $this->broadcast('setting_up');
            $this->runCli("site:setup {$this->slug} --template={$this->template} --db-driver={$this->dbDriver}");

            // Install Composer dependencies
            $this->broadcast('installing_composer');
            $this->runInProject('composer install --no-interaction');

            // Install NPM dependencies
            $this->broadcast('installing_npm');
            $this->runInProject('npm install');

            // Build assets
            $this->broadcast('building');
            $this->runInProject('npm run build');

            // Finalize
            $this->broadcast('finalizing');
            $this->runCli('caddy:reload');

            // Done
            $this->broadcast('ready');

        } catch (\Exception $e) {
            $this->broadcast('failed', $e->getMessage());
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(?\Throwable $exception): void
    {
        $this->broadcast('failed', $exception?->getMessage() ?? 'Unknown error');
    }

    /**
     * Run a launchpad CLI command.
     */
    private function runCli(string $command): void
    {
        $launchpad = $_SERVER['HOME'].'/.local/bin/launchpad';

        $result = Process::timeout(300)->run("{$launchpad} {$command}");

        if (! $result->successful()) {
            throw new \RuntimeException(
                "CLI command failed: {$command}\n".$result->errorOutput()
            );
        }
    }

    /**
     * Run a command in the project directory.
     */
    private function runInProject(string $command): void
    {
        $projectPath = $_SERVER['HOME']."/projects/{$this->slug}";

        $result = Process::timeout(300)
            ->path($projectPath)
            ->run($command);

        if (! $result->successful()) {
            throw new \RuntimeException(
                "Command failed in project: {$command}\n".$result->errorOutput()
            );
        }
    }

    /**
     * Broadcast a status update.
     */
    private function broadcast(string $status, ?string $error = null): void
    {
        event(new ProjectProvisionStatus(
            slug: $this->slug,
            status: $status,
            error: $error,
        ));
    }
}
