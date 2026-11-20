<?php

namespace App\Console\Commands;

use App\Models\AccountSetting;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Console\Command;

class SendRentReminders extends Command
{
    protected $signature = 'notifications:run {--user= : Only one landlord}';

    protected $description = 'Raise rent reminders, overdue chases and lease-expiry warnings, then send what is queued';

    public function handle(NotificationService $notify): int
    {
        $owners = User::query()
            ->whereNull('account_owner_id')
            ->when($this->option('user'), fn ($q) => $q->whereKey($this->option('user')))
            ->get();

        $queued = 0;
        $alerts = 0;

        foreach ($owners as $owner) {
            $settings = AccountSetting::forOwner($owner->id);

            if (! ($settings['notify_tenants'] ?? true)) {
                continue;
            }

            $queued += $notify->rentReminders($owner, (int) ($settings['remind_days_before'] ?? 3));
            $alerts += $notify->leaseExpiryWarnings($owner);
        }

        $result = $notify->dispatchQueued();

        $this->info("Queued {$queued} message(s), raised {$alerts} alert(s).");
        $this->info("Sent {$result['sent']}, failed {$result['failed']}.");

        return self::SUCCESS;
    }
}
