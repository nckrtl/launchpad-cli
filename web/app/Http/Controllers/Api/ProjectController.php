<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ProjectController extends Controller
{
    /**
     * Create a new project using at daemon (like CLI does).
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|min:1|max:255',
            'template' => 'nullable|string',
            'clone_url' => 'nullable|string',
            'fork' => 'boolean',
            'visibility' => 'required|in:public,private',
            'php_version' => 'nullable|string|in:8.3,8.4,8.5',
            'db_driver' => 'nullable|string|in:mysql,pgsql,sqlite',
            'session_driver' => 'nullable|string|in:file,database,redis',
            'cache_driver' => 'nullable|string|in:file,database,redis',
            'queue_driver' => 'nullable|string|in:sync,database,redis',
            'path' => 'nullable|string',
            'minimal' => 'boolean',
        ]);

        $slug = Str::slug($validated['name']);

        // Reject reserved name
        if (strtolower($slug) === 'launchpad') {
            return response()->json([
                'success' => false,
                'error' => 'The name "launchpad" is reserved for the system.',
            ], 422);
        }

        // Find launchpad binary
        $home = $_SERVER['HOME'] ?? '/home/launchpad';
        $launchpadBin = null;
        $paths = [
            "{$home}/.local/bin/launchpad",
            '/usr/local/bin/launchpad',
            "{$home}/projects/launchpad-cli/launchpad",
        ];
        foreach ($paths as $path) {
            if (file_exists($path) && is_executable($path)) {
                $launchpadBin = $path;
                break;
            }
        }
        $launchpadBin = $launchpadBin ?? "{$home}/.local/bin/launchpad";

        // Build provision command
        $provisionCmd = "HOME={$home} {$launchpadBin} provision ".escapeshellarg($slug);

        if (! empty($validated['template'])) {
            $provisionCmd .= ' --template='.escapeshellarg($validated['template']);
        }

        if (! empty($validated['clone_url'])) {
            $provisionCmd .= ' --clone-url='.escapeshellarg($validated['clone_url']);
        }

        if (! empty($validated['fork'])) {
            $provisionCmd .= ' --fork';
        }

        $provisionCmd .= ' --visibility='.escapeshellarg($validated['visibility']);

        if ($validated['name'] !== $slug) {
            $provisionCmd .= ' --name='.escapeshellarg($validated['name']);
        }

        if (! empty($validated['php_version'])) {
            $provisionCmd .= ' --php='.escapeshellarg($validated['php_version']);
        }

        if (! empty($validated['db_driver'])) {
            $provisionCmd .= ' --db-driver='.escapeshellarg($validated['db_driver']);
        }

        if (! empty($validated['session_driver'])) {
            $provisionCmd .= ' --session-driver='.escapeshellarg($validated['session_driver']);
        }

        if (! empty($validated['cache_driver'])) {
            $provisionCmd .= ' --cache-driver='.escapeshellarg($validated['cache_driver']);
        }

        if (! empty($validated['queue_driver'])) {
            $provisionCmd .= ' --queue-driver='.escapeshellarg($validated['queue_driver']);
        }

        if (! empty($validated['minimal'])) {
            $provisionCmd .= ' --minimal';
        }

        // Use at now to run in background (same as CLI project:create)
        $logFile = "/tmp/provision-{$slug}.log";
        $scriptFile = "/tmp/provision-{$slug}.sh";

        // Write launcher script with explicit PATH
        $pathExport = 'export PATH="$HOME/.bun/bin:$HOME/.local/bin:$HOME/.config/herd-lite/bin:/usr/local/bin:/usr/bin:/bin"';
        file_put_contents($scriptFile, "#!/bin/bash\n{$pathExport}\n{$provisionCmd} > {$logFile} 2>&1\n");
        chmod($scriptFile, 0755);

        // Schedule via at
        exec("echo {$scriptFile} | at now 2>/dev/null");

        return response()->json([
            'success' => true,
            'status' => 'provisioning',
            'slug' => $slug,
            'log_file' => $logFile,
            'message' => 'Project provisioning started.',
        ], 202);
    }

    /**
     * Health check endpoint.
     */
    public function health(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'status' => 'ok',
            'timestamp' => now()->toISOString(),
        ]);
    }
}
