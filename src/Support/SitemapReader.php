<?php

namespace NomadicSoft\LaravelIndexNow\Support;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Http\Client\Factory;
use RuntimeException;

final class SitemapReader
{
    public function __construct(
        private readonly Factory $http,
        private readonly Repository $config,
    ) {}

    /** @return list<string> */
    public function read(string $sitemapUrl, int $limit = 0): array
    {
        if (! function_exists('simplexml_load_string')) {
            throw new RuntimeException('The SimpleXML extension is required to import a sitemap.');
        }

        $rootHost = strtolower((string) parse_url($sitemapUrl, PHP_URL_HOST));
        $rootScheme = strtolower((string) parse_url($sitemapUrl, PHP_URL_SCHEME));

        if ($rootHost === '' || ! in_array($rootScheme, ['http', 'https'], true)) {
            throw new RuntimeException('The sitemap must be an absolute HTTP(S) URL.');
        }

        $pending = [$sitemapUrl];
        $visited = [];
        $urls = [];
        $maximumDocuments = max(1, (int) $this->config->get('indexnow.sitemap.max_documents', 100));

        while ($pending !== []) {
            $documentUrl = array_shift($pending);

            if (isset($visited[$documentUrl])) {
                continue;
            }

            if (count($visited) >= $maximumDocuments) {
                throw new RuntimeException(sprintf('Sitemap import exceeded the %d document safety limit.', $maximumDocuments));
            }

            if (strtolower((string) parse_url($documentUrl, PHP_URL_HOST)) !== $rootHost) {
                throw new RuntimeException('A sitemap index referenced a document on a different hostname.');
            }

            $visited[$documentUrl] = true;
            $response = $this->http
                ->accept('application/xml, text/xml, */*')
                ->connectTimeout(max(1, (int) $this->config->get('indexnow.http.connect_timeout', 5)))
                ->timeout(max(1, (int) $this->config->get('indexnow.http.timeout', 10)))
                ->get($documentUrl);

            if (! $response->successful()) {
                throw new RuntimeException(sprintf(
                    'Could not fetch sitemap %s (HTTP %d).',
                    $documentUrl,
                    $response->status(),
                ));
            }

            $body = $response->body();
            $maximumBytes = max(1, (int) $this->config->get('indexnow.sitemap.max_bytes_per_document', 10485760));

            if (strlen($body) > $maximumBytes) {
                throw new RuntimeException(sprintf('Sitemap %s exceeds the configured size limit.', $documentUrl));
            }

            $previousErrors = libxml_use_internal_errors(true);
            $xml = simplexml_load_string($body, \SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);
            libxml_clear_errors();
            libxml_use_internal_errors($previousErrors);

            if ($xml === false) {
                throw new RuntimeException(sprintf('Sitemap %s contains invalid XML.', $documentUrl));
            }

            $locations = $xml->xpath('//*[local-name()="loc"]') ?: [];

            if (strtolower($xml->getName()) === 'sitemapindex') {
                foreach ($locations as $location) {
                    $pending[] = trim((string) $location);
                }

                continue;
            }

            if (strtolower($xml->getName()) !== 'urlset') {
                throw new RuntimeException(sprintf('Sitemap %s has an unsupported root element.', $documentUrl));
            }

            foreach ($locations as $location) {
                $urls[] = trim((string) $location);

                if ($limit > 0 && count($urls) >= $limit) {
                    return $urls;
                }
            }
        }

        return $urls;
    }
}
