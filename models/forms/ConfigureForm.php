<?php

namespace humhub\modules\humhubs3\models\forms;

use humhub\components\SettingsManager;
use humhub\modules\humhubs3\components\BucketPolicyChecker;
use humhub\modules\humhubs3\components\EndpointValidator;
use humhub\modules\humhubs3\components\S3Client;
use humhub\modules\humhubs3\Module;
use Yii;
use yii\base\Model;

class ConfigureForm extends Model
{
    public const DEFAULT_PRESIGNED_URL_TTL = 900;

    public const MIN_PRESIGNED_URL_TTL = 60;

    public const MAX_PRESIGNED_URL_TTL = 604800;

    public bool $enabled = false;
    public string $bucket = '';
    public string $region = 'us-east-1';
    public string $accessKey = '';
    public string $accessKeyEnvVar = '';
    public string $secretKeyField = '';
    public string $secretKeyEnvVar = '';
    public string $prefix = 'humhub';
    public string $endpoint = '';
    public int $presignedUrlTtl = self::DEFAULT_PRESIGNED_URL_TTL;
    public bool $usePathStyle = false;

    /**
     * @inheritdoc
     * @return array<int, mixed>
     */
    public function rules(): array
    {
        return [
            [['enabled', 'usePathStyle'], 'boolean'],
            [['presignedUrlTtl'], 'integer', 'min' => self::MIN_PRESIGNED_URL_TTL, 'max' => self::MAX_PRESIGNED_URL_TTL],
            [['bucket', 'region'], 'required'],
            [[
                'bucket',
                'region',
                'accessKey',
                'accessKeyEnvVar',
                'secretKeyField',
                'secretKeyEnvVar',
                'prefix',
                'endpoint',
            ], 'string', 'max' => 255],
            [['prefix', 'endpoint', 'accessKeyEnvVar', 'secretKeyEnvVar'], 'trim'],
            ['bucket', 'validateBucket'],
            ['region', 'validateRegion'],
            ['prefix', 'validatePrefix'],
            ['endpoint', 'validateEndpoint'],
            [['accessKeyEnvVar', 'secretKeyEnvVar'], 'validateEnvVarName'],
            ['accessKey', 'validateAccessKey'],
            ['secretKeyField', 'validateSecretKey'],
        ];
    }

    /**
     * @inheritdoc
     * @return array<string, string>
     */
    public function attributeLabels(): array
    {
        return [
            'enabled' => Yii::t('HumhubS3Module.base', 'Enable HumHub S3'),
            'bucket' => Yii::t('HumhubS3Module.base', 'Bucket Name'),
            'region' => Yii::t('HumhubS3Module.base', 'AWS Region'),
            'accessKey' => Yii::t('HumhubS3Module.base', 'Access Key ID'),
            'accessKeyEnvVar' => Yii::t('HumhubS3Module.base', 'Access Key Environment Variable'),
            'secretKeyField' => Yii::t('HumhubS3Module.base', 'Secret Access Key'),
            'secretKeyEnvVar' => Yii::t('HumhubS3Module.base', 'Secret Key Environment Variable'),
            'prefix' => Yii::t('HumhubS3Module.base', 'Object Prefix'),
            'endpoint' => Yii::t('HumhubS3Module.base', 'Custom Endpoint'),
            'presignedUrlTtl' => Yii::t('HumhubS3Module.base', 'Presigned Download URL TTL (seconds)'),
            'usePathStyle' => Yii::t('HumhubS3Module.base', 'Use path-style URLs'),
        ];
    }

    /**
     * @inheritdoc
     * @return array<string, string>
     */
    public function attributeHints(): array
    {
        return [
            'accessKeyEnvVar' => Yii::t(
                'HumhubS3Module.base',
                'Optional. When set, HumHub reads the access key from this environment variable at runtime. Database value is used as fallback.'
            ),
            'secretKeyEnvVar' => Yii::t(
                'HumhubS3Module.base',
                'Optional. When set, HumHub reads the secret key from this environment variable at runtime. Database value is used as fallback.'
            ),
            'accessKey' => Yii::t(
                'HumhubS3Module.base',
                'Used when no environment variable is configured, the variable is unset, or empty.'
            ),
            'prefix' => Yii::t('HumhubS3Module.base', 'Optional folder prefix inside the bucket, e.g. "humhub".'),
            'endpoint' => Yii::t('HumhubS3Module.base', 'Leave empty for AWS S3. Use for S3-compatible services such as MinIO.'),
            'presignedUrlTtl' => Yii::t(
                'HumhubS3Module.base',
                'How long private file download links remain valid after HumHub authorizes the request. Default: {default} seconds.',
                ['default' => self::DEFAULT_PRESIGNED_URL_TTL]
            ),
            'usePathStyle' => Yii::t(
                'HumhubS3Module.base',
                'See the explanation below. Leave disabled for standard Amazon S3 buckets.'
            ),
            'secretKeyField' => Yii::t('HumhubS3Module.base', 'Leave blank to keep the existing secret access key.'),
        ];
    }

