<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\CreateProjectJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    /**
     * Create a new project.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'slug' => 'required|string|min:1|max:255',
            'template' => 'required|string',
            'db_driver' => 'required|in:mysql,pgsql,sqlite',
            'visibility' => 'required|in:public,private',
        ]);

        // Reject reserved name
        if (strtolower($validated['slug']) === 'launchpad') {
            return response()->json([
                'error' => 'The name "launchpad" is reserved for the system.',
            ], 422);
        }

        CreateProjectJob::dispatch(
            slug: $validated['slug'],
            template: $validated['template'],
            dbDriver: $validated['db_driver'],
            visibility: $validated['visibility'],
        );

        return response()->json([
            'status' => 'queued',
            'slug' => $validated['slug'],
            'message' => 'Project creation has been queued.',
        ], 202);
    }

    /**
     * Delete a project.
     */
    public function destroy(string $slug): JsonResponse
    {
        // TODO: Implement DeleteProjectJob
        return response()->json([
            'error' => 'Not implemented yet',
        ], 501);
    }

    /**
     * Health check endpoint.
     */
    public function health(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'timestamp' => now()->toISOString(),
        ]);
    }
}
