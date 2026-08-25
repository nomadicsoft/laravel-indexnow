# Laravel IndexNow

Queue-first IndexNow notifications for Laravel 12 and 13.

The package submits only URLs your application explicitly chooses. It groups
them by exact hostname, sends protocol-safe batches, dispatches after database
commit, debounces duplicates through Laravel's cache, and handles IndexNow's
accepted, permanent, rate-limited, and transient responses separately.

## Requirements

- PHP 8.2 or newer
- Laravel 12 or 13
- A public HTTP(S) hostname and an IndexNow verification key

## Installation

From Packagist after publication:

```bash
composer require nomadicsoft/laravel-indexnow
php artisan vendor:publish --tag=indexnow-config
```

From the supplied ZIP while dogfooding the package:

```bash
unzip nomadicsoft-laravel-indexnow-v0.1.0.zip -d packages
```

Add a path repository to the application's `composer.json`:

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "packages/laravel-indexnow",
            "options": {
                "symlink": true
            }
        }
    ]
}
```

Then install and publish the config:

```bash
composer require nomadicsoft/laravel-indexnow:@dev
php artisan vendor:publish --tag=indexnow-config
```

## Setup

Generate a key:

```bash
php artisan indexnow:generate-key
```

Put the generated value in the deployment environment. Leave the integration
disabled until the key is publicly reachable.

```dotenv
INDEXNOW_ENABLED=false
INDEXNOW_KEY=replace-with-generated-key
INDEXNOW_QUEUE_ENABLED=true
INDEXNOW_QUEUE_CONNECTION=database
INDEXNOW_QUEUE_NAME=default
INDEXNOW_CACHE_STORE=database
```

By default the package exposes `/{key}.txt`. The controller checks the request's
exact hostname before returning anything, so a key configured for one host is
not exposed on another host routed to the same application. A static file made
with the command below is even simpler operationally:

```bash
php artisan indexnow:generate-key --write
```

After deploying the key, enable the integration and verify it from outside the
application:

```bash
php artisan indexnow:doctor --remote
```

The package derives the default host and scheme from `APP_URL`. Every subdomain
needs its own explicit host configuration and key.

## Usage

```php
use NomadicSoft\LaravelIndexNow\Facades\IndexNow;

IndexNow::notify(route('articles.show', $article));

IndexNow::notifyMany([
    route('articles.show', $article),
    route('articles.index'),
]);

$results = IndexNow::sendNow([
    'https://example.com/changed-page',
]);
```

`notify()` and `notifyMany()` queue jobs by default. Every job is explicitly
marked for after-commit dispatch even when the application's queue connection
has `after_commit=false`. `sendNow()` is synchronous and bypasses the debounce;
it is intended for commands, diagnostics, and controlled deployment hooks.

Relative paths use the configured default host:

```php
IndexNow::notify('/gpu/a100');
```

Created, updated, redirected, and deleted URLs all use the same IndexNow
payload. For a changed slug, notify both the old URL and the new URL. A `200` or
`202` means the request was received; it does not promise crawling or indexing.

### Eligibility filters

The package rejects unconfigured hosts, credentials, overlong URLs, and URLs
outside a custom key-location scope. It strips fragments, preserves paths,
trailing slashes, and query strings, and skips common tracking-query variants.

Register project-specific canonical/public rules during application boot. Keep
callbacks stateless because Laravel Octane may retain the package singleton:

```php
use NomadicSoft\LaravelIndexNow\Configuration\HostConfiguration;
use NomadicSoft\LaravelIndexNow\Facades\IndexNow;

IndexNow::filterUsing(
    fn (string $url, HostConfiguration $host): bool => ! str_contains($url, '/preview/')
);
```

Do not use a remote page fetch as the primary eligibility rule. The application
already knows whether a record is public, canonical, substantial, and
indexable; express that decision at the publication boundary.

### Opt-in Eloquent events

No observers are registered globally. A public-content model can opt in:

```php
use NomadicSoft\LaravelIndexNow\Concerns\NotifiesIndexNow;
use NomadicSoft\LaravelIndexNow\Contracts\IndexNowable;
use NomadicSoft\LaravelIndexNow\Enums\IndexNowChange;

final class Article extends Model implements IndexNowable
{
    use NotifiesIndexNow;

    public function indexNowUrls(IndexNowChange $change): iterable
    {
        $urls = [route('articles.show', ['slug' => $this->slug])];

        if ($change === IndexNowChange::Updated && $this->wasChanged('slug')) {
            $urls[] = route('articles.show', ['slug' => $this->getOriginal('slug')]);
        }

        return $urls;
    }

    public function shouldNotifyIndexNow(IndexNowChange $change): bool
    {
        return $this->is_published
            && ($this->published_at === null || $this->published_at->isPast());
    }
}
```

If another process writes directly to the database, Eloquent events cannot see
it. Notify from the pipeline's successful publication/cache-invalidation hook
or call the CLI command from a deployment step.

## Commands

```bash
# Queue absolute URLs and/or relative paths
php artisan indexnow:notify /gpu/a100 https://example.com/blog/new-post

# Submit synchronously and show each HTTP result
php artisan indexnow:notify --now /gpu/a100

# One-time backfill from APP_URL/sitemap.xml (sitemap indexes are supported)
php artisan indexnow:submit-sitemap

# Limit a rehearsal or submit synchronously
php artisan indexnow:submit-sitemap --limit=100
php artisan indexnow:submit-sitemap --now
```

Sitemap import needs PHP's SimpleXML extension. Child sitemap documents must
remain on the sitemap index's hostname.

## Multi-host configuration

Each configured entry is exact; `www.example.com` does not inherit the key for
`example.com`.

```php
'hosts' => [
    'example.com' => [
        'key' => env('INDEXNOW_EXAMPLE_KEY'),
        'key_location' => null,
        'endpoint' => null,
        'scheme' => 'https',
    ],
    'docs.example.com' => [
        'key' => env('INDEXNOW_DOCS_KEY'),
        'key_location' => 'https://docs.example.com/seo/docs-key.txt',
        'endpoint' => null,
        'scheme' => 'https',
    ],
],
```

A custom key file under `/seo/` can verify only URLs within `/seo/`. The package
validates that path scope. Its automatic route serves only the conventional
root `/{key}.txt`; custom locations must be served by the application or web
server.

## Delivery behavior

- Batches never mix hostnames and never exceed 10,000 URLs. The default is 500
  to keep database and hosted-queue payloads modest.
- Per-URL cache claims are created inside the queue job, after database commit.
  The default five-minute debounce coalesces overlapping batches.
- `200` is accepted. `202` is accepted with key validation pending.
- `400`, `403`, and `422` fail permanently without automatic retries.
- `429` honors numeric or HTTP-date `Retry-After` values.
- `408`, `425`, network errors, and `5xx` responses use queue-visible retries
  with configurable backoff and jitter.
- Queue payloads contain only the hostname and URL strings. Verification keys
  remain in runtime configuration.

Applications can listen for:

- `UrlsSubmitted`
- `SubmissionDeferred`
- `SubmissionFailed`

## Testing

```bash
composer validate --strict
composer test
composer analyse
composer format:check
```

The test suite uses Laravel's HTTP fakes and never contacts an IndexNow endpoint.

## License

MIT
