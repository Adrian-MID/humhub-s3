<?php

namespace humhub\modules\humhubs3\components;

use humhub\components\mail\Message;
use humhub\modules\file\libs\FileHelper;
use humhub\modules\file\models\File;
use humhub\modules\humhubs3\models\forms\ConfigureForm;
use Yii;
use yii\mail\MessageInterface;

/**
 * Registers S3-backed richtext images for inline (CID) embedding in outgoing emails.
 */
class S3EmailInlineImages
{
    /**
     * @var array<int, array{path: string, cid: string, contentType: string}>
     */
    private static array $attachments = [];

    public static function reset(): void
    {
        self::$attachments = [];
    }

    /**
     * Downloads a file from S3 storage and registers it for inline email embedding.
     *
     * Returns the Content-ID name (without the cid: prefix) or null when unavailable.
     */
    public static function registerFile(File $file): ?string
    {
        if (!ConfigureForm::isActive())
        {
            return null;
        }

        $fileKey = $file->id;
        if (isset(self::$attachments[$fileKey]))
        {
            return self::$attachments[$fileKey]['cid'];
        }

        $store = $file->getStore();
        if (!$store instanceof S3StorageManager)
        {
            return null;
        }

        try
        {
            if (!$store->has(null))
            {
                return null;
            }

            $localPath = $store->get(null);
            if (!is_file($localPath))
            {
                return null;
            }

            $cid = 'hh-s3-' . $file->id . '-' . substr($file->guid, 0, 8);
            $contentType = FileHelper::getMimeTypeByExtension($file->file_name) ?: 'application/octet-stream';

            self::$attachments[$fileKey] = [
                'path' => $localPath,
                'cid' => $cid,
                'contentType' => $contentType,
            ];

            return $cid;
        }
        catch (\Throwable $exception)
        {
            Yii::error(
                'Unable to prepare S3 file for inline email embedding: ' . $exception->getMessage(),
                'humhub-s3'
            );

            return null;
        }
    }

    public static function applyToMessage(MessageInterface $message): void
    {
        if (self::$attachments === [])
        {
            return;
        }

        if (!$message instanceof Message)
        {
            self::reset();

            return;
        }

        foreach (self::$attachments as $attachment)
        {
            $message->embed($attachment['path'], [
                'fileName' => $attachment['cid'],
                'contentType' => $attachment['contentType'],
            ]);
        }

        self::reset();
    }

    /**
     * Clears registrations after send completes or fails after inline parts were attached.
     *
     * HumHub notification and summary mails may call RichTextToEmailHtmlConverter::process()
     * more than once per message (e.g. comment plus parent post). Registrations must persist
     * until Mailer::EVENT_BEFORE_SEND, then be cleared so the next message starts clean.
     */
    public static function finalizeAfterSend(): void
    {
        self::reset();
    }
}
