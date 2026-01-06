<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;

final class ReverbBroadcaster
{
    private string $reverbUrl;

    public function __construct(ConfigManager $config)
    {
        $this->reverbUrl = $config->get('reverb.url', '');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function broadcast(string $channel, string $event, array $data): void
    {
        if (empty($this->reverbUrl)) {
            return; // Silently skip if not configured
        }

        try {
            // No auth required - trusted network
            Http::timeout(5)->post("{$this->reverbUrl}/apps/launchpad/events", [
                'name' => $event,
                'channel' => $channel,
                'data' => json_encode($data),
            ]);
        } catch (\Throwable) {
            // Don't fail the main operation if broadcasting fails
        }
    }
}
