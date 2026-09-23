<?php

namespace App\Console\Commands;

use App\Models\Enrollment;
use App\Models\PlatformPayment;
use App\Services\PlatformPaymentService;
use Illuminate\Console\Command;

class CompletePaidEnrollmentsCommand extends Command
{
    protected $signature = 'enrollments:complete-paid
                            {--limit=200 : Maximum payments to process}';

    protected $description = 'Create accounts for paid enrollment payments whose account was not created';

    public function handle(PlatformPaymentService $platformPaymentService): int
    {
        $payments = PlatformPayment::query()
            ->where('status', PlatformPayment::STATUS_PAID)
            ->whereNotNull('signup_intent_id')
            ->whereIn('type', [
                PlatformPayment::TYPE_PLATFORM_SUBSCRIPTION,
                PlatformPayment::TYPE_SIGNUP,
            ])
            ->whereHas('enrollment', fn ($query) => $query->where('status', '!=', Enrollment::STATUS_COMPLETED))
            ->orderBy('id')
            ->limit(max(1, (int) $this->option('limit')))
            ->get();

        foreach ($payments as $payment) {
            $platformPaymentService->retryEnrollmentCompletion($payment);
        }

        $this->info("Processed {$payments->count()} paid enrollment payment(s).");

        return self::SUCCESS;
    }
}
