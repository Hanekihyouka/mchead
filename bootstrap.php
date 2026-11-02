<?php

declare(strict_types=1);

/** Project root: the directory that contains this file. */
const ROOT_DIR = __DIR__;

spl_autoload_register(static function (string $class): void {
    $prefix = 'McSkinRender\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $file = ROOT_DIR . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

if (!extension_loaded('gd')) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    exit('mc-skin-render requires the PHP gd extension.');
}
