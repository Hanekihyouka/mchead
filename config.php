<?php

/**
 * Renderer configuration.
 *
 * camera.yaw:   rotation around the vertical axis, in degrees.
 * camera.pitch: tilt in degrees, -90..90. Positive values look down on the figure from above.
 *
 * Rendered PNGs are cached and never regenerated, so after changing these values, delete the
 * affected files in head/ and body/ (or the whole directories) to see the new angle.
 */

return [
    'camera' => [
        'yaw' => 45.0,
        'pitch' => 45.0,
    ],
];