    public function validateBucket(string $attribute): void
    {
        $bucket = $this->bucket;
        if ($bucket === '')
        {
            return;
        }

        if (strlen($bucket) < 3 || strlen($bucket) > 63)
        {
            $this->addError($attribute, Yii::t('HumhubS3Module.base', 'Bucket name must be between 3 and 63 characters.'));
            return;
        }

        if (!preg_match('/^[a-z0-9][a-z0-9.-]*[a-z0-9]$/', $bucket) && !preg_match('/^[a-z0-9]$/', $bucket))
        {
            $this->addError($attribute, Yii::t('HumhubS3Module.base', 'Bucket name may only contain lowercase letters, numbers, dots, and hyphens.'));
            return;
        }

        if (str_contains($bucket, '..') || str_contains($bucket, '.-') || str_contains($bucket, '-.'))
        {
            $this->addError($attribute, Yii::t('HumhubS3Module.base', 'Bucket name contains invalid character sequences.'));
            return;
        }

        if (filter_var($bucket, FILTER_VALIDATE_IP) !== false)
        {
            $this->addError($attribute, Yii::t('HumhubS3Module.base', 'Bucket name must not be formatted as an IP address.'));
        }
    }

    public function validateRegion(string $attribute): void
    {
        $region = $this->region;
        if ($region === '')
        {
            return;
        }

        if (!preg_match('/^[a-z0-9-]{2,64}$/', $region))
        {
            $this->addError($attribute, Yii::t('HumhubS3Module.base', 'Region must contain only lowercase letters, numbers, and hyphens.'));
        }
    }

    public function validatePrefix(string $attribute): void
    {
        $prefix = $this->prefix;
        if ($prefix === '')
        {
            return;
        }

        if (str_contains($prefix, '..'))
        {
            $this->addError($attribute, Yii::t('HumhubS3Module.base', 'Object prefix must not contain "..".'));
            return;
        }

        if (!preg_match('/^[a-zA-Z0-9._\-\/]+$/', $prefix))
        {
            $this->addError($attribute, Yii::t('HumhubS3Module.base', 'Object prefix contains invalid characters.'));
        }
    }

    public function validateEndpoint(string $attribute): void
    {
        $endpoint = $this->endpoint;
        if ($endpoint === '')
        {
            return;
        }

        if (!EndpointValidator::isValid($endpoint))
        {
            $this->addError(
                $attribute,
                Yii::t(
                    'HumhubS3Module.base',
                    'Custom endpoint must be a valid HTTPS URL, or HTTP for localhost only. Private and metadata IP addresses are not allowed.'
                )
            );
        }
    }

