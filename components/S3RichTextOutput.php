<?php

namespace humhub\modules\humhubs3\components;

use humhub\modules\content\widgets\richtext\extensions\file\FileExtension;
use humhub\modules\content\widgets\richtext\extensions\link\RichTextLinkExtensionMatch;
use humhub\modules\file\models\File;
use humhub\modules\humhubs3\components\S3FileDelivery;
use humhub\modules\humhubs3\models\forms\ConfigureForm;

/**
 * Replaces file-guid richtext links with presigned S3 URLs before client-side rendering.
 *
 * HumHub's Prosemirror output widget ships markdown to the browser, where humhub.file.js
 * converts file-guid links into /file/file/download URLs. That bypasses S3File::getUrl().
 */
class S3RichTextOutput
{
    public static function replaceFileGuidUrls(string $markdown): string
    {
        if (!ConfigureForm::isActive() || $markdown === '')
        {
            return $markdown;
        }

        $replaced = FileExtension::replaceLinkExtension($markdown, 'file-guid', function (RichTextLinkExtensionMatch $match): string
        {
            $guid = $match->getExtensionId();
            if ($guid === '')
            {
                return $match->getFull();
            }

            $file = File::findOne(['guid' => $guid]);
            if (!$file instanceof File)
            {
                return $match->getFull();
            }

            $url = self::resolvePresignedFileUrl($file);
            if ($url === null)
            {
                return $match->getFull();
            }

            $addition = $match->getAddition();
            if ($addition !== null && $addition !== '')
            {
                $addition = ' ' . $addition;
            }
            else
            {
                $addition = '';
            }

            $prefix = $match->isImage() ? '!' : '';

            return $prefix . '[' . $match->getText() . '](' . $url . $addition . ')';
        });

        return $replaced;
    }

    public static function resolvePresignedFileUrl(File $file): ?string
    {
        return S3FileDelivery::resolvePresignedUrl($file, null, false);
    }
}
