<?php

namespace Tests\Feature;

use App\Jobs\CreateProjectJob;
use App\Jobs\DeleteProjectJob;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ProjectApiTest extends TestCase
{
    public function test_create_project_validates_required_fields(): void
    {
        $response = $this->postJson('/api/projects', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_create_project_rejects_reserved_name(): void
    {
        $response = $this->postJson('/api/projects', [
            'name' => 'launchpad',
            'visibility' => 'private',
        ]);

        // Reserved name returns custom 422 error (not Laravel validation error)
        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ])
            ->assertJsonPath('error', fn ($error) => str_contains($error, 'reserved'));
    }

    public function test_create_project_dispatches_job(): void
    {
        Queue::fake();

        $response = $this->postJson('/api/projects', [
            'name' => 'test-project',
            'visibility' => 'private',
        ]);

        $response->assertStatus(202)
            ->assertJson([
                'success' => true,
                'status' => 'provisioning',
            ]);

        Queue::assertPushed(CreateProjectJob::class, function ($job) {
            return $job->slug === 'test-project';
        });
    }

    public function test_create_project_with_template(): void
    {
        Queue::fake();

        $response = $this->postJson('/api/projects', [
            'name' => 'my-app',
            'template' => 'laravel/laravel',
            'visibility' => 'public',
            'php_version' => '8.4',
        ]);

        $response->assertStatus(202);

        Queue::assertPushed(CreateProjectJob::class, function ($job) {
            return $job->slug === 'my-app'
                && $job->template === 'laravel/laravel'
                && $job->visibility === 'public'
                && $job->phpVersion === '8.4';
        });
    }

    public function test_delete_project_dispatches_job(): void
    {
        Queue::fake();

        $response = $this->deleteJson('/api/projects/test-project');

        $response->assertStatus(202)
            ->assertJson([
                'success' => true,
                'status' => 'deleting',
                'slug' => 'test-project',
            ]);

        Queue::assertPushed(DeleteProjectJob::class, function ($job) {
            return $job->slug === 'test-project';
        });
    }

    public function test_delete_project_returns_correct_structure(): void
    {
        Queue::fake();

        $response = $this->deleteJson('/api/projects/another-project');

        $response->assertStatus(202)
            ->assertJsonStructure([
                'success',
                'status',
                'slug',
            ]);
    }
}
