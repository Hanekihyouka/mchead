<?php

declare(strict_types=1);

namespace McSkinRender\Render;

use GdImage;

/**
 * Minimal software rasterizer: orthographic projection, z-buffer, nearest-neighbour
 * texturing, no lighting. The look is the same flat-shaded style as McHead's
 * MeshBasicMaterial.
 */
final class Rasterizer
{
    /** Same initial view as McHead's web app (yaw = pitch = π/4). */
    public const DEFAULT_YAW = M_PI / 4;
    public const DEFAULT_PITCH = M_PI / 4;

    /** Fraction of the canvas left empty on each side. */
    private const MARGIN = 0.04;
    private const EPSILON = 1e-9;

    /**
     * Renders boxes textured by $skin into a transparent PNG-sized canvas. The figure is
     * scaled uniformly to fit the canvas and centred.
     *
     * @param list<Box> $boxes
     */
    public static function render(
        GdImage $skin,
        array $boxes,
        int $width,
        int $height,
        float $yaw = self::DEFAULT_YAW,
        float $pitch = self::DEFAULT_PITCH,
    ): GdImage {
        $out = imagecreatetruecolor($width, $height);
        imagesavealpha($out, true);
        imagealphablending($out, false);
        imagefilledrectangle($out, 0, 0, $width - 1, $height - 1, imagecolorallocatealpha($out, 0, 0, 0, 127));

        $cy = cos($yaw);
        $sy = sin($yaw);
        $cp = cos($pitch);
        $sp = sin($pitch);
        $rotate = static fn (array $p): array => self::rotate($p, $cy, $sy, $cp, $sp);

        // 1. Rotate every triangle's vertices into view space.
        $triangles = [];
        foreach ($boxes as $box) {
            foreach ($box->quads() as [$a, $b, $c, $d]) {
                $triangles[] = [$rotate($a), $rotate($b), $rotate($c)];
                $triangles[] = [$rotate($a), $rotate($c), $rotate($d)];
            }
        }

        if ($triangles === []) {
            return $out;
        }

        // 2. Fit the figure's bounding box into the canvas.
        $minX = $minY = INF;
        $maxX = $maxY = -INF;
        foreach ($triangles as $triangle) {
            foreach ($triangle as [$x, $y]) {
                $minX = min($minX, $x);
                $maxX = max($maxX, $x);
                $minY = min($minY, $y);
                $maxY = max($maxY, $y);
            }
        }

        $fit = 1 - 2 * self::MARGIN;
        $scale = min(
            $width * $fit / max($maxX - $minX, self::EPSILON),
            $height * $fit / max($maxY - $minY, self::EPSILON),
        );
        $offsetX = $width / 2 - ($minX + $maxX) / 2 * $scale;
        $offsetY = $height / 2 - ($minY + $maxY) / 2 * $scale;

        // 3. Rasterize with a z-buffer. Larger z is closer to the camera (at +z).
        $zbuffer = array_fill(0, $width * $height, -INF);
        foreach ($triangles as $triangle) {
            $screen = array_map(
                static fn (array $p): array => [$p[0] * $scale + $offsetX, $p[1] * $scale + $offsetY, $p[2], $p[3], $p[4]],
                $triangle,
            );
            self::fillTriangle($out, $skin, $zbuffer, $width, $height, $screen[0], $screen[1], $screen[2]);
        }

        return $out;
    }

    /**
     * Rotate with yaw about Y, then pitch about X. These are the same conventions as
     * ThreeJS's Euler XYZ with rotation.x = pitch and rotation.y = yaw.
     *
     * @param array{0: float, 1: float, 2: float, 3: float, 4: float} $p [x, y, z, u, v]
     * @return array{0: float, 1: float, 2: float, 3: float, 4: float} [screenX, screenY, depth, u, v]
     */
    private static function rotate(array $p, float $cy, float $sy, float $cp, float $sp): array
    {
        [$x, $y, $z, $u, $v] = $p;

        $x1 = $x * $cy + $z * $sy;
        $z1 = -$x * $sy + $z * $cy;

        $y2 = $y * $cp - $z1 * $sp;
        $z2 = $y * $sp + $z1 * $cp;

        // Screen Y grows downwards in an image, so flip it.
        return [$x1, -$y2, $z2, $u, $v];
    }

    /**
     * @param array{0: float, 1: float, 2: float, 3: float, 4: float} $a
     * @param array{0: float, 1: float, 2: float, 3: float, 4: float} $b
     * @param array{0: float, 1: float, 2: float, 3: float, 4: float} $c
     * @param array<int, float> $zbuffer modified in place
     */
    private static function fillTriangle(
        GdImage $out,
        GdImage $skin,
        array &$zbuffer,
        int $width,
        int $height,
        array $a,
        array $b,
        array $c,
    ): void {
        $skinWidth = imagesx($skin);
        $skinHeight = imagesy($skin);

        $minX = max(0, (int) floor(min($a[0], $b[0], $c[0])));
        $maxX = min($width - 1, (int) ceil(max($a[0], $b[0], $c[0])));
        $minY = max(0, (int) floor(min($a[1], $b[1], $c[1])));
        $maxY = min($height - 1, (int) ceil(max($a[1], $b[1], $c[1])));

        $denominator = ($b[1] - $c[1]) * ($a[0] - $c[0]) + ($c[0] - $b[0]) * ($a[1] - $c[1]);
        if (abs($denominator) < self::EPSILON) {
            return;
        }

        for ($y = $minY; $y <= $maxY; $y++) {
            $py = $y + 0.5;
            for ($x = $minX; $x <= $maxX; $x++) {
                $px = $x + 0.5;

                // Barycentric coordinates of the pixel centre.
                $l0 = (($b[1] - $c[1]) * ($px - $c[0]) + ($c[0] - $b[0]) * ($py - $c[1])) / $denominator;
                $l1 = (($c[1] - $a[1]) * ($px - $c[0]) + ($a[0] - $c[0]) * ($py - $c[1])) / $denominator;
                $l2 = 1.0 - $l0 - $l1;
                if ($l0 < -self::EPSILON || $l1 < -self::EPSILON || $l2 < -self::EPSILON) {
                    continue;
                }

                $index = $y * $width + $x;
                $depth = $l0 * $a[2] + $l1 * $b[2] + $l2 * $c[2];
                if ($depth <= $zbuffer[$index]) {
                    continue;
                }

                $u = $l0 * $a[3] + $l1 * $b[3] + $l2 * $c[3];
                $v = $l0 * $a[4] + $l1 * $b[4] + $l2 * $c[4];
                $tx = min(max((int) floor($u), 0), $skinWidth - 1);
                $ty = min(max((int) floor($v), 0), $skinHeight - 1);

                $color = imagecolorat($skin, $tx, $ty);
                if ((($color >> 24) & 0x7F) === 127) {
                    // Fully transparent texel: let whatever is behind show through.
                    continue;
                }

                $zbuffer[$index] = $depth;
                imagesetpixel($out, $x, $y, $color);
            }
        }
    }
}
