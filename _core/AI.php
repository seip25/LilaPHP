<?php

declare(strict_types=1);

namespace Core;

/**
 * Universal Multi-Provider AI & LLM Engine (`Core\AI`).
 * 
 * Provides unified, zero-dependency REST integration for leading AI providers:
 * - DeepSeek (DeepSeek-V4-Flash, DeepSeek-Chat, DeepSeek-Reasoner with auto-fallback)
 * - Google Gemini (Gemini 2.0 Flash, Gemini 2.0 Pro, Gemini 1.5 Flash/Pro)
 * - OpenAI (GPT-4o, GPT-4o-mini, o1, o3-mini)
 * - Anthropic Claude (Claude 3.7 Sonnet, Claude 3.5 Sonnet, Claude 3.5 Haiku)
 * - Ollama / Local Models (Llama 3, Mistral, Qwen, DeepSeek-R1)
 * - Custom OpenAI-compatible endpoints (Groq, Together, OpenRouter, vLLM)
 * 
 * @package Core
 */
class AI
{
    /**
     * In-memory cache for repeated prompt queries in current request lifecycle.
     * @var array<string, string>
     */
    private static array $memoryCache = [];

    /**
     * Sends a prompt to the configured default provider and returns the raw text response.
     * 
     * @param string $prompt User prompt text
     * @param array<string, mixed> $options Custom options (provider, model, temperature, max_tokens, system, etc.)
     * @param string|null $apiKey Explicit API key override
     * @return string Generated text response
     * @example $reply = AI::text("Explain quantum computing in one sentence.");
     */
    public static function text(string $prompt, array $options = [], ?string $apiKey = null): string
    {
        $res = self::generate($prompt, $options, $apiKey);
        return $res['text'] ?? '';
    }

    /**
     * Sends a prompt and automatically parses the JSON response into a PHP array.
     * 
     * @param string $prompt User prompt requesting structured JSON data
     * @param array<string, mixed> $options Custom options
     * @param string|null $apiKey Explicit API key override
     * @return array<string, mixed>|null Parsed JSON array or null on failure
     * @example $data = AI::json("Generate a user profile with name, email, and age.");
     */
    public static function json(string $prompt, array $options = [], ?string $apiKey = null): ?array
    {
        $options['json_mode'] = true;
        $res = self::generate($prompt, $options, $apiKey);
        $text = trim($res['text'] ?? '');

        // Strip markdown code fences if present (```json ... ```)
        if (preg_match('/```(?:json)?\s*([\s\S]*?)\s*```/i', $text, $matches)) {
            $text = trim($matches[1]);
        }

        $decoded = json_decode($text, true);
        return (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) ? $decoded : null;
    }

    /**
     * Unified generation dispatcher across all supported AI providers.
     * 
     * @param string|array $prompt String prompt or array of conversation messages `[['role' => 'user', 'content' => '...']]`
     * @param array<string, mixed> $options Provider-specific options
     * @param string|null $apiKey Explicit API key override
     * @return array<string, mixed> Standardized response: `['provider' => ..., 'model' => ..., 'text' => ..., 'usage' => [...], 'raw' => [...]]`
     */
    public static function generate(string|array $prompt, array $options = [], ?string $apiKey = null): array
    {
        $provider = strtolower((string) ($options['provider'] ?? Config::$AI_PROVIDER ?: 'deepseek'));

        return match ($provider) {
            'deepseek' => self::deepseek($prompt, $options, $apiKey),
            'gemini', 'google' => self::gemini($prompt, $options, $apiKey),
            'openai' => self::openai($prompt, $options, $apiKey),
            'anthropic', 'claude' => self::anthropic($prompt, $options, $apiKey),
            'ollama', 'local' => self::ollama($prompt, $options),
            'custom' => self::custom($prompt, $options, $apiKey),
            default => self::deepseek($prompt, $options, $apiKey)
        };
    }

