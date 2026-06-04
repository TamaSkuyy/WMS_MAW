<?php

namespace Tests\Feature\ImportExport;

use App\Models\User;
use App\Services\ImportExport\Imports\UserImporter;
use App\Services\ImportExport\Managers\ImportManager;
use App\Services\ImportExport\Models\ImportLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ImportManagerTest extends TestCase
{
    use RefreshDatabase;

    public function test_preview_rejects_invalid_format(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $file = UploadedFile::fake()->create('test.pdf', 100);

        $this->expectException(\InvalidArgumentException::class);

        $manager = app(ImportManager::class);
        $manager->preview(new UserImporter(), $file);
    }

    public function test_start_creates_import_log(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $file = UploadedFile::fake()->createWithContent(
            'users.csv',
            "Email,Name,Password\nnew@example.com,New User,password123\nother@example.com,Other,password456"
        );

        $manager = app(ImportManager::class);
        $importLog = $manager->start(new UserImporter(), $file, [
            'email' => 'Email',
            'name' => 'Name',
            'password' => 'Password',
        ], $user->id);

        $this->assertDatabaseHas('import_logs', ['id' => $importLog->id]);
        $this->assertEquals('pending', $importLog->status);
    }

    public function test_status_returns_import_log(): void
    {
        $user = User::factory()->create();
        $importLog = ImportLog::factory()->create(['user_id' => $user->id]);

        $manager = app(ImportManager::class);
        $result = $manager->status($importLog->id);

        $this->assertEquals($importLog->id, $result->id);
    }
}
