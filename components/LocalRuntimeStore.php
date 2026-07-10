<?php

namespace humhub\modules\humhubs3\components;

use humhub\modules\file\libs\FileHelper;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Yii;

/**
 * Temporary processing files under protected/runtime/humhub-s3.
 *
 * S3 remains the durable store; this directory only holds short-lived copies for
 * uploads and image processing.
 */
class LocalRuntimeStore
{
    private const STORE_ROOT = '@runtime/humhub-s3';

    public static function getRootPath(): string
    {
        $path = Yii::getAlias(self::STORE_ROOT);
        if (!is_string($path))
        {
            throw new RuntimeException('Unable to resolve alias: ' . self::STORE_ROOT);
        }

        return $path;
    }

    /**
     * @return array{fileCount: int, sizeBytes: int}
     */
    public static function getStats(): array
    {
        $root = self::getRootPath();
        if (!is_dir($root))
        {
            return ['fileCount' => 0, 'sizeBytes' => 0];
        }

        $fileCount = 0;
        $sizeBytes = 0;

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $fileInfo)
        {
            if (!$fileInfo instanceof \SplFileInfo || !$fileInfo->isFile())
            {
                continue;
            }

            $fileCount++;
            $sizeBytes += $fileInfo->getSize();
        }

        return ['fileCount' => $fileCount, 'sizeBytes' => $sizeBytes];
    }

    public static function clear(): void
    {
        $root = self::getRootPath();

        if (is_dir($root))
        {
            FileHelper::removeDirectory($root);
        }

        if (is_dir($root) && self::getStats()['fileCount'] > 0)
        {
            throw new RuntimeException('Unable to remove local runtime store: ' . $root);
        }

        if (!is_dir($root))
        {
            FileHelper::createDirectory($root, 0o755, true);
        }

        if (!is_dir($root))
        {
            throw new RuntimeException('Unable to recreate local runtime store: ' . $root);
        }
    }

    public static function formatSize(int $sizeBytes): string
    {
        if ($sizeBytes < 1024)
        {
            return $sizeBytes . ' B';
        }

        if ($sizeBytes < 1024 * 1024)
        {
            return round($sizeBytes / 1024, 1) . ' KB';
        }

        if ($sizeBytes < 1024 * 1024 * 1024)
        {
            return round($sizeBytes / (1024 * 1024), 1) . ' MB';
        }

        return round($sizeBytes / (1024 * 1024 * 1024), 1) . ' GB';
    }
}
