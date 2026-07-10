<?php

namespace humhub\modules\humhubs3\components;

use humhub\modules\file\libs\FileHelper;
use humhub\modules\humhubs3\ComposerAutoload;
use humhub\modules\humhubs3\models\forms\ConfigureForm;
use RuntimeException;
use Yii;

/**
 * S3-backed storage for HumHub media outside the File module (profile images, branding).
 *
 * Objects are stored in S3. Local files under the process directory are temporary and
 * used only while uploading or transforming images.
 */
class S3MediaStorage
{
    private const PROCESS_ROOT = '@runtime/humhub-s3/process';

    private static ?S3Client $client = null;

    public static function buildObjectKey(string $relativePath): string
    {
        $relativePath = ltrim(str_replace('\\', '/', $relativePath), '/');
        $prefix = trim(ConfigureForm::getSettings()['prefix'], '/');

        if ($prefix !== '')
        {
            return $prefix . '/' . $relativePath;
        }

        return $relativePath;
    }

    public static function getPublicUrl(string $relativePath, bool $withVersion = false): string
    {
        $url = self::getClient()->getPublicObjectUrl(self::buildObjectKey($relativePath));

        if (!$withVersion)
        {
            return $url;
        }

        return self::appendVersionQuery($url, $relativePath);
    }

    /**
     * Returns a local path for processing (Imagine, uploads). Downloads from S3 when needed.
     */
    public static function resolveProcessingPath(string $relativePath, bool $downloadRemote = true): string
    {
        $processPath = self::getProcessPath($relativePath);
        FileHelper::createDirectory(dirname($processPath), 0o755, true);

        if (is_file($processPath))
        {
            return $processPath;
        }

        if ($downloadRemote && self::hasRemote($relativePath))
        {
            self::downloadToProcessPath($relativePath);

            return $processPath;
        }

        return $processPath;
    }

    public static function has(string $relativePath): bool
    {
        return self::hasRemote($relativePath);
    }

    public static function putFile(string $relativePath, string $localPath): void
    {
        if (!is_file($localPath))
        {
            throw new RuntimeException('Cannot upload missing local media file.');
        }

        try
        {
            self::getClient()->putObject(
                self::buildObjectKey($relativePath),
                $localPath,
                FileHelper::getMimeType($localPath) ?: 'application/octet-stream'
            );
        }
        catch (S3Exception $exception)
        {
            Yii::error('S3 media upload failed: ' . $exception->getMessage(), 'humhub-s3');
            throw new RuntimeException(
                Yii::t('HumhubS3Module.base', 'Failed to upload file to S3 storage. Please try again or contact an administrator.'),
                0,
                $exception
            );
        }
        finally
        {
            self::cleanupLocalPath($localPath, $relativePath);
        }
    }

    public static function delete(string $relativePath): void
    {
        self::cleanupProcessingPath($relativePath);

        try
        {
            $objectKey = self::buildObjectKey($relativePath);
            if (self::getClient()->headObject($objectKey))
            {
                self::getClient()->deleteObject($objectKey);
            }
        }
        catch (S3Exception $exception)
        {
            Yii::warning('Unable to delete S3 media object: ' . $exception->getMessage(), 'humhub-s3');
        }
    }

    /**
     * @param string[] $except relative paths to keep
     */
    public static function deleteByPrefix(string $relativePrefix, array $except = []): void
    {
        $relativePrefix = ltrim(str_replace('\\', '/', $relativePrefix), '/');
        $except = array_map(
            static fn(string $path): string => ltrim(str_replace('\\', '/', $path), '/'),
            $except
        );

        $processDir = self::getProcessPath($relativePrefix);
        if (is_dir($processDir))
        {
            FileHelper::removeDirectory($processDir);
        }

        $objectPrefix = self::buildObjectKey($relativePrefix);
        if (!str_ends_with($objectPrefix, '/'))
        {
            $objectPrefix .= '/';
        }

        try
        {
            foreach (self::getClient()->listObjectKeys($objectPrefix) as $key)
            {
                $relativeKey = self::relativePathFromObjectKey($key);
                if ($relativeKey !== null && in_array($relativeKey, $except, true))
                {
                    continue;
                }

                self::getClient()->deleteObject($key);
            }
        }
        catch (S3Exception $exception)
        {
            Yii::warning('Unable to delete S3 media prefix: ' . $exception->getMessage(), 'humhub-s3');
        }
    }

