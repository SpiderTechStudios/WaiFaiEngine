<?php

namespace App\Console\Commands;

use App\Models\OmadaIntegration;
use App\Services\Omada\OmadaException;
use App\Services\Omada\OmadaIntegrationService;
use App\Services\Omada\OmadaSessionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncOmadaCommand extends Command
{
    protected $signature = 'omada:sync {--integration= : Sync a single Omada integration ID}';

    protected $description = 'Refresh Omada controllers and expire finished client sessions';

    public function handle(OmadaIntegrationService $integrations, OmadaSessionService $sessions): int
    {
        $expired = $sessions->expireDue();
        $this->info("Expired {$expired} Omada client session(s).");

        $query = OmadaIntegration::query()->where('status', 'active');

        if ($this->option('integration')) {
            $query->whereKey((int) $this->option('integration'));
        }

        $query->orderBy('id')->each(function (OmadaIntegration $integration) use ($integrations): void {
            $result = $integrations->test($integration);

            if (! ($result['reachable'] ?? false) || ! ($result['authenticated'] ?? false)) {
                $this->warn("Integration {$integration->id}: ".($result['message'] ?? 'unavailable'));
                Log::warning('omada_sync_unavailable', [
                    'integration_id' => $integration->id,
                    'company_id' => $integration->company_id,
                    'status' => $integration->connection_status,
                ]);

                return;
            }

            try {
                $integrations->syncSites($integration);

                if (filled($integration->site_id)) {
                    $integrations->syncAccessPoints($integration);
                    $integrations->syncSsids($integration);
                }
            } catch (OmadaException $exception) {
                $this->warn("Integration {$integration->id}: ".$exception->userMessage());
                Log::warning('omada_sync_failed', [
                    'integration_id' => $integration->id,
                    'company_id' => $integration->company_id,
                    'error_code' => $exception->errorCode,
                ]);
            }
        });

        return self::SUCCESS;
    }
}
