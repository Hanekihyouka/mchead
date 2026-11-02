<?php

declare(strict_types=1);

namespace McSkinRender\Render;

/**
 * Geometry of the player model, matching the vanilla Minecraft 1.8+ player model
 * (classic 4px arms, or slim 3px arms). Overlay layers are inflated slightly so they
 * sit outside the base layer.
 */
final class Model
{
    private const HAT_INFLATE = 0.5;
    private const OVERLAY_INFLATE = 0.25;

    /** @return list<Box> */
    public static function head(): array
    {
        return [
            self::box(-4, 4, 24, 32, -4, 4, 0, 0, 8, 8, 8),
            self::box(-4, 4, 24, 32, -4, 4, 32, 0, 8, 8, 8, self::HAT_INFLATE),
        ];
    }

    /** @return list<Box> */
    public static function body(bool $slim): array
    {
        $arm = $slim ? 3 : 4;

        return [
            ...self::head(),
            self::box(-4, 4, 12, 24, -2, 2, 16, 16, 8, 12, 4),
            self::box(-4, 4, 12, 24, -2, 2, 16, 32, 8, 12, 4, self::OVERLAY_INFLATE),
            // character's right arm (-x)
            self::box(-4 - $arm, -4, 12, 24, -2, 2, 40, 16, $arm, 12, 4),
            self::box(-4 - $arm, -4, 12, 24, -2, 2, 40, 32, $arm, 12, 4, self::OVERLAY_INFLATE),
            // character's left arm (+x)
            self::box(4, 4 + $arm, 12, 24, -2, 2, 32, 48, $arm, 12, 4),
            self::box(4, 4 + $arm, 12, 24, -2, 2, 48, 48, $arm, 12, 4, self::OVERLAY_INFLATE),
            self::box(-4, 0, 0, 12, -2, 2, 0, 16, 4, 12, 4),
            self::box(-4, 0, 0, 12, -2, 2, 0, 32, 4, 12, 4, self::OVERLAY_INFLATE),
            self::box(0, 4, 0, 12, -2, 2, 16, 48, 4, 12, 4),
            self::box(0, 4, 0, 12, -2, 2, 0, 48, 4, 12, 4, self::OVERLAY_INFLATE),
        ];
    }

    private static function box(
        float $x0, float $x1, float $y0, float $y1, float $z0, float $z1,
        int $u, int $v, int $w, int $h, int $d,
        float $inflate = 0.0,
    ): Box {
        return new Box(
            $x0 - $inflate, $x1 + $inflate,
            $y0 - $inflate, $y1 + $inflate,
            $z0 - $inflate, $z1 + $inflate,
            $u, $v, $w, $h, $d,
        );
    }
}