    public function validateEnvVarName(string $attribute): void
    {
        $name = match ($attribute)
        {
            'accessKeyEnvVar' => $this->accessKeyEnvVar,
            'secretKeyEnvVar' => $this->secretKeyEnvVar,
            default => '',
        };

        if ($name === '')
        {
            return;
        }

        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name))
        {
            $this->addError(
                $attribute,
                Yii::t(
                    'HumhubS3Module.base',
                    'Environment variable names may only contain letters, numbers, and underscores, and must not start with a number.'
                )
            );
        }
    }

    public function validateAccessKey(string $attribute): void
    {
        if (!$this->enabled)
        {
            return;
        }

        if ($this->resolveAccessKeyForConnection() === '')
        {
            $this->addError(
                $attribute,
                Yii::t(
                    'HumhubS3Module.base',
                    'An access key is required when S3 storage is enabled. Configure an environment variable or store a key in the database.'
                )
            );
        }
    }

    public function validateSecretKey(string $attribute): void
    {
        if (!$this->enabled)
        {
            return;
        }

        if ($this->resolveSecretKeyForConnection() === '')
        {
            $this->addError(
                $attribute,
                Yii::t(
                    'HumhubS3Module.base',
                    'A secret access key is required when S3 storage is enabled. Configure an environment variable or store a key in the database.'
                )
            );
        }
    }

    public function loadSettings(): void
    {
        $settings = self::getSettings();

        $this->enabled = (bool) $settings['enabled'];
        $this->bucket = $settings['bucket'];
        $this->region = $settings['region'];
        $this->accessKey = $settings['accessKey'];
        $this->accessKeyEnvVar = $settings['accessKeyEnvVar'];
        $this->secretKeyField = '';
        $this->secretKeyEnvVar = $settings['secretKeyEnvVar'];
        $this->prefix = $settings['prefix'];
        $this->endpoint = $settings['endpoint'];
        $this->presignedUrlTtl = $settings['presignedUrlTtl'];
        $this->usePathStyle = (bool) $settings['usePathStyle'];
    }

    public function hasStoredSecretKey(): bool
    {
        return self::resolveSecretKey(self::getSettings()) !== '';
    }

    public function isAccessKeyConfiguredViaEnv(): bool
    {
        return $this->accessKeyEnvVar !== '' && self::readEnvValue($this->accessKeyEnvVar) !== '';
    }

    public function isSecretKeyConfiguredViaEnv(): bool
    {
        return $this->secretKeyEnvVar !== '' && self::readEnvValue($this->secretKeyEnvVar) !== '';
    }

    public function save(): bool
    {
        if (!$this->validate())
        {
            return false;
        }

        $settings = self::getSettingsManager();
        $settings->set('enabled', (int) $this->enabled);
        $settings->set('bucket', $this->bucket);
        $settings->set('region', $this->region);
        $settings->set('accessKey', $this->accessKey);
        $settings->set('accessKeyEnvVar', $this->accessKeyEnvVar);
        $settings->set('secretKeyEnvVar', $this->secretKeyEnvVar);
        $settings->set('prefix', $this->prefix);
        $settings->set('endpoint', $this->endpoint);
        $settings->set('presignedUrlTtl', $this->presignedUrlTtl);
        $settings->set('usePathStyle', (int) $this->usePathStyle);

        if ($this->secretKeyField !== '')
        {
            $settings->set('secretKey', $this->secretKeyField);
        }

        Module::applyStorageManager();
        Module::applyClassMaps();
        Module::applyFileControllerMap();

        return true;
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function testBucketPolicy(): array
    {
        return BucketPolicyChecker::verify($this);
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function testConnection(): array
    {
        if (!$this->validateSettingsForConnection())
        {
            return [
                'success' => false,
                'message' => Yii::t('HumhubS3Module.base', 'Please fill in all required S3 settings before testing.'),
            ];
        }

        if (!$this->validate(['bucket', 'region', 'endpoint']))
        {
            return [
                'success' => false,
                'message' => implode(' ', $this->getFirstErrors()),
            ];
        }

        return self::createClientFromForm($this)->testConnection($this->prefix);
    }

    public static function isActive(): bool
    {
        if (!Yii::$app->isInstalled() || !Yii::$app->isDatabaseInstalled())
        {
            return false;
        }

        $settings = self::getSettings();

        return (bool) $settings['enabled']
            && $settings['bucket'] !== ''
            && $settings['region'] !== ''
            && self::resolveAccessKey($settings) !== ''
            && self::resolveSecretKey($settings) !== '';
    }

    /**
     * @return array{
     *     enabled: bool|int|string,
     *     bucket: string,
     *     region: string,
     *     accessKey: string,
     *     accessKeyEnvVar: string,
     *     secretKey: string,
     *     secretKeyEnvVar: string,
     *     prefix: string,
     *     endpoint: string,
     *     presignedUrlTtl: int,
     *     usePathStyle: bool
     * }
     */
    public static function getSettings(): array
    {
        $settings = self::getSettingsManager();

        return [
            'enabled' => self::getStoredEnabledValue($settings),
            'bucket' => self::getStoredString($settings, 'bucket', ''),
            'region' => self::getStoredString($settings, 'region', 'us-east-1'),
            'accessKey' => self::getStoredString($settings, 'accessKey', ''),
            'accessKeyEnvVar' => self::getStoredString($settings, 'accessKeyEnvVar', ''),
            'secretKey' => self::getStoredString($settings, 'secretKey', ''),
            'secretKeyEnvVar' => self::getStoredString($settings, 'secretKeyEnvVar', ''),
            'prefix' => self::getStoredString($settings, 'prefix', 'humhub'),
            'endpoint' => self::getStoredString($settings, 'endpoint', ''),
            'presignedUrlTtl' => self::getStoredInt($settings, 'presignedUrlTtl', self::DEFAULT_PRESIGNED_URL_TTL),
            'usePathStyle' => self::getStoredBool($settings, 'usePathStyle', false),
        ];
    }

    public static function getPresignedUrlTtl(): int
    {
        $ttl = self::getSettings()['presignedUrlTtl'];

        if ($ttl < self::MIN_PRESIGNED_URL_TTL)
        {
            return self::DEFAULT_PRESIGNED_URL_TTL;
        }

        if ($ttl > self::MAX_PRESIGNED_URL_TTL)
        {
            return self::MAX_PRESIGNED_URL_TTL;
        }

        return $ttl;
    }

    private static function getStoredInt(SettingsManager $settings, string $key, int $default): int
    {
        $value = $settings->get($key, $default);

        if (is_int($value))
        {
            return $value;
        }

        if (is_string($value) && ctype_digit($value))
        {
            return (int) $value;
        }

        if (is_scalar($value))
        {
            return (int) $value;
        }

        return $default;
    }

    private static function getStoredString(SettingsManager $settings, string $key, string $default): string
    {
        $value = $settings->get($key, $default);

        if (is_string($value))
        {
            return $value;
        }

        if (is_scalar($value))
        {
            return (string) $value;
        }

        return $default;
    }

    /**
     * @return bool|int|string
     */
    private static function getStoredEnabledValue(SettingsManager $settings): bool|int|string
    {
        $value = $settings->get('enabled', false);

        if (is_bool($value) || is_int($value) || is_string($value))
        {
            return $value;
        }

        return false;
    }

    private static function getStoredBool(SettingsManager $settings, string $key, bool $default): bool
    {
        $value = $settings->get($key, $default);

        if (is_bool($value))
        {
            return $value;
        }

        if (is_int($value) || is_string($value))
        {
            return (bool) $value;
        }

        return $default;
    }

    public static function createClient(): S3Client
    {
        $settings = self::getSettings();

        return self::createClientFromCredentials(
            self::resolveAccessKey($settings),
            self::resolveSecretKey($settings),
            $settings['region'],
            $settings['bucket'],
            $settings['endpoint'],
            (bool) $settings['usePathStyle']
        );
    }

    public static function readEnvValue(string $name): string
    {
        if ($name === '')
        {
            return '';
        }

        $value = getenv($name);
        if (is_string($value) && $value !== '')
        {
            return $value;
        }

        if (isset($_ENV[$name]) && is_string($_ENV[$name]) && $_ENV[$name] !== '')
        {
            return $_ENV[$name];
        }

        if (isset($_SERVER[$name]) && is_string($_SERVER[$name]) && $_SERVER[$name] !== '')
        {
            return $_SERVER[$name];
        }

        return '';
    }

    /**
     * @param array{
     *     accessKey: string,
     *     accessKeyEnvVar: string,
     *     secretKey: string,
     *     secretKeyEnvVar: string
     * } $settings
     */
    public static function resolveAccessKey(array $settings): string
    {
        $fromEnv = self::readEnvValue($settings['accessKeyEnvVar']);
        if ($fromEnv !== '')
        {
            return $fromEnv;
        }

        return $settings['accessKey'];
    }

    /**
     * @param array{
     *     accessKey: string,
     *     accessKeyEnvVar: string,
     *     secretKey: string,
     *     secretKeyEnvVar: string
     * } $settings
     */
    public static function resolveSecretKey(array $settings): string
    {
        $fromEnv = self::readEnvValue($settings['secretKeyEnvVar']);
        if ($fromEnv !== '')
        {
            return $fromEnv;
        }

        return $settings['secretKey'];
    }

    public static function createClientFromForm(self $form): S3Client
    {
        return self::createClientFromCredentials(
            $form->resolveAccessKeyForConnection(),
            $form->resolveSecretKeyForConnection(),
            $form->region,
            $form->bucket,
            $form->endpoint,
            (bool) $form->usePathStyle
        );
    }

    private static function createClientFromCredentials(
        string $accessKey,
        string $secretKey,
        string $region,
        string $bucket,
        string $endpoint,
        bool $usePathStyle
    ): S3Client {
        return new S3Client(
            $accessKey,
            $secretKey,
            $region,
            $bucket,
            $endpoint !== '' ? $endpoint : null,
            $usePathStyle
        );
    }

    private function resolveAccessKeyForConnection(): string
    {
        $fromEnv = self::readEnvValue($this->accessKeyEnvVar);
        if ($fromEnv !== '')
        {
            return $fromEnv;
        }

        if ($this->accessKey !== '')
        {
            return $this->accessKey;
        }

        return self::getSettings()['accessKey'];
    }

    private function resolveSecretKeyForConnection(): string
    {
        if ($this->secretKeyField !== '')
        {
            return $this->secretKeyField;
        }

        $fromEnv = self::readEnvValue($this->secretKeyEnvVar);
        if ($fromEnv !== '')
        {
            return $fromEnv;
        }

        return self::getSettings()['secretKey'];
    }

    public function validateSettingsForConnection(): bool
    {
        return $this->bucket !== ''
            && $this->region !== ''
            && $this->resolveAccessKeyForConnection() !== ''
            && $this->resolveSecretKeyForConnection() !== '';
    }

    private static function getSettingsManager(): SettingsManager
    {
        $module = Yii::$app->getModule('humhub-s3', false);
        if ($module instanceof Module)
        {
            return $module->settings;
        }

        return new SettingsManager(['moduleId' => 'humhub-s3']);
    }
}
