<?php

namespace App\Support;

class SupplierCodeGenerator
{
    public static function generate(string $name, array $existingCodes = []): string
    {
        // Split on whitespace, dots, and common punctuation that appears
        // in legal entity names (parentheses, commas, brackets).
        // Keep only words whose first character is a letter so codes never
        // include punctuation (e.g. "PT (Persero) Tbk" should yield "PT"
        // not "P(").
        $words = array_values(array_filter(
            preg_split('/[\s.,()\[\]{}]+/', strtoupper(trim($name))),
            fn (string $word) => $word !== ''
                && ctype_alpha($word[0])
                && ! in_array($word, ['PT', 'CV', 'UD'], true)
        ));

        if (empty($words)) {
            $words = ['X'];
        }

        $base = implode('', array_map(fn (string $word) => $word[0], array_slice($words, 0, 3)));

        if (strlen($base) < 2) {
            $base = str_pad($base, 2, 'X');
        }

        $code = $base;
        $suffix = 1;

        while (in_array($code, $existingCodes, true)) {
            $code = $base . $suffix;
            $suffix++;
        }

        return $code;
    }
}
