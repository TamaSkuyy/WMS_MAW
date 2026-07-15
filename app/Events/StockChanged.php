<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class StockChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(public ?int $supplierId = null)
    {
    }

    public function broadcastOn(): Channel
    {
        return new Channel('warehouse.stock');
    }

    public function broadcastAs(): string
    {
        return 'StockChanged';
    }

    public function broadcastWith(): array
    {
        return ['supplierId' => $this->supplierId];
    }
}
