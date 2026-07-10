<?php

namespace humhub\modules\humhubs3\components;

use RuntimeException;

/**
 * Tracks temporary files and removes them at the end of the request.
 */
class TempFileHelper
{
    private static bool $shutdownRegistered = false;

    /** @var list<string> */
    private static array $paths = [];

    public static function create(string $prefix = 'hh3_'): string
    {
        $path = tempnam(sys_get_temp_dir(), $prefix);
        if ($path === false)
        {
            throw new RuntimeException('Unable to create a temporary file.');
        }

        self::track($path);

        return $path;
    }

    public static function track(string $path): void
    {
        if ($path === '')
        {
            return;
        }

        self::registerShutdown();

        if (!in_array($path, self::$paths, true))
        {
            self::$paths[] = $path;
        }
    }

    public static function delete(string $path): void
    {
        if ($path !== '' && is_file($path))
        {
            @unlink($path);
        }

        self::$paths = array_values(array_filter(
            self::$paths,
            static fn(string $tracked): bool => $tracked !== $path
        ));
    }

    public static function cleanupAll(): void
    {
        foreach (self::$paths as $path)
        {
            if (is_file($path))
            {
                @unlink($path);
            }
        }

        self::$paths = [];
    }

    private static function registerShutdown(): void
    {
        if (self::$shutdownRegistered)
        {
            return;
        }

        register_shutdown_function([self::class, 'cleanupAll']);
        self::$shutdownRegistered = true;
    }
}
