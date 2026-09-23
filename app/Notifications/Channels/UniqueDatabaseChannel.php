<?php

namespace App\Notifications\Channels;

use Illuminate\Notifications\Channels\DatabaseChannel;
use Illuminate\Notifications\Notification;

class UniqueDatabaseChannel extends DatabaseChannel
{
    public function send($notifiable, Notification $notification)
    {
        // firstOrCreate recovers unique-key races without resetting existing read state.
        return $notifiable->routeNotificationFor('database', $notification)->firstOrCreate(
            ['id' => $notification->id],
            $this->buildPayload($notifiable, $notification),
        );
    }
}
