<?php

namespace humhub\modules\user\helpers;

use humhub\modules\humhubs3\components\S3MediaStorage;
use Yii;
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

        $legacyPath = S3MediaStorage::getLegacyPath('login-bg/background.png');
        if (is_file($legacyPath))
        {
            @unlink($legacyPath);
        }

        $legacyAsset = self::getLegacyAssetPath();
        if (is_file($legacyAsset))
        {
            @unlink($legacyAsset);
        }

        if ($fileName === null || $fileName === '')
        {
            return;
        }

        $cachePath = S3MediaStorage::resolveLocalPath(self::STORE_PATH, false);
        FileHelper::createDirectory(dirname($cachePath), 0o755, true);
        Image::getImagine()->open($fileName)->save($cachePath);
        S3MediaStorage::putFile(self::STORE_PATH, $cachePath);
    }

    public static function getUrl(): ?string
    {
        if (!static::hasImage())
        {
            return null;
        }

        return S3MediaStorage::buildProxyUrl(['path' => self::STORE_PATH]);
    }

    public static function hasImage(): bool
    {
        return S3MediaStorage::has(self::STORE_PATH)
            || is_file(S3MediaStorage::getLegacyPath('login-bg/background.png'));
    }

    private static function getLegacyAssetPath(): string
    {
        return Yii::getAlias(Yii::$app->assetManager->basePath) . DIRECTORY_SEPARATOR . 'login-bg' . DIRECTORY_SEPARATOR . 'background.png';
    }
}
