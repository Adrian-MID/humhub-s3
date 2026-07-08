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
 * Objects are stored under the configured bucket prefix. A runtime cache holds local
 * copies for Imagine processing and streaming; S3 remains the durable store.
 */
class S3MediaStorage
{
    private const CACHE_ROOT = '@runtime/humhub-s3/media';

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

    public static function getCachePath(string $relativePath): string
    {
        $relativePath = ltrim(str_replace('\\', '/', $relativePath), '/');

        return self::resolveAlias(self::CACHE_ROOT . '/' . $relativePath);
    }

    /**
     * Returns a local path for reading or writing, fetching from S3 or legacy uploads on demand.
     */
    public static function resolveLocalPath(string $relativePath, bool $downloadRemote = true): string
    {
        $cachePath = self::getCachePath($relativePath);
        FileHelper::createDirectory(dirname($cachePath), 0o755, true);

        if (is_file($cachePath))
        {
            return $cachePath;
        }

        if ($downloadRemote && self::hasRemote($relativePath))
        {
            self::downloadToCache($relativePath);

            return $cachePath;
        }

        $legacyPath = self::getLegacyPath($relativePath);
        if (is_file($legacyPath))
        {
            return $legacyPath;
        }

        return $cachePath;
    }

    public static function has(string $relativePath): bool
    {
        if (is_file(self::getCachePath($relativePath)))
        {
            return true;
        }

        if (self::hasRemote($relativePath))
        {
            return true;
        }

        return is_file(self::getLegacyPath($relativePath));
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
    }

    public static function delete(string $relativePath): void
    {
        $cachePath = self::getCachePath($relativePath);
        if (is_file($cachePath))
        {
            @unlink($cachePath);
        }

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

        $cacheDir = self::getCachePath($relativePrefix);
        if (is_dir($cacheDir))
        {
            FileHelper::removeDirectory($cacheDir);
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
        $cachePath = self::getCachePath($relativePath);
        if (is_file($cachePath))
        {
            $mtime = filemtime($cachePath);

            return $mtime !== false ? $mtime : null;
        }

        if (is_file(self::getLegacyPath($relativePath)))
        {
            $mtime = filemtime(self::getLegacyPath($relativePath));

            return $mtime !== false ? $mtime : null;
        }

        return null;
    }

    /**
     * @param array<string, scalar|null> $params
     */
    public static function buildProxyUrl(array $params, bool $scheme = false): string
    {
        $version = null;
        if (isset($params['path']) && is_string($params['path']))
        {
            $version = self::getLastModified($params['path']);
        }

        if ($version !== null)
        {
            $params['v'] = $version;
        }

        return MediaProxyRoute::buildUrl($params, $scheme);
    }

    public static function getLegacyPath(string $relativePath): string
    {
        return self::resolveAlias('@webroot/uploads/' . ltrim(str_replace('\\', '/', $relativePath), '/'));
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
        catch (S3Exception $exception)
        {
            Yii::warning('Unable to check S3 media object: ' . $exception->getMessage(), 'humhub-s3');

            return false;
        }
    }

    private static function downloadToCache(string $relativePath): void
    {
        $cachePath = self::getCachePath($relativePath);
        FileHelper::createDirectory(dirname($cachePath), 0o755, true);

        try
        {
            self::getClient()->getObject(self::buildObjectKey($relativePath), $cachePath);
        }
        catch (S3Exception $exception)
        {
            if (is_file($cachePath))
            {
                @unlink($cachePath);
            }

            Yii::error('Unable to download media from S3: ' . $exception->getMessage(), 'humhub-s3');
            throw new RuntimeException(
                Yii::t('HumhubS3Module.base', 'Failed to retrieve file from S3 storage. Please try again later.'),
                0,
                $exception
            );
        }

        @chmod($cachePath, 0o644);
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
