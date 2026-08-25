<?php

namespace Tests\Feature;

use App\Models\Rack;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditableByTraitTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_sets_created_by_and_updated_by_from_auth_user(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $rack = Rack::create(['code' => 'A-01', 'zone' => 'Zona A']);

        $this->assertSame($user->id, $rack->created_by);
        $this->assertSame($user->id, $rack->updated_by);
    }

    public function test_updating_sets_updated_by_from_auth_user(): void
    {
        $creator = User::factory()->create();
        $this->actingAs($creator);
        $rack = Rack::create(['code' => 'A-02', 'zone' => 'Zona A']);

        $editor = User::factory()->create();
        $this->actingAs($editor);
        $rack->update(['zone' => 'Zona B']);

        $fresh = $rack->fresh();
        $this->assertSame($creator->id, $fresh->created_by);
        $this->assertSame($editor->id, $fresh->updated_by);
    }

    public function test_explicitly_set_values_are_not_overwritten(): void
    {
        $authUser = User::factory()->create();
        $explicitUser = User::factory()->create();
        $this->actingAs($authUser);

        $rack = Rack::create([
            'code' => 'A-03',
            'zone' => 'Zona C',
            'created_by' => $explicitUser->id,
            'updated_by' => $explicitUser->id,
        ]);

        $this->assertSame($explicitUser->id, $rack->created_by);
        $this->assertSame($explicitUser->id, $rack->updated_by);
    }

    public function test_creator_and_updater_relations(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $rack = Rack::create(['code' => 'A-04', 'zone' => 'Zona D']);

        $this->assertSame($user->id, $rack->creator->id);
        $this->assertSame($user->id, $rack->updater->id);
    }
}