    public static function getLastModified(string $relativePath): ?int
    {
        try
        {
            return self::getClient()->getObjectLastModified(self::buildObjectKey($relativePath));
        }
        catch (\Throwable $exception)
        {
            if ($exception instanceof S3Exception)
            {
                Yii::warning('Unable to read S3 media last-modified: ' . $exception->getMessage(), 'humhub-s3');
            }

            return null;
        }
    }

    /**
     * Appends ?v= for cache busting. HumHub JS appends &c= after upload; the base URL must
     * already contain a query string or that suffix is parsed as part of the object key.
     */
    public static function appendVersionQuery(string $url, string $relativePath): string
    {
        $version = self::getLastModified($relativePath) ?? time();
        $separator = str_contains($url, '?') ? '&' : '?';

        return $url . $separator . 'v=' . $version;
    }

    public static function cleanupProcessingPath(string $relativePath): void
    {
        $processPath = self::getProcessPath($relativePath);
        if (is_file($processPath))
        {
            TempFileHelper::delete($processPath);
        }
    }

    public static function getProcessPath(string $relativePath): string
    {
        $relativePath = ltrim(str_replace('\\', '/', $relativePath), '/');

        return self::resolveAlias(self::PROCESS_ROOT . '/' . $relativePath);
    }

    private static function cleanupLocalPath(string $localPath, string $relativePath): void
    {
        $processPath = self::getProcessPath($relativePath);
        if ($localPath === $processPath || str_starts_with($localPath, sys_get_temp_dir()))
        {
            TempFileHelper::delete($localPath);
        }
    }

    private static function resolveAlias(string $alias): string
    {
        $path = Yii::getAlias($alias);
        if (!is_string($path))
        {
            throw new RuntimeException('Unable to resolve alias: ' . $alias);
        }

        return $path;
    }

    private static function hasRemote(string $relativePath): bool
    {
        try
        {
            return self::getClient()->headObject(self::buildObjectKey($relativePath));
        }
        catch (\Throwable $exception)
        {
            if ($exception instanceof S3Exception)
            {
                Yii::warning('Unable to check S3 media object: ' . $exception->getMessage(), 'humhub-s3');
            }

            return false;
        }
    }

    private static function downloadToProcessPath(string $relativePath): void
    {
        $processPath = self::getProcessPath($relativePath);
        FileHelper::createDirectory(dirname($processPath), 0o755, true);

        try
        {
            self::getClient()->getObject(self::buildObjectKey($relativePath), $processPath);
            TempFileHelper::track($processPath);
        }
        catch (S3Exception $exception)
        {
            if (is_file($processPath))
            {
                TempFileHelper::delete($processPath);
            }

            Yii::error('Unable to download media from S3: ' . $exception->getMessage(), 'humhub-s3');
            throw new RuntimeException(
                Yii::t('HumhubS3Module.base', 'Failed to retrieve file from S3 storage. Please try again later.'),
                0,
                $exception
            );
        }

        @chmod($processPath, 0o644);
    }

    private static function getClient(): S3Client
    {
        if (self::$client === null)
        {
            ComposerAutoload::ensureLoaded();
            self::$client = ConfigureForm::createClient();
        }

        return self::$client;
    }

    private static function relativePathFromObjectKey(string $objectKey): ?string
    {
        $prefix = trim(ConfigureForm::getSettings()['prefix'], '/');
        if ($prefix !== '' && str_starts_with($objectKey, $prefix . '/'))
        {
            return substr($objectKey, strlen($prefix) + 1);
        }

        if ($prefix === '')
        {
            return $objectKey;
        }

        return null;
    }
}
