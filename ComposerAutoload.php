<?php

namespace humhub\modules\humhubs3;

/**
 * Loads this module's Composer vendor autoloader on demand.
 *
 * When the module is required from HumHub's root composer.json, dependencies live in
 * HumHub's vendor tree and are already autoloaded. Manual installs keep dependencies
 * in this module's vendor/ folder instead. Autoload is deferred so config.php can be
 * included during module discovery without failing on platform checks or missing vendor/.
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
