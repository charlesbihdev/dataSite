<?php

namespace App\Support;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;

/**
 * Safely inspect an `encrypted`-cast attribute for admin display. Reading an encrypted attribute whose
 * ciphertext was written under a different APP_KEY throws a DecryptException — which would 500 the very
 * settings page you need to fix it on. This reports "set / unreadable" WITHOUT throwing, so the screen
 * still renders and the admin can simply type a new value to replace the stale one.
 */
final class EncryptedSecret
{
    /**
     * @return array{set: bool, unreadable: bool} set = a usable value is stored; unreadable = a value is
     *                                            stored but can't be decrypted (key changed) — warn & replace.
     */
    public static function status(?Model $model, string $attribute): array
    {
        if ($model === null) {
            return ['set' => false, 'unreadable' => false];
        }

        $raw = $model->getRawOriginal($attribute);
        if ($raw === null || $raw === '') {
            return ['set' => false, 'unreadable' => false];
        }

        try {
            return ['set' => (string) $model->{$attribute} !== '', 'unreadable' => false];
        } catch (DecryptException) {
            return ['set' => false, 'unreadable' => true];
        }
    }
}
