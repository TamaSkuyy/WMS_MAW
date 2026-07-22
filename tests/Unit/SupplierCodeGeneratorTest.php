<?php

namespace Tests\Unit;

use App\Support\SupplierCodeGenerator;
use PHPUnit\Framework\TestCase;

class SupplierCodeGeneratorTest extends TestCase
{
    // ── Basic cases ──────────────────────────────────────────────

    public function test_generates_code_from_first_three_significant_words(): void
    {
        $this->assertSame('AJM', SupplierCodeGenerator::generate('PT. ADI JAYA MAKMUR'));
    }

    public function test_strips_common_legal_prefixes(): void
    {
        $this->assertSame('SM', SupplierCodeGenerator::generate('CV SEJAHTERA MANDIRI'));
        // Single word after stripping UD → pads to 2 chars
        $this->assertSame('MX', SupplierCodeGenerator::generate('UD MANDIRI'));
    }

    public function test_strips_pt_without_dot(): void
    {
        $this->assertSame('AJM', SupplierCodeGenerator::generate('PT ADI JAYA MAKMUR'));
    }

    // ── Collision handling ───────────────────────────────────────

    public function test_appends_numeric_suffix_on_collision(): void
    {
        $this->assertSame(
            'AJM1',
            SupplierCodeGenerator::generate('PT. ADI JAYA MAKMUR', ['AJM'])
        );
    }

    public function test_increments_suffix_until_unique(): void
    {
        $this->assertSame(
            'AJM3',
            SupplierCodeGenerator::generate('PT. ADI JAYA MAKMUR', ['AJM', 'AJM1', 'AJM2'])
        );
    }

    // ── Short names (padding) ────────────────────────────────────

    public function test_pads_single_word_names(): void
    {
        $this->assertSame('MX', SupplierCodeGenerator::generate('Mandiri'));
    }

    public function test_single_word_resolves_collision(): void
    {
        $this->assertSame(
            'MX1',
            SupplierCodeGenerator::generate('Mandiri', ['MX'])
        );
    }

    public function test_only_legal_prefix_uses_fallback(): void
    {
        // All words are stripped (PT + CV + UD) — falls back to X, padded to XX
        $this->assertSame('XX', SupplierCodeGenerator::generate('PT CV UD'));
    }

    // ── Special characters ───────────────────────────────────────

    public function test_strips_parentheses(): void
    {
        // PT → stripped, PANGESTU NARPATI PERSERO TBK → first 3 letters: P N P
        $this->assertSame('PNP', SupplierCodeGenerator::generate('PT Pangestu Narpati (Persero) Tbk'));
    }

    public function test_skips_words_starting_with_number(): void
    {
        // "1ST" skipped (not alphabetic), "SUPPLIER" → single word pads to 2 chars
        $this->assertSame('SX', SupplierCodeGenerator::generate('1ST Supplier'));
    }

    public function test_handles_only_special_chars(): void
    {
        // No valid alphabetic words → fallback to X, padded to XX
        $this->assertSame('XX', SupplierCodeGenerator::generate('( ) [ ] 123'));
    }

    // ── Edge cases ───────────────────────────────────────────────

    public function test_empty_string(): void
    {
        $this->assertSame('XX', SupplierCodeGenerator::generate(''));
    }

    public function test_whitespace_only(): void
    {
        $this->assertSame('XX', SupplierCodeGenerator::generate('   '));
    }

    public function test_single_letter_name(): void
    {
        $this->assertSame('AX', SupplierCodeGenerator::generate('A'));
    }

    public function test_two_letter_name(): void
    {
        // Single word "AB" → first letter "A" → padded to 2 chars → "AX"
        $this->assertSame('AX', SupplierCodeGenerator::generate('AB'));
    }

    public function test_lowercase_converts_to_uppercase(): void
    {
        $this->assertSame('NF', SupplierCodeGenerator::generate('nike factory'));
    }

    public function test_mixed_case_with_punctuation(): void
    {
        $this->assertSame('MPU', SupplierCodeGenerator::generate('PT. Mega Power, Utama (Tbk)'));
    }
}
