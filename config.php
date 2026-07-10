<?php

// HumHub validates event callbacks while loading config.php, before the module instance
// boots. Ensure module classes are autoloadable at that point.
foreach ([__DIR__ . '/../vendor/autoload.php', __DIR__ . '/vendor/autoload.php'] as $autoloadFile)
{
    if (is_file($autoloadFile))
    {
        require_once $autoloadFile;
        break;
    }
}

/** @noinspection MissedFieldInspection */
return [
    'id' => 'humhub-s3',
    'class' => 'humhub\modules\humhubs3\Module',
    'namespace' => 'humhub\modules\humhubs3',
    'events' => [
        ['class' => 'humhub\components\Application', 'event' => 'onInit', 'callback' => ['humhub\modules\humhubs3\Events', 'onApplicationInit']],
        ['class' => 'humhub\components\console\Application', 'event' => 'onInit', 'callback' => ['humhub\modules\humhubs3\Events', 'onApplicationInit']],
        ['class' => 'humhub\components\ModuleManager', 'event' => 'beforeModuleDisabled', 'callback' => ['humhub\modules\humhubs3\Events', 'onBeforeModuleDisable']],
        ['class' => 'humhub\modules\admin\widgets\SettingsMenu', 'event' => 'init', 'callback' => ['humhub\modules\humhubs3\Events', 'onSettingsMenuInit']],
    ],
];
