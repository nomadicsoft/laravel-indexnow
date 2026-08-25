<?php

namespace NomadicSoft\LaravelIndexNow\Support;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Str;

final class UrlEligibility
{
    public function __construct(private readonly Repository $config) {}

    public function allows(string $url): bool
    {
        $path = (string) (parse_url($url, PHP_URL_PATH) ?: '/');

        foreach ((array) $this->config->get('indexnow.urls.exclude_paths', []) as $pattern) {
            if (is_string($pattern) && Str::is($pattern, $path)) {
                return false;
            }
        }

        $query = (string) (parse_url($url, PHP_URL_QUERY) ?? '');

        if ($query === '') {
            return true;
        }

        parse_str($query, $parameters);

        foreach (array_keys($parameters) as $parameter) {
            foreach ((array) $this->config->get('indexnow.urls.exclude_query_parameters', []) as $pattern) {
                if (is_string($pattern) && Str::is($pattern, (string) $parameter)) {
                    return false;
                }
            }
        }

        return true;
    }
}
