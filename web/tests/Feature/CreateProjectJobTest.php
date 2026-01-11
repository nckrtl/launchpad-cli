<?php

namespace Tests\Feature;

use App\Jobs\CreateProjectJob;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class CreateProjectJobTest extends TestCase
{
    public function test_job_builds_correct_basic_command(): void
    {
        Process::fake(['*' => Process::result(output: '{"success":true}')]);

        $job = new CreateProjectJob(
            slug: 'my-app',
        );

        $job->handle();

        Process::assertRan(function ($process) {
            $command = $process->command;

            return str_contains($command, 'provision')
                && str_contains($command, "'my-app'");
        });
    }

    public function test_job_includes_template_option(): void
    {
        Process::fake(['*' => Process::result(output: '{"success":true}')]);

        $job = new CreateProjectJob(
            slug: 'my-app',
            template: 'liftoff-starterkit',
        );

        $job->handle();

        Process::assertRan(function ($process) {
            $command = $process->command;

            return str_contains($command, '--template=')
                && str_contains($command, 'liftoff-starterkit');
        });
    }

    public function test_job_includes_php_version(): void
    {
        Process::fake(['*' => Process::result(output: '{"success":true}')]);

        $job = new CreateProjectJob(
            slug: 'my-app',
            phpVersion: '8.4',
        );

        $job->handle();

        Process::assertRan(function ($process) {
            $command = $process->command;

            return str_contains($command, '--php=')
                && str_contains($command, '8.4');
        });
    }

    public function test_job_includes_db_driver(): void
    {
        Process::fake(['*' => Process::result(output: '{"success":true}')]);

        $job = new CreateProjectJob(
            slug: 'my-app',
            dbDriver: 'pgsql',
        );

        $job->handle();

        Process::assertRan(function ($process) {
            $command = $process->command;

            return str_contains($command, '--db-driver=')
                && str_contains($command, 'pgsql');
        });
    }

    public function test_job_includes_fork_flag(): void
    {
        Process::fake(['*' => Process::result(output: '{"success":true}')]);

        $job = new CreateProjectJob(
            slug: 'my-app',
            fork: true,
        );

        $job->handle();

        Process::assertRan(function ($process) {
            $command = $process->command;

            return str_contains($command, '--fork');
        });
    }

    public function test_job_throws_on_failure(): void
    {
        Process::fake(['*' => Process::result(
            output: '',
            errorOutput: 'Provision failed: something went wrong',
            exitCode: 1,
        )]);

        $job = new CreateProjectJob(slug: 'my-app');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Provision failed');

        $job->handle();
    }
}
