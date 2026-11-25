<?php

namespace App\Notifications\FarmOperations;

use App\Models\Team;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DailyFarmRemindersNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array{
     *     teamName: string,
     *     date: string,
     *     overdueHealthEvents: list<array{id: int, title: string, farmName: string, cycleName: string|null, scheduledOn: string}>,
     *     dueSoonHealthEvents: list<array{id: int, title: string, farmName: string, cycleName: string|null, scheduledOn: string}>,
     *     activeWithdrawals: list<array{id: int, title: string, farmName: string, cycleName: string|null, endsOn: string|null}>,
     *     lowStockItems: list<array{id: int, name: string, farmName: string|null, currentQuantity: string, unit: string}>,
     *     waterQualityAlerts: list<array{cycleId: int, farmId: int, farmName: string, cycleName: string, alerts: list<string>}>,
     *     overdueTasksCount: int,
     *     hasAlerts: bool,
     *     whatsappText: string,
     * }  $digest
     */
    public function __construct(
        public Team $team,
        public array $digest,
    ) {
        //
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject(__('Daily farm alerts for :team (:date)', [
                'team' => $this->team->name,
                'date' => $this->digest['date'],
            ]))
            ->greeting(__('Good morning, team'))
            ->line(__('Here is your operational summary for :team:', ['team' => $this->team->name]));

        foreach ($this->digest['waterQualityAlerts'] as $water) {
            $message->line(sprintf('Water alert (%s — %s): %s', $water['farmName'], $water['cycleName'], implode('; ', $water['alerts'])));
        }

        foreach ($this->digest['overdueHealthEvents'] as $event) {
            $message->line(sprintf('Overdue health event (%s): %s was due %s.', $event['farmName'], $event['title'], $event['scheduledOn']));
        }

        foreach ($this->digest['dueSoonHealthEvents'] as $event) {
            $message->line(sprintf('Upcoming health event (%s): %s scheduled for %s.', $event['farmName'], $event['title'], $event['scheduledOn']));
        }

        foreach ($this->digest['activeWithdrawals'] as $withdrawal) {
            $message->line(sprintf('Withdrawal hold (%s): %s — safe harvest on/after %s.', $withdrawal['farmName'], $withdrawal['title'], $withdrawal['endsOn'] ?? 'N/A'));
        }

        foreach ($this->digest['lowStockItems'] as $item) {
            $message->line(sprintf('Low stock: %s (%s %s left).', $item['name'], $item['currentQuantity'], $item['unit']));
        }

        if ($this->digest['overdueTasksCount'] > 0) {
            $message->line(sprintf('Overdue farm tasks: %d', $this->digest['overdueTasksCount']));
        }

        return $message->action(__('Open Farmwell Dashboard'), route('dashboard', ['current_team' => $this->team->slug]));
    }
}
