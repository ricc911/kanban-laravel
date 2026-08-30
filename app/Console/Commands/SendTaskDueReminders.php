<?php

namespace App\Console\Commands;

use App\Actions\Tasks\SendTaskDueReminders as ReminderAction;
use Illuminate\Console\Command;

class SendTaskDueReminders extends Command
{
    protected $signature = 'tasks:send-due-reminders';

    protected $description = 'Invia i promemoria delle scadenze task';

    public function handle(ReminderAction $action): int
    {
        $counts = $action->execute();
        $this->info("Due soon notifications: {$counts['due_soon']}");
        $this->info("Overdue notifications: {$counts['overdue']}");

        return self::SUCCESS;
    }
}
