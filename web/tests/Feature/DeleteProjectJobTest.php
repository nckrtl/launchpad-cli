<?php

namespace Tests\Feature;

use App\Jobs\DeleteProjectJob;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class DeleteProjectJobTest extends TestCase
{
    public function test_job_builds_correct_basic_command(): void
    {
        Process::fake(['*' => Process::result(output: '{"success":true}')]);

        $job = new DeleteProjectJob(
            slug: 'my-app',
        );

        $job->handle();

        Process::assertRan(function ($process) {
            $command = $process->command;

            return str_contains($command, 'project:delete')
                && str_contains($command, "--slug='my-app'")
                && str_contains($command, '--force')
                && str_contains($command, '--json');
        });
    }

    public function test_job_includes_delete_repo_flag(): void
    {
        Process::fake(['*' => Process::result(output: '{"success":true}')]);

        $job = new DeleteProjectJob(
            slug: 'my-app',
            deleteRepo: true,
        );

        $job->handle();

        Process::assertRan(function ($process) {
            $command = $process->command;

            return str_contains($command, '--delete-repo');
        });
    }

    public function test_job_includes_keep_db_flag(): void
    {
        Process::fake(['*' => Process::result(output: '{"success":true}')]);

        $job = new DeleteProjectJob(
            slug: 'my-app',
            keepDb: true,
        );

        $job->handle();

        Process::assertRan(function ($process) {
            $command = $process->command;

            return str_contains($command, '--keep-db');
        });
    }

    public function test_job_throws_on_failure(): void
    {
        Process::fake(['*' => Process::result(
            output: '',
            errorOutput: 'Delete failed: permission denied',
            exitCode: 1,
        )]);

        $job = new DeleteProjectJob(slug: 'my-app');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Delete failed');

        $job->handle();
    }

    public function test_job_has_correct_timeout(): void
    {
        $job = new DeleteProjectJob(slug: 'test');

        $this->assertEquals(60, $job->timeout);
    }

    public function test_job_has_correct_tries(): void
    {
        $job = new DeleteProjectJob(slug: 'test');

        $this->assertEquals(1, $job->tries);
    }
}
