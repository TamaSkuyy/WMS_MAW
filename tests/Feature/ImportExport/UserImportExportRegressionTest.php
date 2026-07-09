<?php

namespace Tests\Feature\ImportExport;

use App\Models\User;
use App\Services\ImportExport\Models\ImportLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserImportExportRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_export_still_requires_admin_role(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('users.export', ['format' => 'xlsx']));

        $response->assertForbidden();
    }

    public function test_import_template_downloads_successfully(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('users.import-template', ['format' => 'csv']));

        $response->assertOk();
    }

    public function test_import_status_route_is_owner_scoped(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $importLog = ImportLog::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($stranger);
        $this->get(route('import.status', $importLog->id))->assertForbidden();

        $this->actingAs($owner);
        $this->get(route('import.status', $importLog->id))->assertOk();
    }
}
