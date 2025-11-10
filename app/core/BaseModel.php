<?php

namespace Core;

use Attribute;
use ReflectionProperty;
use Throwable;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Field
{
    public bool $required;
    public mixed $default;
    public ?int $min = null;
    public ?int $max = null;
    public ?int $min_length = null;
    public ?int $max_length = null;
    public ?int $length = null;
    public ?string $format = null;
    public ?string $pattern = null;
    public array $messages;

    public function __construct(
        bool $required = true,
        mixed $default = null,
        ?int $min = null,
        ?int $max = null,
        ?int $min_length = null,
        ?int $max_length = null,
        ?int $length = null,
        ?string $format = null,
        ?string $pattern = null,
        array $messages = []
    ) {
        $this->required = $required;
        $this->default = $default;
        $this->min = $min;
        $this->max = $max;
        $this->min_length = $min_length;
        $this->max_length = $max_length;
        $this->length = $length;
        $this->format = $format;
        $this->pattern = $pattern;
        $this->messages = $messages;
    }
}

class ValidationException extends \Exception
{
    public array|string|null $response;
    public function __construct(array $errors, string $lang = "en", bool $jsonResponse)
    {

        if ($jsonResponse) {
            $msg = "";
            $keys = array_keys($errors);
            $lastKey = end($keys);
            foreach ($errors as $key => $value) {
                $separator = ($key === $lastKey) ? " ." : " , ";
                $msg .= implode(" , ", $value) . $separator;
            }

            $this->response = Response::JSON([
                "success" => false,
                "error" => true,
                "msg" => $msg,
                "errors" => $errors
            ], 400);
        } else {
            $html = <<<HTML
             <ul>
HTML;
            foreach ($errors as $field => $messages) {
                foreach ($messages as $msg) {
                    $html .= <<<HTML
                    <li><strong class='text-red-500'>$field:</strong> $msg</li>
HTML;
                }
            }
            $html .= <<<HTML
            </ul>
HTML;
            $this->response = Response::HTML($html, 400);
        }
        exit();
    }
}

abstract class BaseModel
{

    protected static array $defaultMessages = [
        'en' => [
            'required' => "Field ':field' is required",
            'length'   => "Field ':field' must be exactly :length characters long",
            'min_length' => "Field ':field' must be at least :min_length characters long",
            'max_length' => "Field ':field' must not exceed :max_length characters",
            'min'      => "Field ':field' must be at least :min",
            'max'      => "Field ':field' must not exceed :max",
            'email'    => "Field ':field' must be a valid email address",
            'ip'       => "Field ':field' must be a valid IP address",
            'url'      => "Field ':field' must be a valid URL",
            'uuid'     => "Field ':field' must be a valid UUID",
            'regex'    => "Field ':field' does not match the required format",
            'number'   => "Field ':field' must be a valid number",
            'integer'  => "Field ':field' must be a whole number",
            'float'    => "Field ':field' must be a decimal number",
            'boolean'  => "Field ':field' must be true or false",
            'date'     => "Field ':field' must be a valid date",
            'datetime' => "Field ':field' must be a valid date and time",
            'alpha'    => "Field ':field' can only contain letters",
            'alphanumeric' => "Field ':field' can only contain letters and numbers",
            'numeric'  => "Field ':field' can only contain numbers",
            'phone'    => "Field ':field' must be a valid phone number",
            'credit_card' => "Field ':field' must be a valid credit card number",
            'domain'   => "Field ':field' must be a valid domain name",
            'mac_address' => "Field ':field' must be a valid MAC address",
            'json'     => "Field ':field' must be a valid JSON string",
            'base64'   => "Field ':field' must be a valid Base64 string",
        ],
        'es' => [
            'required' => "El campo ':field' es obligatorio",
            'length'   => "El campo ':field' debe tener exactamente :length caracteres",
            'min_length' => "El campo ':field' debe tener al menos :min_length caracteres",
            'max_length' => "El campo ':field' no debe exceder :max_length caracteres",
            'min'      => "El campo ':field' debe ser como mínimo :min",
            'max'      => "El campo ':field' no debe exceder :max",
            'email'    => "El campo ':field' debe ser una dirección de correo válida",
            'ip'       => "El campo ':field' debe ser una dirección IP válida",
            'url'      => "El campo ':field' debe ser una URL válida",
            'uuid'     => "El campo ':field' debe ser un UUID válido",
            'regex'    => "El campo ':field' no cumple con el formato requerido",
            'number'   => "El campo ':field' debe ser un número válido",
            'integer'  => "El campo ':field' debe ser un número entero",
            'float'    => "El campo ':field' debe ser un número decimal",
            'boolean'  => "El campo ':field' debe ser verdadero o falso",
            'date'     => "El campo ':field' debe ser una fecha válida",
            'datetime' => "El campo ':field' debe ser una fecha y hora válidas",
            'alpha'    => "El campo ':field' solo puede contener letras",
            'alphanumeric' => "El campo ':field' solo puede contener letras y números",
            'numeric'  => "El campo ':field' solo puede contener números",
            'phone'    => "El campo ':field' debe ser un número de teléfono válido",
            'credit_card' => "El campo ':field' debe ser un número de tarjeta de crédito válido",
            'domain'   => "El campo ':field' debe ser un nombre de dominio válido",
            'mac_address' => "El campo ':field' debe ser una dirección MAC válida",
            'json'     => "El campo ':field' debe ser una cadena JSON válida",
            'base64'   => "El campo ':field' debe ser una cadena Base64 válida",
        ]
    ];

