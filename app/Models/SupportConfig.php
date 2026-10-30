<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * The admin support contact agents reach from their portal, a singleton row. Nothing is shown
 * until an admin saves a number.
 *
 * @property int $id
 * @property string|null $whatsapp_number
 */
#[Fillable(['whatsapp_number'])]
class SupportConfig extends Model
{
    public static function current(): ?self
    {
        return static::query()->latest('id')->first();
    }

    /** wa.me chat link for the saved number, or null when none is set. */
    public static function whatsappUrl(): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) static::current()?->whatsapp_number);

        if ($digits === '') {
            return null;
        }

        // wa.me needs the international form; a local Ghana number (0XXXXXXXXX) becomes 233XXXXXXXXX.
        if (strlen($digits) === 10 && str_starts_with($digits, '0')) {
            $digits = '233'.substr($digits, 1);
        }

        return "https://wa.me/{$digits}";
    }
}
