<?php

namespace humhub\modules\humhubs3\components;

use RuntimeException;
use Yii;

/**
 * Loads HumHub core classes into an internal namespace so S3 overrides can extend them.
 */
class CoreClassLoader
{
    private const BASE_NAMESPACE = 'humhub\\modules\\humhubs3\\libs\\core';

    /** @var array<string, true> */
    private static array $loaded = [];

    public static function requireCore(string $coreClassFqn): void
    {
        $baseFqn = self::toBaseFqn($coreClassFqn);
        if (isset(self::$loaded[$baseFqn]) || class_exists($baseFqn, false))
        {
            self::$loaded[$baseFqn] = true;

            return;
        }

        $alias = '@' . str_replace('\\', '/', ltrim($coreClassFqn, '\\')) . '.php';
        $path = Yii::getAlias($alias, false);
        if (!is_string($path) || !is_file($path))
        {
            throw new RuntimeException('Core class file not found: ' . $coreClassFqn);
        }

        $code = file_get_contents($path);
        if ($code === false)
        {
            throw new RuntimeException('Cannot read core class file: ' . $path);
        }

        $code = preg_replace(
            '/^namespace\s+[^;]+;/m',
            'namespace ' . self::BASE_NAMESPACE . ';',
            $code,
            1
        );

        if ($code === null)
        {
            throw new RuntimeException('Unable to prepare core class: ' . $coreClassFqn);
        }

        eval('?>' . $code);

        self::$loaded[$baseFqn] = true;
    }

    public static function baseClass(string $coreClassFqn): string
    {
        return self::toBaseFqn($coreClassFqn);
    }

    private static function toBaseFqn(string $coreClassFqn): string
    {
        $parts = explode('\\', ltrim($coreClassFqn, '\\'));

        return self::BASE_NAMESPACE . '\\' . end($parts);
    }
}
