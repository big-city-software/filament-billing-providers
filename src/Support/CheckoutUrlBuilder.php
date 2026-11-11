<?php

declare(strict_types=1);

namespace SegmentHoldings\FilamentBillingProviders\Support;

use InvalidArgumentException;

/**
 * Builds a checkout / portal redirect URL from provider config.
 */
final class CheckoutUrlBuilder
{
    /**
     * @param  array<string, mixed>  $providerConfig
     * @param  array<string, scalar|null>  $replacements
     */
    public function build(array $providerConfig, array $replacements = []): string
    {
        $direct = $providerConfig['checkout_url'] ?? null;

        if (is_string($direct) && $direct !== '') {
            return $this->applyReplacements($direct, $replacements);
        }

        $pattern = $providerConfig['checkout_url_pattern'] ?? null;

        if (is_string($pattern) && $pattern !== '') {
            $merged = array_merge(
                [
                    'product_id' => $providerConfig['product_id'] ?? null,
                    'price_id' => $providerConfig['price_id'] ?? null,
                    'variant_id' => $providerConfig['variant_id'] ?? null,
                    'store_slug' => $providerConfig['store_slug'] ?? null,
                    'success_url' => $providerConfig['success_url'] ?? null,
                ],
                $replacements,
            );

            return $this->applyReplacements($pattern, $merged);
        }

        throw new InvalidArgumentException(
            'No checkout_url or checkout_url_pattern configured for this billing provider.',
        );
    }

    /**
     * @param  array<string, scalar|null>  $replacements
     */
    private function applyReplacements(string $url, array $replacements): string
    {
        foreach ($replacements as $key => $value) {
            $url = str_replace('{'.$key.'}', (string) ($value ?? ''), $url);
        }

        return $url;
    }
}
