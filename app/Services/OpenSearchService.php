<?php

namespace App\Services;

use OpenSearch\Client;
use OpenSearch\ClientBuilder;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * OpenSearch 基础交互服务（兼容 AWS OpenSearch）
 *
 * 用于统计功能：事件发生时上传到 OpenSearch。
 * - 不同事件上传到不同 index
 * - 不同 index 设计不同模版
 * - 支持多个 index 聚合查询
 */
class OpenSearchService
{
    protected ?Client $client = null;

    protected bool $enabled = false;

    protected string $indexPrefix = '';

    public function __construct()
    {
        $this->enabled = config('opensearch.enabled', false);
        $this->indexPrefix = rtrim(config('opensearch.index_prefix', 'playapi'), '-');
    }

    protected function debug(string $message, array $context = []): void
    {
        if (config('opensearch.debug', false)) {
            Log::debug('[OpenSearch] ' . $message, $context);
        }
    }

    /**
     * 规范化 host：https 无端口时补 :443，避免 opensearch-php 错误使用 9200（AWS OpenSearch 兼容）
     */
    protected function normalizeHost(string $host): string
    {
        if (empty($host)) {
            return $host;
        }
        $parsed = parse_url($host);
        if (!is_array($parsed) || !isset($parsed['host'])) {
            return $host;
        }
        if (isset($parsed['port'])) {
            return $host;
        }
        $scheme = $parsed['scheme'] ?? 'http';
        $path = $parsed['path'] ?? '';
        $query = isset($parsed['query']) ? '?' . $parsed['query'] : '';
        $fragment = isset($parsed['fragment']) ? '#' . $parsed['fragment'] : '';
        $port = ($scheme === 'https') ? ':443' : ':9200';

        return $scheme . '://' . $parsed['host'] . $port . $path . $query . $fragment;
    }

    /**
     * 获取 OpenSearch 客户端（懒加载）
     */
    public function getClient(): ?Client
    {
        if (!$this->enabled) {
            return null;
        }

        if ($this->client === null) {
            $this->client = $this->buildClient();
        }

        return $this->client;
    }

