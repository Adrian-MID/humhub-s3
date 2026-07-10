<?php

use humhub\modules\humhubs3\models\forms\ConfigureForm;
use humhub\widgets\bootstrap\Tabs;
use yii\helpers\StringHelper;
use yii\helpers\Url;
use yii\web\View;

/**
 * @var View $this
 * @var string $tab
 * @var bool $isActive
 */
?>

<div class="panel panel-default">
    <div class="panel-heading">
        <?= Yii::t('HumhubS3Module.base', '<strong>HumHub S3</strong> Configuration'); ?>
    </div>
    <div class="panel-body">
        <p class="help-block">
            <?= Yii::t(
                'HumhubS3Module.base',
                'Store HumHub uploads in Amazon S3 or another S3-compatible service. All durable files live in your bucket. This server only keeps short-lived processing files while an upload or image conversion runs.'
            ); ?>
        </p>

        <?php if ($isActive): ?>
            <div class="alert alert-success">
                <?= Yii::t('HumhubS3Module.base', 'S3 storage is active. New uploads are stored in the configured bucket.'); ?>
            </div>
        <?php elseif ((bool) (ConfigureForm::getSettings()['enabled'] ?? false)): ?>
            <div class="alert alert-warning">
                <?= Yii::t('HumhubS3Module.base', 'S3 storage is enabled but not fully configured yet.'); ?>
            </div>
        <?php else: ?>
            <div class="alert alert-info">
                <?= Yii::t('HumhubS3Module.base', 'S3 storage is disabled. Files continue to use the local filesystem.'); ?>
            </div>
        <?php endif; ?>

        <?= Tabs::widget([
            'renderTabContent' => false,
            'options' => [
                'style' => ['margin-bottom' => '0'],
            ],
            'items' => [
                [
                    'label' => Yii::t('HumhubS3Module.base', 'General'),
                    'url' => ['index'],
                    'active' => StringHelper::startsWith(Url::current(), Url::to(['index'])),
                ],
                [
                    'label' => Yii::t('HumhubS3Module.base', 'Bucket Policy'),
                    'url' => ['bucket-policy'],
                    'active' => StringHelper::startsWith(Url::current(), Url::to(['bucket-policy'])),
                ],
                [
                    'label' => Yii::t('HumhubS3Module.base', 'Processing Cache'),
                    'url' => ['processing-cache'],
                    'active' => StringHelper::startsWith(Url::current(), Url::to(['processing-cache'])),
                ],
            ],
        ]) ?>

        <br>

        <?= $tab ?>
    </div>
</div>
