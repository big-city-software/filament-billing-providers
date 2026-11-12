<?php

declare(strict_types=1);

namespace SegmentHoldings\FilamentBillingProviders\Support;

/**
 * Resolves a Filament tenant key from a verified webhook payload.
 *
 * A per-provider callback in `tenant_resolvers` wins. Otherwise the first
 * non-empty scalar at `tenant_resolution.{provider}.paths` is used.
 * Callbacks must be registered at runtime (a service provider). Closures
 * in a cached config file will not survive `config:cache`.
 */
final class TenantResolver
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function resolve(string $provider, array $payload): ?string
    {
        $callbacks = config('filament-billing-providers.tenant_resolvers', []);

        if (is_array($callbacks) && isset($callbacks[$provider]) && is_callable($callbacks[$provider])) {
            $resolved = $callbacks[$provider]($payload);

            return $this->scalarOrNull($resolved);
        }

        $paths = config('filament-billing-providers.tenant_resolution.'.$provider.'.paths');

        if (! is_array($paths) || $paths === []) {
            $paths = $this->defaultPaths($provider);
        }

        foreach ($paths as $path) {
            if (! is_string($path) || $path === '') {
                continue;
            }

            $value = data_get($payload, $path);
            $scalar = $this->scalarOrNull($value);

            if ($scalar !== null) {
                return $scalar;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function defaultPaths(string $provider): array
    {
        return match ($provider) {
            'paddle' => ['data.custom_data.tenant_id', 'data.custom_data.tenantId'],
            'lemon_squeezy' => [
                'meta.custom_data.tenant_id',
                'meta.custom_data.tenantId',
                'data.attributes.custom_data.tenant_id',
                'data.attributes.custom_data.tenantId',
            ],
            default => [
                'data.metadata.tenant_id',
                'data.metadata.tenantId',
                'data.custom_field_data.tenant_id',
                'data.custom_field_data.tenantId',
            ],
        };
    }

    private function scalarOrNull(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $string = trim((string) $value);

        return $string === '' ? null : $string;
    }
}
