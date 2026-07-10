<?php

namespace humhub\modules\humhubs3\controllers;

use humhub\modules\file\actions\UploadAction;
use humhub\modules\file\controllers\FileController as BaseFileController;
use humhub\modules\humhubs3\actions\S3DownloadAction;

/**
 * Swaps HumHub file downloads for presigned S3 redirects when S3 storage is active.
 */
class FileController extends BaseFileController
{
    /**
     * @inheritdoc
     * @return array<string, array<string, class-string>|class-string>
     */
    public function actions()
    {
        return [
            'download' => [
                'class' => S3DownloadAction::class,
            ],
            'upload' => [
                'class' => UploadAction::class,
            ],
        ];
    }
}
