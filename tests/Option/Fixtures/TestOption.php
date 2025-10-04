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
    private $value;

    public function __construct($value = null)
    {
        $this->value = $value;
    }

    public function get()
    {
        return $this->value;
    }

    public function unwrapOr($default)
    {
        if ($this->isDefined()) {
            return $this->value;
        }

        return $default;
    }

    public function unwrapOrElse($callable)
    {
        if ($this->isDefined()) {
            return $this->value;
        }

        return $callable();
    }

    public function unwrapOrThrow(Exception $ex)
    {
        if ($this->isDefined()) {
            return $this->value;
        }

        throw $ex;
    }

    public function isEmpty()
    {
        return $this->value === null;
    }

    public function isDefined()
    {
        return $this->value !== null;
    }

    public function orElse(callable $else)
    {
        if ($this->isDefined()) {
            return $this;
        }

        return $else();
    }

    public function ifDefined($callable): void
    {
        if ($this->isDefined()) {
            $callable($this->value);
        }
    }

    public function forAll($callable): void
    {
        if ($this->isDefined()) {
            $callable($this->value);
        }
    }

    public function map($callable)
    {
        if ($this->isDefined()) {
            return new self($callable($this->value));
        }

        return $this;
    }

    public function flatMap($callable)
    {
        if ($this->isDefined()) {
            return $callable($this->value);
        }

        return $this;
    }

    public function filter($callable)
    {
        if ($this->isDefined() && $callable($this->value)) {
            return $this;
        }

        return None::create();
    }

    public function filterNot($callable)
    {
        if ($this->isDefined() && !$callable($this->value)) {
            return $this;
        }

        return None::create();
    }

    public function select($value)
    {
        if ($this->isDefined() && $this->value === $value) {
            return $this;
        }

        return None::create();
    }

    public function reject($value)
    {
        if ($this->isDefined() && $this->value !== $value) {
            return $this;
        }

        return None::create();
    }

    public function foldLeft($initialValue, $callable)
    {
        if ($this->isDefined()) {
            return $callable($initialValue, $this->value);
        }

        return $initialValue;
    }

    public function foldRight($initialValue, $callable)
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
