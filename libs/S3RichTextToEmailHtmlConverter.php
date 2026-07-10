<?php

namespace humhub\modules\content\widgets\richtext\converter;

use humhub\modules\content\widgets\richtext\extensions\link\LinkParserBlock;
use humhub\modules\file\models\File;
use humhub\modules\humhubs3\components\CoreClassLoader;
use humhub\modules\humhubs3\components\S3EmailInlineImages;
use humhub\modules\humhubs3\models\forms\ConfigureForm;

CoreClassLoader::requireCore('humhub\modules\content\widgets\richtext\converter\RichTextToEmailHtmlConverter');

/**
 * Embeds S3-backed richtext images as inline (CID) attachments in notification emails.
 *
 * Presigned S3 URLs are not suitable for email clients, and HumHub download tokens
 * are not used while S3 storage is active. Images are attached to the outgoing
 * message instead, without affecting web or stream rendering.
 */
class RichTextToEmailHtmlConverter extends \humhub\modules\humhubs3\libs\core\RichTextToEmailHtmlConverter
{
    private const CID_PLACEHOLDER_PREFIX = 'https://humhub-s3-inline.invalid/';

    /**
     * @inheritdoc
     */
    public $allowedSchemes = [
        'http' => true,
        'https' => true,
        'mailto' => true,
        'ftp' => true,
        'cid' => true,
    ];

    /**
     * @param string $text
     * @param array<string, mixed> $options
     */
    public static function process($text, $options = []): string
    {
        S3EmailInlineImages::reset();

        if (ConfigureForm::isActive())
        {
            unset($options[RichTextToHtmlConverter::OPTION_CACHE_KEY]);
        }

        return parent::process($text, $options);
    }

    /**
     * HTMLPurifier has no cid: URI scheme handler and would strip inline image sources.
     *
     * @param mixed $text
     * @inheritdoc
     */
    protected function onAfterParse($text): string
    {
        if (!is_string($text))
        {
            return parent::onAfterParse($text);
        }

        if (!ConfigureForm::isActive() || !str_contains($text, 'cid:'))
        {
            return parent::onAfterParse($text);
        }

        /** @var array<string, string> $placeholders */
        $placeholders = [];
        $text = preg_replace_callback(
            '/\bsrc=(["\'])cid:([^"\']+)\1/i',
            static function (array $matches) use (&$placeholders): string
            {
                $placeholder = self::CID_PLACEHOLDER_PREFIX . count($placeholders);
                $placeholders[$placeholder] = 'cid:' . $matches[2];

                return 'src=' . $matches[1] . $placeholder . $matches[1];
            },
            $text
        ) ?? $text;

        $text = parent::onAfterParse($text);

        return $placeholders === [] ? $text : strtr($text, $placeholders);
    }

    protected function tokenizeBlock(LinkParserBlock $linkBlock): LinkParserBlock
    {
        if (!ConfigureForm::isActive())
        {
            return parent::tokenizeBlock($linkBlock);
        }

        $fileId = $linkBlock->getFileId();
        if ($fileId === null)
        {
            return parent::tokenizeBlock($linkBlock);
        }

        $file = File::findOne(['id' => $fileId]);
        if (!$file instanceof File)
        {
            return parent::tokenizeBlock($linkBlock);
        }

        $cid = S3EmailInlineImages::registerFile($file);
        if ($cid === null)
        {
            return parent::tokenizeBlock($linkBlock);
        }

        $linkBlock->setUrl('cid:' . $cid);

        return $linkBlock;
    }
}
