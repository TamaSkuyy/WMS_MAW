<?php

namespace App\Services\ImportExport\Imports;

use App\Models\User;
use App\Services\ImportExport\Base\BaseImporter;
use App\Services\ImportExport\Contracts\Importable;

class UserImporter extends BaseImporter implements Importable
{
    public function modelType(): string
    {
        return User::class;
    }

    public function uniqueKey(): string
    {
        return 'email';
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8'],
        ];
    }
}
