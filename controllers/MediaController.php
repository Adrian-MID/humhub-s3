<?php

namespace humhub\modules\humhubs3\controllers;

use humhub\components\access\ControllerAccess;
use humhub\components\Controller;
use humhub\modules\file\libs\FileHelper;
use humhub\modules\humhubs3\components\S3MediaStorage;
use humhub\modules\humhubs3\models\forms\ConfigureForm;
use Yii;
use yii\web\NotFoundHttpException;

/**
 * Streams S3-backed media (profile images, branding) through HumHub instead of webroot uploads.
 */
class MediaController extends Controller
{
    /**
     * Public media proxy — same guest policy as legacy /uploads/ URLs and PWA assets.
     *
     * Uses ControllerAccess (not StrictAccess) so guests may fetch profile/branding
     * assets without enabling global guest mode. File module uploads stay private.
     *
     * @var class-string<ControllerAccess>
     */
    public $access = ControllerAccess::class;

    /**
     * @inheritdoc
     * @return array<int, array<string, mixed>>
     */
    protected function getAccessRules(): array
    {
        return [];
    }

    /**
     * Serves profile images, banner images, or branding assets from S3.
     *
     * @return \yii\web\Response
     */
    public function actionServe(
        ?string $type = null,
        ?string $guid = null,
        string $variant = '',
        ?string $path = null
    ) {
        if (!ConfigureForm::isActive())
        {
            throw new NotFoundHttpException();
        }

        if ($path !== null && $path !== '')
        {
            return $this->streamBrandingAsset($path);
        }

        if ($type === null || $guid === null)
        {
            throw new NotFoundHttpException();
        }

        return match ($type)
        {
            'profile' => $this->streamProfileAsset('profile_image', $guid, $variant),
            'banner' => $this->streamProfileAsset('profile_image/banner', $guid, $variant),
            default => throw new NotFoundHttpException(),
        };
    }

    /**
     * @return \yii\web\Response
     */
    private function streamProfileAsset(string $folder, string $guid, string $variant): \yii\web\Response
    {
        if (!preg_match('/^[a-f0-9-]{36}$/i', $guid))
        {
            throw new NotFoundHttpException();
        }

        if (!preg_match('/^(_org|_cropped)?$/', $variant))
        {
            throw new NotFoundHttpException();
        }

        $relativePath = $folder . '/' . $guid . $variant . '.jpg';
        if (!S3MediaStorage::has($relativePath))
        {
            throw new NotFoundHttpException();
        }

        return $this->sendLocalFile(S3MediaStorage::resolveLocalPath($relativePath), $guid . $variant . '.jpg');
    }

    /**
     * @return \yii\web\Response
     */
    private function streamBrandingAsset(string $path): \yii\web\Response
    {
        $path = str_replace('\\', '/', $path);
        if ($path === '' || str_contains($path, '..') || !preg_match('#^branding/[a-zA-Z0-9._/-]+$#', $path))
        {
            throw new NotFoundHttpException();
        }

        if (!S3MediaStorage::has($path))
        {
            throw new NotFoundHttpException();
        }

        return $this->sendLocalFile(S3MediaStorage::resolveLocalPath($path), basename($path));
    }

    /**
     * @return \yii\web\Response
     */
    private function sendLocalFile(string $localPath, string $downloadName): \yii\web\Response
    {
        if (!is_file($localPath))
        {
            throw new NotFoundHttpException();
        }

        $mimeType = FileHelper::getMimeType($localPath) ?: 'application/octet-stream';

        return Yii::$app->response->sendFile($localPath, $downloadName, [
            'inline' => true,
            'mimeType' => $mimeType,
        ]);
    }
}
