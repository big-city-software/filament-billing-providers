<?php

declare(strict_types=1);

namespace Illuminate\Http;

class RedirectResponse
{
    public function __construct(
        private readonly string $url,
        private readonly int $status = 302,
    ) {}

    public function getTargetUrl(): string
    {
        return $this->url;
    }

    public function getStatusCode(): int
    {
        return $this->status;
    }
}
