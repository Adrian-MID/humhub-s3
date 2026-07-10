<?php

namespace humhub\modules\user\helpers;

use humhub\modules\humhubs3\components\S3MediaStorage;
use yii\helpers\FileHelper;
use yii\imagine\Image;

/**
 * S3-backed replacement for HumHub's final LoginBackgroundImageHelper.
 */
final class LoginBackgroundImageHelper
{
    public const MIN_WIDTH = 800;
    public const MIN_HEIGHT = 600;
    public const RECOMMENDED_WIDTH = 1920;
    public const RECOMMENDED_HEIGHT = 1080;

    private const STORE_PATH = 'branding/login-bg.png';

    public static function set(?string $fileName): void
    {
        S3MediaStorage::delete(self::STORE_PATH);

        if ($fileName === null || $fileName === '')
        {
            return;
        }

        $processPath = S3MediaStorage::resolveProcessingPath(self::STORE_PATH, false);
        FileHelper::createDirectory(dirname($processPath), 0o755, true);
        Image::getImagine()->open($fileName)->save($processPath);
        S3MediaStorage::putFile(self::STORE_PATH, $processPath);
    }

    public static function getUrl(): ?string
    {
        if (!static::hasImage())
        {
            return null;
        }

        return S3MediaStorage::getPublicUrl(self::STORE_PATH);
    }

    public static function hasImage(): bool
    {
        return S3MediaStorage::has(self::STORE_PATH);
    }
}
