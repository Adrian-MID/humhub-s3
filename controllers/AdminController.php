<?php

namespace humhub\modules\humhubs3\controllers;

use humhub\modules\admin\components\Controller;
use humhub\modules\admin\permissions\ManageSettings;
use humhub\modules\humhubs3\components\BucketPolicyTemplate;
use humhub\modules\humhubs3\components\LocalRuntimeStore;
use humhub\modules\humhubs3\models\forms\ConfigureForm;
use Throwable;
use Yii;

class AdminController extends Controller
{
    /**
     * @inheritdoc
     * @return array<int, array<string, mixed>>
     */
    protected function getAccessRules(): array
    {
        return [
            ['permission' => ManageSettings::class],
        ];
    }

    /**
     * @inheritdoc
     */
    public function init(): void
    {
        $this->subLayout = '@admin/views/layouts/setting';
        parent::init();
    }

    /**
     * @return string|\yii\web\Response
     */
    public function actionIndex()
    {
        $model = $this->loadConfigureForm();
        $post = self::normalizePostData(Yii::$app->request->post());

        if ($model->load($post))
        {
            if (!empty($post['testConnection']))
            {
                $this->flashTestResult($model->testConnection());
            }
            elseif ($model->save())
            {
                $this->view->saved();

                return $this->redirect(['index']);
            }
        }

        return $this->renderTab('general', [
            'model' => $model,
        ]);
    }

    /**
     * @return string|\yii\web\Response
     */
    public function actionBucketPolicy()
    {
        $model = $this->loadConfigureForm();
        $post = self::normalizePostData(Yii::$app->request->post());

        if (!empty($post['testBucketPolicy']))
        {
            $this->flashTestResult($model->testBucketPolicy());
        }

        return $this->renderTab('bucket-policy', [
            'model' => $model,
            'bucketPolicyJson' => BucketPolicyTemplate::toJson($model->bucket, $model->prefix),
            'bucketPolicyNeedsPlaceholderReview' => BucketPolicyTemplate::needsPlaceholderReview($model->bucket),
        ]);
    }

    /**
     * @return string|\yii\web\Response
     */
    public function actionProcessingCache()
    {
        $post = self::normalizePostData(Yii::$app->request->post());

        if (!empty($post['clearLocalStore']))
        {
            $this->handleClearLocalStore();

            return $this->redirect(['processing-cache']);
        }

        return $this->renderTab('processing-cache', [
            'localStoreStats' => LocalRuntimeStore::getStats(),
        ]);
    }

    /**
     * @return \yii\web\Response
     */
    public function actionClearLocalStore()
    {
        $this->handleClearLocalStore();

        return $this->redirect(['processing-cache']);
    }

    /**
     * @param array<string, mixed> $tabParams
     */
    private function renderTab(string $partial, array $tabParams = []): string
    {
        return $this->render('tabs', [
            'tab' => Yii::$app->view->render('@humhub-s3/views/admin/' . $partial, $tabParams),
            'isActive' => ConfigureForm::isActive(),
        ]);
    }

    private function loadConfigureForm(): ConfigureForm
    {
        $model = new ConfigureForm();
        $model->loadSettings();

        return $model;
    }

    /**
     * @param array{success: bool, message: string} $result
     */
    private function flashTestResult(array $result): void
    {
        if ($result['success'])
        {
            $this->view->success($result['message']);
        }
        else
        {
            $this->view->error($result['message']);
        }
    }

    private function handleClearLocalStore(): void
    {
        try
        {
            LocalRuntimeStore::clear();
            $this->view->success(Yii::t('HumhubS3Module.base', 'Processing cache cleared successfully.'));
        }
        catch (Throwable $exception)
        {
            Yii::error('Unable to clear local runtime store: ' . $exception->getMessage(), 'humhub-s3');
            $this->view->error(Yii::t('HumhubS3Module.base', 'Unable to clear the processing cache. Check the application log for details.'));
        }
    }

    /**
     * @param array<mixed, mixed> $post
     * @return array<string, mixed>
     */
    private static function normalizePostData(array $post): array
    {
        $normalized = [];
        foreach ($post as $key => $value)
        {
            if (is_string($key))
            {
                $normalized[$key] = $value;
            }
        }

        return $normalized;
    }
}
