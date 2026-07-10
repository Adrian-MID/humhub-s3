<?php

namespace humhub\libs;

use humhub\modules\humhubs3\components\S3MediaStorage;
use yii\helpers\FileHelper;
use yii\imagine\Image;
use yii\web\UploadedFile;

class LogoImage
{
    public const MIN_WIDTH = 100;
    public const MIN_HEIGHT = 120;
    public const RECOMMENDED_MIN_HEIGHT = 248;

    private const ORIGINAL_PATH = 'branding/logo.png';

    /**
     * @inheritdoc
     */
    public static function set(?UploadedFile $file = null): void
    {
        self::purgeLogoFiles();

        if ($file === null)
        {
            return;
        }

        $processPath = S3MediaStorage::resolveProcessingPath(self::ORIGINAL_PATH, false);
        FileHelper::createDirectory(dirname($processPath), 0o755, true);
        Image::getImagine()->open($file->tempName)->save($processPath);
        S3MediaStorage::putFile(self::ORIGINAL_PATH, $processPath);
    }

    /**
     * @inheritdoc
     */
    /**
     * @param int|null $maxWidth
     * @param int|null $maxHeight
     * @param bool $autoResize
     * @return string|null
     */
    public static function getUrl($maxWidth = null, $maxHeight = null, $autoResize = true)
    {
        if ($maxWidth === null)
        {
            $maxWidth = 600;
        }

        if ($maxHeight === null)
        {
            $maxHeight = 80;
        }

        $width = (int) $maxWidth;
        $height = (int) $maxHeight;

        $variantPath = self::getVariantPath($width, $height);
        if (S3MediaStorage::has($variantPath))
        {
            return S3MediaStorage::getPublicUrl($variantPath);
        }

        if (static::hasImage() && $autoResize)
        {
            $sourcePath = self::resolveOriginalPath();
            $variantLocalPath = S3MediaStorage::resolveProcessingPath($variantPath, false);
            FileHelper::createDirectory(dirname($variantLocalPath), 0o755, true);

            try
            {
                $image = Image::getImagine()->open($sourcePath);
                if ($image->getSize()->getHeight() > $height)
                {
                    $image->resize($image->getSize()->heighten($height));
                }
                if ($image->getSize()->getWidth() > $width)
                {
                    $image->resize($image->getSize()->widen($width));
                }
                $image->save($variantLocalPath);
                S3MediaStorage::putFile($variantPath, $variantLocalPath);
            }
            finally
            {
                S3MediaStorage::cleanupProcessingPath(self::ORIGINAL_PATH);
            }

            return static::getUrl($width, $height, false);
        }

        return null;
    }

    private static function resolveOriginalPath(): string
    {
        return S3MediaStorage::resolveProcessingPath(self::ORIGINAL_PATH);
    }

    /**
     * @inheritdoc
     */
    public static function hasImage(): bool
    {
        return S3MediaStorage::has(self::ORIGINAL_PATH);
    }

    private static function getVariantPath(int $maxWidth, int $maxHeight): string
    {
        return 'branding/logo/' . $maxWidth . 'x' . $maxHeight . '.png';
    }

    private static function purgeLogoFiles(): void
    {
        S3MediaStorage::deleteByPrefix('branding/logo');
    }
}
