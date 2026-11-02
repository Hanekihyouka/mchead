<?php

declare(strict_types=1);

namespace McSkinRender;

use GdImage;

/**
 * A Minecraft skin, normalized to the 64x64 layout (1.8+).
 */
final class Skin
{
    /**
     * @throws ApiException 422 for sizes that are not 64x64 or 64x32, 502 for non-PNG data
     */
    public static function fromPngBytes(string $bytes): GdImage
    {
        $image = Image::decodePng($bytes);

        $width = imagesx($image);
        $height = imagesy($image);
        if ($width !== 64 || ($height !== 64 && $height !== 32)) {
            throw new ApiException(422, "unsupported skin size {$width}x{$height}");
        }

        return $height === 64 ? $image : self::convertLegacy($image);
    }

    /**
     * Pre-1.8 skins are 64x32 and only contain the right limbs. Build the 64x64
     * layout by copying the texture and mirroring the right limbs into the left ones.
     */
    private static function convertLegacy(GdImage $source): GdImage
    {
        $image = imagecreatetruecolor(64, 64);
        imagesavealpha($image, true);
        imagealphablending($image, false);
        imagefilledrectangle($image, 0, 0, 63, 63, imagecolorallocatealpha($image, 0, 0, 0, 127));
        imagecopy($image, $source, 0, 0, 0, 0, 64, 32);

        // Pre-1.8 skins have no hat layer. The area at (32,0) is often opaque junk (black),
        // so make it transparent, as Minecraft's own legacy conversion does.
        imagefilledrectangle($image, 32, 0, 63, 15, imagecolorallocatealpha($image, 0, 0, 0, 127));

        self::mirrorRegion($image, 40, 16, 16, 16, 32, 48); // left arm  <- right arm
        self::mirrorRegion($image, 40, 32, 16, 16, 48, 48); // left arm overlay (empty in legacy)
        self::mirrorRegion($image, 0, 16, 16, 16, 16, 48);  // left leg  <- right leg
        self::mirrorRegion($image, 0, 32, 16, 16, 0, 48);   // left leg overlay (empty in legacy)

        return $image;
    }

    private static function mirrorRegion(GdImage $image, int $sx, int $sy, int $w, int $h, int $dx, int $dy): void
    {
        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                imagesetpixel($image, $dx + $x, $dy + $y, imagecolorat($image, $sx + $w - 1 - $x, $sy + $y));
            }
        }
    }
}
