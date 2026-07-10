<?php

namespace humhub\modules\humhubs3\components;

/**
 * Builds the AWS bucket policy JSON for HumHub S3 storage.
 *
 * The policy grants the HumHub IAM user read/write access under the configured prefix
 * (required for private attachments and presigned downloads) and allows anonymous
 * public read on branding and profile image paths only.
 */
class BucketPolicyTemplate
{
    public const PLACEHOLDER_BUCKET = 'YOUR-BUCKET';

    public const PLACEHOLDER_IAM_USER_ARN = 'YOUR-IAM-USER-ARN';

    public static function toJson(string $bucket, string $prefix, ?string $iamUserArn = null): string
    {
        $policy = self::buildPolicy($bucket, $prefix, $iamUserArn);
        $json = json_encode($policy, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        return is_string($json) ? $json : '{}';
    }

    /**
     * @return array<string, mixed>
     */
    public static function buildPolicy(string $bucket, string $prefix, ?string $iamUserArn = null): array
    {
        $bucketName = trim($bucket);
        if ($bucketName === '')
        {
            $bucketName = self::PLACEHOLDER_BUCKET;
        }

        $principalArn = self::normalizeIamUserArn($iamUserArn);

        return [
            'Version' => '2012-10-17',
            'Statement' => [
                self::buildServiceObjectStatement($bucketName, $prefix, $principalArn),
                self::buildServiceListStatement($bucketName, $prefix, $principalArn),
                self::buildPublicMediaStatement($bucketName, $prefix),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function buildServiceObjectStatement(string $bucket, string $prefix, string $iamUserArn): array
    {
        return [
            'Sid' => 'HumHubServiceObjectAccess',
            'Effect' => 'Allow',
            'Principal' => [
                'AWS' => $iamUserArn,
            ],
            'Action' => [
                's3:GetObject',
                's3:PutObject',
                's3:DeleteObject',
            ],
            'Resource' => self::buildPrefixObjectArn($bucket, $prefix),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function buildServiceListStatement(string $bucket, string $prefix, string $iamUserArn): array
    {
        $statement = [
            'Sid' => 'HumHubServiceListAccess',
            'Effect' => 'Allow',
            'Principal' => [
                'AWS' => $iamUserArn,
            ],
            'Action' => 's3:ListBucket',
            'Resource' => self::buildBucketArn($bucket),
        ];

        $listPrefixes = self::buildListPrefixes($prefix);
        if ($listPrefixes !== [])
        {
            $statement['Condition'] = [
                'StringLike' => [
                    's3:prefix' => $listPrefixes,
                ],
            ];
        }

        return $statement;
    }

    /**
     * @return array<string, mixed>
     */
    private static function buildPublicMediaStatement(string $bucket, string $prefix): array
    {
        return [
            'Sid' => 'HumHubPublicMedia',
            'Effect' => 'Allow',
            'Principal' => '*',
            'Action' => 's3:GetObject',
            'Resource' => self::buildPublicResourceArns($bucket, $prefix),
        ];
    }

    /**
     * @return list<string>
     */
    public static function buildPublicResourceArns(string $bucket, string $prefix): array
    {
        $bucket = trim($bucket);
        $prefix = trim(trim($prefix), '/');

        $resources = [];
        foreach (['branding/*', 'profile_image/*'] as $suffix)
        {
            $objectKey = $prefix !== '' ? $prefix . '/' . $suffix : $suffix;
            $resources[] = 'arn:aws:s3:::' . $bucket . '/' . $objectKey;
        }

        return $resources;
    }

    /**
     * @return list<string>
     */
    private static function buildListPrefixes(string $prefix): array
    {
        $prefix = trim(trim($prefix), '/');
        if ($prefix === '')
        {
            return [];
        }

        return [$prefix . '/*', $prefix];
    }

    private static function buildBucketArn(string $bucket): string
    {
        return 'arn:aws:s3:::' . trim($bucket);
    }

    private static function buildPrefixObjectArn(string $bucket, string $prefix): string
    {
        $prefix = trim(trim($prefix), '/');
        $objectKey = $prefix !== '' ? $prefix . '/*' : '*';

        return 'arn:aws:s3:::' . trim($bucket) . '/' . $objectKey;
    }

    private static function normalizeIamUserArn(?string $iamUserArn): string
    {
        $iamUserArn = trim((string) $iamUserArn);

        return $iamUserArn !== '' ? $iamUserArn : self::PLACEHOLDER_IAM_USER_ARN;
    }

    public static function usesPlaceholderBucket(string $bucket): bool
    {
        return trim($bucket) === '';
    }

    public static function usesPlaceholderIamUserArn(?string $iamUserArn = null): bool
    {
        $iamUserArn = trim((string) $iamUserArn);

        return $iamUserArn === '' || $iamUserArn === self::PLACEHOLDER_IAM_USER_ARN;
    }

    public static function needsPlaceholderReview(string $bucket, ?string $iamUserArn = null): bool
    {
        return self::usesPlaceholderBucket($bucket) || self::usesPlaceholderIamUserArn($iamUserArn);
    }
}
