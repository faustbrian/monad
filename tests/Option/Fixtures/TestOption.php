<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Option\Fixtures;

use Exception;
use Traversable;
use ArrayIterator;
use Cline\Monad\Option\None;
use Cline\Monad\Option\Option;

class TestOption extends Option
{
    public function __construct(private readonly mixed $value = null)
    {
    }

    public function get(): mixed
    {
        return $this->value;
    }

    public function unwrapOr(mixed $default): mixed
    {
        if ($this->isDefined()) {
            return $this->value;
        }

        return $default;
    }

    public function unwrapOrElse(callable $callable): mixed
    {
        if ($this->isDefined()) {
            return $this->value;
        }

        return $callable();
    }

    public function unwrapOrThrow(Exception $ex): mixed
    {
        if ($this->isDefined()) {
            return $this->value;
        }

        throw $ex;
    }

    public function isEmpty(): bool
    {
        return $this->value === null;
    }

    public function isDefined(): bool
    {
        return $this->value !== null;
    }

    public function orElse(callable $else): Option
    {
        if ($this->isDefined()) {
            return $this;
        }

        return $else();
    }

    public function ifDefined(callable $callable): void
    {
        if ($this->isDefined()) {
            $callable($this->value);
        }
    }

    public function forAll(callable $callable): Option
    {
        if ($this->isDefined()) {
            $callable($this->value);
        }

        return $this;
    }

    public function map(callable $callable): Option
    {
        if ($this->isDefined()) {
            return new self($callable($this->value));
        }

        return $this;
    }

    public function flatMap(callable $callable): Option
    {
        if ($this->isDefined()) {
            return $callable($this->value);
        }

        return $this;
    }

    public function filter(callable $callable): Option
    {
        if ($this->isDefined() && $callable($this->value)) {
            return $this;
        }

        return None::create();
    }

    public function filterNot(callable $callable): Option
    {
        if ($this->isDefined() && !$callable($this->value)) {
            return $this;
        }

        return None::create();
    }

    public function select(mixed $value): Option
    {
        if ($this->isDefined() && $this->value === $value) {
            return $this;
        }

        return None::create();
    }

    public function reject(mixed $value): Option
    {
        if ($this->isDefined() && $this->value !== $value) {
            return $this;
        }

        return None::create();
    }

    public function foldLeft(mixed $initialValue, callable $callable): mixed
    {
        if ($this->isDefined()) {
            return $callable($initialValue, $this->value);
        }

        return $initialValue;
    }

    public function foldRight(mixed $initialValue, callable $callable): mixed
    {
        if ($this->isDefined()) {
            return $callable($this->value, $initialValue);
        }

        return $initialValue;
    }

    public function getIterator(): Traversable
    {
        if ($this->isDefined()) {
            return new ArrayIterator([$this->value]);
        }

        return new ArrayIterator([]);
    }
}
