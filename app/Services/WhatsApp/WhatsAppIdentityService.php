<?php

namespace App\Services\WhatsApp;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class WhatsAppIdentityService
{
    public function findOrCreate(string $phoneE164): User
    {
        if (! preg_match('/^\+[1-9]\d{7,14}$/', $phoneE164)) {
            throw new InvalidArgumentException(
                'Phone number must be in normalized E.164 format.'
            );
        }

        $existing = User::where('phone_e164', $phoneE164)->first();

        if ($existing) {
            return $existing;
        }

        try {
            return DB::transaction(fn () => User::firstOrCreate(
                ['phone_e164' => $phoneE164],
                [
                    'name' => 'WhatsApp User',
                    'email' => null,
                    'password' => null,
                ]
            ));
        } catch (QueryException $exception) {
            $existing = User::where('phone_e164', $phoneE164)->first();

            if ($existing) {
                return $existing;
            }

            throw $exception;
        }
    }
}
