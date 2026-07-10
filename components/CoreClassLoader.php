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

    /** @var array<string, true> */
    private static array $loading = [];

    public static function requireCore(string $coreClassFqn): void
    {
        $baseFqn = self::toBaseFqn($coreClassFqn);
        if (isset(self::$loaded[$baseFqn]) || class_exists($baseFqn, false))
        {
            self::$loaded[$baseFqn] = true;

            return;
        }

        if (isset(self::$loading[$baseFqn]))
        {
            return;
        }

        self::$loading[$baseFqn] = true;

        try
        {
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

            $originalNamespace = self::extractNamespace($code);
            if ($originalNamespace !== null)
            {
                self::requireDependentCoreClasses($code, $originalNamespace);
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
        finally
        {
            unset(self::$loading[$baseFqn]);
        }
    }

    private static function extractNamespace(string $code): ?string
    {
        if (preg_match('/^namespace\s+([^;]+);/m', $code, $matches) !== 1)
        {
            return null;
        }

        return trim($matches[1]);
    }

    private static function requireDependentCoreClasses(string $code, string $originalNamespace): void
    {
        self::requireParentCoreClass($code, $originalNamespace);

        $references = [];
        if (preg_match_all('/\b([A-Z][A-Za-z0-9_]*)::/m', $code, $matches) !== false)
        {
            foreach ($matches[1] as $reference)
            {
                $references[] = $reference;
            }
        }

        if (preg_match_all('/\bnew ([A-Z][A-Za-z0-9_]*)\b/m', $code, $matches) !== false)
        {
            foreach ($matches[1] as $reference)
            {
                $references[] = $reference;
            }
        }

        foreach (array_unique($references) as $reference)
        {
            if (in_array($reference, ['self', 'parent', 'static'], true))
            {
                continue;
            }

            $classFqn = self::resolveClassReference($code, $reference, $originalNamespace);
            if (!str_starts_with($classFqn, $originalNamespace . '\\'))
            {
                continue;
            }

            self::requireCore($classFqn);
        }
    }

    private static function requireParentCoreClass(string $code, string $originalNamespace): void
    {
        if (preg_match('/class\s+\w+\s+extends\s+([^\s{]+)/', $code, $matches) !== 1)
        {
            return;
        }

        $parentClass = self::resolveClassReference($code, $matches[1], $originalNamespace);
        if (!str_starts_with($parentClass, $originalNamespace . '\\'))
        {
            return;
        }

        self::requireCore($parentClass);
    }

    private static function resolveClassReference(string $code, string $reference, string $originalNamespace): string
    {
        $reference = ltrim($reference, '\\');
        if (str_contains($reference, '\\'))
        {
            return $reference;
        }

        $imports = self::extractUseImports($code);
        if (isset($imports[$reference]))
        {
            return $imports[$reference];
        }

        return $originalNamespace . '\\' . $reference;
    }

    /**
     * @return array<string, string>
     */
    private static function extractUseImports(string $code): array
    {
        $imports = [];

        if (preg_match_all('/^use\s+([^;]+);/m', $code, $matches) !== false)
        {
            foreach ($matches[1] as $statement)
            {
                $statement = trim($statement);
                if ($statement === '')
                {
                    continue;
                }

                if (str_contains($statement, ' as '))
                {
                    [$fqn, $alias] = array_map('trim', explode(' as ', $statement, 2));
                    $imports[$alias] = ltrim($fqn, '\\');
                    continue;
                }

                $fqn = ltrim($statement, '\\');
                $parts = explode('\\', $fqn);
                $imports[end($parts)] = $fqn;
            }
        }

        return $imports;
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
