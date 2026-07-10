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
use yii\helpers\Url;
use yii\imagine\Image;
use yii\web\UploadedFile;

CoreClassLoader::requireCore('humhub\libs\ProfileImage');

/**
 * Profile and space images stored in S3 with temporary local files for processing.
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
        $relativePath = $this->getRelativePath($prefix);

        if (S3MediaStorage::has($relativePath))
        {
            return S3MediaStorage::getPublicUrl($relativePath, withVersion: true);
        }

        return $this->getDefaultImageUrl($scheme);
    }

    /**
     * @param bool|string $scheme
     */
    protected function getDefaultImageUrl($scheme = false): string
    {
        $path = '@web-static/img/' . $this->defaultImage . '.jpg';
        $path = Yii::$app->view->theme->applyTo($path);

        return Url::to($this->resolveAlias($path), $this->normalizeScheme($scheme));
    }

    protected function resolveAlias(string $alias): string
    {
        $path = Yii::getAlias($alias);
        if (!is_string($path))
        {
            throw new Exception('Unable to resolve alias: ' . $alias);
        }

        return $path;
    }

    /**
     * @param bool|string $scheme
     */
    protected function normalizeScheme($scheme): bool|string
    {
        return is_string($scheme) ? $scheme : (bool) $scheme;
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
        return S3MediaStorage::resolveProcessingPath($this->getRelativePath($prefix));
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
        $localPath = S3MediaStorage::getProcessPath($this->getRelativePath($prefix));
        if (!is_file($localPath))
        {
            throw new Exception('Processed profile image is missing from the processing workspace.');
        }

        S3MediaStorage::putFile($this->getRelativePath($prefix), $localPath);
    }
}
