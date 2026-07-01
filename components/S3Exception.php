<?php

namespace humhub\modules\humhubs3\components;

use RuntimeException;

/**
 * Exception thrown when an S3 HTTP request fails.
 */
class S3Exception extends RuntimeException
{
    public int $statusCode;

    public function __construct(
        int $statusCode,
        string $message,
        ?\Throwable $previous = null
    ) {
        $this->statusCode = $statusCode;
        parent::__construct($message, 0, $previous);
    }
}
