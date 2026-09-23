<?php

namespace App\Support;

use App\Models\Pet;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

class PetQrReference
{
    public static function url(Pet $pet): string
    {
        // Authenticated encryption hides the ID and prevents reference tampering.
        // The creation timestamp also keeps an old link from resolving to a reused ID.
        $payload = 'pawid-public-pet:'.$pet->id.':'.$pet->created_at?->getTimestamp();
        $reference = rtrim(strtr(Crypt::encryptString($payload), '+/', '-_'), '=');

        return route('qrcodes.scan', ['reference' => $reference]);
    }

    public static function resolve(string $reference): ?Pet
    {
        if (strlen($reference) > 1024 || !preg_match('/\A[A-Za-z0-9_-]+\z/', $reference)) {
            return null;
        }

        try {
            $payload = Crypt::decryptString(strtr($reference, '-_', '+/'));
        } catch (DecryptException $exception) {
            return null;
        }

        if (!preg_match('/\Apawid-public-pet:([1-9][0-9]{0,18}):([0-9]+)\z/', $payload, $matches)
            || filter_var($matches[1], FILTER_VALIDATE_INT) === false) {
            return null;
        }

        $pet = Pet::query()->select('id', 'name', 'owner_email', 'created_at')
            ->with(['owner' => fn ($query) => $query->select('email', 'name')->where('role', 'user')])
            ->find($matches[1]);

        return $pet && $pet->owner && (string) $pet->created_at?->getTimestamp() === $matches[2] ? $pet : null;
    }
}
