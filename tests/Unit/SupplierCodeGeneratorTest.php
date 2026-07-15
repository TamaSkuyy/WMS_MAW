<?php

namespace Tests\Unit;

use App\Support\SupplierCodeGenerator;
use PHPUnit\Framework\TestCase;

class SupplierCodeGeneratorTest extends TestCase
{
    public function test_generates_code_from_first_three_significant_words(): void
    {
        $this->assertSame('AJM', SupplierCodeGenerator::generate('PT. ADI JAYA MAKMUR'));
    }

    public function test_strips_common_legal_prefixes(): void
    {
        $this->assertSame('SM', SupplierCodeGenerator::generate('CV SEJAHTERA MANDIRI'));
    }

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
            'AJM2',
            SupplierCodeGenerator::generate('PT. ADI JAYA MAKMUR', ['AJM', 'AJM1'])
        );
    }

    public function test_pads_single_word_names(): void
    {
        $this->assertSame('MX', SupplierCodeGenerator::generate('Mandiri'));
    }
}
