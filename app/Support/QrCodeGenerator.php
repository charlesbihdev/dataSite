<?php

namespace App\Support;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Writer\PngWriter;

/**
 * Generates a PNG QR code (via endroid/qr-code + GD) as a data URI, so it can be stored in a column,
 * rendered inline with an <img>, and downloaded as a file for flyers. High error correction keeps it
 * scannable even when printed small or lightly damaged.
 */
final class QrCodeGenerator
{
    public static function pngDataUri(string $text, int $size = 320): string
    {
        return (new Builder(
            writer: new PngWriter,
            data: $text,
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: $size,
            margin: 12,
        ))->build()->getDataUri();
    }
}
