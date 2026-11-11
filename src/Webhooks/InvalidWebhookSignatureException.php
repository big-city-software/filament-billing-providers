<?php

declare(strict_types=1);

namespace SegmentHoldings\FilamentBillingProviders\Webhooks;

use RuntimeException;

final class InvalidWebhookSignatureException extends RuntimeException
{
}
