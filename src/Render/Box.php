<?php

declare(strict_types=1);

namespace McSkinRender\Render;

/**
 * An axis-aligned box in model space, textured with the vanilla Minecraft box UV layout.
 *
 * Units are skin texels (1 unit = 1/16 block). Y points up, feet are at y = 0.
 * The character faces +z; its right side is at -x.
 */
final class Box
{
    public function __construct(
        public readonly float $x0,
        public readonly float $x1,
        public readonly float $y0,
        public readonly float $y1,
        public readonly float $z0,
        public readonly float $z1,
        public readonly int $u,
        public readonly int $v,
        public readonly int $w,
        public readonly int $h,
        public readonly int $d,
    ) {
    }

    /**
     * The six faces as quads. Each quad is four corners in order (0,0), (1,0), (1,1), (0,1)
     * in face coordinates, where the first axis runs left-to-right as seen from outside the
     * box. Each corner is [x, y, z, texU, texV].
     *
     * @return list<list<array{0: float, 1: float, 2: float, 3: float, 4: float}>>
     */
    public function quads(): array
    {
        $W = $this->x1 - $this->x0;
        $H = $this->y1 - $this->y0;
        $D = $this->z1 - $this->z0;
        [$x0, $x1, $y0, $y1, $z0, $z1] = [$this->x0, $this->x1, $this->y0, $this->y1, $this->z0, $this->z1];
        [$u, $v, $w, $h, $d] = [$this->u, $this->v, $this->w, $this->h, $this->d];

        // [texture rect x, texture rect y, rect width, rect height, position(s, t)]
        $faces = [
            // front (+z): left = -x, top = +y
            [$u + $d, $v + $d, $w, $h, static fn (float $s, float $t): array => [$x0 + $s * $W, $y1 - $t * $H, $z1]],
            // back (-z): left = +x
            [$u + 2 * $d + $w, $v + $d, $w, $h, static fn (float $s, float $t): array => [$x1 - $s * $W, $y1 - $t * $H, $z0]],
            // right (-x): left = -z
            [$u, $v + $d, $d, $h, static fn (float $s, float $t): array => [$x0, $y1 - $t * $H, $z0 + $s * $D]],
            // left (+x): left = +z
            [$u + $d + $w, $v + $d, $d, $h, static fn (float $s, float $t): array => [$x1, $y1 - $t * $H, $z1 - $s * $D]],
            // top (+y): left = -x, top of texture = back (-z)
            [$u + $d, $v, $w, $d, static fn (float $s, float $t): array => [$x0 + $s * $W, $y1, $z0 + $t * $D]],
            // bottom (-y): left = -x, top of texture = front (+z)
            [$u + $d + $w, $v, $w, $d, static fn (float $s, float $t): array => [$x0 + $s * $W, $y0, $z1 - $t * $D]],
        ];

        $quads = [];
        foreach ($faces as [$ru, $rv, $rw, $rh, $position]) {
            $quad = [];
            foreach ([[0, 0], [1, 0], [1, 1], [0, 1]] as [$s, $t]) {
                [$x, $y, $z] = $position((float) $s, (float) $t);
                $quad[] = [$x, $y, $z, $ru + $s * $rw, $rv + $t * $rh];
            }
            $quads[] = $quad;
        }

        return $quads;
    }
}
