<?php

namespace QrzLite\Tests\Support;

/**
 * Thrown by the test application where Joomla would redirect and end the request.
 */
final class Redirected extends \RuntimeException
{
    public function __construct(public readonly string $url)
    {
        parent::__construct('Redirected to ' . $url);
    }
}