    private string $lang;

    public function __construct(array $data = [], string|null $lang = null, bool $jsonResponse = true)
    {
        if ($lang == null) {
            $session = Session::getInstance();
            $this->lang = $session->get("lang", "en");
        } else $this->lang = $lang;

        $ref = new \ReflectionClass($this);
        $errors = [];
        $validatedValues = [];

        foreach ($ref->getProperties(ReflectionProperty::IS_PUBLIC) as $prop) {
            $name = $prop->getName();
            $attrs = $prop->getAttributes(Field::class);
            $field = $attrs[0]->newInstance() ?? null;

            $value = $data[$name] ?? ($field?->default ?? null);

            if ($field) {
                if ($field->required && ($value === null || $value === '')) {
                    $errors[$name][] = $this->formatMessage('required', $field, [':field' => $name]);
                    continue;
                }

                if ($value === null || $value === '') {
                    $validatedValues[$name] = $value;
                    continue;
                }

                if (is_string($value)) {
                    $strlen = strlen($value);
                    
                    if ($field->length !== null && $strlen !== $field->length) {
                        $errors[$name][] = $this->formatMessage('length', $field, [
                            ':field' => $name,
                            ':length' => $field->length
                        ]);
                    }
                    
                    if ($field->min_length !== null && $strlen < $field->min_length) {
                        $errors[$name][] = $this->formatMessage('min_length', $field, [
                            ':field' => $name,
                            ':min_length' => $field->min_length
                        ]);
                    }
                    
                    if ($field->max_length !== null && $strlen > $field->max_length) {
                        $errors[$name][] = $this->formatMessage('max_length', $field, [
                            ':field' => $name,
                            ':max_length' => $field->max_length
                        ]);
                    }
                }

                if (is_numeric($value)) {
                    if ($field->min !== null && $value < $field->min) {
                        $errors[$name][] = $this->formatMessage('min', $field, [
                            ':field' => $name,
                            ':min' => $field->min
                        ]);
                    }
                    if ($field->max !== null && $value > $field->max) {
                        $errors[$name][] = $this->formatMessage('max', $field, [
                            ':field' => $name,
                            ':max' => $field->max
                        ]);
                    }
                }

                if ($field->format && $value !== null && $value !== '') {
                    switch ($field->format) {
                        case 'email':
                            if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                                $errors[$name][] = $this->formatMessage('email', $field, [':field' => $name]);
                            }
                            break;
                        case 'ip':
                            if (!filter_var($value, FILTER_VALIDATE_IP)) {
                                $errors[$name][] = $this->formatMessage('ip', $field, [':field' => $name]);
                            }
                            break;
                        case 'url':
                            if (!filter_var($value, FILTER_VALIDATE_URL)) {
                                $errors[$name][] = $this->formatMessage('url', $field, [':field' => $name]);
                            }
                            break;
                        case 'uuid':
                            if (!preg_match(
                                '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
                                $value
                            )) {
                                $errors[$name][] = $this->formatMessage('uuid', $field, [':field' => $name]);
                            }
                            break;
                        case 'number':
                            if (!is_numeric($value)) {
                                $errors[$name][] = $this->formatMessage('number', $field, [':field' => $name]);
                            }
                            break;
                        case 'integer':
                            if (!filter_var($value, FILTER_VALIDATE_INT)) {
                                $errors[$name][] = $this->formatMessage('integer', $field, [':field' => $name]);
                            }
                            break;
                        case 'float':
                            if (!filter_var($value, FILTER_VALIDATE_FLOAT)) {
                                $errors[$name][] = $this->formatMessage('float', $field, [':field' => $name]);
                            }
                            break;
                        case 'boolean':
                            if (!is_bool($value) && !in_array(strtolower($value), ['true', 'false', '1', '0', 'yes', 'no'])) {
                                $errors[$name][] = $this->formatMessage('boolean', $field, [':field' => $name]);
                            }
                            break;
                        case 'date':
                            if (!strtotime($value)) {
                                $errors[$name][] = $this->formatMessage('date', $field, [':field' => $name]);
                            }
                            break;
                        case 'datetime':
                            if (DateTime::createFromFormat('Y-m-d H:i:s', $value) === false) {
                                $errors[$name][] = $this->formatMessage('datetime', $field, [':field' => $name]);
                            }
                            break;
                        case 'alpha':
                            if (!ctype_alpha($value)) {
                                $errors[$name][] = $this->formatMessage('alpha', $field, [':field' => $name]);
                            }
                            break;
                        case 'alphanumeric':
                            if (!ctype_alnum($value)) {
                                $errors[$name][] = $this->formatMessage('alphanumeric', $field, [':field' => $name]);
                            }
                            break;
                        case 'numeric':
                            if (!ctype_digit($value)) {
                                $errors[$name][] = $this->formatMessage('numeric', $field, [':field' => $name]);
                            }
                            break;
                        case 'phone':
                            if (!preg_match('/^\+?[0-9\s\-\(\)]{10,}$/', $value)) {
                                $errors[$name][] = $this->formatMessage('phone', $field, [':field' => $name]);
                            }
                            break;
                        case 'credit_card':
                            if (!$this->validateCreditCard($value)) {
                                $errors[$name][] = $this->formatMessage('credit_card', $field, [':field' => $name]);
                            }
                            break;
                        case 'domain':
                            if (!filter_var($value, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
                                $errors[$name][] = $this->formatMessage('domain', $field, [':field' => $name]);
                            }
                            break;
                        case 'mac_address':
                            if (!filter_var($value, FILTER_VALIDATE_MAC)) {
                                $errors[$name][] = $this->formatMessage('mac_address', $field, [':field' => $name]);
                            }
                            break;
                        case 'json':
                            if (!json_decode($value)) {
                                $errors[$name][] = $this->formatMessage('json', $field, [':field' => $name]);
                            }
                            break;
                        case 'base64':
                            if (!base64_decode($value, true)) {
                                $errors[$name][] = $this->formatMessage('base64', $field, [':field' => $name]);
                            }
                            break;
                        case 'regex':
                            if ($field->pattern && !preg_match($field->pattern, $value)) {
                                $errors[$name][] = $this->formatMessage('regex', $field, [':field' => $name]);
                            }
                            break;
                    }
                }
            }

            $validatedValues[$name] = $value;
        }

        if (!empty($errors)) {
            throw new ValidationException(errors: $errors, lang: $this->lang, jsonResponse: $jsonResponse);
        }

        foreach ($ref->getProperties(ReflectionProperty::IS_PUBLIC) as $prop) {
            $prop->setValue($this, $validatedValues[$prop->getName()]);
        }
    }

    private function formatMessage(string $key, Field $field, array $vars): string
    {
        $msg = $field->messages[$key] ?? static::$defaultMessages[$this->lang][$key] ?? $key;
        foreach ($vars as $var => $val) { 
            $msg = str_replace($var, $val, $msg);
        }
        return $msg;
    }

    private function validateCreditCard(string $number): bool
    {
        $number = preg_replace('/\D/', '', $number);
        
        $sum = 0;
        $reverse = strrev($number);
        
        for ($i = 0; $i < strlen($reverse); $i++) {
            $digit = (int)$reverse[$i];
            if ($i % 2 === 1) {
                $digit *= 2;
                if ($digit > 9) {
                    $digit -= 9;
                }
            }
            $sum += $digit;
        }
        
        return $sum % 10 === 0;
    }
}