<?php

use humhub\modules\humhubs3\components\BucketPolicyTemplate;
use humhub\modules\humhubs3\models\forms\ConfigureForm;
use humhub\modules\ui\form\widgets\ActiveForm;
use yii\helpers\Html;
use yii\web\View;

/**
 * @var View $this
 * @var ConfigureForm $model
 * @var string $bucketPolicyJson
 * @var bool $bucketPolicyNeedsPlaceholderReview
 */
?>

<p class="help-block">
    <?= Yii::t(
        'HumhubS3Module.base',
        'Apply this bucket policy in the AWS S3 console under Bucket, then Permissions, then Bucket policy. It allows your HumHub IAM user to manage objects under the configured prefix and allows public read access for branding and profile images only.'
    ); ?>
</p>

<?php if ($bucketPolicyNeedsPlaceholderReview): ?>
    <div class="alert alert-warning">
        <?= Yii::t(
            'HumhubS3Module.base',
            'Replace {bucketPlaceholder} with your bucket name and {iamPlaceholder} with the full IAM user ARN (for example arn:aws:iam::123456789012:user/humhub-media) before applying the policy.',
            [
                'bucketPlaceholder' => BucketPolicyTemplate::PLACEHOLDER_BUCKET,
                'iamPlaceholder' => BucketPolicyTemplate::PLACEHOLDER_IAM_USER_ARN,
            ]
        ); ?>
    </div>
<?php endif; ?>

<p class="help-block">
    <?= Yii::t(
        'HumhubS3Module.base',
        'Remove any bucket policy statements that explicitly deny s3:GetObject for your HumHub IAM user. A Deny statement always wins over Allow and will break uploads and downloads.'
    ); ?>
</p>

<p class="help-block">
    <?= Yii::t(
        'HumhubS3Module.base',
        'You may also need to adjust S3 Block Public Access so scoped public read on branding and profile images is allowed.'
    ); ?>
</p>

<div class="form-group">
    <label for="humhub-s3-bucket-policy">
        <?= Yii::t('HumhubS3Module.base', 'Bucket policy JSON'); ?>
    </label>
    <textarea
        id="humhub-s3-bucket-policy"
        class="form-control"
        rows="16"
        readonly
        spellcheck="false"
    ><?= Html::encode($bucketPolicyJson) ?></textarea>
</div>

<div class="form-group">
    <?= Html::button(Yii::t('HumhubS3Module.base', 'Copy bucket policy'), [
        'type' => 'button',
        'class' => 'btn btn-default',
        'id' => 'humhub-s3-copy-bucket-policy',
    ]); ?>
    <span
        id="humhub-s3-copy-bucket-policy-status"
        class="help-block"
        style="display: none; margin-top: 8px;"
        aria-live="polite"
    ></span>
</div>

<?php $form = ActiveForm::begin(['id' => 'humhub-s3-bucket-policy-form']); ?>

<div class="form-group">
    <?= Html::submitButton(Yii::t('HumhubS3Module.base', 'Test Bucket Policy'), [
        'class' => 'btn btn-info',
        'name' => 'testBucketPolicy',
        'value' => '1',
    ]); ?>
</div>

<p class="help-block">
    <?= Yii::t(
        'HumhubS3Module.base',
        'Test Bucket Policy uploads temporary branding and private test objects, then checks that branding is publicly readable and other objects stay private.'
    ); ?>
</p>

<?php ActiveForm::end(); ?>

<?php
$copySuccessMessage = Yii::t('HumhubS3Module.base', 'Bucket policy copied to clipboard.');
$copyErrorMessage = Yii::t('HumhubS3Module.base', 'Could not copy automatically. Select the policy text and copy it manually.');
$copySuccessJson = json_encode($copySuccessMessage, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
$copyErrorJson = json_encode($copyErrorMessage, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
$this->registerJs(<<<JS
(function () {
    var button = document.getElementById('humhub-s3-copy-bucket-policy');
    var textarea = document.getElementById('humhub-s3-bucket-policy');
    var status = document.getElementById('humhub-s3-copy-bucket-policy-status');
    if (!button || !textarea || !status) {
        return;
    }

    function showStatus(message) {
        status.textContent = message;
        status.style.display = 'block';
    }

    button.addEventListener('click', function () {
        textarea.focus();
        textarea.select();

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(textarea.value).then(function () {
                showStatus({$copySuccessJson});
            }).catch(function () {
                showStatus({$copyErrorJson});
            });
            return;
        }

        try {
            if (document.execCommand('copy')) {
                showStatus({$copySuccessJson});
            } else {
                showStatus({$copyErrorJson});
            }
        } catch (e) {
            showStatus({$copyErrorJson});
        }
    });
})();
JS);
?>
