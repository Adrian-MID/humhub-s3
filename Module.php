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
     * Maps HumHub media classes to S3-backed override files.
     *
     * Yii class maps must point to file paths, not replacement class names.
     *
     * @var array<string, string>
     */
    private const MEDIA_CLASS_MAP = [
        'humhub\libs\ProfileImage' => '@humhub/modules/humhubs3/libs/S3ProfileImage.php',
        'humhub\libs\ProfileBannerImage' => '@humhub/modules/humhubs3/libs/S3ProfileBannerImage.php',
        'humhub\libs\LogoImage' => '@humhub/modules/humhubs3/libs/S3LogoImage.php',
        'humhub\modules\web\pwa\widgets\SiteIcon' => '@humhub/modules/humhubs3/libs/S3SiteIcon.php',
        'humhub\modules\user\helpers\LoginBackgroundImageHelper' => '@humhub/modules/humhubs3/libs/S3LoginBackgroundImageHelper.php',
        'humhub\widgets\mails\MailHeaderImage' => '@humhub/modules/humhubs3/libs/S3MailHeaderImage.php',
    ];

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

    /**
     * Swaps HumHub media helpers for S3-backed implementations when storage is active.
     *
     * Profile images, banners, and site branding otherwise write to webroot/uploads/ and
     * break on ephemeral hosts. Class maps keep the public HumHub APIs unchanged.
     */
    public static function applyClassMaps(): void
    {
        if (!ConfigureForm::isActive())
        {
            self::removeClassMaps();

            return;
        }

        foreach (self::MEDIA_CLASS_MAP as $class => $path)
        {
            Yii::$classMap[$class] = $path;
        }
    }

    /**
     * Restores HumHub's default media classes when S3 media overrides are removed.
     */
    public static function removeClassMaps(): void
    {
        foreach (array_keys(self::MEDIA_CLASS_MAP) as $class)
        {
            unset(Yii::$classMap[$class]);
        }
    }
}
