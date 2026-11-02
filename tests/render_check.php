<?php

declare(strict_types=1);

/**
 * Orientation self-check for the renderer. Run: php tests/render_check.php
 *
 * Uses a synthetic skin where R = texel x * 4 and G = texel y * 4. A flat, unrotated
 * head's front face must show texel (8..15, 8..15) with x increasing to the right and
 * y increasing downwards. Any mirroring or flipping in the UV mapping fails here.
 */

require __DIR__ . '/../bootstrap.php';

use McSkinRender\Render\Model;
use McSkinRender\Render\Rasterizer;

$skin = imagecreatetruecolor(64, 64);
imagesavealpha($skin, true);
imagealphablending($skin, false);
for ($y = 0; $y < 64; $y++) {
    for ($x = 0; $x < 64; $x++) {
        imagesetpixel($skin, $x, $y, imagecolorallocatealpha($skin, $x * 4, $y * 4, 0, 0));
    }
}

// Base layer only (no hat), flat view. Head spans 8 units, so the canvas maps 512px to
// 8 units with a 4% margin: scale = 512 * 0.92 / 8 = 58.88 px per unit, offset 256.
$image = Rasterizer::render($skin, [Model::head()[0]], 512, 512, 0.0, 0.0);

$failures = 0;
$check = static function (string $label, int $px, int $py, int $minR, int $maxR, int $minG, int $maxG) use ($image, &$failures): void {
    $color = imagecolorat($image, $px, $py);
    $r = ($color >> 16) & 0xFF;
    $g = ($color >> 8) & 0xFF;
    $ok = $r >= $minR && $r <= $maxR && $g >= $minG && $g <= $maxG;
    if (!$ok) {
        $failures++;
    }
    printf("%s %-28s pixel(%d,%d) r=%d g=%d\n", $ok ? 'PASS' : 'FAIL', $label, $px, $py, $r, $g);
};

// Left of centre -> texel u ≈ 8 (R 32..35); right of centre -> u ≈ 15 (R 60..63).
$check('left edge of front face', 60, 256, 32, 35, 32, 63);
$check('right edge of front face', 452, 256, 60, 63, 32, 63);
// Top of face -> texel v ≈ 8 (G 32..35); bottom -> v ≈ 15 (G 60..63).
$check('top of front face', 256, 40, 32, 63, 32, 35);
$check('bottom of front face', 256, 470, 32, 63, 60, 63);

if ($failures > 0) {
    fwrite(STDERR, "{$failures} check(s) failed\n");
    exit(1);
}

echo "all orientation checks passed\n";
