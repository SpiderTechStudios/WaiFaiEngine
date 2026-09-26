<?php

namespace App\Console\Commands;

use App\Services\AccessExpiryService;
use Illuminate\Console\Command;

class ExpireAccessCommand extends Command
{
    protected $signature = 'access:expire';

    protected $description = 'Expire due access grants and end their live hotspot/captive sessions (customers are kept)';

    public function handle(AccessExpiryService $accessExpiryService): int
    {
        $result = $accessExpiryService->expireDue();

        $this->info(sprintf(
            'Expired %d grant(s), ended %d network session(s), expired %d captive session(s).',
            $result['grants'],
            $result['network_sessions'],
            $result['captive_sessions'],
        ));

        return self::SUCCESS;
    }
}
