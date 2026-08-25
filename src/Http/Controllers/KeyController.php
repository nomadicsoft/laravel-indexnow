<?php

namespace NomadicSoft\LaravelIndexNow\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use NomadicSoft\LaravelIndexNow\Configuration\HostRegistry;
use NomadicSoft\LaravelIndexNow\Exceptions\ConfigurationException;

final class KeyController
{
    public function __invoke(
        Request $request,
        string $indexNowKey,
        HostRegistry $hosts,
    ): Response {
        try {
            $host = $hosts->forHost($request->getHost());
        } catch (ConfigurationException) {
            abort(404);
        }

        if (! $host->usesStandardRootKeyLocation()
            || ! hash_equals($host->key, $indexNowKey)) {
            abort(404);
        }

        return response($host->key, 200, [
            'Cache-Control' => 'public, max-age=3600',
            'Content-Type' => 'text/plain; charset=utf-8',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
