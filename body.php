<?php

declare(strict_types=1);

/**
 * Full body render API.
 *
 *   /body.php?uuid=<uuid>
 *   /body.php/<uuid>[.png]
 *   /body/<uuid>.png              (via .htaccess)
 *
 * Responds with a PNG (image/png) of the player's 3D body.
 */

require __DIR__ . '/bootstrap.php';

McSkinRender\Api::serve('body');
