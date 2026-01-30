<?php

namespace App\Services;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Contracts\Encryption\DecryptException;

class EncryptionService
{
    public function encrypt(string $value): string
    {
        return Crypt::encryptString($value);
    }

    public function decrypt(string $encryptedValue): ?string
    {
        try {
            return Crypt::decryptString($encryptedValue);
        } catch (DecryptException $e) {
            return null;
        }
    }

    public function mask(string $value, int $visibleChars = 4): string
    {
        $length = strlen($value);

        if ($length <= $visibleChars) {
            return str_repeat('*', $length);
        }

        return str_repeat('*', $length - $visibleChars) . substr($value, -$visibleChars);
    }
}
