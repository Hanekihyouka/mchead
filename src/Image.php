<?php

declare(strict_types=1);

namespace McSkinRender;

use GdImage;

final class Image
{
    private const PNG_SIGNATURE = "\x89PNG\r\n\x1a\n";

    /**
     * @throws ApiException 502 when the bytes are not a PNG the GD extension can read
     */
    public static function decodePng(string $bytes): GdImage
    {
        $image = str_starts_with($bytes, self::PNG_SIGNATURE) ? @imagecreatefromstring($bytes) : false;
        if ($image === false) {
            throw new ApiException(502, 'skin is not a valid PNG');
        }

        if (!imageistruecolor($image)) {
            imagepalettetotruecolor($image);
        }
        imagesavealpha($image, true);

        return $image;
    }

    public static function encodePng(GdImage $image): string
    {
        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }
}
