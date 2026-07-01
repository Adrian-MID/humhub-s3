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
 * HumHub file storage backed by S3, with a local runtime cache for compatibility.
 *
 * HumHub's file module talks only to StorageManager — uploads, downloads,
 * thumbnails, and deletes all go through the same methods. This class extends the
 * default local manager: writes still land in the runtime cache first, then sync
 * to S3. Reads prefer the cache and pull from S3 on demand.
 */
class S3StorageManager extends StorageManager
{
    /**
     * Local runtime cache. HumHub expects real filesystem paths (sendFile, GD, etc.),
     * so objects are materialized here even though S3 is the durable store.
     *
     * @var string
     */
    protected $storagePath = '@runtime/humhub-s3/cache';

    private ?S3Client $client = null;

    /**
     * Checks whether a file (or variant such as a thumbnail) exists.
     *
     * Used before HumHub replaces file content, after a successful store, and when
     * building download URLs that include a content hash. Checks the local cache
     * first, then S3 if the cache is cold.
     *
     * @param string|null $variant
     * @inheritdoc
     */
    public function has($variant = null): bool
    {
        if (parent::has($variant))
        {
            return true;
        }

        try
        {
            return $this->getClient()->headObject($this->getObjectKey($variant));
        }
        catch (S3Exception $exception)
        {
            Yii::warning('Unable to check S3 object: ' . $exception->getMessage(), 'humhub-s3');
            return false;
        }
    }

    /**
     * Returns the local path HumHub uses to read a file.
     *
     * Called when a user downloads or previews an attachment, when HumHub computes
     * size or SHA-1 metadata, and when image conversion reads the source bytes.
     * HumHub always works with filesystem paths, so if the object is not cached yet
     * it is downloaded from S3 first via downloadToLocal().
     *
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
                if ($this->getClient()->headObject($this->getObjectKey($variant)))
                {
                    $this->downloadToLocal($variant);
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
     * Lists generated variants (thumbnails, previews, etc.) for this file.
     *
     * Used when a download request includes a variant parameter — HumHub checks that
     * the variant exists before serving it. Merges local cache entries with keys
     * found in S3 so variants remain discoverable after cache eviction.
     *
     * @param string[] $except
     * @return string[]
     * @inheritdoc
     */
    public function getVariants($except = []): array
    {
        $variants = parent::getVariants($except);
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
     * Stores an uploaded file from the browser.
     *
     * Triggered by the file upload widget and attachment flows (posts, comments,
     * profile images, etc.) when HumHub stores an UploadedFile. Writes to the local
     * cache, then pushes to S3; rolls back the cache file if the upload fails.
     *
     * @param string|null $variant
     * @inheritdoc
     */
    public function set(UploadedFile $file, $variant = null): void
    {
        parent::set($file, $variant);
        $this->uploadFromLocal($variant);
    }

    /**
     * Stores raw string content as a file.
     *
     * Used when HumHub saves generated or programmatic content (exports, rendered
     * assets, etc.). Same write-through to S3 as set().
     *
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
     * Stores a file by copying from an existing path on disk.
     *
     * Used when HumHub copies an already-stored file into another record, or when
     * importing from a temp path. Same write-through to S3 as set().
     *
     * @param string|null $variant
     * @inheritdoc
     */
    public function setByPath(string $path, $variant = null): void
    {
        parent::setByPath($path, $variant);
        $this->uploadFromLocal($variant);
    }

    /**
     * Removes file bytes from S3 and the local cache.
     *
     * Called when a file record is deleted, when unused files are cleaned up by the
     * daily cron job, and when file content is replaced (old variants are cleared
     * first, optionally keeping history entries). Remote objects are removed before
     * the local cache so S3 does not retain orphaned data.
     *
     * @param string|null $variant
     * @param string[] $except variant names to keep (e.g. file-history snapshots)
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

    private function getClient(): S3Client
    {
        if ($this->client === null)
        {
            ComposerAutoload::ensureLoaded();
            $this->client = ConfigureForm::createClient();
        }

        return $this->client;
    }

    /**
     * Builds the S3 key prefix for this HumHub file (bucket folder + sharded GUID path).
     *
     * Each file's variants share one prefix, e.g. "humhub/a/3/{guid}/".
     */
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

    /**
     * Full S3 object key for a variant (defaults to the original "file" variant).
     */
    private function getObjectKey(?string $variant = null): string
    {
        if ($variant === null)
        {
            $variant = $this->originalFileName;
        }

        return $this->getObjectPrefix() . '/' . $variant;
    }

    /**
     * Pushes a freshly cached local file to S3 after HumHub finishes a write.
     *
     * HumHub always writes locally first; this is the second step that makes the
     * upload durable. On failure the local copy is removed so HumHub does not think
     * the file exists when S3 does not have it.
     */
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
        }
        catch (S3Exception $exception)
        {
            @unlink($localPath);
            Yii::error('S3 upload failed: ' . $exception->getMessage(), 'humhub-s3');
            throw new RuntimeException(
                Yii::t('HumhubS3Module.base', 'Failed to upload file to S3 storage. Please try again or contact an administrator.'),
                0,
                $exception
            );
        }
    }

    /**
     * Pulls an object from S3 into the runtime cache on first read.
     *
     * HumHub cannot stream directly from S3 — downloads, previews, and image tools
     * need a local path. Invoked from get() when the cache is empty but the object
     * but the object exists remotely (e.g. after a server restart or on another node).
     */
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
            if (is_file($localPath))
            {
                @unlink($localPath);
            }
            throw $exception;
        }

        @chmod($localPath, $this->fileMode);
    }

    /**
     * Deletes all S3 objects under this file's prefix except those listed in $except.
     *
     * Used when HumHub deletes an entire attachment or replaces its content.
     *
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

    /**
     * Deletes a single variant from S3 (e.g. one thumbnail) while leaving others intact.
     */
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
