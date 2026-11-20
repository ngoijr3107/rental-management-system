<?php

use Illuminate\Support\Facades\Schedule;

/*
| Bill every active lease on the 1st of the month, then tidy up leases
| whose end date has passed. Requires a single cron entry on the server:
|
|   * * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
*/
// Runs daily and raises only what is due, so a lease on any cycle —
// monthly, quarterly, six-month, annual — is billed on its own dates.
Schedule::command('invoices:generate')->dailyAt('01:00');
Schedule::command('leases:expire')->dailyAt('00:30');

// Rent reminders, overdue chases and lease-expiry warnings. Runs after
// billing so a freshly raised invoice can be announced the same morning.
Schedule::command('notifications:run')->dailyAt('07:00');
