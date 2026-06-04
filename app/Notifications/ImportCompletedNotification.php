<?php

namespace App\Notifications;

use App\Services\ImportExport\Models\ImportLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class ImportCompletedNotification extends Notification implements ShouldBroadcast
{
    use Queueable;

    public function __construct(private readonly ImportLog $importLog) {}

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'message' => "Import of {$this->importLog->file_name} completed: {$this->importLog->processed_rows} rows imported, {$this->importLog->skipped_rows} skipped.",
            'import_log_id' => $this->importLog->id,
            'status' => $this->importLog->status,
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
