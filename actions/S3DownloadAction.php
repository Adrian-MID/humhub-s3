<?php

namespace humhub\modules\humhubs3\actions;

use humhub\modules\file\actions\DownloadAction;
use humhub\modules\humhubs3\components\S3FileDelivery;
use humhub\modules\humhubs3\components\S3StorageManager;
use humhub\modules\humhubs3\models\forms\ConfigureForm;
use Yii;
use yii\web\HttpException;

/**
 * Redirects authorized file downloads to a presigned S3 URL instead of streaming locally.
 */
class S3DownloadAction extends DownloadAction
{
    /**
     * @inheritdoc
     */
    public function init(): void
    {
        parent::init();
        $this->enableHttpCache = false;
    }

    /**
     * @return \yii\web\Response|null
     */
    public function run()
    {
        if (!ConfigureForm::isActive())
        {
            return parent::run();
        }

        $store = $this->file->getStore();
        if (!$store instanceof S3StorageManager)
        {
            throw new HttpException(404, Yii::t('FileModule.base', 'Could not find requested file!'));
        }

        $url = S3FileDelivery::resolvePresignedUrl($this->file, $this->variant, $this->download);
        if ($url === null)
        {
            throw new HttpException(404, Yii::t('FileModule.base', 'Could not find requested file!'));
        }

        return Yii::$app->response->redirect($url);
    }

    /**
     * @param mixed $variant
     */
    protected function loadVariant($variant): void
    {
        if ($variant === null)
        {
            $suffix = $_GET['suffix'] ?? null;
            $variant = is_string($suffix) && $suffix !== '' ? $suffix : null;
        }

        if ($variant !== null && !is_string($variant))
        {
            throw new HttpException(404, Yii::t('FileModule.base', 'Could not find requested file variant!'));
        }

        if (is_string($variant))
        {
            $store = $this->file->getStore();
            if ($store instanceof S3StorageManager)
            {
                if (!in_array($variant, $store->getVariants(), true) && !$store->has($variant))
                {
                    throw new HttpException(404, Yii::t('FileModule.base', 'Could not find requested file variant!'));
                }
            }
            elseif ($store instanceof \humhub\modules\file\components\StorageManager)
            {
                if (!in_array($variant, $store->getVariants(), true))
                {
                    throw new HttpException(404, Yii::t('FileModule.base', 'Could not find requested file variant!'));
                }
            }
        }

        $this->variant = is_string($variant) ? $variant : null;
    }

    /**
     * @inheritdoc
     */
    protected function checkFileExists(): void
    {
        if (!ConfigureForm::isActive())
        {
            parent::checkFileExists();

            return;
        }

        $store = $this->file->getStore();
        if (!$store instanceof S3StorageManager || !$store->ensureRemoteVariant($this->variant))
        {
            throw new HttpException(404, Yii::t('FileModule.base', 'Could not find requested file!'));
        }
    }
}
