<?php

namespace App\Console\Commands;

use App\Services\AccessPresenceService;
use Illuminate\Console\Command;

class ExpireStalePresenceCommand extends Command
{
    protected $signature = 'access:presence';

    protected $description = 'Legacy presence check; access is ended only when the paid grant expires';

    public function handle(AccessPresenceService $presenceService): int
    {
        $result = $presenceService->expireStale();

        $this->info(sprintf(
            'Presence check complete. %d network session(s) and %d captive session(s) were ended (access follows grant expiry).',
            $result['network_sessions'],
            $result['captive_sessions'],
        ));

        return self::SUCCESS;
    }
}
