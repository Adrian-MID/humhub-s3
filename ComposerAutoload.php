<?php

namespace humhub\modules\humhubs3;

/**
 * Loads this module's Composer vendor autoloader on demand.
 *
 * Manual installs keep dependencies in the module folder rather than HumHub's root
 * vendor. Autoload is deferred so config.php can be included during module discovery
 * without failing on platform checks or missing vendor/.
 */
class ComposerAutoload
{
    private static bool $loaded = false;

    public static function ensureLoaded(): void
    {
        if (self::$loaded)
        {
            return;
        }

        $autoloadFile = __DIR__ . '/vendor/autoload.php';
        if (is_file($autoloadFile))
        {
            require_once $autoloadFile;
        }

        self::$loaded = true;
    }
}
