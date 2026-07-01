<?php

namespace humhub\modules\humhubs3\components;

use AsyncAws\Core\Exception\Http\ClientException;
use AsyncAws\Core\Exception\Http\HttpException;
use AsyncAws\S3\Exception\NoSuchKeyException;
use AsyncAws\S3\S3Client as AsyncS3Client;
use AsyncAws\S3\ValueObject\AwsObject;
use humhub\modules\humhubs3\ComposerAutoload;
use RuntimeException;
use Symfony\Component\HttpClient\HttpClient;
use Yii;

/**
 * HumHub-facing S3 adapter backed by AsyncAws.
 */
class S3Client
{
    private string $bucket;

    private AsyncS3Client $client;

    public function __construct(
        string $accessKey,
        string $secretKey,
        string $region,
        string $bucket,
        ?string $endpoint = null,
        bool $usePathStyle = false
    ) {
        ComposerAutoload::ensureLoaded();

        $this->bucket = $bucket;

        $configuration = [
            'region' => $region,
            'accessKeyId' => $accessKey,
            'accessKeySecret' => $secretKey,
            'pathStyleEndpoint' => $usePathStyle ? 'true' : 'false',
        ];

        if ($endpoint !== null && $endpoint !== '')
        {
            $configuration['endpoint'] = rtrim($endpoint, '/');
        }

        $this->client = new AsyncS3Client(
            $configuration,
            null,
            HttpClient::create([
                'verify_peer' => self::shouldValidateSsl(),
                'verify_host' => self::shouldValidateSsl(),
                'max_duration' => 120,
                'timeout' => 120,
            ])
        );
    }

    public function putObject(string $key, string $sourcePath, ?string $contentType = null): void
    {
        if (!is_readable($sourcePath))
        {
            throw new RuntimeException('Cannot read source file for upload.');
        }

        $resource = fopen($sourcePath, 'rb');
        if ($resource === false)
        {
            throw new RuntimeException('Failed to read source file for upload.');
        }

        try
        {
            $params = [
                'Bucket' => $this->bucket,
                'Key' => $key,
                'Body' => $resource,
                'ContentLength' => filesize($sourcePath) ?: 0,
                'ContentType' => $contentType ?: 'application/octet-stream',
            ];

            $this->client->putObject($params)->resolve();
        }
        catch (\Throwable $exception)
        {
            self::rethrow($exception);
        }
        finally
        {
            if (is_resource($resource))
            {
                fclose($resource);
            }
        }
    }

    public function getObject(string $key, string $destinationPath): void
    {
        $directory = dirname($destinationPath);
        if (!is_dir($directory) && !mkdir($directory, 0o755, true) && !is_dir($directory))
        {
            throw new RuntimeException('Unable to create destination directory.');
        }

        try
        {
            $result = $this->client->getObject([
                'Bucket' => $this->bucket,
                'Key' => $key,
            ]);

            $source = $result->getBody()->getContentAsResource();
            $destination = fopen($destinationPath, 'wb');
            if ($destination === false)
            {
                throw new RuntimeException('Failed to write downloaded object.');
            }

            try
            {
                if (stream_copy_to_stream($source, $destination) === false)
                {
                    throw new RuntimeException('Failed to write downloaded object.');
                }
            }
            finally
            {
                fclose($destination);
                if (is_resource($source))
                {
                    fclose($source);
                }
            }
        }
        catch (\Throwable $exception)
        {
            throw self::rethrow($exception);
        }
    }

    public function headObject(string $key): bool
    {
        try
        {
            $this->client->headObject([
                'Bucket' => $this->bucket,
                'Key' => $key,
            ])->resolve();

            return true;
        }
        catch (NoSuchKeyException)
        {
            return false;
        }
        catch (ClientException $exception)
        {
            if ($exception->getCode() === 404)
            {
                return false;
            }

            // Some IAM policies allow GetObject but reject HeadObject.
            if ($exception->getCode() === 403)
            {
                try
                {
                    $this->client->getObject([
                        'Bucket' => $this->bucket,
                        'Key' => $key,
                        'Range' => 'bytes=0-0',
                    ])->resolve();

                    return true;
                }
                catch (NoSuchKeyException)
                {
                    return false;
                }
                catch (ClientException $getException)
                {
                    if ($getException->getCode() === 404)
                    {
                        return false;
                    }

                    throw self::rethrow($getException);
                }
            }

            throw self::rethrow($exception);
        }
        catch (\Throwable $exception)
        {
            throw self::rethrow($exception);
        }
    }

    public function deleteObject(string $key): void
    {
        try
        {
            $this->client->deleteObject([
                'Bucket' => $this->bucket,
                'Key' => $key,
            ])->resolve();
        }
        catch (\Throwable $exception)
        {
            throw self::rethrow($exception);
        }
    }

