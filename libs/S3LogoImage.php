<?php

namespace humhub\libs;

use humhub\modules\humhubs3\components\S3MediaStorage;
use Yii;
use yii\base\ErrorException;
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

        $cachePath = S3MediaStorage::resolveLocalPath(self::ORIGINAL_PATH, false);
        FileHelper::createDirectory(dirname($cachePath), 0o755, true);
        Image::getImagine()->open($file->tempName)->save($cachePath);
        S3MediaStorage::putFile(self::ORIGINAL_PATH, $cachePath);
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
            return S3MediaStorage::buildProxyUrl(['path' => $variantPath]);
        }

        if (static::hasImage() && $autoResize)
        {
            $sourcePath = self::resolveOriginalPath();
            $cachePath = S3MediaStorage::resolveLocalPath($variantPath, false);
            FileHelper::createDirectory(dirname($cachePath), 0o755, true);

            $image = Image::getImagine()->open($sourcePath);
            if ($image->getSize()->getHeight() > $height)
            {
                $image->resize($image->getSize()->heighten($height));
            }
            if ($image->getSize()->getWidth() > $width)
            {
                $image->resize($image->getSize()->widen($width));
            }
            $image->save($cachePath);
            S3MediaStorage::putFile($variantPath, $cachePath);

            return static::getUrl($width, $height, false);
        }

        return null;
    }

    private static function resolveOriginalPath(): string
    {
        if (S3MediaStorage::has(self::ORIGINAL_PATH))
        {
            return S3MediaStorage::resolveLocalPath(self::ORIGINAL_PATH);
        }

        return S3MediaStorage::getLegacyPath('logo_image/logo.png');
    }

    /**
     * @inheritdoc
     */
    public static function hasImage(): bool
    {
        return S3MediaStorage::has(self::ORIGINAL_PATH)
            || is_file(S3MediaStorage::getLegacyPath('logo_image/logo.png'));
    }

    private static function getVariantPath(int $maxWidth, int $maxHeight): string
    {
        return 'branding/logo/' . $maxWidth . 'x' . $maxHeight . '.png';
    }

    private static function purgeLogoFiles(): void
    {
        S3MediaStorage::deleteByPrefix('branding/logo');

        try
        {
            $logoAssetPath = Yii::getAlias(Yii::$app->assetManager->basePath . DIRECTORY_SEPARATOR . 'logo');
            if (is_string($logoAssetPath))
            {
                FileHelper::removeDirectory($logoAssetPath);
            }
        }
        catch (ErrorException $e)
        {
            Yii::error($e->getMessage(), 'admin');
        }

        try
        {
            $legacyLogoPath = Yii::getAlias('@webroot/uploads/logo_image/');
            if (is_string($legacyLogoPath))
            {
                FileHelper::removeDirectory($legacyLogoPath);
            }
        }
        catch (ErrorException $e)
        {
            Yii::error($e->getMessage(), 'admin');
        }
    }
}
