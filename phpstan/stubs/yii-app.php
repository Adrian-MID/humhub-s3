<?php

class Yii
{
    /** @var \humhub\components\Application */
    public static $app;

    public static function t(string $category, string $message, array $params = [], ?string $language = null): string
    {
    }

    public static function warning(string $message, string $category = 'application'): void
    {
    }

    public static function error(string $message, string $category = 'application'): void
    {
    }
}
