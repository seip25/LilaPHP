<?php

namespace Core;

class ValidationException extends \Exception
{
    public array $errors;
    public ?string $lang;
    public bool $jsonResponse;

    public function __construct(array $errors, string|null $lang = "en", bool $jsonResponse = true)
    {
        parent::__construct("Validation failed");
        $this->errors = $errors;
        $this->lang = $lang;
        $this->jsonResponse = $jsonResponse;
    }

    public function render(): void
    {
        $errors = $this->errors;
        if ($this->jsonResponse) {
            $msg = "";
            $html = "<ul>";
            $keys = array_keys($errors);
            $lastKey = end($keys);
            foreach ($errors as $key => $value) {
                $key = ucfirst(string: $key);
                $separator = ($key === $lastKey) ? " ." : " , ";
                $msg .= implode(" , ", $value) . $separator;
                foreach ($value as $m) {
                    $html .= "<li class='text-red-500'>$m</li>";
                }
            }
            $html .= "</ul>";

            Response::JSON([
                "success" => false,
                "error" => true,
                "message" => $msg,
                "html" => $html,
                "errors" => $errors
            ], 400);
        } else {
            $html = "<ul>";
            foreach ($errors as $field => $messages) {
                $field = ucfirst(string: $field);
                foreach ($messages as $m) {
                    $html .= "<li class='text-red-500 text-danger'><strong>$field:</strong> $m</li>";
                }
            }
            $html .= "</ul>";
            Response::HTML($html, 400);
        }
    }
}
