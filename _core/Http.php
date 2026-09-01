<?php

declare(strict_types=1);

namespace Core;

/**
 * High-Performance HTTP Client & cURL Wrapper.
 * 
 * Provides connection pooling, automatic JSON serialization/deserialization,
 * configurable timeouts, configurable SSL verification, and concurrent `curl_multi_*` asynchronous execution.
 * 
 * @package Core
 */
class Http
{
    /**
     * Sends a GET HTTP request and parses response JSON/headers.
     * 
     * @param string $url Target endpoint URL
     * @param array $headers Optional HTTP header strings
     * @param int $timeout Timeout in seconds (default: 10)
     * @param bool $sslVerify Enable or disable SSL certificate verification (default: false)
     * @return array<string, mixed> Response map `['status' => int, 'body' => string, 'json' => array|null, 'time_ms' => float]`
     */
    public static function get(string $url, array $headers = [], int $timeout = 10, bool $sslVerify = false): array
    {
        return self::send('GET', $url, null, $headers, $timeout, $sslVerify);
    }

    /**
     * Sends a POST request with JSON or form payload.
     * 
     * @param string $url Target endpoint URL
     * @param mixed $payload Array dictionary (auto JSON encoded) or raw string payload
     * @param array $headers Optional HTTP header strings
     * @param int $timeout Timeout in seconds (default: 10)
     * @param bool $sslVerify Enable or disable SSL certificate verification (default: false)
     * @return array<string, mixed>
     */
    public static function post(string $url, mixed $payload = [], array $headers = [], int $timeout = 10, bool $sslVerify = false): array
    {
        return self::send('POST', $url, $payload, $headers, $timeout, $sslVerify);
    }

    /**
     * Sends a customizable HTTP request (`PUT`, `DELETE`, `PATCH`, etc.).
     * 
     * @param string $method HTTP method string
     * @param string $url Target URL
     * @param mixed $payload Payload data
     * @param array $headers HTTP header strings
     * @param int $timeout Request timeout seconds
     * @param bool $sslVerify Enable or disable SSL certificate verification (default: false)
     * @return array<string, mixed>
     */
    public static function send(string $method, string $url, mixed $payload = null, array $headers = [], int $timeout = 10, bool $sslVerify = false): array
    {
        $ch = curl_init();
        $start = microtime(true);

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, min(5, $timeout));
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $sslVerify);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $sslVerify ? 2 : 0);

        $hasContentType = false;
        foreach ($headers as $header) {
            if (stripos($header, 'Content-Type:') === 0) {
                $hasContentType = true;
                break;
            }
        }

        if ($payload !== null) {
            if (is_array($payload)) {
                $payloadStr = json_encode($payload, JSON_UNESCAPED_UNICODE);
                if (!$hasContentType) {
                    $headers[] = 'Content-Type: application/json';
                }
            } else {
                $payloadStr = (string) $payload;
            }
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payloadStr);
        }

        if (!empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

        $response = curl_exec($ch);
        $timeMs = round((microtime(true) - $start) * 1000, 2);

        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            Logger::error("HTTP Client Error [{$method} {$url}]: {$error}");
            return [
                'status' => 0,
                'error' => $error,
                'body' => '',
                'json' => null,
                'time_ms' => $timeMs
            ];
        }

        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $body = substr($response, $headerSize);
        $json = json_decode($body, true);

        return [
            'status' => $status,
            'body' => $body,
            'json' => json_last_error() === JSON_ERROR_NONE ? $json : null,
            'time_ms' => $timeMs
        ];
    }

    /**
     * Executes multiple concurrent cURL requests asynchronously.
     * 
     * @param array<string, array> $requests Map of request descriptors `['req1' => ['url' => '...', 'method' => 'GET']]`
     * @param bool $sslVerify Enable or disable SSL certificate verification (default: false)
     * @return array<string, array> Map of results per request key
     */
    public static function multi(array $requests, bool $sslVerify = false): array
    {
        $mh = curl_multi_init();
        $handles = [];
        $results = [];

        foreach ($requests as $key => $req) {
            $ch = curl_init();
            $url = $req['url'] ?? '';
            $method = strtoupper($req['method'] ?? 'GET');
            $timeout = $req['timeout'] ?? 10;
            $verify = $req['ssl_verify'] ?? $sslVerify;

            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $verify);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $verify ? 2 : 0);

            if (isset($req['payload'])) {
                $payload = is_array($req['payload']) ? json_encode($req['payload']) : $req['payload'];
                curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            }

            curl_multi_add_handle($mh, $ch);
            $handles[$key] = $ch;
        }

        do {
            $status = curl_multi_exec($mh, $active);
            if ($active) {
                curl_multi_select($mh);
            }
        } while ($active && $status === CURLM_OK);

        foreach ($handles as $key => $ch) {
            $body = curl_multi_getcontent($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $json = json_decode($body, true);

            $results[$key] = [
                'status' => $code,
                'body' => $body,
                'json' => json_last_error() === JSON_ERROR_NONE ? $json : null
            ];
            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);
        }

        curl_multi_close($mh);
        return $results;
    }
}
