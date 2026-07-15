<?php

namespace Tests\Unit;

use App\Events\StockChanged;
use PHPUnit\Framework\TestCase;

class StockChangedTest extends TestCase
{
    public function test_broadcasts_with_supplier_id_when_given(): void
    {
        $event = new StockChanged(supplierId: 42);

        $this->assertSame(['supplierId' => 42], $event->broadcastWith());
    }

    public function test_broadcasts_with_null_supplier_id_by_default(): void
    {
        $event = new StockChanged();

        $this->assertSame(['supplierId' => null], $event->broadcastWith());
    }

    public function test_broadcast_name_is_the_short_class_name(): void
    {
        // Without broadcastAs(), Laravel broadcasts using the fully
        // qualified class name (e.g. "App\Events\StockChanged"), which does
        // NOT match the frontend's dot-prefixed `.listen('.StockChanged', ...)`
        // (laravel-echo's leading-dot form matches the literal string with
        // no namespace prepended) — so the listener would silently never
        // fire. broadcastAs() must return the short name to keep them in sync.
        $event = new StockChanged();

        $this->assertSame('StockChanged', $event->broadcastAs());
    }
}
