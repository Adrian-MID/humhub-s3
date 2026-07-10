<?php

namespace humhub\modules\humhubs3\components;

use humhub\modules\file\components\StorageManager;
use humhub\modules\file\libs\FileHelper;
use humhub\modules\humhubs3\ComposerAutoload;
use humhub\modules\humhubs3\models\forms\ConfigureForm;
use RuntimeException;
use Yii;
use yii\helpers\ArrayHelper;
use yii\web\UploadedFile;

/**
 * HumHub file storage backed by S3, with temporary local files for processing only.
 *
 * Uploads and metadata operations may materialize bytes locally for a single request.
 * Authorized downloads redirect to presigned S3 URLs instead of streaming from disk.
 */
class S3StorageManager extends StorageManager
{
    /**
     * Local workspace for uploads and image processing within the current request.
     *
     * @var string
     */
    protected $storagePath = '@runtime/humhub-s3/cache';

    private ?S3Client $client = null;

    /** @var array<string, true> */
    private array $uploadedVariants = [];

    /**
     * @param string|null $variant
     * @inheritdoc
     */
    public function has($variant = null): bool
    {
        if ($this->wasUploadedThisRequest($variant))
        {
            return true;
        }

        if ($this->remoteHas($variant))
        {
            return true;
        }

        return is_file(parent::get($variant));
    }

    /**
     * Returns the local processing path without downloading from S3.
     */
    public function getStoragePath(?string $variant = null): string
    {
        return parent::get($variant);
    }

    /**
     * Ensures a variant exists in S3, uploading from the local processing workspace when needed.
     *
     * HumHub converters (e.g. preview-image) write variants directly to disk without calling set().
     */
    public function ensureRemoteVariant(?string $variant = null): bool
    {
        if ($this->remoteHas($variant))
        {
            return true;
        }

        S3FileVariantMaterializer::materialize($this->file, $this, $variant);

        $localPath = $this->getStoragePath($variant);
        if (!is_file($localPath))
        {
            return false;
        }

        $this->uploadFromLocal($variant);

        return $this->remoteHas($variant);
    }

    /**
     * @param string|null $variant
     * @return string
     * @inheritdoc
     */
    public function get($variant = null): string
    {
        $localPath = parent::get($variant);

        if (!is_file($localPath))
        {
            try
            {
                if ($this->remoteHas($variant))
                {
                    $this->downloadToLocal($variant);
                    TempFileHelper::track($localPath);
                }
            }
            catch (S3Exception $exception)
            {
                Yii::error('Unable to download file from S3: ' . $exception->getMessage(), 'humhub-s3');
                throw new RuntimeException(
                    Yii::t('HumhubS3Module.base', 'Failed to retrieve file from S3 storage. Please try again later.'),
                    0,
                    $exception
                );
            }
        }

        return $localPath;
    }

    /**
     * @param string[] $except
     * @return string[]
     * @inheritdoc
     */
    public function getVariants($except = []): array
    {
        $variants = [];
        $prefix = $this->getObjectPrefix() . '/';

        try
        {
            foreach ($this->getClient()->listObjectKeys($prefix) as $key)
            {
                if (!str_starts_with($key, $prefix))
                {
                    continue;
                }

                $variant = basename($key);
                if ($variant === '' || in_array($variant, ArrayHelper::merge(['file'], $except), true))
                {
                    continue;
                }

                $variants[] = $variant;
            }
        }
        catch (S3Exception $exception)
        {
            Yii::warning('Unable to list S3 object variants: ' . $exception->getMessage(), 'humhub-s3');
        }

        return array_values(array_unique($variants));
    }

    /**
     * @param string|null $variant
     * @inheritdoc
     */
    public function set(UploadedFile $file, $variant = null): void
    {
        parent::set($file, $variant);

        $localPath = parent::get($variant);
        if (!is_file($localPath) && is_readable($file->tempName))
        {
            FileHelper::createDirectory(dirname($localPath), $this->fileMode, true);
            if (is_uploaded_file($file->tempName))
            {
                move_uploaded_file($file->tempName, $localPath);
            }
            else
            {
                copy($file->tempName, $localPath);
            }

            @chmod($localPath, $this->fileMode);
        }

        $this->uploadFromLocal($variant);
    }

    /**
     * @param string $content
     * @param string|null $variant
     * @inheritdoc
     */
    public function setContent($content, $variant = null): void
    {
        parent::setContent($content, $variant);
        $this->uploadFromLocal($variant);
    }

    /**
     * @param string|null $variant
     * @inheritdoc
     */
    public function setByPath(string $path, $variant = null): void
    {
        parent::setByPath($path, $variant);
        $this->uploadFromLocal($variant);
    }

