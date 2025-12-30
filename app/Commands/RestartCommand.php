<?php

namespace App\Commands;

use LaravelZero\Framework\Commands\Command;

class RestartCommand extends Command
{
    protected $signature = 'restart';

    protected $description = 'Restart all Launchpad services';

    public function handle(): int
    {
        $this->call('stop');
        $this->call('start');

        return self::SUCCESS;
    }
}
