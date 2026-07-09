<?php

namespace humhub\modules\humhubs3\controllers;

use humhub\modules\admin\components\Controller;
use humhub\modules\admin\permissions\ManageSettings;
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
        $model = new ConfigureForm();
        $model->loadSettings();

        $post = self::normalizePostData(Yii::$app->request->post());

        if (!empty($post['clearLocalStore']))
        {
            $this->handleClearLocalStore();
        }
        elseif ($model->load($post))
        {
            if (!empty($post['testConnection']))
            {
                $result = $model->testConnection();

                if ($result['success'])
                {
                    $this->view->success($result['message']);
                }
                else
                {
                    $this->view->error($result['message']);
                }
            }
            elseif ($model->save())
            {
                $this->view->saved();
                return $this->redirect(['/humhub-s3/admin/index']);
            }
        }

        return $this->render('index', [
            'model' => $model,
            'isActive' => ConfigureForm::isActive(),
            'localStoreStats' => LocalRuntimeStore::getStats(),
        ]);
    }

    /**
     * @return \yii\web\Response
     */
    public function actionClearLocalStore()
    {
        $this->handleClearLocalStore();

        return $this->redirect(['/humhub-s3/admin/index']);
    }

    private function handleClearLocalStore(): void
    {
        try
        {
            LocalRuntimeStore::clear();
            $this->view->success(Yii::t('HumhubS3Module.base', 'Local runtime cache cleared successfully.'));
        }
        catch (Throwable $exception)
        {
            Yii::error('Unable to clear local runtime store: ' . $exception->getMessage(), 'humhub-s3');
            $this->view->error(Yii::t('HumhubS3Module.base', 'Unable to clear the local runtime cache. Check the application log for details.'));
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
