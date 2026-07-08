<?php

namespace humhub\modules\web\pwa\widgets;

use humhub\components\View;
use humhub\modules\humhubs3\components\CoreClassLoader;
use humhub\modules\humhubs3\components\S3MediaStorage;
use Imagine\Image\Box;
use Yii;
use yii\base\ErrorException;
use yii\base\Exception;
use yii\imagine\Image;
use yii\web\UploadedFile;

CoreClassLoader::requireCore('humhub\modules\web\pwa\widgets\SiteIcon');

class SiteIcon extends \humhub\modules\humhubs3\libs\core\SiteIcon
{
    private const ORIGINAL_PATH = 'branding/icon.png';

    /**
     * @inheritdoc
     */
    public static function set(?UploadedFile $file = null): void
    {
        self::purgeSiteIconFiles();

        if ($file === null)
        {
            return;
        }

        $cachePath = S3MediaStorage::resolveLocalPath(self::ORIGINAL_PATH, false);
        \humhub\modules\file\libs\FileHelper::createDirectory(dirname($cachePath), 0o755, true);
        Image::getImagine()->open($file->tempName)->save($cachePath);
        S3MediaStorage::putFile(self::ORIGINAL_PATH, $cachePath);
    }

    /**
     * @inheritdoc
     */
    /**
     * @param int $size
     * @param bool $autoResize
     * @return string|null
     */
    public static function getUrl($size, $autoResize = true)
    {
        $sizeValue = (int) $size;

        $manualPath = self::getManualUploadPath($sizeValue);
        if (S3MediaStorage::has($manualPath))
        {
            return S3MediaStorage::buildProxyUrl(['path' => $manualPath]);
        }

        $variantPath = self::getVariantPath($sizeValue);
        if (S3MediaStorage::has($variantPath))
        {
            return S3MediaStorage::buildProxyUrl(['path' => $variantPath]);
        }

        if (!$autoResize)
        {
            return null;
        }

        $webModule = Yii::$app->getModule('web');
        $defaultIcon = ($webModule instanceof \yii\base\Module)
            ? $webModule->getBasePath() . '/pwa/resources/default_icon.png'
            : '';

        $baseIcon = static::hasImage()
            ? self::resolveOriginalPath()
            : $defaultIcon;

        $cachePath = S3MediaStorage::resolveLocalPath($variantPath, false);
        \humhub\modules\file\libs\FileHelper::createDirectory(dirname($cachePath), 0o755, true);

        try
        {
            Image::getImagine()->open($baseIcon)->resize(new Box($sizeValue, $sizeValue))->save($cachePath);
            S3MediaStorage::putFile($variantPath, $cachePath);
        }
        catch (\Exception $ex)
        {
            Yii::error('Could not resize site icon: ' . $ex->getMessage());
        }

        return static::getUrl($sizeValue, false);
    }

    /**
     * @inheritdoc
     */
    public static function hasImage(): bool
    {
        return S3MediaStorage::has(self::ORIGINAL_PATH)
            || is_file(S3MediaStorage::getLegacyPath('icon/icon.png'));
    }

    private static function resolveOriginalPath(): string
    {
        if (S3MediaStorage::has(self::ORIGINAL_PATH))
        {
            return S3MediaStorage::resolveLocalPath(self::ORIGINAL_PATH);
        }

        return S3MediaStorage::getLegacyPath('icon/icon.png');
    }

    /**
     * @inheritdoc
     */
    public static function registerMetaTags(View $view): void
    {
        $view->registerLinkTag(['rel' => 'apple-touch-icon', 'href' => static::getUrl(152), 'sizes' => '152x152']);
        $view->registerLinkTag(['rel' => 'apple-touch-icon', 'href' => static::getUrl(180), 'sizes' => '180x180']);
        $view->registerLinkTag(['rel' => 'apple-touch-icon', 'href' => static::getUrl(167), 'sizes' => '167x167']);
        $view->registerLinkTag(['rel' => 'icon', 'href' => static::getUrl(192), 'sizes' => '192x192']);
        $view->registerLinkTag(['rel' => 'icon', 'href' => static::getUrl(96), 'sizes' => '96x96']);
        $view->registerLinkTag(['rel' => 'icon', 'href' => static::getUrl(32), 'sizes' => '32x32']);
    }

    private static function getVariantPath(int $size): string
    {
        return 'branding/icon/' . $size . 'x' . $size . '.png';
    }

    private static function getManualUploadPath(int $size): string
    {
        return 'branding/icon/manual/' . $size . 'x' . $size . '.png';
    }

    protected static function purgeSiteIconFiles(): void
    {
        S3MediaStorage::deleteByPrefix('branding/icon');

        try
        {
            $siteIconsPath = Yii::getAlias(Yii::$app->assetManager->basePath . DIRECTORY_SEPARATOR . 'siteicons');
            if (is_string($siteIconsPath))
            {
                \humhub\modules\file\libs\FileHelper::removeDirectory($siteIconsPath);
            }
        }
        catch (ErrorException $e)
        {
            Yii::error($e->getMessage(), 'admin');
        }

        try
        {
            $legacyIconPath = Yii::getAlias('@webroot/uploads/icon/');
            if (is_string($legacyIconPath))
            {
                \yii\helpers\FileHelper::removeDirectory($legacyIconPath);
            }
        }
        catch (ErrorException $e)
        {
            Yii::error($e->getMessage(), 'admin');
        }
    }
}
