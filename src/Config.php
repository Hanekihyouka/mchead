<?php

declare(strict_types=1);

namespace McSkinRender;

/**
 * Loads config.php from the project root. Missing keys fall back to the defaults below.
 */
final class Config
{
    private const FILE = ROOT_DIR . '/config.php';

    private const DEFAULTS = [
        'camera' => [
            'yaw' => 45.0,
            'pitch' => 45.0,
        ],
    ];

    /**
     * @return array{yaw: float, pitch: float} camera angles in radians
     * @throws \RuntimeException when config.php is unreadable or a value is invalid
     */
    public static function camera(): array
    {
        $config = self::load();

        $yaw = self::number($config['camera']['yaw'], 'camera.yaw');
        $pitch = self::number($config['camera']['pitch'], 'camera.pitch');
        if ($pitch < -90 || $pitch > 90) {
            throw new \RuntimeException('camera.pitch must be between -90 and 90 degrees');
        }

        return [
            'yaw' => deg2rad($yaw),
            'pitch' => deg2rad($pitch),
        ];
    }

    /**
     * @return array{camera: array{yaw: mixed, pitch: mixed}}
     */
    private static function load(): array
    {
        if (!is_file(self::FILE)) {
            return self::DEFAULTS;
        }

        $loaded = require self::FILE;
        if (!is_array($loaded)) {
            throw new \RuntimeException('config.php must return an array');
        }

        return array_replace_recursive(self::DEFAULTS, $loaded);
    }

    private static function number(mixed $value, string $key): float
    {
        if (!is_int($value) && !is_float($value)) {
            throw new \RuntimeException("{$key} must be a number");
        }

        return (float) $value;
    }
}
