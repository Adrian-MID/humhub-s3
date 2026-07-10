<?php

namespace humhub\modules\humhubs3\components;

use humhub\modules\file\libs\FileHelper;
use humhub\modules\file\models\File;
use humhub\modules\humhubs3\models\forms\ConfigureForm;
use Yii;

/**
 * Builds presigned S3 URLs for private File module attachments.
 */
class S3FileDelivery
{
    public static function canUsePresignedUrl(File $file): bool
    {
        if (!ConfigureForm::isActive())
        {
            return false;
        }

        $store = $file->getStore();
        if (!$store instanceof S3StorageManager)
        {
            return false;
        }

        if (
            empty($file->object_model)
            && (Yii::$app->user->isGuest || (int) $file->created_by !== (int) Yii::$app->user->id)
        ) {
            return false;
        }

        return $file->canView();
    }

    public static function createPresignedUrl(File $file, ?string $variant, bool $forceDownload): string
    {
        $store = $file->getStore();
        if (!$store instanceof S3StorageManager)
        {
            throw new \RuntimeException('S3 storage manager is required to build a presigned file URL.');
        }

        $fileName = self::resolveDownloadFileName($file, $variant);
        $mimeType = FileHelper::getMimeTypeByExtension($fileName) ?: 'application/octet-stream';
        $fileModule = Yii::$app->getModule('file');
        $inlineMimeTypes = $fileModule instanceof \humhub\modules\file\Module
            ? $fileModule->inlineMimeTypes
            : [];
        $inline = !$forceDownload && in_array($mimeType, $inlineMimeTypes, true);

        return $store->createPresignedDownloadUrl(
            $variant,
            self::buildResponseContentDisposition($fileName, $inline)
        );
    }

    /**
     * Builds a Content-Disposition value for presigned GET requests.
     *
     * Inline delivery (feed images, previews) omits disposition entirely — browsers only need
     * the object bytes. Attachment downloads use an ISO-8859-1-safe filename because S3
     * rejects non-ASCII values in response-content-disposition.
     */
    public static function buildResponseContentDisposition(string $fileName, bool $inline): ?string
    {
        if ($inline)
        {
            return null;
        }

        $asciiFileName = self::toIso88591FileName($fileName);

        return 'attachment; filename="' . str_replace(['\\', '"'], ['\\\\', '\\"'], $asciiFileName) . '"';
    }

    /**
     * Reduces a UTF-8 filename to characters S3 accepts in response-content-disposition.
     */
    public static function toIso88591FileName(string $fileName): string
    {
        if ($fileName === '')
        {
            return 'download';
        }

        $converted = iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $fileName);
        if ($converted === false || $converted === '')
        {
            $converted = preg_replace('/[^\x20-\x7E]/', '_', $fileName) ?? '';
        }

        return $converted !== '' ? $converted : 'download';
    }

    public static function resolveDownloadFileName(File $file, ?string $variant): string
    {
        if ($variant === null || $variant === '')
        {
            return $file->file_name;
        }

        if (FileHelper::hasExtension($variant))
        {
            $variantParts = pathinfo($variant);
            $orgParts = pathinfo($file->file_name);
            $variantExtension = $variantParts['extension'] ?? 'bin';

            return $orgParts['filename'] . '_' . $variantParts['filename'] . '.' . $variantExtension;
        }

        if (FileHelper::hasExtension($file->file_name))
        {
            $parts = pathinfo($file->file_name);
            $extension = $parts['extension'] ?? 'bin';

            return $parts['filename'] . '_' . $variant . '.' . $extension;
        }

        return $file->file_name . '_' . $variant;
    }

    public static function isPresignedS3Url(string $url): bool
    {
        return str_contains($url, 'X-Amz-Signature=')
            || str_contains($url, 'X-Amz-Algorithm=')
            || str_contains($url, 'X-Amz-Credential=');
    }

    /**
     * Returns a presigned S3 URL for delivery, or null when unavailable.
     *
     * Never returns HumHub /file/file/download URLs — callers must not fall back to local delivery.
     */
    public static function resolvePresignedUrl(File $file, ?string $variant = null, bool $forceDownload = false): ?string
    {
        if (!ConfigureForm::isActive() || !self::canUsePresignedUrl($file))
        {
            return null;
        }

        $store = $file->getStore();
        if (!$store instanceof S3StorageManager)
        {
            return null;
        }

        if (!$store->ensureRemoteVariant($variant))
        {
            return null;
        }

        $presignedUrl = self::createPresignedUrl($file, $variant, $forceDownload);

        return self::isPresignedS3Url($presignedUrl) ? $presignedUrl : null;
    }
}
