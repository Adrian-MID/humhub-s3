<?php

namespace humhub\modules\file\models;

use humhub\modules\humhubs3\components\CoreClassLoader;
use humhub\modules\humhubs3\components\S3FileDelivery;
use humhub\modules\humhubs3\models\forms\ConfigureForm;

CoreClassLoader::requireCore('humhub\modules\file\models\File');

/**
 * Returns presigned S3 URLs for private attachments when S3 storage is active.
 *
 * Never falls back to HumHub /file/file/download URLs while S3 is enabled.
 */
class File extends \humhub\modules\humhubs3\libs\core\File
{
    /**
     * @param array<string, mixed>|string $params
     * @param bool $absolute
     */
    public function getUrl($params = [], $absolute = true): string
    {
        if (is_string($params))
        {
            $suffix = $params;
            $params = [];
            if ($suffix !== '')
            {
                $params['variant'] = $suffix;
            }
        }

        if (!ConfigureForm::isActive())
        {
            return parent::getUrl($params, $absolute);
        }

        $variant = isset($params['variant']) && is_string($params['variant']) && $params['variant'] !== ''
            ? $params['variant']
            : null;
        $forceDownload = !empty($params['download']);

        $presignedUrl = S3FileDelivery::resolvePresignedUrl($this, $variant, $forceDownload);
        if ($presignedUrl !== null)
        {
            return $presignedUrl;
        }

        if ($variant !== null)
        {
            $presignedUrl = S3FileDelivery::resolvePresignedUrl($this, null, $forceDownload);
            if ($presignedUrl !== null)
            {
                return $presignedUrl;
            }
        }

        return '';
    }
}
