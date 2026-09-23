<?php

namespace App\Console\Commands;

use App\Services\PetCareReminders;
use Illuminate\Console\Command;

class SendPetCareReminders extends Command
{
    protected $signature = 'pawid:care-reminders {--dry-run : Count new reminders without writing notifications}';
    protected $description = 'Create vaccination and deworming reminders for current pet owners';

    public function handle(PetCareReminders $reminders): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $count = $reminders->generate($dryRun);
        $this->info($dryRun ? "Would create {$count} reminder(s). No notifications written." : "Created {$count} reminder(s).");

        return self::SUCCESS;
    }
}
