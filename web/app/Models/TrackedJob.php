<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class TrackedJob extends Model
{
    use HasUuids;

    protected $fillable = [
        'id',
        'type',
        'subject',
        'status',
        'payload',
        'error',
    ];

    protected $casts = [
        'payload' => 'array',
    ];

    public function markProcessing(): void
    {
        $this->update(['status' => 'processing']);
    }

    public function markCompleted(): void
    {
        $this->update(['status' => 'completed']);
    }

    public function markFailed(string $error): void
    {
        $this->update(['status' => 'failed', 'error' => $error]);
    }
}
