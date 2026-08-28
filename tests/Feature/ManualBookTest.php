<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Manual Book (/docs) — static HTML docs.
 *
 * Route harus mengembalikan 404 (bukan 500) saat file tidak ada:
 * response()->file() melempar FileNotFoundException → HTTP 500 kalau
 * tidak ada guard file_exists (regresi lama di production).
 *
 * Catatan: response()->file() menghasilkan BinaryFileResponse yang tidak
 * menyimpan konten di memori (getContent() = false), jadi assertion konten
 * memakai header Content-Type / Content-Length, bukan assertSee().
 */
class ManualBookTest extends TestCase
{
    public function test_docs_index_is_served(): void
    {
        $index = public_path('docs/index.html');

        $this->get('/docs')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/html; charset=utf-8')
            ->assertHeader('Content-Length', (string) filesize($index));
    }

    public function test_docs_trailing_slash_is_served(): void
    {
        $index = public_path('docs/index.html');

        $this->get('/docs/')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/html; charset=utf-8')
            ->assertHeader('Content-Length', (string) filesize($index));
    }

    public function test_docs_specific_html_file_is_served(): void
    {
        $file = public_path('docs/supplier-management.html');

        $this->get('/docs/supplier-management.html')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/html; charset=utf-8')
            ->assertHeader('Content-Length', (string) filesize($file));
    }

    public function test_missing_html_file_returns_404_not_500(): void
    {
        $this->get('/docs/tidak-ada-file.html')
            ->assertNotFound();
    }

    public function test_missing_file_without_html_extension_returns_404(): void
    {
        $this->get('/docs/tidak-ada-file')
            ->assertNotFound();
    }

    public function test_non_html_docs_path_returns_404(): void
    {
        $this->get('/docs/../secret')
            ->assertNotFound();
    }
}
