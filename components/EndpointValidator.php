<?php

namespace humhub\modules\humhubs3\components;

/**
 * Validates custom S3 endpoint URLs to reduce SSRF risk.
 */
class EndpointValidator
{
    /**
     * Returns true when the endpoint URL is safe to use.
     */
    public static function isValid(string $endpoint): bool
    {
        $endpoint = trim($endpoint);
        if ($endpoint === '')
        {
            return true;
        }

        $parts = parse_url($endpoint);
        if ($parts === false || !isset($parts['scheme'], $parts['host']))
        {
            return false;
        }

        $scheme = strtolower($parts['scheme']);
        if (!in_array($scheme, ['http', 'https'], true))
        {
            return false;
        }

        if (isset($parts['user']) || isset($parts['pass']))
        {
            return false;
        }

        $host = strtolower($parts['host']);
        if ($host === '')
        {
            return false;
        }

        if (self::isLinkLocalOrMetadata($host))
        {
            return false;
        }

        if ($scheme === 'http' && !self::isLocalhost($host))
        {
            return false;
        }

        return true;
    }

    private static function isLocalhost(string $host): bool
    {
        return in_array($host, ['localhost', '127.0.0.1', '::1'], true);
    }

    private static function isLinkLocalOrMetadata(string $host): bool
    {
        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false)
        {
            return false;
        }

        return str_starts_with($host, '169.254.');
    }
}
