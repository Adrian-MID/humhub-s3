<?php

namespace humhub\modules\content\widgets\richtext\converter;

use humhub\modules\content\widgets\richtext\extensions\link\LinkParserBlock;
use humhub\modules\humhubs3\components\CoreClassLoader;
use humhub\modules\humhubs3\components\S3FileDelivery;

CoreClassLoader::requireCore('humhub\modules\content\widgets\richtext\converter\RichTextToEmailHtmlConverter');

/**
 * Preserves presigned S3 attachment URLs in notification emails.
 */
class RichTextToEmailHtmlConverter extends \humhub\modules\humhubs3\libs\core\RichTextToEmailHtmlConverter
{
    protected function tokenizeBlock(LinkParserBlock $linkBlock): LinkParserBlock
    {
        $url = $linkBlock->getUrl();
        if (is_string($url) && S3FileDelivery::isPresignedS3Url($url))
        {
            return $linkBlock;
        }

        return parent::tokenizeBlock($linkBlock);
    }
}
