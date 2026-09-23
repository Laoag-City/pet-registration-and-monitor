<?php

namespace App\Notifications;

use App\Notifications\Channels\UniqueDatabaseChannel;
use Illuminate\Notifications\Notification;
use Ramsey\Uuid\Uuid;

class PetCareReminder extends Notification
{
    public function __construct(public array $schedule, public string $stage, public int $priority)
    {
        // Includes owner, pet, record, treatment type, due date and reminder stage.
        $this->id = (string) Uuid::uuid5(Uuid::NAMESPACE_URL, 'pawid:'.$schedule['key'].':'.$stage);
    }

    public function via(object $notifiable): array
    {
        return [UniqueDatabaseChannel::class];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'schedule_key' => $this->schedule['key'],
            'pet_id' => $this->schedule['pet']->id,
            'medical_type' => $this->schedule['type'],
            'record_id' => $this->schedule['record']->id,
            'due_date' => $this->schedule['due']->toDateString(),
            'stage' => $this->stage,
            'priority' => $this->priority,
        ];
    }
}
