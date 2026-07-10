<?php

use humhub\modules\humhubs3\models\forms\ConfigureForm;
use humhub\modules\ui\form\widgets\ActiveForm;
use yii\helpers\Html;
use yii\web\View;

/**
 * @var View $this
 * @var ConfigureForm $model
 */
?>

<?php $form = ActiveForm::begin(['id' => 'humhub-s3-settings-form']); ?>

<?= $form->field($model, 'enabled')->checkbox(); ?>
<?= $form->field($model, 'prefix')->textInput(); ?>
<?= $form->field($model, 'presignedUrlTtl')->input('number', [
    'min' => ConfigureForm::MIN_PRESIGNED_URL_TTL,
    'max' => ConfigureForm::MAX_PRESIGNED_URL_TTL,
]); ?>

<hr>

<h4><?= Yii::t('HumhubS3Module.base', 'Connection'); ?></h4>

<?= $form->field($model, 'endpoint')->textInput(); ?>

<div class="form-group field-configureform-usepathstyle">
    <?= $form->field($model, 'usePathStyle')->checkbox(); ?>
    <div class="help-block">
        <p>
            <?= Yii::t(
                'HumhubS3Module.base',
                'S3 URLs can use virtual-hosted style or path-style addressing. Virtual-hosted style puts the bucket name in the hostname, for example mybucket.s3.ap-southeast-2.amazonaws.com. Path-style puts the bucket name in the URL path, for example s3.ap-southeast-2.amazonaws.com/mybucket.'
            ); ?>
        </p>
        <p>
            <?= Yii::t(
                'HumhubS3Module.base',
                'Leave this disabled for Amazon S3. AWS expects virtual-hosted style for standard buckets. Enable path-style only when your provider requires it, such as MinIO or some private S3-compatible endpoints.'
            ); ?>
        </p>
    </div>
</div>

<hr>

<h4><?= Yii::t('HumhubS3Module.base', 'Storage'); ?></h4>

<?= $form->field($model, 'bucket')->textInput(); ?>
<?= $form->field($model, 'region')->textInput(); ?>

<hr>

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
    <?= Yii::t(
        'HumhubS3Module.base',
        'Test Connection uploads, downloads, and deletes a small temporary file in the branding folder of your configured prefix. Use it to confirm credentials and bucket access before enabling storage.'
    ); ?>
</p>

<?php ActiveForm::end(); ?>
