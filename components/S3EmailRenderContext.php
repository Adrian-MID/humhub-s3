<?php

namespace humhub\modules\humhubs3\components;

use humhub\modules\content\interfaces\ContentOwner;
use humhub\modules\user\models\User;

/**
 * Tracks the MailContentEntry being rendered so email HTML conversion can append stream attachments.
 */
class S3EmailRenderContext
{
    /** @var list<array{owner: ContentOwner, receiver: ?User}> */
    private static array $stack = [];

    public static function push(ContentOwner $owner, ?User $receiver): void
    {
        self::$stack[] = ['owner' => $owner, 'receiver' => $receiver];
    }

    public static function pop(): void
    {
        if (self::$stack !== [])
        {
            array_pop(self::$stack);
        }
    }

    /**
     * @return array{owner: ContentOwner, receiver: ?User}|null
     */
    public static function current(): ?array
    {
        if (self::$stack === [])
        {
            return null;
        }

        return self::$stack[count(self::$stack) - 1];
    }
}
