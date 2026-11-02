<?php

declare(strict_types=1);

namespace McSkinRender;

use GdImage;
use McSkinRender\Render\Model;
use McSkinRender\Render\Rasterizer;

/**
 * Cache-first orchestration. Every request checks head/ or body/ first; a missing
 * render is built from skin/ (or downloaded into skin/ if needed) and then stored.
 * Cache entries are never updated.
 */
final class Service
{
    /** @var array{0: int, 1: int} */
    private const HEAD_SIZE = [512, 512];

    /** @var array{0: int, 1: int} */
    private const BODY_SIZE = [512, 768];

    public function __construct(
        private readonly Storage $storage,
        private readonly MojangClient $mojang,
    ) {
    }

    /**
     * @return string path of the cached head PNG
     */
    public function head(string $uuid): string
    {
        $cached = $this->storage->path('head', $uuid);
        if (is_file($cached)) {
            return $cached;
        }

        $skin = $this->loadSkin($uuid, null);
        $image = Rasterizer::render($skin, Model::head(), self::HEAD_SIZE[0], self::HEAD_SIZE[1]);

        return $this->storage->save('head', $uuid, Image::encodePng($image));
    }

    /**
     * @return string path of the cached body PNG
     */
    public function body(string $uuid): string
    {
        $cached = $this->storage->path('body', $uuid);
        if (is_file($cached)) {
            return $cached;
        }

        // The arm width (classic 4px vs slim 3px) only comes from the profile, so ask for it
        // even when the skin itself is already cached. A failure here is not cached.
        $profile = $this->mojang->fetchSkinInfo($uuid);
        if ($profile === null) {
            throw new ApiException(404, 'player has no skin');
        }

        $skin = $this->loadSkin($uuid, $profile);
        $image = Rasterizer::render(
            $skin,
            Model::body($profile['slim']),
            self::BODY_SIZE[0],
            self::BODY_SIZE[1],
        );

        return $this->storage->save('body', $uuid, Image::encodePng($image));
    }

    /**
     * Returns the normalized 64x64 skin. Downloads and caches the original skin file if missing.
     *
     * @param array{url: string, slim: bool}|null $profile already-fetched profile, if any
     */
    private function loadSkin(string $uuid, ?array $profile): GdImage
    {
        $cached = $this->storage->path('skin', $uuid);
        if (is_file($cached)) {
            return Skin::fromPngBytes((string) file_get_contents($cached));
        }

        if ($profile === null) {
            $profile = $this->mojang->fetchSkinInfo($uuid);
            if ($profile === null) {
                throw new ApiException(404, 'player has no skin');
            }
        }

        $bytes = $this->mojang->download($profile['url']);

        // Validate before caching, so a bad download never becomes a permanent entry.
        $skin = Skin::fromPngBytes($bytes);
        $this->storage->save('skin', $uuid, $bytes);

        return $skin;
    }
}
