<?php

namespace yii\base;

class Event extends BaseObject
{
    /** @var object|null */
    public $sender;
}

class BaseObject
{
    /**
     * @param array<string, mixed> $config
     */
    public function __construct(array $config = [])
    {
    }
}

class Component extends BaseObject
{
}

class Module extends Component
{
    public string $id = '';

    public function init(): void
    {
    }

    /**
     * @param string|bool $id
     */
    public function getModule($id, $throwException = true): ?\yii\base\Module
    {
    }

    public function getBasePath(): string
    {
    }

    /**
     * @param array<string, mixed> $config
     */
    public function set(string $id, array $config): void
    {
    }
}

class Model extends Component
{
    /**
     * @return array<int, mixed>
     */
    public function rules(): array
    {
        return [];
    }

    /**
     * @return array<string, string>
     */
    public function attributeLabels(): array
    {
        return [];
    }

    /**
     * @return array<string, string>
     */
    public function attributeHints(): array
    {
        return [];
    }

    public function validate(?array $attributeNames = null): bool
    {
    }

    /**
     * @param array<string, mixed> $data
     * @param string|null $formName
     */
    public function load(array $data, ?string $formName = null): bool
    {
    }

    public function addError(string $attribute, string $error): void
    {
    }

    /**
     * @return array<string, string>
     */
    public function getFirstErrors(): array
    {
    }
}

class Widget extends Component
{
    /**
     * @param array<string, mixed> $config
     * @return static
     */
    public static function begin(array $config = []): static
    {
    }

    public static function end(): void
    {
    }

    /**
     * @param array<string, mixed> $options
     */
    public function field(Model $model, string $attribute, array $options = []): widgets\ActiveField
    {
    }
}

class InvalidConfigException extends \Exception
{
}

class Exception extends \Exception
{
}

namespace yii\web;

class AssetManager extends \yii\base\Component
{
    public string $baseUrl = '';
    public string $basePath = '';
}

class Application extends \yii\base\Module
{
    public Request $request;

    public User $user;

    public Response $response;

    public string $name = '';

    /** @var \yii\web\AssetManager */
    public $assetManager;

    /** @var UrlManager */
    public $urlManager;

    /** @var array<string, mixed> */
    public array $params = [];

    public function isInstalled(): bool
    {
    }

    public function isDatabaseInstalled(): bool
    {
    }
}

class UrlManager extends \yii\base\Component
{
    /** @var array<int, UrlRuleInterface> */
    public array $rules = [];

    /**
     * @param array<int, array<string, mixed>> $rules
     */
    public function addRules(array $rules, bool $append = true): void
    {
    }

    /**
     * @return array{0: string, 1: array<string, mixed>}
     */
    public function parseRequest(Request $request): array
    {
    }
}

interface UrlRuleInterface
{
    /**
     * @param array<string, mixed> $params
     */
    public function createUrl($manager, $route, $params);

    /**
     * @return array{0: string, 1: array<string, mixed>}|false
     */
    public function parseRequest($manager, $request);
}

class Controller extends \yii\base\Component
{
    /** @var View */
    public $view;

    public function init(): void
    {
    }

    public function render(string $view, array $params = []): string
    {
    }

    /**
     * @param string|array<int, string> $url
     */
    public function redirect($url, int $statusCode = 302): Response
    {
    }
}

class Response
{
    /**
     * @param array<string, mixed> $options
     */
    public function sendFile(string $filePath, string $attachmentName = '', array $options = []): self
    {
    }
}

class Request
{
    public function setPathInfo(string $pathInfo): void
    {
    }

    public function setUrl(string $url): void
    {
    }

    /**
     * @param string|null $name
     * @param mixed $defaultValue
     * @return ($name is null ? array<string, mixed> : mixed)
     */
    public function post(?string $name = null, mixed $defaultValue = null)
    {
    }
}

class User extends \yii\base\Component
{
    public function can(string $permission): bool
    {
    }
}

class UploadedFile extends \yii\base\BaseObject
{
    public string $tempName = '';
}

class View extends \yii\base\Component
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

class HttpException extends \Exception
{
}

class NotFoundHttpException extends HttpException
{
}

namespace yii\console;

class Application extends \yii\base\Module
{
}

namespace yii\db;

class ActiveRecord extends \yii\base\BaseObject
{
}

namespace yii\helpers;

class Url
{
    /**
     * @param string|array<int|string, mixed> $url
     * @param bool|string $scheme
     */
    public static function to($url, $scheme = false): string
    {
    }
}

class ArrayHelper
{
    /**
     * @param array<int|string, mixed> $array
     * @param array<int|string, mixed> $merge
     * @return array<int|string, mixed>
     */
    public static function merge(array $array, array $merge): array
    {
    }
}

class Html
{
    /**
     * @param array<string, mixed> $options
     */
    public static function submitButton(string $content, array $options = []): string
    {
    }
}

class FileHelper
{
    public static function createDirectory(string $path, int $mode = 0775, bool $recursive = true): bool
    {
    }

    public static function removeDirectory(string $path): bool
    {
    }

    public static function unlink(string $path): bool
    {
    }
}

namespace yii\widgets;

class ActiveForm extends \yii\base\Widget
{
}

class ActiveField
{
    /**
     * @param array<string, mixed> $options
     */
    public function textInput(array $options = []): self
    {
    }

    /**
     * @param array<string, mixed> $options
     */
    public function passwordInput(array $options = []): self
    {
    }

    public function checkbox(): self
    {
    }
}
