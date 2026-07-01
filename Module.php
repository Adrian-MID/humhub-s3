<?php

namespace humhub\modules\humhubs3;

use humhub\components\Module as BaseModule;
use humhub\modules\file\components\StorageManager;
use humhub\modules\humhubs3\components\S3StorageManager;
use humhub\modules\humhubs3\models\forms\ConfigureForm;
use Yii;
use yii\helpers\Url;

class Module extends BaseModule
{
    /**
     * @inheritdoc
     */
    public function init(): void
    {
        ComposerAutoload::ensureLoaded();
        parent::init();
    }

    /**
     * Admin modules page link to the S3 settings screen.
     *
     * @inheritdoc
     */
    public function getConfigUrl(): string
    {
        return Url::to(['/humhub-s3/admin/index']);
    }

    /**
     * Swaps the file module's storage manager class based on current settings.
     *
     * Called on every request bootstrap and after an admin saves settings. When S3
     * is enabled and configured, HumHub's File model will instantiate S3StorageManager
     * instead of the built-in local StorageManager for all subsequent uploads and
     * downloads.
     */
    public static function applyStorageManager(): void
    {
        $fileModule = Yii::$app->getModule('file');
        if (!$fileModule instanceof \humhub\modules\file\Module)
        {
            return;
        }

        if (ConfigureForm::isActive())
        {
            $fileModule->storageManagerClass = S3StorageManager::class;
            return;
        }

        $fileModule->storageManagerClass = StorageManager::class;
    }
}
