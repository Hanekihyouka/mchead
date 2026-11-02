<?php

declare(strict_types=1);

/**
 * Head render API.
 *
 *   /head.php?uuid=<uuid>
 *   /head.php/<uuid>[.png]
 *   /head/<uuid>.png              (via .htaccess)
 *
 * Responds with a PNG (image/png) of the player's 3D head.
 */

require __DIR__ . '/bootstrap.php';

McSkinRender\Api::serve('head');
