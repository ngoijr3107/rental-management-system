<?php

namespace App\Console\Commands;

use App\Services\LeaseService;
use Illuminate\Console\Command;

class ExpireFinishedLeases extends Command
{
    protected $signature = 'leases:expire';

    protected $description = 'Mark leases whose end date has passed as expired and free their units';

    public function handle(LeaseService $leases): int
    {
        $count = $leases->expireFinishedLeases();

        $this->info("{$count} lease(s) expired.");

        return self::SUCCESS;
    }
}
