<?php

declare(strict_types=1);

namespace DevWizard\Filex\Support;

use ArrayAccess;
use Countable;
use Iterator;

/**
 * FilexResult - Result object for file operations
 *
 * Provides convenient access to file operation results with helper methods.
 * This class wraps the array results from file operations and provides
 * object-oriented access to the data.
 */
class FilexResult implements ArrayAccess, Countable, Iterator
{
    private array $results;

    private int $position = 0;

    public function __construct(array $results)
    {
        $this->results = $results;
    }

    /**
     * Get the final path of the first successful file operation
     */
    public function getPath(): ?string
    {
        $firstSuccess = $this->getFirstSuccessful();

        return $firstSuccess['finalPath'] ?? null;
    }

    /**
     * Get all final paths from successful operations
     */
    public function getPaths(): array
    {
        return array_values(array_filter(array_map(function ($result) {
            return $result['success'] ? ($result['finalPath'] ?? null) : null;
        }, $this->results)));
    }

    /**
     * Check if any operation was successful
     */
    public function isSuccess(): bool
    {
        return count($this->getSuccessful()) > 0;
    }

    /**
     * Check if all operations were successful
     */
    public function isAllSuccess(): bool
    {
        return count($this->getSuccessful()) === count($this->results);
    }

    /**
     * Get the first successful result
     */
    public function getFirstSuccessful(): ?array
    {
        foreach ($this->results as $result) {
            if ($result['success'] ?? false) {
                return $result;
            }
        }

        return null;
    }

    /**
     * Get all successful results
     */
    public function getSuccessful(): array
    {
        return array_values(array_filter($this->results, fn ($result) => $result['success'] ?? false));
    }

    /**
     * Get all failed results
     */
    public function getFailed(): array
    {
        return array_values(array_filter($this->results, fn ($result) => ! ($result['success'] ?? false)));
    }

    /**
     * Get the first error message from failed operations
     */
    public function getErrorMessage(): ?string
    {
        $failed = $this->getFailed();

        return ! empty($failed) ? ($failed[0]['message'] ?? null) : null;
    }

    /**
     * Get all error messages from failed operations
     */
    public function getErrorMessages(): array
    {
        return array_values(array_filter(array_map(function ($result) {
            return ! ($result['success'] ?? false) ? ($result['message'] ?? null) : null;
        }, $this->results)));
    }

    /**
     * Get metadata from the first successful operation
     */
    public function getMetadata(): ?array
    {
        $firstSuccess = $this->getFirstSuccessful();

        return $firstSuccess['metadata'] ?? null;
    }

    /**
     * Get the temporary path from the first successful operation
     */
    public function getTempPath(): ?string
    {
        $firstSuccess = $this->getFirstSuccessful();

        return $firstSuccess['tempPath'] ?? null;
    }

    /**
     * Get count of successful operations
     */
    public function getSuccessCount(): int
    {
        return count($this->getSuccessful());
    }

    /**
     * Get count of failed operations
     */
    public function getFailedCount(): int
    {
        return count($this->getFailed());
    }

    /**
     * Get the raw results array
     */
    public function toArray(): array
    {
        return $this->results;
    }

    /**
     * Convert to JSON
     */
    public function toJson(): string
    {
        return json_encode($this->results);
    }

    /**
     * Magic method to access array elements as properties
     */
    public function __get($key)
    {
        return $this->results[$key] ?? null;
    }

    /**
     * Magic method to check if array element exists
     */
    public function __isset($key): bool
    {
        return isset($this->results[$key]);
    }

    /**
     * ArrayAccess implementation
     */
    public function offsetExists($offset): bool
    {
        return isset($this->results[$offset]);
    }

    public function offsetGet($offset): mixed
    {
        return $this->results[$offset] ?? null;
    }

    public function offsetSet($offset, $value): void
    {
        if (is_null($offset)) {
            $this->results[] = $value;
        } else {
            $this->results[$offset] = $value;
        }
    }

    public function offsetUnset($offset): void
    {
        unset($this->results[$offset]);
    }

    /**
     * Iterator implementation
     */
    public function rewind(): void
    {
        $this->position = 0;
    }

    public function current(): mixed
    {
        return $this->results[$this->position];
    }

    public function key(): mixed
    {
        return $this->position;
    }

    public function next(): void
    {
        $this->position++;
    }

    public function valid(): bool
    {
        return isset($this->results[$this->position]);
    }

    /**
     * Countable implementation
     */
    public function count(): int
    {
        return count($this->results);
    }
}
