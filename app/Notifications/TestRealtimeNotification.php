<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class TestRealtimeNotification extends Notification implements ShouldBroadcastNow
{
    use Queueable;

    public function __construct()
    {
        //
    }

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toDatabase($notifiable)
    {
        return [
            'title' => 'Realtime Reverb ⚡',
            'message' => 'Ini notifikasi masuk tanpa refresh!',
            'icon' => 'fas fa-bolt text-yellow-500',
            'link' => '/dashboard'
        ];
    }

    public function toBroadcast($notifiable)
    {
        return new BroadcastMessage([
            'title' => 'Realtime Reverb ⚡',
            'message' => 'Ini notifikasi masuk tanpa refresh!',
            'icon' => 'fas fa-bolt text-yellow-500',
            'link' => '/dashboard',
            'id' => $this->id,
        ]);
    }
}
