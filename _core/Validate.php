<?php

namespace Core;

/**
 * High-performance API Input Validation engine.
 * 
 * Free of Twig or Session translation dependencies. Resolves validation messages
 * directly using `$_REQUEST['lang'] ?? 'en'` and caches them in memory.
 * 
 * @package Core
 */
class Validate
{
    private static array $messagesCache = [];

    /**
     * Resolves and loads validation messages for a requested language code.
     * 
     * @param string|null $lang Requested language code (e.g., 'es', 'en', 'pt-br')
     * @return array<string, string>
     * @example $messages = \Core\Validate::getMessages('es');
     */
    public static function getMessages(?string $lang = null): array
    {
        $lang = $lang ?? (string) ($_REQUEST['lang'] ?? 'en');
        $lang = strtolower(trim($lang));

        if (!in_array($lang, ['en', 'es', 'pt-br', 'pt'], true)) {
            $lang = 'en';
        }
        if ($lang === 'pt') {
            $lang = 'pt-br';
        }

        if (!isset(self::$messagesCache[$lang])) {
            $file = __DIR__ . "/locales/validation_{$lang}.php";
            self::$messagesCache[$lang] = file_exists($file) ? require $file : require __DIR__ . "/locales/validation_en.php";
        }

        return self::$messagesCache[$lang];
    }

    /**
     * Validates a data map against defined rules.
     * 
     * @param array $data Input payload dictionary
     * @param array<string, string|array> $rules Map of field names to validation rules
     * @param string|null $lang Language code override
     * @return array<string, array<int, string>> Map of errors per field (empty if valid)
     * @example $errors = \Core\Validate::check($_POST, ['email' => 'required|email', 'age' => 'number|min:18']);
     */
    public static function check(array $data, array $rules, ?string $lang = null): array
    {
        $messages = self::getMessages($lang);
        $errors = [];

        foreach ($rules as $field => $fieldRules) {
            $ruleList = is_string($fieldRules) ? explode('|', $fieldRules) : $fieldRules;
            $value = $data[$field] ?? null;

            foreach ($ruleList as $rule) {
                $ruleName = $rule;
                $parameter = null;

                if (str_contains($rule, ':')) {
                    [$ruleName, $parameter] = explode(':', $rule, 2);
                }

                $ruleName = trim($ruleName);
                $errorMsg = self::validateField($field, $value, $ruleName, $parameter, $messages);

                if ($errorMsg !== null) {
                    $errors[$field][] = $errorMsg;
                    break;
                }
            }
        }

        return $errors;
    }

    /**
     * Validates inputs and halts execution with a 422/400 JSON error if validation fails.
     * 
     * @param array $data Input payload dictionary
     * @param array<string, string|array> $rules Map of validation rules
     * @param string|null $lang Language code override
     * @return array Sanitized input data containing only the validated fields
     * @example $clean = \Core\Validate::assert($_POST, ['email' => 'required|email']);
     */
    public static function assert(array $data, array $rules, ?string $lang = null): array
    {
        $errors = self::check($data, $rules, $lang);

        if (!empty($errors)) {
            Response::error('Validation failed', 422, $errors);
        }

        return array_intersect_key($data, $rules);
    }

