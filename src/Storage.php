<?php

declare(strict_types=1);

namespace McSkinRender;

/**
 * File cache under skin/, head/ and body/, one PNG per UUID.
 *
 * Entries are write-once: once a file exists it is never replaced.
 */
final class Storage
{
    public function __construct(private readonly string $root)
    {
    }

    /**
     * @param 'skin'|'head'|'body' $kind
     */
    public function path(string $kind, string $uuid): string
    {
        return $this->root . '/' . $kind . '/' . $uuid . '.png';
    }

    /**
     * Atomically store bytes as the cache entry for $kind/$uuid.
     * If the entry already exists, the existing file is kept untouched.
     *
     * @param 'skin'|'head'|'body' $kind
     * @return string absolute path of the cache entry
     */
    public function save(string $kind, string $uuid, string $bytes): string
    {
        $target = $this->path($kind, $uuid);
        if (is_file($target)) {
            return $target;
        }

        $dir = dirname($target);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException("Cannot create cache directory {$dir}");
        }

        // Write to a temp file and rename, so readers never see a partial PNG.
        $tmp = $target . '.' . bin2hex(random_bytes(8)) . '.tmp';
        if (file_put_contents($tmp, $bytes) === false) {
            throw new \RuntimeException("Cannot write {$tmp}");
        }

        if (!rename($tmp, $target)) {
            @unlink($tmp);
            throw new \RuntimeException("Cannot move cache entry into {$target}");
        }

        return $target;
    }
}