    /**
     * @return string[]
     */
    public function listObjectKeys(string $prefix): array
    {
        try
        {
            $result = $this->client->listObjectsV2([
                'Bucket' => $this->bucket,
                'Prefix' => $prefix,
            ]);

            $keys = [];
            foreach ($result as $object)
            {
                if (!$object instanceof AwsObject)
                {
                    continue;
                }

                $key = $object->getKey();
                if ($key !== null && $key !== '')
                {
                    $keys[] = $key;
                }
            }

            return $keys;
        }
        catch (\Throwable $exception)
        {
            throw self::rethrow($exception);
        }
    }

    /**
     * Uploads a test object, verifies it, and removes it again.
     *
     * @return array{success: bool, message: string}
     */
    public function testConnection(): array
    {
        $testKey = 'humhub-s3-test-' . bin2hex(random_bytes(8)) . '.txt';
        $testContent = 'HumHub S3 connection test at ' . gmdate('c');

        $tempFilePath = tempnam(sys_get_temp_dir(), 's3test_');
        if ($tempFilePath === false)
        {
            return ['success' => false, 'message' => 'Could not create a temporary test file.'];
        }

        $downloadFilePath = null;

        try
        {
            if (file_put_contents($tempFilePath, $testContent) === false)
            {
                return ['success' => false, 'message' => 'Could not write the temporary test file.'];
            }

            try
            {
                $this->putObject($testKey, $tempFilePath, 'text/plain');
            }
            catch (S3Exception $exception)
            {
                Yii::warning('S3 connection test upload failed: ' . $exception->getMessage(), 'humhub-s3');
                return ['success' => false, 'message' => self::getPublicErrorMessage($exception, 'upload')];
            }

            $downloadFilePath = tempnam(sys_get_temp_dir(), 's3test_dl_');
            if ($downloadFilePath === false)
            {
                return ['success' => false, 'message' => 'Could not create a temporary download file.'];
            }

            try
            {
                $this->getObject($testKey, $downloadFilePath);
            }
            catch (S3Exception $exception)
            {
                Yii::warning('S3 connection test download failed: ' . $exception->getMessage(), 'humhub-s3');
                return [
                    'success' => false,
                    'message' => self::getPublicErrorMessage($exception, 'download'),
                ];
            }

            if (file_get_contents($downloadFilePath) !== $testContent)
            {
                return ['success' => false, 'message' => 'Downloaded test file content did not match the upload.'];
            }

            try
            {
                $this->deleteObject($testKey);

                return [
                    'success' => true,
                    'message' => 'Successfully connected to S3. Upload, download, and delete operations verified.',
                ];
            }
            catch (S3Exception $exception)
            {
                Yii::warning('S3 connection test delete failed: ' . $exception->getMessage(), 'humhub-s3');
                return [
                    'success' => true,
                    'message' => 'Upload and download verified successfully. Could not delete the test file. '
                        . 'Add s3:DeleteObject to the IAM policy, or remove the test object manually from the bucket.',
                ];
            }
        }
        catch (\Throwable $exception)
        {
            Yii::error('S3 connection test failed: ' . $exception->getMessage(), 'humhub-s3');
            return ['success' => false, 'message' => 'Connection test failed. Check the application log for details.'];
        }
        finally
        {
            if (is_file($tempFilePath))
            {
                unlink($tempFilePath);
            }
            if (is_string($downloadFilePath) && is_file($downloadFilePath))
            {
                unlink($downloadFilePath);
            }

            try
            {
                $this->deleteObject($testKey);
            }
            catch (S3Exception)
            {
                // Ignore cleanup errors.
            }
        }
    }

    private static function shouldValidateSsl(): bool
    {
        $curlConfig = Yii::$app->params['curl'] ?? null;
        if (!is_array($curlConfig))
        {
            return true;
        }

        $validateSsl = $curlConfig['validateSsl'] ?? true;

        return is_bool($validateSsl) ? $validateSsl : (bool) $validateSsl;
    }

    /**
     * @return never
     */
    private static function rethrow(\Throwable $exception): void
    {
        if ($exception instanceof S3Exception || $exception instanceof RuntimeException)
        {
            throw $exception;
        }

        if ($exception instanceof HttpException)
        {
            $message = $exception->getAwsMessage() ?: $exception->getMessage();
            throw new S3Exception($exception->getCode(), $message, $exception);
        }

        throw new RuntimeException($exception->getMessage(), 0, $exception);
    }

    private static function getPublicErrorMessage(S3Exception $exception, string $operation): string
    {
        if ($exception->statusCode === 403)
        {
            return 'Access denied while trying to ' . $operation
                . ' to S3. Verify the bucket name, credentials, and IAM permissions.';
        }

        if ($exception->statusCode === 404)
        {
            return 'The configured S3 bucket or endpoint could not be found.';
        }

        return 'Could not ' . $operation . ' to S3 (HTTP ' . $exception->statusCode . '). '
            . 'Check your settings and IAM policy.';
    }
}
