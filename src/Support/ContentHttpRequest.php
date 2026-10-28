<?php

namespace Fayne\PageBuilder\Support;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

final class ContentHttpRequest
{
    public function execute(array $config, bool $public = false): array
    {
        $url = $config['url'] ?? '';
        if (! is_string($url) || ! is_string($config['method'] ?? 'GET')) {
            throw new InvalidArgumentException(__('filament-page-builder::ui.http_url_method_strings'));
        }
        $method = strtoupper($config['method'] ?? 'GET');
        $endpoint = config('filament-page-builder.http.endpoints', [])[$url] ?? null;
        if (! is_string($url) || ! is_array($endpoint) || parse_url($url, PHP_URL_SCHEME) !== 'https' || parse_url($url, PHP_URL_USER) !== null || parse_url($url, PHP_URL_FRAGMENT) !== null
            || ! in_array($method, $endpoint['methods'] ?? [], true) || ! in_array($method, ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'], true)
            || ($public && ($method !== 'GET' || ! ($endpoint['public'] ?? false)))) {
            throw new InvalidArgumentException(__('filament-page-builder::ui.http_request_not_allowed'));
        }
        $headers = $this->pairs($config['headers'] ?? []);
        $pending = Http::timeout(10)->connectTimeout(5)->withoutRedirecting()->withHeaders([...$headers, ...($endpoint['headers'] ?? [])]);
        $body = $config['body'] ?? [];
        $type = $config['bodyType'] ?? 'raw';
        if ($method === 'GET') {
            $options = ['query' => is_array($body) ? $this->pairs($body) : []];
        } elseif ($type === 'raw') {
            $decoded = is_string($body) ? json_decode($body, true) : $body;
            if (! is_array($decoded)) {
                throw new InvalidArgumentException(__('filament-page-builder::ui.http_raw_body_object'));
            }
            $options = ['json' => $decoded];
        } elseif ($type === 'form-data') {
            $options = ['multipart' => collect($this->pairs($body))->map(fn ($value, $name) => ['name' => $name, 'contents' => (string) $value])->values()->all()];
        } elseif ($type === 'x-www-form-urlencoded') {
            $options = ['form_params' => $this->pairs($body)];
        } else {
            throw new InvalidArgumentException(__('filament-page-builder::ui.http_body_type_unsupported'));
        }
        try {
            $response = $pending->send($method, $url, $options);

            return ['ok' => $response->successful(), 'status' => $response->status(), 'response_body' => $response->body()];
        } catch (ConnectionException) {
            return ['ok' => false, 'status' => null, 'response_body' => ''];
        }
    }

    public function resolve(array $config, bool $public = false, array $options = []): array
    {
        $target = $config[$config['mappingTargetKey'] ?? 'targetAttribute'] ?? $config['targetAttribute'] ?? '';
        $path = $config[$config['mappingPathKey'] ?? 'responsePath'] ?? $config['responsePath'] ?? '';
        if (! is_string($target) || ! preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $target) || ! is_string($path) || $path === '') {
            throw new InvalidArgumentException(__('filament-page-builder::ui.http_response_mapping_invalid'));
        }
        $response = ($options['fixedResponse'] ?? false) && is_array($config['fixedResponseData'] ?? null) ? $config['fixedResponseData'] : $this->execute($config, $public);
        $body = json_decode($response['response_body'] ?? $response['responseBody'] ?? '', true);

        return ['target' => $target, 'value' => ($response['ok'] ?? false) && is_array($body) ? data_get($body, $path) : null];
    }

    public function pairs(mixed $rows): array
    {
        if (! is_array($rows)) {
            throw new InvalidArgumentException(__('filament-page-builder::ui.http_pairs_array'));
        }
        $result = [];
        foreach ($rows as $key => $value) {
            if (is_array($value) && is_string($value['key'] ?? null)) {
                $result[$value['key']] = (string) ($value['value'] ?? '');
            } elseif (is_string($key) && is_scalar($value)) {
                $result[$key] = (string) $value;
            } else {
                throw new InvalidArgumentException(__('filament-page-builder::ui.http_pairs_invalid'));
            }
        }

        return $result;
    }
}
