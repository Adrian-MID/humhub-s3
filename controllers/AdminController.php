<?php

namespace humhub\modules\humhubs3\controllers;

use humhub\modules\admin\components\Controller;
use humhub\modules\admin\permissions\ManageSettings;
use humhub\modules\humhubs3\models\forms\ConfigureForm;
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
        if ($model->load($post))
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
        ]);
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
