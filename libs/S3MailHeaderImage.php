<?php

namespace humhub\widgets\mails;

use humhub\libs\LogoImage;
use humhub\modules\humhubs3\components\CoreClassLoader;
use humhub\modules\humhubs3\components\S3MediaStorage;
use Yii;
use yii\helpers\FileHelper;
use yii\helpers\Url;
use yii\imagine\Image;

CoreClassLoader::requireCore('humhub\widgets\mails\MailHeaderImage');

class MailHeaderImage extends \humhub\modules\humhubs3\libs\core\MailHeaderImage
{
    private const STORE_PATH = 'branding/mail-header.png';

    /**
     * @inheritdoc
     */
    public static function set(?string $fileName): void
    {
        S3MediaStorage::delete(self::STORE_PATH);

        if ($fileName === null || $fileName === '')
        {
            return;
        }

        $image = Image::getImagine()->open($fileName);
        if ($image->getSize()->getWidth() > self::MAX_WIDTH)
        {
            $image->resize($image->getSize()->widen(self::MAX_WIDTH));
        }
        if ($image->getSize()->getHeight() > self::MAX_HEIGHT)
        {
            $image->resize($image->getSize()->heighten(self::MAX_HEIGHT));
        }

        $processPath = S3MediaStorage::resolveProcessingPath(self::STORE_PATH, false);
        FileHelper::createDirectory(dirname($processPath), 0o755, true);
        $image->save($processPath);
        S3MediaStorage::putFile(self::STORE_PATH, $processPath);
    }

    /**
     * @inheritdoc
     */
    public static function getUrl(): ?string
    {
        if (!static::hasImage())
        {
            return null;
        }

        return S3MediaStorage::getPublicUrl(self::STORE_PATH);
    }

    /**
     * @inheritdoc
     */
    public static function hasImage(): bool
    {
        return S3MediaStorage::has(self::STORE_PATH);
    }

    /**
     * Class-map overrides live under libs/, but mail views remain in HumHub core.
     *
     * @inheritdoc
     */
    public function getViewPath(): string
    {
        return (string) Yii::getAlias('@humhub/widgets/mails/views');
    }

    /**
     * @inheritdoc
     */
    public function run()
    {
        $hasMailHeaderImage = static::hasImage();
        $hasLogoImage = LogoImage::hasImage();
        $showNameInsteadOfLogo = (bool) Yii::$app->settings->get('showNameInsteadOfLogo');

        $imgUrl = null;
        if ($hasMailHeaderImage)
        {
            $imgUrl = static::getUrl();
        }
        elseif ($hasLogoImage && !$showNameInsteadOfLogo)
        {
            $imgUrl = LogoImage::getUrl((int) self::MAX_WIDTH, (int) self::LOGO_MAX_HEIGHT);
        }

        $imgUrl = $imgUrl ? Url::to($imgUrl, true) : null;

        return $this->render('mailHeaderImage', [
            'imgUrl' => $imgUrl,
            'appName' => Yii::$app->name,
            'verticalMargin' => $this->verticalMargin,
            'backgroundColor' => $this->backgroundColor,
        ]);
    }
}
