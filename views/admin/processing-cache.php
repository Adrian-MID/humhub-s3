<?php

use humhub\modules\humhubs3\components\LocalRuntimeStore;
use humhub\modules\ui\form\widgets\ActiveForm;
use yii\helpers\Html;
use yii\web\View;

/**
 * @var View $this
 * @var array{fileCount: int, sizeBytes: int} $localStoreStats
 */
?>

<p class="help-block">
    <?= Yii::t(
        'HumhubS3Module.base',
        'HumHub keeps a short-lived processing folder at <code>protected/runtime/humhub-s3</code> while uploads and image conversions run. Normal requests clean up after themselves, so this folder should usually be empty.'
    ); ?>
</p>

<p class="help-block">
    <?= Yii::t(
        'HumhubS3Module.base',
        'S3 remains the durable store. Visitors always receive S3 URLs. Clearing this folder does not delete anything from your bucket and does not change what people see on the site.'
    ); ?>
</p>

<h4><?= Yii::t('HumhubS3Module.base', 'When to clear it'); ?></h4>

<p class="help-block">
    <?= Yii::t(
        'HumhubS3Module.base',
        'Clear the processing cache only if this server ran out of disk space or a request was interrupted and left files behind. That is uncommon on a healthy server.'
    ); ?>
</p>

<?php if ($localStoreStats['fileCount'] > 0): ?>
    <div class="alert alert-warning">
        <?= Yii::t(
            'HumhubS3Module.base',
            'This server currently has {count} processing file(s) using {size}. You can clear them if they look stale.',
            [
                'count' => $localStoreStats['fileCount'],
                'size' => LocalRuntimeStore::formatSize($localStoreStats['sizeBytes']),
            ]
        ); ?>
    </div>
<?php else: ?>
    <div class="alert alert-success">
        <?= Yii::t('HumhubS3Module.base', 'The processing cache is empty. No action is needed.'); ?>
    </div>
<?php endif; ?>

<?php $cacheForm = ActiveForm::begin(['id' => 'humhub-s3-cache-form']); ?>

<div class="form-group">
    <?= Html::submitButton(Yii::t('HumhubS3Module.base', 'Empty Processing Cache'), [
        'class' => 'btn btn-danger',
        'name' => 'clearLocalStore',
        'value' => '1',
        'onclick' => 'return confirm(' . json_encode(
            Yii::t(
                'HumhubS3Module.base',
                'Delete all temporary processing files in protected/runtime/humhub-s3 on this server? Files in S3 are not affected.'
            ),
            JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
        ) . ');',
    ]); ?>
</div>

<?php ActiveForm::end(); ?>
