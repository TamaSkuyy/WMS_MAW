<?php

namespace App\Support;

class SupplierCodeGenerator
{
    public static function generate(string $name, array $existingCodes = []): string
    {
        $words = array_values(array_filter(
            preg_split('/[\s.]+/', strtoupper(trim($name))),
            fn (string $word) => $word !== '' && ! in_array($word, ['PT', 'CV', 'UD'], true)
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
