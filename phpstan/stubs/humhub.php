<?php

namespace humhub\components\access;

class ControllerAccess extends \yii\base\BaseObject
{
    public const RULE_LOGGED_IN_ONLY = 'login';

    public function run(): bool
    {
    }
}

namespace humhub\components;

class ModuleEvent extends \yii\base\Event
{
    public string $moduleId = '';

    /** @var Module|null */
    public $module;
}

class Module extends \yii\base\Module
{
    /** @var SettingsManager */
    public $settings;

    public function disable(): void
    {
    }
}

class SettingsManager
{
    /**
     * @param array<string, mixed> $config
     */
    public function __construct(array $config = [])
    {
    }

    public function get(string $name, mixed $default = null): mixed
    {
    }

    public function set(string $name, mixed $value): void
    {
    }

    public function deleteAll(?string $prefix = null): void
    {
    }
}

class Application extends \yii\web\Application
{
    public InstallationState $installationState;

    /** @var SettingsManager */
    public $settings;

    /** @var \yii\web\UrlManager */
    public $urlManager;

    public View $view;

    public function isInstalled(): bool
    {
    }

    public function isDatabaseInstalled(): bool
    {
    }
}

class Theme
{
    public function applyTo(string $path): string
    {
    }
}

class Controller extends \yii\web\Controller
{
    /** @var View|\yii\web\View */
    public $view;

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function getAccessRules()
    {
    }
}

class Widget extends \yii\base\Widget
{
}

class View extends \yii\web\View
{
    public Theme $theme;

    /**
     * @param array<string, mixed> $options
     */
    public function registerLinkTag(array $options): void
    {
    }
}

class InstallationState
{
    public function hasState(int $state): bool
    {
    }
}

namespace humhub\components\console;

class Application extends \yii\console\Application
{
}

namespace humhub\components\bootstrap;

interface BootstrapInterface
{
    public function bootstrap($app): void;
}

class ModuleAutoLoader implements BootstrapInterface
{
    public function bootstrap($app): void
    {
    }
}

namespace humhub\helpers;

class ControllerHelper
{
    public static function isActivePath(string $moduleId, string $controllerId): bool
    {
    }
}

namespace humhub\modules\file;

class Module extends \humhub\components\Module
{
    /** @var class-string */
    public $storageManagerClass;
}

namespace humhub\modules\file\components;

class StorageManager extends \yii\base\Component
{
    public string $originalFileName = 'file';

    public int $fileMode = 0744;

    /** @var \humhub\modules\file\models\File */
    protected $file;

    public function has($variant = null): bool
    {
    }

    public function get($variant = null): string
    {
    }

    /**
     * @param string[] $except
     * @return string[]
     */
    public function getVariants($except = []): array
    {
    }

    public function set(\yii\web\UploadedFile $file, $variant = null): void
    {
    }

    public function setContent($content, $variant = null): void
    {
    }

    public function setByPath(string $path, $variant = null): void
    {
    }

    /**
     * @param string[] $except
     */
    public function delete($variant = null, $except = []): void
    {
    }

    public function setFile(\humhub\modules\file\models\File $file): void
    {
    }
}

namespace humhub\modules\file\libs;

class FileHelper extends \yii\helpers\FileHelper
{
    public static function getMimeType(string $path): ?string
    {
    }
}

namespace humhub\modules\file\models;

class File extends \yii\db\ActiveRecord
{
    public string $guid = '';
}

namespace humhub\modules\admin\components;

class Controller extends \humhub\components\Controller
{
    public ?string $subLayout = null;

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function getAccessRules(): array
    {
    }

    public function render(string $view, array $params = []): string
    {
    }

    public function redirect($url, $statusCode = 302): \yii\web\Response
    {
    }
}

namespace humhub\libs;

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

    public function delete()
    {
    }

    /**
     * @param int $width
     * @param array<string, mixed> $cfg
     * @return string
     */
    public function render($width = 32, $cfg = [])
    {
    }
}

class ProfileBannerImage extends ProfileImage
{
    /**
     * @param mixed $guid
     */
    public function __construct($guid, $defaultImage = 'default_banner')
    {
    }

    /**
     * @param UploadedFile|string $file
     */
    public function setNew($file)
    {
    }

    /**
     * @param int|string $width
     * @param array<string, mixed> $cfg
     * @return string
     */
    public function render($width = 32, $cfg = [])
    {
    }
}

class LogoImage
{
    public const MIN_WIDTH = 100;
    public const MIN_HEIGHT = 120;
    public const RECOMMENDED_MIN_HEIGHT = 248;

    public static function set(?UploadedFile $file = null)
    {
    }

    /**
     * @return string|null
     */
    public static function getUrl($maxWidth = null, $maxHeight = null, $autoResize = true)
    {
    }

    /**
     * @return bool
     */
    public static function hasImage()
    {
    }
}

namespace humhub\helpers;

class Html
{
    /**
     * @param array<string, mixed> $options
     * @param array<string, mixed> $style
     */
    public static function addCssStyle(array &$options, array $style): void
    {
    }

    /**
     * @param array<string, mixed> $options
     */
    public static function img(string $src, array $options = []): string
    {
    }
}

namespace humhub\modules\file\libs;

class ImageHelper
{
    public static function checkMaxDimensions(string $file): void
    {
    }

    public static function fixJpegOrientation(mixed $image, string $file): void
    {
    }
}

namespace humhub\modules\web\pwa\widgets;

use humhub\components\View;
use yii\web\UploadedFile;

class SiteIcon
{
    public const MIN_WIDTH = 256;
    public const MIN_HEIGHT = 256;
    public const RECOMMENDED_WIDTH = 512;
    public const RECOMMENDED_HEIGHT = 512;

    public static function set(?UploadedFile $file = null)
    {
    }

    /**
     * @return string|null
     */
    public static function getUrl($size, $autoResize = true)
    {
    }

    /**
     * @return bool
     */
    public static function hasImage()
    {
    }

    public static function registerMetaTags(View $view)
    {
    }
}

namespace humhub\modules\user\helpers;

final class LoginBackgroundImageHelper
{
    public const MIN_WIDTH = 800;
    public const MIN_HEIGHT = 600;
    public const RECOMMENDED_WIDTH = 1920;
    public const RECOMMENDED_HEIGHT = 1080;

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
}

namespace humhub\widgets\mails;

use yii\base\Widget;

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

namespace humhub\modules\admin\permissions;

class ManageSettings
{
}

namespace humhub\modules\admin\widgets;

class SettingsMenu extends \yii\base\Widget
{
    public function addEntry(\humhub\modules\ui\menu\MenuLink $entry): void
    {
    }
}

namespace humhub\modules\ui\menu;

class MenuLink
{
    /**
     * @param array<string, mixed> $config
     */
    public function __construct(array $config = [])
    {
    }
}

namespace humhub\modules\ui\form\widgets;

class ActiveForm extends \yii\widgets\ActiveForm
{
}

namespace humhub\modules\ui\view\components;

class View extends \yii\web\View
{
    public function success(string $message): void
    {
    }

    public function error(string $message): void
    {
    }

    public function saved(): void
    {
    }
}
