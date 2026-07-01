<?php

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
