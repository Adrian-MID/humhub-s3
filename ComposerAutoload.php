<?php

namespace humhub\modules\humhubs3;

use AsyncAws\S3\S3Client;

/**
 * Loads this module's Composer dependencies on demand.
 *
 * Dependencies may live in any of these locations (checked in order):
 * - HumHub's protected/vendor/ (git-based installs that require async-aws/s3 at the web root)
 * - protected/modules/vendor/ (composer require from the modules folder)
 * - this module's vendor/ (manual clone + composer install inside the module)
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

        if (class_exists(S3Client::class))
        {
            self::$loaded = true;

            return;
        }

        foreach (self::autoloadCandidates() as $autoloadFile)
        {
            if (is_file($autoloadFile))
            {
                require_once $autoloadFile;
                break;
            }
        }

        self::$loaded = true;
    }

    /**
     * @return list<string>
     */
    private static function autoloadCandidates(): array
    {
        return [
            __DIR__ . '/../vendor/autoload.php',
            __DIR__ . '/vendor/autoload.php',
            __DIR__ . '/../../vendor/autoload.php',
        ];
    }
}
