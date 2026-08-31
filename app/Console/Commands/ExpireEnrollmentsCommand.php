<?php

namespace App\Console\Commands;

use App\Services\EnrollmentService;
use Illuminate\Console\Command;

class ExpireEnrollmentsCommand extends Command
{
    protected $signature = 'enrollments:expire';

    protected $description = 'Expire timed-out registration enrollments and release temporary reservations';

    public function handle(EnrollmentService $enrollmentService): int
    {
        $count = $enrollmentService->expireDueEnrollments();
        $this->info("Expired {$count} enrollment(s).");

        return self::SUCCESS;
    }
}
