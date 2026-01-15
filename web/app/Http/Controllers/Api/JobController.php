<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TrackedJob;
use Illuminate\Http\JsonResponse;

class JobController extends Controller
{
    public function show(TrackedJob $trackedJob): JsonResponse
    {
        return response()->json([
            'id' => $trackedJob->id,
            'type' => $trackedJob->type,
            'subject' => $trackedJob->subject,
            'status' => $trackedJob->status,
            'error' => $trackedJob->error,
            'created_at' => $trackedJob->created_at,
            'updated_at' => $trackedJob->updated_at,
        ]);
    }
}
