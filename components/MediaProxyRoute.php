<?php

namespace humhub\modules\humhubs3\components;

use humhub\modules\humhubs3\models\forms\ConfigureForm;
use Yii;
use yii\helpers\Url;
use yii\web\Request;

/**
 * Resolves and registers the public media proxy URL path.
 */
class MediaProxyRoute
{
    public const DEFAULT_PATH = 'humhub-s3/media/serve';

    public const CONTROLLER_ROUTE = 'humhub-s3/media/serve';

    private const MIN_LENGTH = 2;

    private const MAX_LENGTH = 64;

    /**
     * Single-segment paths that must not be used for the media proxy.
     *
     * @var list<string>
     */
    private const RESERVED_SEGMENTS = [
        'admin',
        'api',
        'assets',
        'auth',
        'captcha',
        'content',
        'dashboard',
        'debug',
        'error',
        'file',
        'home',
        'humhub',
        'humhubs3',
        'index',
        'installer',
        'legal',
        'live',
        'login',
        'mail',
        'marketplace',
        'media',
        'notification',
        'oembed',
        'post',
        'public',
        'r',
        's',
        'search',
        'serve',
        'space',
        'static',
        'stream',
        'uploads',
        'user',
        'wiki',
    ];

    private static bool $urlRuleRegistered = false;

    public static function getPath(): string
    {
        $configured = ConfigureForm::getSettings()['mediaProxyPath'];
        if ($configured === '')
        {
            return self::DEFAULT_PATH;
        }

        $normalized = self::normalizeCustomSegment($configured);
        if (!self::isAlphanumericSegment($normalized) || self::isReservedSegment($normalized))
        {
            return self::DEFAULT_PATH;
        }

        return $normalized;
    }

    public static function normalizeCustomSegment(string $path): string
    {
        return strtolower(trim($path));
    }

    public static function isValid(string $path): bool
    {
        return self::getValidationError($path) === null;
    }

    public static function getValidationError(string $path): ?string
    {
        if ($path === '')
        {
            return null;
        }

        $normalized = self::normalizeCustomSegment($path);

        if (!self::isAlphanumericSegment($normalized))
        {
            return Yii::t(
                'HumhubS3Module.base',
                'Media proxy path must contain only letters and numbers (no slashes or special characters).'
            );
        }

        if (self::isReservedSegment($normalized))
        {
            return Yii::t(
                'HumhubS3Module.base',
                'Media proxy path "{path}" is reserved and cannot be used.',
                ['path' => $normalized]
            );
        }

        if (self::isPathInUse($normalized))
        {
            return Yii::t(
                'HumhubS3Module.base',
                'Media proxy path "{path}" is already used by HumHub.',
                ['path' => $normalized]
            );
        }

        return null;
    }

    public static function isAlphanumericSegment(string $path): bool
    {
        if ($path === '')
        {
            return true;
        }

        $length = strlen($path);

        return $length >= self::MIN_LENGTH
            && $length <= self::MAX_LENGTH
            && (bool) preg_match('/^[a-zA-Z0-9]+$/', $path);
    }

    public static function isReservedSegment(string $path): bool
    {
        return in_array(strtolower($path), self::RESERVED_SEGMENTS, true);
    }

    public static function isPathInUse(string $path): bool
    {
        if (self::isEnabledModuleId($path))
        {
            return true;
        }

        $request = new Request();
        $request->setPathInfo($path);
        $request->setUrl('/' . $path);

        foreach (Yii::$app->urlManager->rules as $rule)
        {
            if ($rule->parseRequest(Yii::$app->urlManager, $request) !== false)
            {
                return true;
            }
        }

        return false;
    }

    private static function isEnabledModuleId(string $path): bool
    {
        return Yii::$app->getModule($path, false) !== null;
    }

    public static function registerUrlRule(): void
    {
        if (self::$urlRuleRegistered)
        {
            return;
        }

        $path = self::getPath();
        if ($path === self::DEFAULT_PATH)
        {
            self::$urlRuleRegistered = true;

            return;
        }

        Yii::$app->urlManager->addRules([
            [
                'pattern' => $path,
                'route' => self::CONTROLLER_ROUTE,
            ],
        ], true);

        self::$urlRuleRegistered = true;
    }

    /**
     * @param array<string, scalar|null> $params
     */
    public static function buildUrl(array $params, bool $scheme = false): string
    {
        return Url::to(array_merge(['/' . self::getPath()], $params), $scheme);
    }
}
