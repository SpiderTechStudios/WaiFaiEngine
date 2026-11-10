<?php

namespace App\Console\Commands;

use App\Services\AccessPresenceService;
use Illuminate\Console\Command;

class ExpireStalePresenceCommand extends Command
{
    protected $signature = 'access:presence';

    protected $description = 'Mark WiFiDog clients offline when their activity counters stop (grants are kept)';

    public function handle(AccessPresenceService $presenceService): int
    {
        $result = $presenceService->expireStale();

        $this->info(sprintf(
            'Marked %d network session(s) offline and %d captive session(s) disconnected.',
            $result['network_sessions'],
            $result['captive_sessions'],
        ));

        return self::SUCCESS;
    }
}
