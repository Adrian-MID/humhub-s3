<?php

namespace humhub\modules\humhubs3\components;

use humhub\modules\file\converter\PreviewImage;
use humhub\modules\file\models\File;

/**
 * Generates missing file variants locally for processing, then callers upload them to S3.
 *
 * HumHub converters write directly to the storage path; this never serves bytes to users.
 */
class S3FileVariantMaterializer
{
    /** @var array<string, class-string> */
    private const KNOWN_VARIANT_CONVERTERS = [
        'preview-image' => PreviewImage::class,
    ];

    public static function materialize(File $file, S3StorageManager $store, ?string $variant): bool
    {
        if ($variant === null || $variant === '')
        {
            return $store->remoteHas(null);
        }

        if ($store->remoteHas($variant) || is_file($store->getStoragePath($variant)))
        {
            return true;
        }

        $converterClass = self::KNOWN_VARIANT_CONVERTERS[$variant] ?? null;
        if ($converterClass === null)
        {
            return false;
        }

        if (!$store->remoteHas(null))
        {
            return false;
        }

        $converter = new $converterClass();
        if (!$converter->applyFile($file))
        {
            return false;
        }

        $converter->getFilename();

        return is_file($store->getStoragePath($variant));
    }
}
