<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\Services\DisableServiceJob;
use App\Jobs\Services\EnableServiceJob;
use App\Jobs\Services\RestartServiceJob;
use App\Jobs\Services\StartServiceJob;
use App\Jobs\Services\StopServiceJob;
use App\Models\TrackedJob;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

class ServiceController extends Controller
{
    public function status()
    {
        // Synchronous call - returns current status immediately
        $launchpadBinary = env('LAUNCHPAD_BINARY', '/usr/local/bin/launchpad');
        $result = Process::timeout(30)->run("{$launchpadBinary} status --json");

        if ($result->successful()) {
            $data = json_decode($result->output(), true);

            return response()->json($data);
        }

        return response()->json([
            'success' => false,
            'error' => $result->errorOutput() ?: 'Failed to get status',
        ], 500);
    }

    public function start(string $service)
    {
        return $this->dispatchServiceJob('start_service', $service, StartServiceJob::class, 'start');
    }

    public function stop(string $service)
    {
        return $this->dispatchServiceJob('stop_service', $service, StopServiceJob::class, 'stop');
    }

    public function restart(string $service)
    {
        return $this->dispatchServiceJob('restart_service', $service, RestartServiceJob::class, 'restart');
    }

    public function enable(string $service)
    {
        return $this->dispatchServiceJob('enable_service', $service, EnableServiceJob::class, 'enable');
    }

    public function disable(string $service)
    {
        return $this->dispatchServiceJob('disable_service', $service, DisableServiceJob::class, 'disable');
    }

    private function dispatchServiceJob(string $type, string $service, string $jobClass, string $action)
    {
        $jobId = (string) Str::uuid();

        TrackedJob::create([
            'id' => $jobId,
            'type' => $type,
            'subject' => $service,
            'status' => 'pending',
            'payload' => ['action' => $action],
        ]);

        $jobClass::dispatch($jobId, $service);

        return response()->json(['jobId' => $jobId], 202);
    }
}
