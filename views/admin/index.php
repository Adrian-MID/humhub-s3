<?php

use humhub\modules\humhubs3\components\LocalRuntimeStore;
use humhub\modules\humhubs3\models\forms\ConfigureForm;
use humhub\modules\ui\form\widgets\ActiveForm;
use yii\helpers\Html;
use yii\web\View;

/**
 * @var View $this
 * @var ConfigureForm $model
 * @var bool $isActive
 * @var array{fileCount: int, sizeBytes: int} $localStoreStats
 */
?>

<div class="panel panel-default">
    <div class="panel-heading">
        <?= Yii::t('HumhubS3Module.base', '<strong>HumHub S3</strong> Configuration'); ?>
    </div>
    <div class="panel-body">
        <div class="help-block">
            <?= Yii::t('HumhubS3Module.base', 'Configure Amazon S3 or an S3-compatible service to store HumHub uploads remotely instead of on the local server.'); ?>
        </div>

        <?php if ($isActive): ?>
            <div class="alert alert-success">
                <?= Yii::t('HumhubS3Module.base', 'Status: S3 storage is active. New uploads will be stored in the configured bucket.'); ?>
            </div>
        <?php elseif ($model->enabled): ?>
            <div class="alert alert-warning">
                <?= Yii::t('HumhubS3Module.base', 'Status: S3 storage is enabled but not fully configured yet.'); ?>
            </div>
        <?php else: ?>
            <div class="alert alert-info">
                <?= Yii::t('HumhubS3Module.base', 'Status: S3 storage is disabled. Files continue to use the local filesystem.'); ?>
            </div>
        <?php endif; ?>

        <?php $form = ActiveForm::begin(['id' => 'humhub-s3-settings-form']); ?>

        <?= $form->field($model, 'enabled')->checkbox(); ?>
        <hr>

        <?= $form->field($model, 'bucket')->textInput(); ?>
        <?= $form->field($model, 'region')->textInput(); ?>

        <h4><?= Yii::t('HumhubS3Module.base', 'Credentials'); ?></h4>
        <p class="help-block">
            <?= Yii::t(
                'HumhubS3Module.base',
                'Environment variables take precedence when the variable name is set and the value exists on the server. Otherwise the database values below are used.'
            ); ?>
        </p>

        <?= $form->field($model, 'accessKeyEnvVar')->textInput([
            'placeholder' => 'AWS_ACCESS_KEY_ID',
        ]); ?>
        <?= $form->field($model, 'secretKeyEnvVar')->textInput([
            'placeholder' => 'AWS_SECRET_ACCESS_KEY',
        ]); ?>

        <?php if ($model->isAccessKeyConfiguredViaEnv() || $model->isSecretKeyConfiguredViaEnv()): ?>
            <div class="alert alert-info">
                <?= Yii::t('HumhubS3Module.base', 'Credentials are currently loaded from environment variables on this server.'); ?>
            </div>
        <?php endif; ?>

        <p class="help-block">
            <?= Yii::t('HumhubS3Module.base', 'Database fallback (optional when environment variables are configured):'); ?>
        </p>

        <?= $form->field($model, 'accessKey')->textInput(); ?>
        <?= $form->field($model, 'secretKeyField')->passwordInput([
            'autocomplete' => 'new-password',
            'placeholder' => $model->hasStoredSecretKey()
                ? Yii::t('HumhubS3Module.base', 'Leave blank to keep existing key')
                : '',
        ]); ?>
        <?= $form->field($model, 'prefix')->textInput(); ?>
        <?= $form->field($model, 'mediaProxyPath')->textInput([
            'placeholder' => 's3media',
        ]); ?>
        <?= $form->field($model, 'endpoint')->textInput(); ?>
        <?= $form->field($model, 'usePathStyle')->checkbox(); ?>

        <div class="form-group">
            <?= Html::submitButton(Yii::t('HumhubS3Module.base', 'Save'), [
                'class' => 'btn btn-primary',
                'name' => 'saveSettings',
                'value' => '1',
            ]); ?>
            <?= Html::submitButton(Yii::t('HumhubS3Module.base', 'Test Connection'), [
                'class' => 'btn btn-info',
                'name' => 'testConnection',
                'value' => '1',
            ]); ?>
        </div>

        <p class="help-block">
            <?= Yii::t('HumhubS3Module.base', 'Use "Test Connection" to verify the current form values by uploading, downloading, and deleting a temporary file in the bucket.'); ?>
        </p>

        <?php ActiveForm::end(); ?>
    </div>
</div>

<div class="panel panel-default">
    <div class="panel-heading">
        <?= Yii::t('HumhubS3Module.base', '<strong>Local Runtime Cache</strong>'); ?>
    </div>
    <div class="panel-body">
        <p class="help-block">
            <?= Yii::t(
                'HumhubS3Module.base',
                'This module keeps temporary local copies of files from S3 in <code>protected/runtime/humhub-s3</code> for image processing and streaming. S3 remains the durable store. Clearing this cache does not delete anything from your bucket.'
            ); ?>
        </p>

        <h4><?= Yii::t('HumhubS3Module.base', 'When to clear it'); ?></h4>
        <p class="help-block">
            <?= Yii::t(
                'HumhubS3Module.base',
                'Clear the local store when this server may be serving outdated cached files. This is common when multiple ephemeral instances share the same S3 bucket. For example, after branding assets (logo, favicon, login background), profile pictures, or banners are uploaded on a different server or container. Emptying the cache forces this instance to re-download the latest files from S3 on the next request.'
            ); ?>
        </p>

        <?php if ($localStoreStats['fileCount'] > 0): ?>
            <p class="help-block">
                <?= Yii::t(
                    'HumhubS3Module.base',
                    'Cached files on this server: {count} ({size})',
                    [
                        'count' => $localStoreStats['fileCount'],
                        'size' => LocalRuntimeStore::formatSize($localStoreStats['sizeBytes']),
                    ]
                ); ?>
            </p>
        <?php else: ?>
            <p class="help-block">
                <?= Yii::t('HumhubS3Module.base', 'No cached files on this server.'); ?>
            </p>
        <?php endif; ?>

        <?php $cacheForm = ActiveForm::begin(['id' => 'humhub-s3-cache-form']); ?>

        <div class="form-group">
            <?= Html::submitButton(Yii::t('HumhubS3Module.base', 'Empty Local Store'), [
                'class' => 'btn btn-danger',
                'name' => 'clearLocalStore',
                'value' => '1',
                'onclick' => 'return confirm(' . json_encode(
                    Yii::t(
                        'HumhubS3Module.base',
                        'Delete all locally cached files in protected/runtime/humhub-s3 on this server? Files in S3 are not affected.'
                    ),
                    JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
                ) . ');',
            ]); ?>
        </div>

        <?php ActiveForm::end(); ?>
    </div>
</div>
