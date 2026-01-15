<?php

namespace App\Jobs\Services;

use App\Events\ServiceStatusChanged;
use App\Models\TrackedJob;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Process;

class EnableServiceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $jobId,
        public string $service,
    ) {}

    public function handle(): void
    {
        $trackedJob = TrackedJob::find($this->jobId);
        $trackedJob?->markProcessing();

        $launchpadBinary = env('LAUNCHPAD_BINARY', '/usr/local/bin/launchpad');
        $result = Process::timeout(120)->run("{$launchpadBinary} services:enable {$this->service}");

        if ($result->successful()) {
            $trackedJob?->markCompleted();
            broadcast(new ServiceStatusChanged(
                jobId: $this->jobId,
                service: $this->service,
                status: 'enabled',
                action: 'enable',
            ));
        } else {
            $error = $result->errorOutput() ?: $result->output();
            $trackedJob?->markFailed($error);
            broadcast(new ServiceStatusChanged(
                jobId: $this->jobId,
                service: $this->service,
                status: 'failed',
                action: 'enable',
                error: $error,
            ));
        }
    }
}
