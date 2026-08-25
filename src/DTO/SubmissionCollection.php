<?php

namespace NomadicSoft\LaravelIndexNow\DTO;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use JsonSerializable;
use Traversable;

/** @implements IteratorAggregate<int, SubmissionResult> */
final readonly class SubmissionCollection implements Countable, IteratorAggregate, JsonSerializable
{
    /**
     * @param  list<SubmissionResult>  $results
     */
    public function __construct(private array $results) {}

    /** @return list<SubmissionResult> */
    public function all(): array
    {
        return $this->results;
    }

    public function count(): int
    {
        return count($this->results);
    }

    public function successful(): bool
    {
        if ($this->results === []) {
            return false;
        }

        foreach ($this->results as $result) {
            if (! $result->accepted()) {
                return false;
            }
        }

        return true;
    }

    public function failedCount(): int
    {
        return count(array_filter(
            $this->results,
            fn (SubmissionResult $result): bool => ! $result->accepted(),
        ));
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->results);
    }

    public function jsonSerialize(): array
    {
        return $this->results;
    }
}
