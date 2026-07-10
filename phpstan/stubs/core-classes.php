<?php

/**
 * Stubs for HumHub core classes loaded at runtime into the core namespace by CoreClassLoader.
 */

namespace humhub\modules\humhubs3\libs\core;

use humhub\components\View;
use humhub\components\Widget;
use yii\web\UploadedFile;

class ProfileImage
{
    /** @var string */
    protected $guid = '';

    /** @var int */
    protected $width = 150;

    /** @var int */
    protected $height = 150;

    /** @var string */
    protected $folder_images = 'profile_image';

    /** @var string */
    protected $defaultImage = 'default_user';

    /**
     * @param mixed $guid
     */
    public function __construct($guid, $defaultImage = 'default_user')
    {
    }

    /**
     * @param string $prefix
     * @param bool $scheme
     * @return string
     */
    public function getUrl($prefix = '', $scheme = false)
    {
    }

    /**
     * @return bool
     */
    public function hasImage()
    {
    }

    /**
     * @param string $prefix
     * @return string
     */
    public function getPath($prefix = '')
    {
    }

    /**
     * @param int $x
     * @param int $y
     * @param int $h
     * @param int $w
     * @return bool
     */
    public function cropOriginal($x, $y, $h, $w)
    {
    }

    /**
     * @param UploadedFile|string $file
     */
    public function setNew($file)
    {
    }

    public function delete(): void
    {
    }
}

class SiteIcon extends Widget
{
    public const MIN_WIDTH = 256;
    public const MIN_HEIGHT = 256;
    public const RECOMMENDED_WIDTH = 512;
    public const RECOMMENDED_HEIGHT = 512;

    public static function set(?UploadedFile $file = null): void
    {
    }

    /**
     * @return string|null
     */
    public static function getUrl($size, $autoResize = true)
    {
    }

    public static function hasImage(): bool
    {
    }

    public static function registerMetaTags(View $view): void
    {
    }
}

class MailHeaderImage extends Widget
{
    public const MIN_WIDTH = 50;
    public const MIN_HEIGHT = 50;
    public const RECOMMENDED_WIDTH = 600;
    public const RECOMMENDED_HEIGHT = 150;
    public const MAX_WIDTH = 600;
    public const MAX_HEIGHT = 300;
    public const LOGO_MAX_HEIGHT = 60;

    public int $verticalMargin = 10;
    public ?string $backgroundColor = null;

    public static function set(?string $fileName): void
    {
    }

    /**
     * @return string|null
     */
    public static function getUrl(): ?string
    {
    }

    public static function hasImage(): bool
    {
    }

    /**
     * @return string
     */
    public function run()
    {
    }

    /**
     * @param array<string, mixed> $params
     * @return string
     */
    public function render(string $view, array $params = []): string
    {
    }
}

class File extends \yii\db\ActiveRecord
{
    public string $guid = '';

    public string $file_name = '';

    public string $object_model = '';

    public int $created_by = 0;

    /**
     * @param \humhub\modules\user\models\User|null $user
     */
    public function canView($user = null): bool
    {
    }

    public function getStore(): \humhub\modules\file\components\StorageManagerInterface
    {
    }

    /**
     * @param array<string, mixed>|string $params
     * @param bool $absolute
     */
    public function getUrl($params = [], $absolute = true): string
    {
    }
}

class RichTextToEmailHtmlConverter
{
    protected function tokenizeBlock(\humhub\modules\content\widgets\richtext\extensions\link\LinkParserBlock $linkBlock): \humhub\modules\content\widgets\richtext\extensions\link\LinkParserBlock
    {
    }
}

class FileExtension
{
    public static function replaceLinkExtension(?string $text, ?string $extension, callable $callback): string
    {
    }

    public function onBeforeOutput(\humhub\modules\content\widgets\richtext\ProsemirrorRichText $richtext, string $output): string
    {
    }
}
