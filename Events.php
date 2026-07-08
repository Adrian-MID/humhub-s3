<?php

namespace humhub\modules\humhubs3;

use humhub\components\ModuleEvent;
use humhub\helpers\ControllerHelper;
use humhub\modules\file\components\StorageManager;
use humhub\modules\admin\permissions\ManageSettings;
use humhub\modules\admin\widgets\SettingsMenu;
use humhub\modules\humhubs3\components\MediaProxyRoute;
use humhub\modules\ui\menu\MenuLink;
use Yii;
use yii\base\BaseObject;
use yii\base\Event;

class Events extends BaseObject
{
    /**
     * Wires S3 storage into HumHub after the application boots.
     *
     * Runs on web and console requests so queue workers and cron jobs use the same
     * storage backend as the UI.
     */
    public static function onApplicationInit(): void
    {
        ComposerAutoload::ensureLoaded();
        Module::applyStorageManager();
        Module::applyClassMaps();
        MediaProxyRoute::registerUrlRule();
    }

    /**
     * Restores HumHub's default local storage when this module is disabled.
     *
     * Hooked from ModuleManager so HumHub's own disable/uninstall lifecycle (including
     * background queue jobs) is not overridden.
     */
    public static function onBeforeModuleDisable(ModuleEvent $event): void
    {
        if ($event->moduleId !== 'humhub-s3')
        {
            return;
        }

        $fileModule = Yii::$app->getModule('file');
        if ($fileModule instanceof \humhub\modules\file\Module)
        {
            $fileModule->storageManagerClass = StorageManager::class;
        }

        Module::removeClassMaps();
    }

    /**
     * Adds "HumHub S3" under Administration → Settings.
     */
    public static function onSettingsMenuInit(Event $event): void
    {
        /** @var SettingsMenu $menu */
        $menu = $event->sender;

        $menu->addEntry(new MenuLink([
            'label' => Yii::t('HumhubS3Module.base', 'HumHub S3'),
            'url' => ['/humhub-s3/admin/index'],
            'sortOrder' => 650,
            'isActive' => ControllerHelper::isActivePath('humhub-s3', 'admin'),
            'isVisible' => Yii::$app->user->can(ManageSettings::class),
        ]));
    }
}
