<?php

declare(strict_types=1);

namespace McSkinRender;

/**
 * Talks to Mojang's public session server to find a player's skin, and downloads
 * the skin texture from Mojang's texture host.
 */
final class MojangClient
{
    private const PROFILE_URL = 'https://sessionserver.mojang.com/session/minecraft/profile/%s';
    private const TEXTURE_HOST = 'textures.minecraft.net';
    /** Upper bound for any upstream response (profiles and skins are a few KB). */
    private const MAX_RESPONSE_BYTES = 1048576;

    /**
     * @return array{url: string, slim: bool}|null null when the player has no skin
     * @throws ApiException 404 unknown player, 502 upstream failure
     */
    public function fetchSkinInfo(string $uuid): ?array
    {
        [$status, $body] = $this->get(sprintf(self::PROFILE_URL, $uuid), self::MAX_RESPONSE_BYTES);

        if ($status === 204 || $status === 404) {
            throw new ApiException(404, 'player not found');
        }
        if ($status !== 200) {
            throw new ApiException(502, "profile API returned HTTP {$status}");
        }

        $profile = json_decode($body, true);
        $encoded = null;
        foreach ((is_array($profile) ? ($profile['properties'] ?? []) : []) as $property) {
            if (is_array($property) && ($property['name'] ?? null) === 'textures') {
                $encoded = $property['value'] ?? null;
                break;
            }
        }
        if (!is_string($encoded)) {
            return null;
        }

        $decoded = base64_decode($encoded, true);
        $textures = is_string($decoded) ? json_decode($decoded, true) : null;
        $skin = is_array($textures) ? ($textures['textures']['SKIN'] ?? null) : null;
        if (!is_array($skin) || !isset($skin['url']) || !is_string($skin['url'])) {
            return null;
        }

        return [
            'url' => $skin['url'],
            'slim' => (($skin['metadata']['model'] ?? 'default') === 'slim'),
        ];
    }

    /**
     * @throws ApiException 502 when the URL is not Mojang's texture host or the download fails
     */
    public function download(string $url): string
    {
        $parts = parse_url($url);
        if (
            !is_array($parts)
            || !in_array($parts['scheme'] ?? '', ['http', 'https'], true)
            || ($parts['host'] ?? '') !== self::TEXTURE_HOST
        ) {
            throw new ApiException(502, 'refusing to download skin from an unexpected host');
        }

        [$status, $body] = $this->get($url, self::MAX_RESPONSE_BYTES);
        if ($status !== 200) {
            throw new ApiException(502, "skin download returned HTTP {$status}");
        }

        return $body;
    }

    /**
     * @return array{0: int, 1: string} [HTTP status (0 if unknown), body]
     * @throws ApiException 502 on transport errors or oversized bodies
     */
    private function get(string $url, int $maxBytes): array
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 15,
                'ignore_errors' => true,
                'follow_location' => 0,
                'header' => "User-Agent: mc-skin-render/1.0\r\nAccept: */*\r\n",
            ],
        ]);

        $body = @file_get_contents($url, false, $context);
        if ($body === false) {
            throw new ApiException(502, 'upstream request failed');
        }
        if (strlen($body) > $maxBytes) {
            throw new ApiException(502, 'upstream response too large');
        }

        // PHP fills $http_response_header in this function's scope after file_get_contents().
        $status = 0;
        if (isset($http_response_header[0]) && preg_match('#^HTTP/\S+\s+(\d{3})#', $http_response_header[0], $m) === 1) {
            $status = (int) $m[1];
        }

        return [$status, $body];
    }
}