    /**
     * @param string|null $variant
     * @param string[] $except
     * @inheritdoc
     */
    public function delete($variant = null, $except = []): void
    {
        if ($variant === null)
        {
            $this->deleteAllRemoteVariants($except);
        }
        else
        {
            $this->deleteRemoteVariant($variant);
        }

        parent::delete($variant, $except);
    }

    public function remoteHas(?string $variant = null): bool
    {
        try
        {
            return $this->getClient()->headObject($this->getObjectKey($variant));
        }
        catch (\Throwable $exception)
        {
            if ($exception instanceof S3Exception)
            {
                Yii::warning('Unable to check S3 object: ' . $exception->getMessage(), 'humhub-s3');
            }

            return false;
        }
    }

    public function createPresignedDownloadUrl(?string $variant, ?string $responseContentDisposition): string
    {
        $expires = new \DateTimeImmutable('+' . ConfigureForm::getPresignedUrlTtl() . ' seconds');

        return $this->getClient()->presignGet(
            $this->getObjectKey($variant),
            $expires,
            $responseContentDisposition
        );
    }

    private function getClient(): S3Client
    {
        if ($this->client === null)
        {
            ComposerAutoload::ensureLoaded();
            $this->client = ConfigureForm::createClient();
        }

        return $this->client;
    }

    private function getObjectPrefix(): string
    {
        $settings = ConfigureForm::getSettings();
        $prefix = trim($settings['prefix'], '/');
        $guid = $this->file->guid;

        $path = substr($guid, 0, 1) . '/' . substr($guid, 1, 1) . '/' . $guid;
        if ($prefix !== '')
        {
            return $prefix . '/' . $path;
        }

        return $path;
    }

    private function getObjectKey(?string $variant = null): string
    {
        if ($variant === null)
        {
            $variant = $this->originalFileName;
        }

        return $this->getObjectPrefix() . '/' . $variant;
    }

    private function uploadFromLocal(?string $variant = null): void
    {
        $localPath = parent::get($variant);
        if (!is_file($localPath))
        {
            throw new RuntimeException('Local cache file is missing after write.');
        }

        try
        {
            $this->getClient()->putObject(
                $this->getObjectKey($variant),
                $localPath,
                FileHelper::getMimeType($localPath) ?: 'application/octet-stream'
            );
            $this->markUploaded($variant);
        }
        catch (S3Exception $exception)
        {
            TempFileHelper::delete($localPath);
            Yii::error('S3 upload failed: ' . $exception->getMessage(), 'humhub-s3');
            throw new RuntimeException(
                Yii::t('HumhubS3Module.base', 'Failed to upload file to S3 storage. Please try again or contact an administrator.'),
                0,
                $exception
            );
        }
        finally
        {
            TempFileHelper::delete($localPath);
        }
    }

    private function markUploaded(?string $variant): void
    {
        $this->uploadedVariants[$this->normalizeVariantKey($variant)] = true;
    }

    private function wasUploadedThisRequest(?string $variant): bool
    {
        return isset($this->uploadedVariants[$this->normalizeVariantKey($variant)]);
    }

    private function normalizeVariantKey(?string $variant): string
    {
        if ($variant === null || $variant === '')
        {
            return $this->originalFileName;
        }

        return $variant;
    }

    private function downloadToLocal(?string $variant = null): void
    {
        $localPath = parent::get($variant);
        FileHelper::createDirectory(dirname($localPath), $this->fileMode, true);

        try
        {
            $this->getClient()->getObject($this->getObjectKey($variant), $localPath);
        }
        catch (S3Exception $exception)
        {
            TempFileHelper::delete($localPath);
            throw $exception;
        }

        @chmod($localPath, $this->fileMode);
    }

    /**
     * @param string[] $except
     */
    private function deleteAllRemoteVariants(array $except = []): void
    {
        $prefix = $this->getObjectPrefix() . '/';

        try
        {
            foreach ($this->getClient()->listObjectKeys($prefix) as $key)
            {
                $variant = basename($key);
                if (in_array($variant, $except, true))
                {
                    continue;
                }

                $this->getClient()->deleteObject($key);
            }
        }
        catch (S3Exception $exception)
        {
            Yii::warning('Unable to delete remote S3 variants: ' . $exception->getMessage(), 'humhub-s3');
        }
    }

    private function deleteRemoteVariant(string $variant): void
    {
        try
        {
            if ($this->getClient()->headObject($this->getObjectKey($variant)))
            {
                $this->getClient()->deleteObject($this->getObjectKey($variant));
            }
        }
        catch (S3Exception $exception)
        {
            Yii::warning('Unable to delete remote S3 object: ' . $exception->getMessage(), 'humhub-s3');
        }
    }
}
