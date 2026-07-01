<?php

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

    public function isInstalled(): bool
    {
    }

    public function isDatabaseInstalled(): bool
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

namespace humhub\components;

class Controller extends \yii\web\Controller
{
    /** @var \humhub\modules\ui\view\components\View|\yii\web\View */
    public $view;
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
