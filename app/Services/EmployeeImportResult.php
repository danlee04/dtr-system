<?php

namespace App\Services;

/**
 * What an import did, or why it did nothing.
 */
final readonly class EmployeeImportResult
{
    /**
     * @param  list<string>  $errors
     */
    private function __construct(
        public int $created,
        public int $updated,
        public int $unchanged,
        public array $errors,
    ) {}

    public static function succeeded(int $created, int $updated, int $unchanged): self
    {
        return new self($created, $updated, $unchanged, []);
    }

    /**
     * @param  list<string>  $errors
     */
    public static function failed(array $errors): self
    {
        return new self(0, 0, 0, $errors);
    }

    public function isSuccessful(): bool
    {
        return $this->errors === [];
    }
}
