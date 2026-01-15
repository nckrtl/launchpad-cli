<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class DeleteProjectJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 60; // 1 minute max

    public function __construct(
        public string $slug,
        public bool $deleteRepo = false,
        public bool $keepDb = false,
    ) {}

    public function handle(): void
    {
        $orbit = $this->findOrbitBinary();
        $home = $_SERVER['HOME'] ?? '/home/launchpad';

        try {
            // Build the delete command
            // The CLI handles all broadcasting via ReverbBroadcaster
            $command = "{$orbit} project:delete --slug=".escapeshellarg($this->slug).' --force --json';

            if ($this->deleteRepo) {
                $command .= ' --delete-repo';
            }
            if ($this->keepDb) {
                $command .= ' --keep-db';
            }

            Log::info('DeleteProjectJob: Running', ['slug' => $this->slug, 'command' => $command]);

            $result = Process::timeout($this->timeout - 5)
                ->env([
                    'HOME' => $home,
                    'PATH' => "{$home}/.local/bin:{$home}/.config/herd-lite/bin:/usr/local/bin:/usr/bin:/bin",
                ])
                ->run($command);

            if (! $result->successful()) {
                $error = $result->errorOutput() ?: $result->output();
                Log::error('DeleteProjectJob: Failed', ['slug' => $this->slug, 'error' => substr($error, 0, 1000)]);
                throw new \RuntimeException('Delete failed: '.substr($error, 0, 500));
            }

            Log::info('DeleteProjectJob: Completed', ['slug' => $this->slug]);

        } catch (\Exception $e) {
            Log::error('DeleteProjectJob: Exception', ['slug' => $this->slug, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    public function failed(?\Throwable $exception): void
    {
        Log::error('DeleteProjectJob: Job failed', [
            'slug' => $this->slug,
            'error' => $exception?->getMessage() ?? 'Unknown error',
        ]);
    }

    private function findOrbitBinary(): string
    {
        $home = $_SERVER['HOME'] ?? '/home/launchpad';
        $paths = ["{$home}/.local/bin/orbit", '/usr/local/bin/orbit', "{$home}/projects/orbit-cli/orbit"];
        foreach ($paths as $path) {
            if (file_exists($path) && is_executable($path)) {
                return $path;
            }
        }

        return "{$home}/.local/bin/orbit";
    }
}