    /**
     * Evaluates a single rule on a specific value and formats the error message.
     * 
     * @param string $field Field name
     * @param mixed $value Field value
     * @param string $rule Validation rule name
     * @param string|null $parameter Rule parameter (if applicable)
     * @param array $messages Translation dictionary
     * @return string|null Error message or null if valid
     * @example $err = self::validateField('age', 15, 'min', '18', $messages);
     */
    private static function validateField(string $field, mixed $value, string $rule, ?string $parameter, array $messages): ?string
    {
        if ($rule === 'required') {
            if ($value === null || $value === '' || (is_array($value) && empty($value))) {
                return self::formatMessage($messages['required'] ?? "Field ':field' is required", $field);
            }
            return null;
        }

        if ($value === null || $value === '') {
            return null;
        }

        return match ($rule) {
            'email' => filter_var($value, FILTER_VALIDATE_EMAIL) === false ? self::formatMessage($messages['email'] ?? "Invalid email", $field) : null,
            'url' => filter_var($value, FILTER_VALIDATE_URL) === false ? self::formatMessage($messages['url'] ?? "Invalid URL", $field) : null,
            'ip' => filter_var($value, FILTER_VALIDATE_IP) === false ? self::formatMessage($messages['ip'] ?? "Invalid IP", $field) : null,
            'number', 'numeric' => !is_numeric($value) ? self::formatMessage($messages['number'] ?? "Must be numeric", $field) : null,
            'integer', 'int' => filter_var($value, FILTER_VALIDATE_INT) === false ? self::formatMessage($messages['integer'] ?? "Must be an integer", $field) : null,
            'float' => filter_var($value, FILTER_VALIDATE_FLOAT) === false ? self::formatMessage($messages['float'] ?? "Must be a float", $field) : null,
            'boolean', 'bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) === null ? self::formatMessage($messages['boolean'] ?? "Must be boolean", $field) : null,
            'alpha' => preg_match('/^[a-zA-Z\s]+$/', (string)$value) !== 1 ? self::formatMessage($messages['alpha'] ?? "Only letters allowed", $field) : null,
            'alphanumeric' => preg_match('/^[a-zA-Z0-9\s]+$/', (string)$value) !== 1 ? self::formatMessage($messages['alphanumeric'] ?? "Only alphanumeric allowed", $field) : null,
            'min_length' => mb_strlen((string)$value) < (int)$parameter ? self::formatMessage($messages['min_length'] ?? "Minimum length :min_length", $field, ['min_length' => $parameter]) : null,
            'max_length' => mb_strlen((string)$value) > (int)$parameter ? self::formatMessage($messages['max_length'] ?? "Maximum length :max_length", $field, ['max_length' => $parameter]) : null,
            'length' => mb_strlen((string)$value) !== (int)$parameter ? self::formatMessage($messages['length'] ?? "Exact length :length required", $field, ['length' => $parameter]) : null,
            'min' => (is_numeric($value) ? (float)$value : mb_strlen((string)$value)) < (float)$parameter ? self::formatMessage($messages['min'] ?? "Minimum value :min", $field, ['min' => $parameter]) : null,
            'max' => (is_numeric($value) ? (float)$value : mb_strlen((string)$value)) > (float)$parameter ? self::formatMessage($messages['max'] ?? "Maximum value :max", $field, ['max' => $parameter]) : null,
            'in' => !in_array((string)$value, explode(',', (string)$parameter), true) ? self::formatMessage("Field ':field' must be one of: {$parameter}", $field) : null,
            'regex' => preg_match((string)$parameter, (string)$value) !== 1 ? self::formatMessage($messages['regex'] ?? "Invalid format", $field) : null,
            'json' => !is_string($value) || json_validate($value) !== true ? self::formatMessage($messages['json'] ?? "Invalid JSON", $field) : null,
            'uuid' => preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', (string)$value) !== 1 ? self::formatMessage($messages['uuid'] ?? "Invalid UUID", $field) : null,
            default => null
        };
    }

    /**
     * Replaces placeholder tokens in a message template.
     * 
     * @param string $template Message string template
     * @param string $field Target field name
     * @param array<string, string> $params Replacement tokens map
     * @return string
     * @example $msg = self::formatMessage("Min :min", 'age', ['min' => '18']);
     */
    private static function formatMessage(string $template, string $field, array $params = []): string
    {
        $formatted = str_replace(':field', $field, $template);
        foreach ($params as $key => $val) {
            $formatted = str_replace(":{$key}", (string) $val, $formatted);
        }
        return $formatted;
    }
}
