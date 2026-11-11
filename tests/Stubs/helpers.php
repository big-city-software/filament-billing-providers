<?php

declare(strict_types=1);

/**
 * In-memory config() helper for unit tests without a Laravel container.
 *
 * @param  string|array<string, mixed>|null  $key
 */
function config(string|array|null $key = null, mixed $default = null): mixed
{
    static $store = [];

    if (is_array($key)) {
        foreach ($key as $k => $v) {
            if (str_contains((string) $k, '.')) {
                $segments = explode('.', (string) $k);
                $root = array_shift($segments);
                $store[$root] = config_nested_set($store[$root] ?? [], $segments, $v);
            } else {
                $store[$k] = $v;
            }
        }

        return null;
    }

    if ($key === null) {
        return $store;
    }

    if (array_key_exists($key, $store)) {
        return $store[$key];
    }

    $segments = explode('.', $key);
    $value = $store;

    foreach ($segments as $segment) {
        if (! is_array($value) || ! array_key_exists($segment, $value)) {
            return $default;
        }
        $value = $value[$segment];
    }

    return $value;
}

/**
 * @param  array<string, mixed>  $existing
 * @param  list<string>  $path
 * @return array<string, mixed>
 */
function config_nested_set(array $existing, array $path, mixed $value): array
{
    if ($path === []) {
        return is_array($value) ? $value : $existing;
    }

    $head = array_shift($path);

    if ($path === []) {
        $existing[$head] = $value;

        return $existing;
    }

    $child = $existing[$head] ?? [];
    $existing[$head] = config_nested_set(is_array($child) ? $child : [], $path, $value);

    return $existing;
}
