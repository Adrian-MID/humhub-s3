<?php

namespace humhub\modules\humhubs3\components;

use humhub\modules\humhubs3\models\forms\ConfigureForm;
use Symfony\Component\HttpClient\HttpClient;
use Yii;

/**
 * Verifies bucket policy behaviour required by HumHub S3 storage.
 */
class BucketPolicyChecker
{
    /**
     * @return array{success: bool, message: string}
     */
    public static function verify(ConfigureForm $form): array
    {
        if (!$form->validateSettingsForConnection())
        {
            return [
                'success' => false,
                'message' => Yii::t('HumhubS3Module.base', 'Please save valid bucket, region, and credential settings on the General tab before testing the bucket policy.'),
            ];
        }

        if (!$form->validate(['bucket', 'region', 'endpoint']))
        {
            return [
                'success' => false,
                'message' => implode(' ', $form->getFirstErrors()),
            ];
        }

        $client = ConfigureForm::createClientFromForm($form);
        $issues = self::runFunctionalChecks($client, $form->prefix);

        if ($issues === [])
        {
            return [
                'success' => true,
                'message' => Yii::t(
                    'HumhubS3Module.base',
                    'Bucket policy checks passed. Public branding access and private object access look correct for the configured prefix.'
                ),
            ];
        }

        return [
            'success' => false,
            'message' => implode(' ', $issues),
        ];
    }

    /**
     * @return list<string>
     */
    private static function runFunctionalChecks(S3Client $client, string $prefix): array
    {
        $issues = [];
        $publicTestKey = self::buildPublicTestObjectKey($prefix);
        $privateTestKey = self::buildPrivateTestObjectKey($prefix);
        $tempFilePath = tempnam(sys_get_temp_dir(), 's3policy_');

        if ($tempFilePath === false)
        {
            return [Yii::t('HumhubS3Module.base', 'Could not create a temporary file for the bucket policy test.')];
        }

        $testContent = 'HumHub bucket policy test at ' . gmdate('c');

        try
        {
            if (file_put_contents($tempFilePath, $testContent) === false)
            {
                return [Yii::t('HumhubS3Module.base', 'Could not write the temporary bucket policy test file.')];
            }

            $issues = array_merge($issues, self::verifyPublicBrandingAccess($client, $publicTestKey, $tempFilePath));
            $issues = array_merge($issues, self::verifyPrivateObjectAccess($client, $privateTestKey, $tempFilePath));
        }
        finally
        {
            if (is_file($tempFilePath))
            {
                @unlink($tempFilePath);
            }
        }

        return $issues;
    }

    /**
     * @return list<string>
     */
    private static function verifyPublicBrandingAccess(S3Client $client, string $testKey, string $tempFilePath): array
    {
        $issues = [];

        try
        {
            $client->putObject($testKey, $tempFilePath, 'text/plain');
        }
        catch (S3Exception)
        {
            return [Yii::t('HumhubS3Module.base', 'Could not upload a branding test object. Check IAM permissions for the configured prefix.')];
        }

        if (!$client->headObject($testKey))
        {
            $issues[] = Yii::t('HumhubS3Module.base', 'Uploaded branding test object is not readable with the configured credentials.');
        }

        $publicUrl = $client->getPublicObjectUrl($testKey);
        if (!self::canFetchPublicUrl($publicUrl))
        {
            $issues[] = Yii::t(
                'HumhubS3Module.base',
                'Anonymous read failed for a branding test object. Confirm the HumHubPublicMedia policy is applied and that S3 Block Public Access allows scoped public read.'
            );
        }

        try
        {
            $client->deleteObject($testKey);
        }
        catch (S3Exception)
        {
            $issues[] = Yii::t('HumhubS3Module.base', 'Could not delete the branding test object after the policy check.');
        }

        return $issues;
    }

    /**
     * @return list<string>
     */
    private static function verifyPrivateObjectAccess(S3Client $client, string $testKey, string $tempFilePath): array
    {
        $issues = [];

        try
        {
            $client->putObject($testKey, $tempFilePath, 'text/plain');
        }
        catch (S3Exception)
        {
            return [Yii::t('HumhubS3Module.base', 'Could not upload a private test object. Check IAM permissions for the configured prefix.')];
        }

        if (!$client->headObject($testKey))
        {
            $issues[] = Yii::t('HumhubS3Module.base', 'Uploaded private test object is not readable with the configured credentials.');
        }

        $publicUrl = $client->getPublicObjectUrl($testKey);
        if (self::canFetchPublicUrl($publicUrl))
        {
            $issues[] = Yii::t(
                'HumhubS3Module.base',
                'A private test object was readable without authentication. File attachments should stay private and only branding and profile images should be public.'
            );
        }

        try
        {
            $client->deleteObject($testKey);
        }
        catch (S3Exception)
        {
            $issues[] = Yii::t('HumhubS3Module.base', 'Could not delete the private test object after the policy check.');
        }

        return $issues;
    }

    private static function buildPublicTestObjectKey(string $prefix): string
    {
        $prefix = trim($prefix, '/');

        return ($prefix !== '' ? $prefix . '/' : '') . 'branding/humhub-s3-policy-test-' . bin2hex(random_bytes(8)) . '.txt';
    }

    private static function buildPrivateTestObjectKey(string $prefix): string
    {
        $prefix = trim($prefix, '/');

        return ($prefix !== '' ? $prefix . '/' : '') . 'humhub-s3-policy-test-private-' . bin2hex(random_bytes(8)) . '.txt';
    }

    private static function canFetchPublicUrl(string $url): bool
    {
        try
        {
            $response = HttpClient::create([
                'verify_peer' => S3Client::shouldValidateSslForHttpClient(),
                'verify_host' => S3Client::shouldValidateSslForHttpClient(),
                'timeout' => 15,
            ])->request('GET', $url);

            return $response->getStatusCode() >= 200 && $response->getStatusCode() < 300;
        }
        catch (\Throwable $exception)
        {
            Yii::warning('Public bucket policy test request failed: ' . $exception->getMessage(), 'humhub-s3');

            return false;
        }
    }
}
