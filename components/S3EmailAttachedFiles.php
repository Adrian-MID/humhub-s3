<?php

namespace humhub\modules\humhubs3\components;

use humhub\helpers\Html;
use humhub\modules\content\interfaces\ContentOwner;
use humhub\modules\file\actions\DownloadAction;
use humhub\modules\file\converter\PreviewImage;
use humhub\modules\file\models\File;
use humhub\modules\humhubs3\models\forms\ConfigureForm;
use humhub\modules\user\models\User;
use yii\helpers\Url;

/**
 * Renders stream-attached files in notification emails when S3 storage is active.
 *
 * HumHub shows uploaded files below post/comment text via FilePreview, but
 * MailContentEntry only converts the richtext body. Stream attachments that are
 * not embedded inline in the message are appended here.
 */
class S3EmailAttachedFiles
{
    /**
     * Appends HTML for attached files that are visible on the stream but not referenced in richtext.
     */
    public static function appendStreamFilesHtml(string $html, ContentOwner $owner, ?User $receiver): string
    {
        if (!ConfigureForm::isActive() || !$owner instanceof \yii\db\ActiveRecord)
        {
            return $html;
        }

        $markdown = $owner->getContentDescription();
        $chunks = [];

        foreach ($owner->fileManager->findStreamFiles() as $file)
        {
            if (self::isReferencedInMarkdown($markdown, $file))
            {
                continue;
            }

            $chunk = self::renderFileHtml($file, $receiver);
            if ($chunk !== '')
            {
                $chunks[] = $chunk;
            }
        }

        if ($chunks === [])
        {
            return $html;
        }

        return $html . '<div style="margin-top:10px;">' . implode('', $chunks) . '</div>';
    }

    private static function isReferencedInMarkdown(string $markdown, File $file): bool
    {
        return str_contains($markdown, $file->guid);
    }

    private static function renderFileHtml(File $file, ?User $receiver): string
    {
        $previewImage = new PreviewImage();
        if ($previewImage->applyFile($file))
        {
            $cid = S3EmailInlineImages::registerFile($file);
            if ($cid === null)
            {
                return '';
            }

            return '<p style="margin:0 0 10px;">'
                . Html::img('cid:' . $cid, [
                    'alt' => $file->file_name,
                    'style' => 'max-width:100%;',
                ])
                . '</p>';
        }

        $downloadUrl = self::buildTokenizedDownloadUrl($file, $receiver);
        if ($downloadUrl === null)
        {
            return '';
        }

        return '<p style="margin:0 0 6px;">'
            . Html::a(Html::encode($file->file_name), $downloadUrl, [
                'target' => '_blank',
                'rel' => 'nofollow noreferrer noopener',
            ])
            . '</p>';
    }

    private static function buildTokenizedDownloadUrl(File $file, ?User $receiver): ?string
    {
        if ($receiver === null)
        {
            return null;
        }

        $url = Url::to([
            '/file/file/download',
            'guid' => $file->guid,
            'hash_sha1' => $file->getHash(8),
        ], true);

        $token = DownloadAction::generateDownloadToken($file, $receiver);
        $separator = str_contains($url, '?') ? '&' : '?';

        return $url . $separator . 'token=' . rawurlencode($token);
    }
}
