<?php

namespace humhub\modules\humhubs3;

use humhub\components\mail\Mailer;
use humhub\components\ModuleEvent;
use humhub\helpers\ControllerHelper;
use humhub\modules\file\components\StorageManager;
use humhub\modules\admin\permissions\ManageSettings;
use humhub\modules\admin\widgets\SettingsMenu;
use humhub\modules\humhubs3\components\S3EmailInlineImages;
use humhub\modules\ui\menu\MenuLink;
use Yii;
use yii\base\BaseObject;
use yii\base\Event;
use yii\mail\BaseMailer;
use yii\mail\MailEvent;

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
        Module::applyFileControllerMap();
        self::registerMailInlineImageHandler();
    }

    /**
     * Attaches S3 richtext images registered during email HTML conversion.
     */
    private static function registerMailInlineImageHandler(): void
    {
        static $registered = false;
        if ($registered)
        {
            return;
        }

        $registered = true;

        Event::on(Mailer::class, BaseMailer::EVENT_BEFORE_SEND, static function (MailEvent $event): void
        {
            S3EmailInlineImages::applyToMessage($event->message);
        });
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
            unset($fileModule->controllerMap['file']);
        }

        Module::removeClassMaps();
    }

    /**
     * Adds HumHub S3 settings and maintenance actions under Administration → Settings.
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
