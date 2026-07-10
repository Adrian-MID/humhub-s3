<?php

namespace humhub\modules\content\widgets\richtext\extensions\file;

use humhub\modules\content\widgets\richtext\ProsemirrorRichText;
use humhub\modules\humhubs3\components\CoreClassLoader;
use humhub\modules\humhubs3\components\S3RichTextOutput;

CoreClassLoader::requireCore('humhub\modules\content\widgets\richtext\extensions\file\FileExtension');

/**
 * Replaces file-guid markdown with presigned S3 URLs before Prosemirror client rendering.
 */
class FileExtension extends \humhub\modules\humhubs3\libs\core\FileExtension
{
    public function onBeforeOutput(ProsemirrorRichText $richtext, string $output): string
    {
        if ($richtext->edit)
        {
            return $output;
        }

        return S3RichTextOutput::replaceFileGuidUrls($output);
    }
}
