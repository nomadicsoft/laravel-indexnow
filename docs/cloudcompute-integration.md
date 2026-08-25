# CloudCompute integration

This package supports both relevant application baselines:

- `app-cloudcompute-ru`: PHP 8.2+, Laravel 12
- `cloudcompute-ru`: PHP 8.3+, Laravel 13

Production database queues and cache work without Redis. Tests can continue to
use the existing sync queue and array cache. The package explicitly requests
after-commit dispatch because the applications' queue connection does not need
to enable it globally.

## Install from the ZIP

Place the extracted directory at `packages/laravel-indexnow`, add the Composer
path repository shown in the main README, then run:

```bash
composer require nomadicsoft/laravel-indexnow:@dev
php artisan vendor:publish --tag=indexnow-config
php artisan indexnow:generate-key
```

Use a separate `APP_URL` and `INDEXNOW_KEY` per deployment. This naturally gives
`cloudcompute.ru` and `atmoscompute.com` distinct verification keys while the
same codebase and config file can serve both.

```dotenv
INDEXNOW_ENABLED=true
INDEXNOW_QUEUE_ENABLED=true
INDEXNOW_QUEUE_CONNECTION=database
INDEXNOW_CACHE_STORE=database
```

Verify each deployment:

```bash
php artisan indexnow:doctor --remote
```

## Initial backfill

CloudCompute's `/sitemap.xml` is already the curated source of indexable URLs.
It is safer than walking route definitions or tables because it includes the
existing availability and content-quality rules.

```bash
php artisan indexnow:submit-sitemap
```

Run this once after enabling IndexNow. Normal publication hooks should handle
incremental changes afterwards.

## Article pipeline hook

The article pipelines write rows directly and then call the authenticated
cache-clear endpoint. Those writes bypass Eloquent observers, so the reliable
notification boundary is the cache-clear controller after it confirms the new
article is publicly resolvable.

```php
use App\Services\BlogContentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use NomadicSoft\LaravelIndexNow\Facades\IndexNow;
use Symfony\Component\HttpFoundation\Response;

public function __invoke(Request $request, BlogContentService $blog): Response
{
    $token = config('services.pipeline.cache_clear_token');

    if ($token === null || $token === '') {
        abort(404);
    }

    if (! hash_equals($token, (string) $request->header('X-Pipeline-Token', ''))) {
        abort(403);
    }

    $slug = $request->validate([
        'slug' => ['nullable', 'string', 'max:255', 'regex:/\A[a-z0-9][a-z0-9-]*\z/'],
    ])['slug'] ?? null;

    Artisan::call('cache:clear-articles');

    if ($slug !== null && $blog->find($slug) !== null) {
        IndexNow::notifyMany([
            route('blog.show', ['slug' => $slug]),
            route('blog.index'),
        ]);
    }

    return response('OK');
}
```

Have the Python cache helper include the successfully published slug:

```python
response = httpx.post(
    CACHE_CLEAR_URL,
    headers={"X-Pipeline-Token": token},
    json={"slug": slug},
    timeout=30.0,
)
response.raise_for_status()
```

Calling `BlogContentService::find()` after cache invalidation reuses the real
publication rules for database-backed and file-backed content, locale,
publication time, and visibility.

## Other content changes

- Notify both old and new URLs when adding a redirect or changing a slug.
- Notify removed URLs after deploying their `404` or `410` response.
- For file-backed docs, tutorials, templates, and application manifests, call
  `indexnow:notify` from a successful deployment hook with the changed public
  paths.
- Do not attach the model trait to inventory records unless their public URL is
  already gated by the same availability and `noindex` rules used by the
  sitemap.
