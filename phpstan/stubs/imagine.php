<?php

namespace Imagine;

interface ImageInterface
{
    public function crop(\Imagine\Image\Point $point, \Imagine\Image\Box $box): self;

    public function resize(\Imagine\Image\Box $size): self;

    public function thumbnail(\Imagine\Image\Box $size, int $settings = 0): self;

    public function getSize(): \Imagine\Image\Box;

    /**
     * @param array<string, mixed> $options
     */
    public function save(string $path, array $options = []): self;
}

interface ImagineInterface
{
    public function open(string $path): ImageInterface;
}

namespace Imagine\Image;

class Box
{
    public function __construct(int $width, int $height)
    {
    }

    public function getWidth(): int
    {
    }

    public function getHeight(): int
    {
    }

    public function heighten(int $height): self
    {
    }

    public function widen(int $width): self
    {
    }
}

class Point
{
    public function __construct(int $x, int $y)
    {
    }
}

interface ManipulatorInterface
{
    public const THUMBNAIL_OUTBOUND = 0;
}

namespace yii\imagine;

class Image
{
    public static function getImagine(): \Imagine\ImagineInterface
    {
    }
}

namespace yii\base;

class ErrorException extends Exception
{
}
