<?php

namespace App\Console\Commands;

use App\Actions\FarmOperations\BuildDailyOperationalReminders;
use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\TeamSetting;
use App\Notifications\FarmOperations\DailyFarmRemindersNotification;
use Illuminate\Console\Command;

class SendDailyFarmRemindersCommand extends Command
{
    protected $signature = 'farmwell:send-daily-reminders {--team= : Limit reminders to a specific team slug}';

    protected $description = 'Send daily operational reminders and health, stock, and water alerts to active farm teams';

    public function handle(BuildDailyOperationalReminders $buildReminders): int
    {
        $teamSlug = $this->option('team');

        $teams = Team::query()
            ->when(is_string($teamSlug) && $teamSlug !== '', fn ($query) => $query->where('slug', $teamSlug))
            ->get();

        $notificationsSent = 0;

        foreach ($teams as $team) {
            $setting = TeamSetting::query()
                ->where('team_id', $team->id)
                ->where('setting_group', 'operations')
                ->where('setting_key', 'daily_reminders')
                ->first();

            if (($setting?->value['enabled'] ?? true) === false) {
                continue;
            }

            $digest = $buildReminders->handle($team);

            if (! $digest['hasAlerts']) {
                continue;
            }

            $recipients = $team->members()
                ->wherePivotIn('role', [
                    TeamRole::Owner->value,
                    TeamRole::Admin->value,
                    TeamRole::Operator->value,
                ])
                ->whereNotNull('email_verified_at')
                ->get();

            foreach ($recipients as $recipient) {
                $recipient->notify(new DailyFarmRemindersNotification($team, $digest));
                $notificationsSent++;
            }
        }

        $this->info(sprintf('Dispatched %d daily farm reminder notification(s).', $notificationsSent));

        return self::SUCCESS;
    }
}