    /**
     * DeepSeek AI Integration (`https://api.deepseek.com/chat/completions`).
     * Features automatic model fallback (e.g. deepseek-v4-flash -> deepseek-chat).
     * 
     * @param string|array $prompt String prompt or messages list
     * @param array<string, mixed> $options Model, temperature, max_tokens, system, json_mode
     * @param string|null $apiKey API key (defaults to DEEPSEEK_API_KEY / API_IA_KEY)
     * @return array<string, mixed>
     */
    public static function deepseek(string|array $prompt, array $options = [], ?string $apiKey = null): array
    {
        $key = self::resolveKey('deepseek', $apiKey);
        $primaryModel = (string) ($options['model'] ?? 'deepseek-v4-flash');
        $fallbackModel = (string) ($options['fallback_model'] ?? 'deepseek-chat');
        $messages = self::normalizeMessages($prompt, $options['system'] ?? null);

        $payload = [
            'model' => $primaryModel,
            'messages' => $messages,
            'temperature' => (float) ($options['temperature'] ?? 0.7),
            'stream' => false
        ];

        if (isset($options['max_tokens'])) {
            $payload['max_tokens'] = (int) $options['max_tokens'];
        }

        if (!empty($options['json_mode'])) {
            $payload['response_format'] = ['type' => 'json_object'];
        }

        $headers = [
            'Content-Type: application/json',
            "Authorization: Bearer {$key}"
        ];

        $timeout = (int) ($options['timeout'] ?? 30);
        $res = Http::post('https://api.deepseek.com/chat/completions', $payload, $headers, $timeout);

        // Auto-fallback if primary model failed or returned 404 / 400
        if ($res['status'] !== 200 && $primaryModel !== $fallbackModel) {
            Logger::warning("DeepSeek primary model [{$primaryModel}] returned HTTP {$res['status']}, retrying with fallback [{$fallbackModel}]...");
            $payload['model'] = $fallbackModel;
            $res = Http::post('https://api.deepseek.com/chat/completions', $payload, $headers, $timeout);
            $primaryModel = $fallbackModel;
        }

        if ($res['status'] !== 200) {
            $errMsg = $res['json']['error']['message'] ?? "DeepSeek HTTP {$res['status']} error";
            Logger::error("AI DeepSeek Error: {$errMsg}");
            return [
                'provider' => 'deepseek',
                'model' => $primaryModel,
                'text' => '',
                'error' => $errMsg,
                'status' => $res['status'],
                'raw' => $res['json']
            ];
        }

        $choice = $res['json']['choices'][0]['message'] ?? [];
        $text = trim((string) ($choice['content'] ?? ''));
        $usage = $res['json']['usage'] ?? [];

        return [
            'provider' => 'deepseek',
            'model' => $primaryModel,
            'text' => $text,
            'reasoning' => $choice['reasoning_content'] ?? null,
            'error' => null,
            'status' => 200,
            'usage' => [
                'prompt_tokens' => $usage['prompt_tokens'] ?? 0,
                'completion_tokens' => $usage['completion_tokens'] ?? 0,
                'total_tokens' => $usage['total_tokens'] ?? 0,
            ],
            'raw' => $res['json']
        ];
    }

    /**
     * Google Gemini API Integration (`generativelanguage.googleapis.com`).
     * Supports Gemini 3.7 Flash, Gemini 3.6 Flash, Gemini 3.5 Flash, Gemini 3.1 Pro,
     * Gemini 2.0 Flash/Pro with automated fallback.
     * 
     * @param string|array $prompt String prompt or messages list
     * @param array<string, mixed> $options Model (gemini-3.7-flash, gemini-3.6-flash, gemini-3.5-flash, gemini-3.1-pro), fallback_model, temperature, system
     * @param string|null $apiKey API key (defaults to GEMINI_API_KEY / API_IA_KEY)
     * @return array<string, mixed>
     */
    public static function gemini(string|array $prompt, array $options = [], ?string $apiKey = null): array
    {
        $key = self::resolveKey('gemini', $apiKey);
        $primaryModel = (string) ($options['model'] ?? 'gemini-3.7-flash');
        $fallbackModel = (string) ($options['fallback_model'] ?? 'gemini-3.5-flash');

        $contents = [];
        if (is_string($prompt)) {
            $contents[] = [
                'role' => 'user',
                'parts' => [['text' => $prompt]]
            ];
        } else {
            foreach ($prompt as $msg) {
                $role = ($msg['role'] ?? 'user') === 'assistant' ? 'model' : 'user';
                $contents[] = [
                    'role' => $role,
                    'parts' => [['text' => (string) ($msg['content'] ?? '')]]
                ];
            }
        }

        $payload = [
            'contents' => $contents,
            'generationConfig' => [
                'temperature' => (float) ($options['temperature'] ?? 0.7),
            ]
        ];

        if (!empty($options['json_mode'])) {
            $payload['generationConfig']['responseMimeType'] = 'application/json';
        }

        if (!empty($options['system'])) {
            $payload['systemInstruction'] = [
                'parts' => [['text' => (string) $options['system']]]
            ];
        }

        $timeout = (int) ($options['timeout'] ?? 30);
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$primaryModel}:generateContent?key={$key}";
        $res = Http::post($url, $payload, ['Content-Type: application/json'], $timeout);

