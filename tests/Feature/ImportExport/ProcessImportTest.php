<?php

namespace Tests\Feature\ImportExport;

use App\Models\User;
use App\Services\ImportExport\DTOs\ImportConfig;
use App\Services\ImportExport\Enums\ImportFormat;
use App\Services\ImportExport\Enums\ImportStatus;
use App\Services\ImportExport\Imports\UserImporter;
use App\Services\ImportExport\Jobs\ProcessImport;
use App\Services\ImportExport\Models\ImportLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProcessImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_imports_valid_rows(): void
    {
        $user = User::factory()->create();

        $file = UploadedFile::fake()->createWithContent(
            'test.csv',
            "Email,Name,Password\nnew@example.com,New User,password123\nother@example.com,Other User,password456"
        );
        $path = $file->store('imports/test');

        $importLog = ImportLog::factory()->create([
            'user_id' => $user->id,
            'model_type' => User::class,
            'status' => 'pending',
        ]);

        $config = new ImportConfig(
            format: ImportFormat::Csv,
            filePath: Storage::path($path),
            modelType: User::class,
            columnMapping: ['email' => 'Email', 'name' => 'Name', 'password' => 'Password'],
            validationRules: [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'max:255'],
                'password' => ['required', 'string', 'min:8'],
            ],
            uniqueKey: 'email',
            importerClass: UserImporter::class,
            chunkSize: 500,
        );

        (new ProcessImport($config, $importLog->id))->handle();

        $importLog->refresh();
        $this->assertEquals(ImportStatus::Completed->value, $importLog->status);
        $this->assertEquals(2, $importLog->processed_rows);

        $this->assertDatabaseHas('users', ['email' => 'new@example.com']);
        $this->assertDatabaseHas('users', ['email' => 'other@example.com']);
    }

    public function test_job_skips_duplicates(): void
    {
        $user = User::factory()->create();
        User::factory()->create(['email' => 'existing@example.com']);

        $file = UploadedFile::fake()->createWithContent(
            'test.csv',
            "Email,Name,Password\nexisting@example.com,Existing,password123\nnew@example.com,New,password456"
        );
        $path = $file->store('imports/test');

        $importLog = ImportLog::factory()->create([
            'user_id' => $user->id,
            'model_type' => User::class,
            'status' => 'pending',
        ]);

        $config = new ImportConfig(
            format: ImportFormat::Csv,
            filePath: Storage::path($path),
            modelType: User::class,
            columnMapping: ['email' => 'Email', 'name' => 'Name', 'password' => 'Password'],
            validationRules: [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'max:255'],
                'password' => ['required', 'string', 'min:8'],
            ],
            uniqueKey: 'email',
            importerClass: UserImporter::class,
            chunkSize: 500,
        );

        (new ProcessImport($config, $importLog->id))->handle();

        $importLog->refresh();
        $this->assertEquals(ImportStatus::Completed->value, $importLog->status);
        $this->assertEquals(1, $importLog->processed_rows);
        $this->assertEquals(1, $importLog->skipped_rows);
    }

    public function test_job_collects_validation_errors(): void
    {
        $user = User::factory()->create();

        $file = UploadedFile::fake()->createWithContent(
            'test.csv',
            "Email,Name,Password\nbad-email,Valid Name,short"
        );
        $path = $file->store('imports/test');

        $importLog = ImportLog::factory()->create([
            'user_id' => $user->id,
            'model_type' => User::class,
            'status' => 'pending',
        ]);

        $config = new ImportConfig(
            format: ImportFormat::Csv,
            filePath: Storage::path($path),
            modelType: User::class,
            columnMapping: ['email' => 'Email', 'name' => 'Name', 'password' => 'Password'],
            validationRules: [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'max:255'],
                'password' => ['required', 'string', 'min:8'],
            ],
            uniqueKey: 'email',
            importerClass: UserImporter::class,
            chunkSize: 500,
        );

        (new ProcessImport($config, $importLog->id))->handle();

        $importLog->refresh();
        $this->assertEquals(ImportStatus::Completed->value, $importLog->status);
        $this->assertNotEmpty($importLog->errors);
    }
}