    /**
     * 构建 OpenSearch 客户端
     */
    protected function buildClient(): Client
    {
        $hosts = config('opensearch.hosts', ['http://localhost:9200']);
        $hosts = array_filter(array_map('trim', $hosts));
        $hosts = !empty($hosts) ? $hosts : ['http://localhost:9200'];

        // AWS OpenSearch: https URL 无端口时 opensearch-php 会错误使用 9200，需显式加 :443
        $hosts = array_map([$this, 'normalizeHost'], $hosts);

        $params = [
            'hosts' => $hosts,
        ];

        if (($username = config('opensearch.username')) && ($password = config('opensearch.password'))) {
            $params['basicAuthentication'] = [$username, $password];
        }

        $builder = ClientBuilder::create();
        $builder->setHosts($params['hosts']);

        if (isset($params['basicAuthentication'])) {
            $builder->setBasicAuthentication(
                $params['basicAuthentication'][0],
                $params['basicAuthentication'][1]
            );
        }

        if ($logger = Log::getLogger()) {
            $builder->setLogger($logger);
        }

        $client = $builder->build();
        $this->debug('Client built', ['hosts' => $params['hosts'], 'auth' => isset($params['basicAuthentication'])]);
        return $client;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * 获取完整 index 名称（带前缀）
     */
    public function getIndexName(string $indexSuffix): string
    {
        return $this->indexPrefix . '-' . ltrim($indexSuffix, '-');
    }

    /**
     * 根据事件类型获取 index 名称
     */
    public function getIndexForEvent(string $eventType): string
    {
        $indices = config('opensearch.event_indices', []);
        $suffix = $indices[$eventType] ?? 'events-' . str_replace('_', '-', $eventType);

        return $this->getIndexName($suffix);
    }

    /**
     * 健康检查 / Ping
     */
    public function ping(): bool
    {
        $client = $this->getClient();
        if (!$client) {
            return false;
        }

        try {
            $this->debug('Ping start');
            $client->ping();
            $this->debug('Ping ok');
            return true;
        } catch (Throwable $e) {
            Log::warning('OpenSearch ping failed', [
                'message' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * 索引单个文档
     *
     * @param  string  $index  index 名称（完整名或后缀，会自动加前缀）
     * @param  array  $document  文档内容
     * @param  string|null  $id  文档 ID，为空则自动生成
     * @return array{success: bool, id?: string, error?: string} 索引结果
     */
    public function indexDocument(string $index, array $document, ?string $id = null): array
    {
        $client = $this->getClient();
        if (!$client) {
            return ['success' => false, 'error' => 'OpenSearch disabled'];
        }

        $indexName = str_contains($index, '-') ? $index : $this->getIndexName($index);

        $params = [
            'index' => $indexName,
            'body' => $document,
        ];
        if ($id !== null) {
            $params['id'] = $id;
        }

        try {
            $this->debug('Index document', ['index' => $indexName, 'id' => $id, 'document_keys' => array_keys($document)]);
            $response = $client->index($params);
            $responseArray = is_array($response) ? $response : (array) $response;
            $docId = $responseArray['_id'] ?? $id;
            $this->debug('Index document ok', ['index' => $indexName, '_id' => $docId]);

            return [
                'success' => true,
                'id' => $docId,
            ];
        } catch (Throwable $e) {
            $this->debug('Index document failed', ['index' => $indexName, 'error' => $e->getMessage()]);
            Log::error('OpenSearch index failed', [
                'index' => $indexName,
                'message' => $e->getMessage(),
                'document' => $document,
            ]);
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * 批量索引文档
     *
     * @param  string  $index  index 名称
     * @param  array<int, array>  $documents  [['id' => 'xxx', 'body' => [...]], ...]，id 可选
     * @return array{success: bool, indexed: int, errors: array, error?: string}
     */
    public function bulkIndex(string $index, array $documents): array
    {
        $client = $this->getClient();
        if (!$client) {
            return ['success' => false, 'indexed' => 0, 'errors' => [], 'error' => 'OpenSearch disabled'];
        }

        $indexName = str_contains($index, '-') ? $index : $this->getIndexName($index);

        $body = [];
        foreach ($documents as $doc) {
            $action = ['index' => ['_index' => $indexName]];
            if (!empty($doc['id'])) {
                $action['index']['_id'] = $doc['id'];
            }
            $body[] = $action;
            $body[] = $doc['body'] ?? $doc;
        }

        if (empty($body)) {
            return ['success' => true, 'indexed' => 0, 'errors' => []];
        }

        try {
            $this->debug('Bulk index', ['index' => $indexName, 'count' => count($documents)]);
            $response = $client->bulk(['body' => $body]);
            $responseArray = is_array($response) ? $response : (array) $response;

            $errors = [];
            $indexed = 0;
            if (isset($responseArray['items'])) {
                foreach ($responseArray['items'] as $item) {
                    $op = $item['index'] ?? $item['create'] ?? $item;
                    if (isset($op['error'])) {
                        $errors[] = $op['error'];
                    } else {
                        $indexed++;
                    }
                }
            }

            $this->debug('Bulk index ok', ['index' => $indexName, 'indexed' => $indexed, 'errors_count' => count($errors)]);
            return [
                'success' => empty($errors),
                'indexed' => $indexed,
                'errors' => $errors,
            ];
        } catch (Throwable $e) {
            $this->debug('Bulk index failed', ['index' => $indexName, 'error' => $e->getMessage()]);
            Log::error('OpenSearch bulk index failed', [
                'index' => $indexName,
                'message' => $e->getMessage(),
                'count' => count($documents),
            ]);
            return [
                'success' => false,
                'indexed' => 0,
                'errors' => [$e->getMessage()],
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * 根据 ID 获取文档
     *
     * @return array{success: bool, document?: array, found?: bool, error?: string}
     */
    public function getDocument(string $index, string $id): array
    {
        $client = $this->getClient();
        if (!$client) {
            return ['success' => false, 'error' => 'OpenSearch disabled'];
        }

        $indexName = str_contains($index, '-') ? $index : $this->getIndexName($index);

        try {
            $this->debug('Get document', ['index' => $indexName, 'id' => $id]);
            $response = $client->get([
                'index' => $indexName,
                'id' => $id,
            ]);
            $responseArray = is_array($response) ? $response : (array) $response;

            return [
                'success' => true,
                'found' => ($responseArray['found'] ?? false),
                'document' => $responseArray['_source'] ?? null,
                '_id' => $responseArray['_id'] ?? $id,
                '_index' => $responseArray['_index'] ?? $indexName,
            ];
        } catch (Throwable $e) {
            $this->debug('Get document failed', ['index' => $indexName, 'id' => $id, 'error' => $e->getMessage()]);
            $isNotFound = str_contains($e->getMessage(), '404') || str_contains($e->getMessage(), 'not_found');
            if (!$isNotFound) {
                Log::error('OpenSearch get document failed', [
                    'index' => $indexName,
                    'id' => $id,
                    'message' => $e->getMessage(),
                ]);
            }
            return [
                'success' => $isNotFound,
                'found' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * 搜索（支持单 index 或多 index 聚合）
     *
     * @param  string|array  $indices  index 名称或索引名数组，支持通配符如 'events-*'
     * @param  array  $query  OpenSearch 查询 DSL
     * @param  array  $options  size, from, sort, aggs 等
     * @return array{success: bool, hits?: array, total?: int, aggregations?: array, error?: string}
     */
    public function search(string|array $indices, array $query = [], array $options = []): array
    {
        $client = $this->getClient();
        if (!$client) {
            return ['success' => false, 'error' => 'OpenSearch disabled'];
        }

        $indexNames = is_array($indices)
            ? array_map(fn ($i) => str_contains($i, '-') ? $i : $this->getIndexName($i), $indices)
            : (str_contains($indices, '-') ? $indices : $this->getIndexName($indices));

        $params = [
            'index' => $indexNames,
            'body' => array_filter($query) ?: ['query' => ['match_all' => (object) []]],
        ];

        if (isset($options['size'])) {
            $params['body']['size'] = (int) $options['size'];
        }
        if (isset($options['from'])) {
            $params['body']['from'] = (int) $options['from'];
        }
        if (isset($options['sort'])) {
            $params['body']['sort'] = $options['sort'];
        }
        if (isset($options['aggs'])) {
            $params['body']['aggs'] = $options['aggs'];
        }
        if (isset($options['_source'])) {
            $params['body']['_source'] = $options['_source'];
        }

        try {
            $this->debug('Search', ['indices' => $indexNames, 'query' => $params['body']]);
            $response = $client->search($params);
            $responseArray = is_array($response) ? $response : (array) $response;

            $total = $responseArray['hits']['total'] ?? 0;
            if (is_array($total) && isset($total['value'])) {
                $total = $total['value'];
            }

            $this->debug('Search ok', ['indices' => $indexNames, 'total' => (int) $total, 'hits_count' => count($responseArray['hits']['hits'] ?? [])]);
            return [
                'success' => true,
                'hits' => $responseArray['hits']['hits'] ?? [],
                'total' => (int) $total,
                'aggregations' => $responseArray['aggregations'] ?? [],
            ];
        } catch (Throwable $e) {
            $this->debug('Search failed', ['indices' => $indexNames, 'error' => $e->getMessage()]);
            Log::error('OpenSearch search failed', [
                'indices' => $indexNames,
                'message' => $e->getMessage(),
            ]);
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * 多 index 聚合搜索（便捷方法）
     *
     * @param  array  $indexPatterns  ['events-*', 'events-deposit'] 等
     * @param  array  $query  查询
     * @param  array  $options  选项
     */
    public function searchMultiple(array $indexPatterns, array $query = [], array $options = []): array
    {
        $indices = array_map(fn ($p) => $this->getIndexName($p), $indexPatterns);

        return $this->search($indices, $query, $options);
    }

    /**
     * 创建 index（含 settings）
     */
    public function createIndex(string $index, array $settings = [], array $mappings = []): array
    {
        $client = $this->getClient();
        if (!$client) {
            return ['success' => false, 'error' => 'OpenSearch disabled'];
        }

        $indexName = str_contains($index, '-') ? $index : $this->getIndexName($index);

        $body = [];
        if (!empty($settings)) {
            $body['settings'] = $settings;
        }
        if (!empty($mappings)) {
            $body['mappings'] = $mappings;
        }

        try {
            $client->indices()->create([
                'index' => $indexName,
                'body' => $body,
            ]);
            return ['success' => true];
        } catch (Throwable $e) {
            if (str_contains($e->getMessage(), 'resource_already_exists')) {
                return ['success' => true, 'existed' => true];
            }
            Log::error('OpenSearch create index exception', [
                'index' => $indexName,
                'message' => $e->getMessage(),
            ]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * 创建/更新 index template
     *
     * @param  string  $name  template 名称
     * @param  array  $indexPatterns  匹配的 index 模式，如 ['playapi-events-*']
     * @param  array  $template  含 settings 和 mappings
     */
    public function putIndexTemplate(string $name, array $indexPatterns, array $template): array
    {
        $client = $this->getClient();
        if (!$client) {
            return ['success' => false, 'error' => 'OpenSearch disabled'];
        }

        $patterns = array_map(
            fn ($p) => str_contains($p, $this->indexPrefix) ? $p : $this->getIndexName($p),
            $indexPatterns
        );

        $body = [
            'index_patterns' => $patterns,
            ...$template,
        ];

        try {
            $client->indices()->putIndexTemplate([
                'name' => $name,
                'body' => $body,
            ]);
            return ['success' => true];
        } catch (Throwable $e) {
            Log::error('OpenSearch put index template failed', [
                'name' => $name,
                'message' => $e->getMessage(),
            ]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * 检查 index 是否存在
     */
    public function indexExists(string $index): bool
    {
        $client = $this->getClient();
        if (!$client) {
            return false;
        }

        $indexName = str_contains($index, '-') ? $index : $this->getIndexName($index);

        try {
            return $client->indices()->exists(['index' => $indexName]);
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * 上报事件到 OpenSearch（便捷方法）
     *
     * @param  string  $eventType  事件类型，对应 config.event_indices
     * @param  array  $payload  事件数据
     * @param  string|null  $id  文档 ID，指定时用于幂等（相同 ID 覆盖），不传则自动生成
     * @return array{success: bool, id?: string, error?: string}
     */
    public function indexEvent(string $eventType, array $payload, ?string $id = null): array
    {
        $index = $this->getIndexForEvent($eventType);
        $this->debug('Index event', ['event_type' => $eventType, 'index' => $index, 'id' => $id, 'payload_keys' => array_keys($payload)]);

        $document = array_merge([
            'event_type' => $eventType,
            '@timestamp' => now()->toIso8601String(),
        ], $payload);

        return $this->indexDocument($index, $document, $id);
    }

    /**
     * 应用配置中的 index 模版
     *
     * @return array{success: bool, applied: array, errors: array}
     */
    public function applyIndexTemplates(): array
    {
        $client = $this->getClient();
        if (!$client) {
            return ['success' => false, 'applied' => [], 'errors' => ['OpenSearch disabled']];
        }

        $templates = config('opensearch.index_templates', []);
        $applied = [];
        $errors = [];

        foreach ($templates as $name => $config) {
            $patterns = $config['index_patterns'] ?? [];
            $template = $config['template'] ?? [];

            if (empty($patterns) || empty($template)) {
                $errors[] = "Template {$name}: missing index_patterns or template";
                continue;
            }

            $fullPatterns = array_map(fn ($p) => $this->getIndexName($p), $patterns);
            $result = $this->putIndexTemplate($name, $fullPatterns, $template);

            if ($result['success']) {
                $applied[] = $name;
            } else {
                $errors[] = "Template {$name}: " . ($result['error'] ?? 'unknown');
            }
        }

        return [
            'success' => empty($errors),
            'applied' => $applied,
            'errors' => $errors,
        ];
    }

}