        // Auto-fallback if primary model failed or returned 404 / 400
        if ($res['status'] !== 200 && $primaryModel !== $fallbackModel) {
            Logger::warning("Gemini primary model [{$primaryModel}] returned HTTP {$res['status']}, retrying with fallback [{$fallbackModel}]...");
            $fallbackUrl = "https://generativelanguage.googleapis.com/v1beta/models/{$fallbackModel}:generateContent?key={$key}";
            $res = Http::post($fallbackUrl, $payload, ['Content-Type: application/json'], $timeout);
            $primaryModel = $fallbackModel;
        }

        if ($res['status'] !== 200) {
            $errMsg = $res['json']['error']['message'] ?? "Gemini HTTP {$res['status']} error";
            Logger::error("AI Gemini Error: {$errMsg}");
            return [
                'provider' => 'gemini',
                'model' => $primaryModel,
                'text' => '',
                'error' => $errMsg,
                'status' => $res['status'],
                'raw' => $res['json']
            ];
        }

        $candidate = $res['json']['candidates'][0]['content']['parts'][0]['text'] ?? '';
        $usageMeta = $res['json']['usageMetadata'] ?? [];

        return [
            'provider' => 'gemini',
            'model' => $primaryModel,
            'text' => trim((string) $candidate),
            'error' => null,
            'status' => 200,
            'usage' => [
                'prompt_tokens' => $usageMeta['promptTokenCount'] ?? 0,
                'completion_tokens' => $usageMeta['candidatesTokenCount'] ?? 0,
                'total_tokens' => $usageMeta['totalTokenCount'] ?? 0,
            ],
            'raw' => $res['json']
        ];
    }

    /**
     * OpenAI API Integration (`https://api.openai.com/v1/chat/completions`).
     * Supports GPT-4o, GPT-4o-mini, o1, o3-mini.
     * 
     * @param string|array $prompt String prompt or messages list
     * @param array<string, mixed> $options Model, temperature, max_tokens, system prompt, json_mode
     * @param string|null $apiKey API key (defaults to OPENAI_API_KEY / API_IA_KEY)
     * @return array<string, mixed>
     */
    public static function openai(string|array $prompt, array $options = [], ?string $apiKey = null): array
    {
        $key = self::resolveKey('openai', $apiKey);
        $model = (string) ($options['model'] ?? 'gpt-4o-mini');
        $messages = self::normalizeMessages($prompt, $options['system'] ?? null);

        $payload = [
            'model' => $model,
            'messages' => $messages,
            'temperature' => (float) ($options['temperature'] ?? 0.7),
        ];

        if (isset($options['max_tokens'])) {
            $payload['max_tokens'] = (int) $options['max_tokens'];
        }

        if (!empty($options['json_mode'])) {
            $payload['response_format'] = ['type' => 'json_object'];
        }

        $headers = [
            'Content-Type: application/json',
            "Authorization: Bearer {$key}"
        ];

        $timeout = (int) ($options['timeout'] ?? 30);
        $res = Http::post('https://api.openai.com/v1/chat/completions', $payload, $headers, $timeout);

        if ($res['status'] !== 200) {
            $errMsg = $res['json']['error']['message'] ?? "OpenAI HTTP {$res['status']} error";
            Logger::error("AI OpenAI Error: {$errMsg}");
            return [
                'provider' => 'openai',
                'model' => $model,
                'text' => '',
                'error' => $errMsg,
                'status' => $res['status'],
                'raw' => $res['json']
            ];
        }

        $choice = $res['json']['choices'][0]['message'] ?? [];
        $text = trim((string) ($choice['content'] ?? ''));
        $usage = $res['json']['usage'] ?? [];

        return [
            'provider' => 'openai',
            'model' => $model,
            'text' => $text,
            'error' => null,
            'status' => 200,
            'usage' => [
                'prompt_tokens' => $usage['prompt_tokens'] ?? 0,
                'completion_tokens' => $usage['completion_tokens'] ?? 0,
                'total_tokens' => $usage['total_tokens'] ?? 0,
            ],
            'raw' => $res['json']
        ];
    }

    /**
     * Anthropic Claude API Integration (`https://api.anthropic.com/v1/messages`).
     * Supports Claude 3.7 Sonnet, Claude 3.5 Sonnet, Claude 3.5 Haiku.
     * 
     * @param string|array $prompt String prompt or messages list
     * @param array<string, mixed> $options Model (claude-3-7-sonnet-latest, etc.), max_tokens
     * @param string|null $apiKey API key (defaults to ANTHROPIC_API_KEY / API_IA_KEY)
     * @return array<string, mixed>
     */
    public static function anthropic(string|array $prompt, array $options = [], ?string $apiKey = null): array
    {
        $key = self::resolveKey('anthropic', $apiKey);
        $model = (string) ($options['model'] ?? 'claude-3-7-sonnet-latest');
        $messages = [];

        if (is_string($prompt)) {
            $messages[] = ['role' => 'user', 'content' => $prompt];
        } else {
            foreach ($prompt as $msg) {
                if (($msg['role'] ?? '') !== 'system') {
                    $messages[] = [
                        'role' => $msg['role'] ?? 'user',
                        'content' => (string) ($msg['content'] ?? '')
                    ];
                }
            }
        }

        $payload = [
            'model' => $model,
            'messages' => $messages,
            'max_tokens' => (int) ($options['max_tokens'] ?? 1024),
        ];

        if (!empty($options['system'])) {
            $payload['system'] = (string) $options['system'];
        }

        $headers = [
            'Content-Type: application/json',
            "x-api-key: {$key}",
            'anthropic-version: 2023-06-01'
        ];

        $timeout = (int) ($options['timeout'] ?? 30);
        $res = Http::post('https://api.anthropic.com/v1/messages', $payload, $headers, $timeout);

        if ($res['status'] !== 200) {
            $errMsg = $res['json']['error']['message'] ?? "Anthropic HTTP {$res['status']} error";
            Logger::error("AI Anthropic Error: {$errMsg}");
            return [
                'provider' => 'anthropic',
                'model' => $model,
                'text' => '',
                'error' => $errMsg,
                'status' => $res['status'],
                'raw' => $res['json']
            ];
        }

        $content = $res['json']['content'][0]['text'] ?? '';
        $usage = $res['json']['usage'] ?? [];

        return [
            'provider' => 'anthropic',
            'model' => $model,
            'text' => trim((string) $content),
            'error' => null,
            'status' => 200,
            'usage' => [
                'prompt_tokens' => $usage['input_tokens'] ?? 0,
                'completion_tokens' => $usage['output_tokens'] ?? 0,
                'total_tokens' => ($usage['input_tokens'] ?? 0) + ($usage['output_tokens'] ?? 0),
            ],
            'raw' => $res['json']
        ];
    }

    /**
     * Local Ollama API Integration (`http://localhost:11434/api/chat`).
     * 
     * @param string|array $prompt String prompt or messages list
     * @param array<string, mixed> $options Model (llama3, mistral, deepseek-r1, qwen), base_url
     * @return array<string, mixed>
     */
    public static function ollama(string|array $prompt, array $options = []): array
    {
        $baseUrl = rtrim((string) ($options['base_url'] ?? Config::$OLLAMA_BASE_URL ?: 'http://localhost:11434'), '/');
        $model = (string) ($options['model'] ?? 'deepseek-r1');
        $messages = self::normalizeMessages($prompt, $options['system'] ?? null);

        $payload = [
            'model' => $model,
            'messages' => $messages,
            'stream' => false,
        ];

        if (!empty($options['json_mode'])) {
            $payload['format'] = 'json';
        }

        $timeout = (int) ($options['timeout'] ?? 60);
        $res = Http::post("{$baseUrl}/api/chat", $payload, ['Content-Type: application/json'], $timeout);

        if ($res['status'] !== 200) {
            $errMsg = $res['json']['error'] ?? "Ollama HTTP {$res['status']} error";
            Logger::error("AI Ollama Error: {$errMsg}");
            return [
                'provider' => 'ollama',
                'model' => $model,
                'text' => '',
                'error' => $errMsg,
                'status' => $res['status'],
                'raw' => $res['json']
            ];
        }

        $text = trim((string) ($res['json']['message']['content'] ?? ''));

        return [
            'provider' => 'ollama',
            'model' => $model,
            'text' => $text,
            'error' => null,
            'status' => 200,
            'usage' => [
                'prompt_tokens' => $res['json']['prompt_eval_count'] ?? 0,
                'completion_tokens' => $res['json']['eval_count'] ?? 0,
                'total_tokens' => ($res['json']['prompt_eval_count'] ?? 0) + ($res['json']['eval_count'] ?? 0),
            ],
            'raw' => $res['json']
        ];
    }

    /**
     * Custom OpenAI-compatible Provider (Groq, Together, OpenRouter, vLLM, etc.).
     * 
     * @param string|array $prompt
     * @param array<string, mixed> $options Must include 'base_url' or 'endpoint'
     * @param string|null $apiKey
     * @return array<string, mixed>
     */
    public static function custom(string|array $prompt, array $options = [], ?string $apiKey = null): array
    {
        $endpoint = (string) ($options['endpoint'] ?? ($options['base_url'] ?? '') . '/chat/completions');
        $key = (string) ($apiKey ?? Config::$AI_KEY);
        $model = (string) ($options['model'] ?? 'default');
        $messages = self::normalizeMessages($prompt, $options['system'] ?? null);

        $payload = [
            'model' => $model,
            'messages' => $messages,
            'temperature' => (float) ($options['temperature'] ?? 0.7),
        ];

        $headers = ['Content-Type: application/json'];
        if ($key !== '') {
            $headers[] = "Authorization: Bearer {$key}";
        }

        $timeout = (int) ($options['timeout'] ?? 30);
        $res = Http::post($endpoint, $payload, $headers, $timeout);

        if ($res['status'] !== 200) {
            return [
                'provider' => 'custom',
                'model' => $model,
                'text' => '',
                'error' => $res['json']['error']['message'] ?? "Custom AI HTTP {$res['status']}",
                'status' => $res['status'],
                'raw' => $res['json']
            ];
        }

        $text = trim((string) ($res['json']['choices'][0]['message']['content'] ?? ''));

        return [
            'provider' => 'custom',
            'model' => $model,
            'text' => $text,
            'error' => null,
            'status' => 200,
            'raw' => $res['json']
        ];
    }

    /**
     * AI Rate Limiting Helper for public chat endpoints (per-minute & per-day).
     * 
     * @param string $ip Client IP address
     * @param int $limitPerMinute Max queries allowed per minute (default: 6)
     * @param int $limitPerDay Max queries allowed per 24 hours (default: 30)
     * @return array<string, mixed> `['allowed' => bool, 'reason' => string|null, 'status' => int]`
     */
    public static function checkRateLimit(string $ip, int $limitPerMinute = 6, int $limitPerDay = 30): array
    {
        $minuteKey = "ai_limit:min:{$ip}:" . date('YmdHi');
        $dayKey = "ai_limit:day:{$ip}:" . date('Ymd');

        $minCount = (int) Cache::get($minuteKey, 0);
        if ($minCount >= $limitPerMinute) {
            return [
                'allowed' => false,
                'status' => 429,
                'error' => 'You are sending AI queries too quickly. Please wait a moment before trying again.'
            ];
        }

        $dayCount = (int) Cache::get($dayKey, 0);
        if ($dayCount >= $limitPerDay) {
            return [
                'allowed' => false,
                'status' => 429,
                'error' => 'Daily AI query limit reached. Please contact support for assistance.'
            ];
        }

        // Increment counters
        Cache::set($minuteKey, $minCount + 1, 65);
        Cache::set($dayKey, $dayCount + 1, 86400);

        return ['allowed' => true, 'status' => 200, 'error' => null];
    }

    /**
     * Resolves API Key by provider hierarchy with generic fallback (`API_IA_KEY`).
     * 
     * @param string $provider Provider name (deepseek, gemini, openai, anthropic)
     * @param string|null $explicitKey Explicit key passed to method call
     * @return string Resolved API key
     */
    public static function resolveKey(string $provider, ?string $explicitKey = null): string
    {
        if (!empty($explicitKey)) {
            return $explicitKey;
        }

        return match ($provider) {
            'deepseek' => Config::$DEEPSEEK_API_KEY ?: Config::$AI_KEY,
            'gemini', 'google' => Config::$GEMINI_API_KEY ?: Config::$AI_KEY,
            'openai' => Config::$OPENAI_API_KEY ?: Config::$AI_KEY,
            'anthropic', 'claude' => Config::$ANTHROPIC_API_KEY ?: Config::$AI_KEY,
            default => Config::$AI_KEY
        };
    }

    /**
     * Converts a string or array into OpenAI/DeepSeek standard chat messages format.
     * 
     * @param string|array $prompt
     * @param string|null $systemPrompt
     * @return array<int, array<string, string>>
     */
    private static function normalizeMessages(string|array $prompt, ?string $systemPrompt = null): array
    {
        $messages = [];

        if (!empty($systemPrompt)) {
            $messages[] = ['role' => 'system', 'content' => (string) $systemPrompt];
        }

        if (is_string($prompt)) {
            $messages[] = ['role' => 'user', 'content' => $prompt];
        } elseif (is_array($prompt)) {
            foreach ($prompt as $msg) {
                if (isset($msg['role'], $msg['content'])) {
                    $messages[] = [
                        'role' => (string) $msg['role'],
                        'content' => (string) $msg['content']
                    ];
                }
            }
        }

        return $messages;
    }
}
