<?php

namespace App\Services\Integrations\Adapters\Handlers;

use App\Contracts\Integrations\SourceAdapterHandler;
use Illuminate\Support\Facades\Http;

class ApiRestHandler implements SourceAdapterHandler
{
    public function validateConfiguration(array $config): void
    {
        if (empty($config['base_url'])) {
            throw new \InvalidArgumentException('La configuración debe incluir base_url');
        }
    }

    public function getSchema(array $config): array
    {
        $schema = $config['response_schema'] ?? [];

        if (!empty($schema)) {
            return $schema;
        }

        $examples = $this->getExamples($config);
        if (!empty($examples)) {
            $first = $examples[0] ?? [];
            return array_map(fn ($key, $value) => [
                'name' => $key,
                'type' => gettype($value),
            ], array_keys($first), $first);
        }

        return [];
    }

    public function count(array $config): int
    {
        $records = iterator_to_array($this->getRecords($config));
        return count($records);
    }

    public function getRecords(array $config): \Generator
    {
        $client = $this->buildClient($config);
        $paginator = $this->getPaginator($config);

        $page = $paginator['initial'] ?? 1;
        $maxPages = $config['max_pages'] ?? 100;
        $pagesFetched = 0;

        while ($pagesFetched < $maxPages) {
            $response = $client->get($this->buildUrl($config, $page));

            if ($response->failed()) {
                throw new \RuntimeException(
                    "API request failed: {$response->status()} - {$response->body()}"
                );
            }

            $items = $this->extractItems($response->json(), $config['data_path'] ?? null);

            foreach ($items as $item) {
                yield $item;
            }

            $pagesFetched++;

            if (!$this->hasNextPage($response->json(), $config, $page, $items)) {
                break;
            }

            $page = $this->nextPage($page, $config);
        }
    }

    public function getExamples(array $config): array
    {
        $client = $this->buildClient($config);
        $response = $client->get($this->buildUrl($config, 1));

        if ($response->failed()) {
            return [];
        }

        $items = $this->extractItems($response->json(), $config['data_path'] ?? null);

        return array_slice($items, 0, 5);
    }

    public function getStableIdentifier(array $record): string
    {
        return (string) ($record['id'] ?? $record['ID'] ?? '');
    }

    private function buildClient(array $config): \Illuminate\Http\Client\PendingRequest
    {
        $client = Http::timeout($config['timeout'] ?? 30)
            ->withOptions(['verify' => $config['verify_ssl'] ?? true]);

        $authType = $config['auth_type'] ?? null;

        if ($authType === 'bearer' && !empty($config['auth_token'])) {
            $client->withToken($config['auth_token']);
        } elseif ($authType === 'basic' && !empty($config['auth_username'])) {
            $client->withBasicAuth(
                $config['auth_username'],
                $config['auth_password'] ?? ''
            );
        } elseif ($authType === 'api_key' && !empty($config['auth_key'])) {
            $client->withHeaders([
                $config['auth_header'] ?? 'X-API-Key' => $config['auth_key'],
            ]);
        }

        if (!empty($config['headers'])) {
            $client->withHeaders($config['headers']);
        }

        return $client;
    }

    private function buildUrl(array $config, int $page): string
    {
        $baseUrl = rtrim($config['base_url'], '/');
        $endpoint = $config['endpoint'] ?? '';

        $url = $baseUrl . '/' . ltrim($endpoint, '/');

        $queryParams = $config['query_params'] ?? [];

        if ($config['pagination']['type'] ?? null === 'page') {
            $pageParam = $config['pagination']['page_param'] ?? 'page';
            $queryParams[$pageParam] = $page;
        }

        if (!empty($queryParams)) {
            $url .= '?' . http_build_query($queryParams);
        }

        return $url;
    }

    private function getPaginator(array $config): array
    {
        $pag = $config['pagination'] ?? [];
        return [
            'type' => $pag['type'] ?? null,
            'initial' => $pag['initial_page'] ?? 1,
            'page_param' => $pag['page_param'] ?? 'page',
            'limit_param' => $pag['limit_param'] ?? 'per_page',
            'per_page' => $pag['per_page'] ?? 100,
        ];
    }

    private function hasNextPage(?array $responseData, array $config, int $currentPage, array $items): bool
    {
        if (empty($items)) {
            return false;
        }

        $pagType = $config['pagination']['type'] ?? null;

        if ($pagType === 'page') {
            $totalPath = $config['pagination']['total_path'] ?? null;

            if ($totalPath && $responseData) {
                $total = data_get($responseData, $totalPath, 0);
                $perPage = $config['pagination']['per_page'] ?? 100;
                return $currentPage * $perPage < $total;
            }

            return count($items) >= ($config['pagination']['per_page'] ?? 100);
        }

        if ($pagType === 'cursor' && $responseData) {
            $cursorPath = $config['pagination']['next_cursor_path'] ?? null;
            return $cursorPath && !empty(data_get($responseData, $cursorPath));
        }

        if ($pagType === 'offset') {
            $offset = $config['pagination']['offset'] ?? 0;
            $limit = $config['pagination']['limit'] ?? 100;
            return count($items) >= $limit;
        }

        return false;
    }

    private function nextPage(int $currentPage, array $config): int
    {
        $pagType = $config['pagination']['type'] ?? null;

        if ($pagType === 'page') {
            return $currentPage + 1;
        }

        return $currentPage + 1;
    }

    private function extractItems(?array $responseData, ?string $dataPath): array
    {
        if ($responseData === null) {
            return [];
        }

        if ($dataPath) {
            return data_get($responseData, $dataPath, []);
        }

        if (isset($responseData['data'])) {
            return $responseData['data'];
        }

        if (isset($responseData['items'])) {
            return $responseData['items'];
        }

        if (isset($responseData['results'])) {
            return $responseData['results'];
        }

        if (array_is_list($responseData)) {
            return $responseData;
        }

        return [$responseData];
    }
}
