<?php

namespace humhub\libs;

use humhub\helpers\Html;
use humhub\modules\file\libs\ImageHelper;
use humhub\modules\humhubs3\components\S3MediaStorage;
use Imagine\Image\Box;
use Imagine\Image\ManipulatorInterface;
use yii\base\Exception;
use yii\imagine\Image;
use yii\web\UploadedFile;

/**
 * Profile banner images stored in S3 with a local runtime cache for processing.
 */
class ProfileBannerImage extends ProfileImage
{
    /**
     * @var int
     */
    protected $width = 1134;

    /**
     * @var int
     */
    protected $height = 192;

    /**
     * @var string
     */
    protected $folder_images = 'profile_image/banner';

    /**
     * @param mixed $guid
     * @param string $defaultImage
     */
    public function __construct($guid, $defaultImage = 'default_banner')
    {
        parent::__construct($guid, $defaultImage);
    }

    /**
     * @param mixed $file
     * @inheritdoc
     */
    public function setNew($file): bool
    {
        if ($file instanceof UploadedFile)
        {
            $path = $file->tempName;
        }
        elseif (is_string($file))
        {
            $path = $file;
        }
        else
        {
            throw new Exception('Invalid profile banner upload.');
        }

        $this->delete();

        $image = Image::getImagine()->open($path);
        ImageHelper::fixJpegOrientation($image, $path);
        if ($image->getSize()->getWidth() > 2000)
        {
            $image->resize($image->getSize()->widen(2000));
        }
        $image->save($this->getPath('_org'), ['format' => 'jpg']);

        $image->thumbnail(new Box($this->width, $this->height), ManipulatorInterface::THUMBNAIL_OUTBOUND)
            ->save($this->getPath(''));

        $this->syncVariant('_org');
        $this->syncVariant('');

        return true;
    }

    /**
     * @param mixed $width
     * @param array<string, mixed> $cfg
     * @inheritdoc
     */
    public function render($width = 32, $cfg = []): string
    {
        if (is_int($width))
        {
            $width .= 'px';
        }

        Html::addCssStyle($cfg, ['width' => $width]);

        return Html::img($this->getUrl(), $cfg);
    }
}
