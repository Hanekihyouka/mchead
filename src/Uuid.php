<?php

declare(strict_types=1);

namespace McSkinRender;

final class Uuid
{
    /**
     * Accepts a Minecraft UUID with or without dashes, in any case, and returns
     * the canonical form used for every file name: 32 lowercase hex characters.
     */
    public static function normalize(string $raw): string
    {
        $hex = strtolower(str_replace('-', '', trim($raw)));

        if (preg_match('/^[0-9a-f]{32}$/', $hex) !== 1) {
            throw new ApiException(400, 'invalid uuid');
        }

        return $hex;
    }
}
