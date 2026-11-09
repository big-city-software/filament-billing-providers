<?php

declare(strict_types=1);

namespace Illuminate\Http;

/**
 * Minimal request double for middleware unit tests.
 */
class Request
{
    public static function create(string $uri = '/', string $method = 'GET'): self
    {
        return new self;
    }
}
