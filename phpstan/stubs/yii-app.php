<?php

class Yii
{
    /** @var \humhub\components\Application */
    public static $app;

    /** @var array<string, string> */
    public static $classMap = [];

    public static function t(string $category, string $message, array $params = [], ?string $language = null): string
    {
    }

    public static function getAlias(string $alias, bool $throwException = true): string|false
    {
    }

    public static function warning(string $message, string $category = 'application'): void
    {
    }

    public static function error(string $message, string $category = 'application'): void
    {
    }
}
