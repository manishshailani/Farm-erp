<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Small server-side validation helper.
 *
 * All form input is validated here on the server regardless of any
 * client-side (HTML5/JS) validation, per the project's security rules --
 * client-side checks are a UX convenience only and are never trusted.
 */
final class Validator
{
    /** @var array<string,string> */
    private array $errors = [];

    /** @param array<string,mixed> $data */
    public function __construct(private array $data)
    {
    }

    public function required(string $field, string $label): self
    {
        $value = $this->data[$field] ?? null;
        if ($value === null || (is_string($value) && trim($value) === '')) {
            $this->errors[$field] = "{$label} is required.";
        }
        return $this;
    }

    public function maxLength(string $field, int $max, string $label): self
    {
        $value = (string) ($this->data[$field] ?? '');
        if (mb_strlen($value) > $max) {
            $this->errors[$field] ??= "{$label} must be at most {$max} characters.";
        }
        return $this;
    }

    public function numeric(string $field, string $label): self
    {
        $value = $this->data[$field] ?? null;
        if ($value !== null && $value !== '' && !is_numeric($value)) {
            $this->errors[$field] ??= "{$label} must be a number.";
        }
        return $this;
    }

    public function min(string $field, float $min, string $label): self
    {
        $value = $this->data[$field] ?? null;
        if (is_numeric($value) && (float) $value < $min) {
            $this->errors[$field] ??= "{$label} must be at least {$min}.";
        }
        return $this;
    }

    public function email(string $field, string $label): self
    {
        $value = (string) ($this->data[$field] ?? '');
        if ($value !== '' && filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            $this->errors[$field] ??= "{$label} must be a valid email address.";
        }
        return $this;
    }

    public function in(string $field, array $allowed, string $label): self
    {
        $value = $this->data[$field] ?? null;
        if ($value !== null && $value !== '' && !in_array($value, $allowed, true)) {
            $this->errors[$field] ??= "{$label} is not a valid choice.";
        }
        return $this;
    }

    public function date(string $field, string $label): self
    {
        $value = (string) ($this->data[$field] ?? '');
        if ($value !== '') {
            $d = \DateTime::createFromFormat('Y-m-d', $value);
            if (!$d || $d->format('Y-m-d') !== $value) {
                $this->errors[$field] ??= "{$label} must be a valid date (YYYY-MM-DD).";
            }
        }
        return $this;
    }

    public function passes(): bool
    {
        return $this->errors === [];
    }

    public function fails(): bool
    {
        return !$this->passes();
    }

    /** @return array<string,string> */
    public function errors(): array
    {
        return $this->errors;
    }
}
