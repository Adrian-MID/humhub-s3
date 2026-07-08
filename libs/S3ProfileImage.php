<?php

namespace humhub\libs;

use humhub\modules\file\libs\ImageHelper;
use humhub\modules\humhubs3\components\CoreClassLoader;
use humhub\modules\humhubs3\components\S3MediaStorage;
use Imagine\Image\Box;
use Imagine\Image\ManipulatorInterface;
use Imagine\Image\Point;
use Yii;
use yii\base\Exception;
use yii\helpers\FileHelper;
use yii\helpers\Url;
use yii\imagine\Image;
use yii\web\UploadedFile;

CoreClassLoader::requireCore('humhub\libs\ProfileImage');

/**
 * Profile and space images stored in S3 with a local runtime cache for processing.
 */
class ProfileImage extends \humhub\modules\humhubs3\libs\core\ProfileImage
{
    /**
     * @param string $prefix
     * @param bool|string $scheme
     * @inheritdoc
     */
    public function getUrl($prefix = '', $scheme = false)
    {
        if ($this->hasStoredVariant($prefix))
        {
            return S3MediaStorage::buildProxyUrl([
                'type' => 'profile',
                'guid' => $this->guid,
                'variant' => $prefix,
            ], (bool) $scheme);
        }

        $path = '@web-static/img/' . $this->defaultImage . '.jpg';
        $path = Yii::$app->view->theme->applyTo($path);
        $alias = Yii::getAlias($path);
        if (!is_string($alias))
        {
            throw new Exception('Unable to resolve default profile image path.');
        }

        return Url::to($alias, $scheme);
    }

    /**
     * @inheritdoc
     */
    public function hasImage()
    {
        return $this->hasStoredVariant('_org');
    }

    /**
     * @param string $prefix
     * @inheritdoc
     */
    public function getPath($prefix = '')
    {
        return S3MediaStorage::resolveLocalPath($this->getRelativePath($prefix));
    }

    /**
     * @inheritdoc
     */
    public function cropOriginal($x, $y, $h, $w)
    {
        $image = Image::getImagine()->open($this->getPath('_org'))
            ->crop(new Point($x, $y), new Box($w, $h));

        $image->resize($image->getSize()->heighten($this->height))
            ->resize($image->getSize()->widen($this->width))
            ->save($this->getPath());

        $this->syncVariant('');

        return true;
    }

    /**
     * @param mixed $file
     * @inheritdoc
     */
    public function setNew($file): bool
    {
        if ($file instanceof UploadedFile)
        {
            $file = $file->tempName;
        }

        if (!is_string($file))
        {
            throw new Exception('Invalid profile image upload.');
        }

        ImageHelper::checkMaxDimensions($file);

        $this->delete();

        $image = Image::getImagine()->open($file);
        ImageHelper::fixJpegOrientation($image, $file);
        $image->thumbnail($image->getSize())
            ->save($this->getPath('_org'), ['format' => 'jpg']);

        $image = Image::getImagine()->open($this->getPath('_org'));
        if ($image->getSize()->getWidth() > 800)
        {
            $image->resize($image->getSize()->widen(800));
        }
        $image->save($this->getPath('_org'), ['format' => 'jpg']);

        $image->thumbnail(new Box($this->width, $this->height), ManipulatorInterface::THUMBNAIL_OUTBOUND)
            ->save($this->getPath(''));

        $this->syncVariant('_org');
        $this->syncVariant('');

        return true;
    }

    /**
     * @inheritdoc
     */
    public function delete(): void
    {
        foreach (['', '_org', '_cropped'] as $prefix)
        {
            S3MediaStorage::delete($this->getRelativePath($prefix));
            $legacyPath = S3MediaStorage::getLegacyPath($this->getRelativePath($prefix));
            if (is_file($legacyPath))
            {
                FileHelper::unlink($legacyPath);
            }
        }
    }

    protected function getRelativePath(string $prefix = ''): string
    {
        return $this->folder_images . '/' . $this->guid . $prefix . '.jpg';
    }

    protected function hasStoredVariant(string $prefix): bool
    {
        return S3MediaStorage::has($this->getRelativePath($prefix));
    }

    protected function syncVariant(string $prefix): void
    {
        $localPath = S3MediaStorage::getCachePath($this->getRelativePath($prefix));
        if (!is_file($localPath))
        {
            throw new Exception('Processed profile image is missing from the runtime cache.');
        }

        S3MediaStorage::putFile($this->getRelativePath($prefix), $localPath);
    }
}
