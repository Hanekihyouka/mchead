<?php

declare(strict_types=1);

namespace McSkinRender;

/**
 * HTTP glue shared by head.php and body.php: read the UUID from the request,
 * produce the cached (or freshly generated) PNG and send it, or send a JSON error.
 */
final class Api
{
    private const CACHE_CONTROL = 'public, max-age=86400';

    public static function serve(string $kind): void
    {
        try {
            $uuid = Uuid::normalize(self::requestedUuid());
            $camera = Config::camera();
            $service = new Service(new Storage(ROOT_DIR), new MojangClient(), $camera['yaw'], $camera['pitch']);

            $path = $kind === 'head' ? $service->head($uuid) : $service->body($uuid);
            self::sendPng($path);
        } catch (ApiException $e) {
            self::sendError($e->status, $e->getMessage());
        } catch (\Throwable $e) {
            error_log('[mc-skin-render] ' . $e);
            self::sendError(500, 'internal error');
        }
    }

    /**
     * The UUID may come from ?uuid=… or from the path (/head.php/<uuid>[.png]).
     */
    private static function requestedUuid(): string
    {
        if (isset($_GET['uuid']) && is_string($_GET['uuid'])) {
            return $_GET['uuid'];
        }

        $info = trim((string) ($_SERVER['PATH_INFO'] ?? ''), '/');
        $info = (string) preg_replace('/\.png$/i', '', $info);

        if ($info === '' || str_contains($info, '/')) {
            throw new ApiException(400, 'missing uuid');
        }

        return $info;
    }

    private static function sendPng(string $path): void
    {
        header('Content-Type: image/png');
        header('Content-Length: ' . (string) filesize($path));
        header('Cache-Control: ' . self::CACHE_CONTROL);
        readfile($path);
    }

    private static function sendError(int $status, string $message): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => $message], JSON_UNESCAPED_UNICODE);
    }
}
