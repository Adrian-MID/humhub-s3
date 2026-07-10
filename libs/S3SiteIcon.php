<?php

namespace humhub\modules\web\pwa\widgets;

use humhub\components\View;
use humhub\modules\humhubs3\components\CoreClassLoader;
use humhub\modules\humhubs3\components\S3MediaStorage;
use Imagine\Image\Box;
use Yii;
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

        $processPath = S3MediaStorage::resolveProcessingPath(self::ORIGINAL_PATH, false);
        \humhub\modules\file\libs\FileHelper::createDirectory(dirname($processPath), 0o755, true);
        Image::getImagine()->open($file->tempName)->save($processPath);
        S3MediaStorage::putFile(self::ORIGINAL_PATH, $processPath);
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
            return S3MediaStorage::getPublicUrl($manualPath);
        }

        $variantPath = self::getVariantPath($sizeValue);
        if (S3MediaStorage::has($variantPath))
        {
            return S3MediaStorage::getPublicUrl($variantPath);
        }

        if (!$autoResize || !static::hasImage())
        {
            return null;
        }

        $sourcePath = self::resolveOriginalPath();
        $variantLocalPath = S3MediaStorage::resolveProcessingPath($variantPath, false);
        \humhub\modules\file\libs\FileHelper::createDirectory(dirname($variantLocalPath), 0o755, true);

        try
        {
            Image::getImagine()->open($sourcePath)->resize(new Box($sizeValue, $sizeValue))->save($variantLocalPath);
            S3MediaStorage::putFile($variantPath, $variantLocalPath);
        }
        catch (\Exception $ex)
        {
            Yii::error('Could not resize site icon: ' . $ex->getMessage());
        }
        finally
        {
            S3MediaStorage::cleanupProcessingPath(self::ORIGINAL_PATH);
        }

        return static::getUrl($sizeValue, false);
    }

    /**
     * @inheritdoc
     */
    public static function hasImage(): bool
    {
        return S3MediaStorage::has(self::ORIGINAL_PATH);
    }

    private static function resolveOriginalPath(): string
    {
        return S3MediaStorage::resolveProcessingPath(self::ORIGINAL_PATH);
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
    }
}
