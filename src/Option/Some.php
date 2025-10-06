<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Monad\Option;

use ArrayIterator;
use Exception;
use RuntimeException;

use function is_object;

/**
 * Represents the presence of a value in the Option type pattern.
 *
 * Some is the "filled" variant of the Option type, containing an actual value
 * of type T. It provides safe operations for transforming, filtering, and
 * extracting values without null pointer exceptions. All operations on Some
 * will execute with the contained value.
 *
 * Some instances are created when you have a definite value to wrap:
 *
 * ```php
 * $user = new Some($userObject);
 * $name = new Some('John Doe');
 * $count = new Some(42);
 *
 * // Transform the value
 * $upperName = $name->map('strtoupper'); // Some('JOHN DOE')
 * $doubled = $count->map(fn($n) => $n * 2); // Some(84)
 *
 * // Safe extraction
 * $greeting = $name->map(fn($n) => "Hello, $n!")->unwrapOr('Hello, stranger!');
 * ```
 *
 * @template T The type of value contained within this Some
 *
 * @extends Option<T>
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class Some extends Option
{
    /** @var T The value contained within this Some instance */
    private mixed $value;

    /**
     * Create a new Some instance containing the provided value.
     *
     * @param T $value The value to wrap in this Some
     */
    public function __construct($value)
    {
        $this->value = $value;
    }

    /**
     * Create a new Some instance containing the provided value.
     *
     * Static factory method for creating Some instances with explicit typing.
     *
     * @template U
     *
     * @param  U       $value The value to wrap in a new Some
     * @return self<U> New Some instance containing the value
     */
    public static function create($value): self
    {
        return new self($value);
    }

    /**
     * Check if this Option has a defined value.
     *
     * Some always contains a value, so this always returns true.
     *
     * @return bool Always returns true for Some
     */
    public function isDefined(): bool
    {
        return true;
    }

    /**
     * Check if this Option is empty.
     *
     * Some always contains a value, so this always returns false.
     *
     * @return bool Always returns false for Some
     */
    public function isEmpty(): bool
    {
        return false;
    }

    /**
     * Get the contained value.
     *
     * Safe to call on Some as it always contains a value.
     *
     * @return T The contained value
     */
    public function get()
    {
        return $this->value;
    }

    /**
     * Return the contained value, ignoring the default.
     *
     * Since Some always has a value, the default parameter is ignored
     * and the contained value is always returned.
     *
     * @template S
     *
     * @param  S $default Default value (ignored for Some)
     * @return T The contained value
     */
    public function unwrapOr($default)
    {
        return $this->value;
    }

    /**
     * Return the contained value, not executing the callable.
     *
     * Since Some always has a value, the callable is never executed
     * and the contained value is always returned.
     *
     * @template S
     *
     * @param  callable():S $callable Function that would compute default (ignored)
     * @return T            The contained value
     */
    public function unwrapOrElse($callable)
    {
        return $this->value;
    }

    /**
     * Return the contained value, not throwing the exception.
     *
     * Since Some always has a value, the exception is never thrown
     * and the contained value is always returned.
     *
     * @param  Exception $ex Exception that would be thrown (ignored)
     * @return T         The contained value
     */
    public function unwrapOrThrow(Exception $ex)
    {
        return $this->value;
    }

    /**
     * Return this Some, not executing the alternative callable.
     *
     * Since Some already contains a value, the callable is never executed
     * and this Some instance is returned.
     *
     * @param  callable():Option<T> $else Function that would provide alternative (ignored)
     * @return Option<T>            This Some instance
     */
    public function orElse(callable $else)
    {
        return $this;
    }

    /**
     * Execute a callable with the contained value.
     *
     * Since Some contains a value, the callable is always executed
     * with that value. This method is deprecated in favor of forAll().
     *
     * @deprecated Use forAll() instead
     *
     * @param callable(T):mixed $callable Function to execute with the contained value
     */
    public function ifDefined($callable): void
    {
        $this->forAll($callable);
    }

    /**
     * Execute a callable for side effects with the contained value.
     *
     * Since Some contains a value, the callable is always executed
     * with that value for side effects. Returns this Some for chaining.
     *
     * @param  callable(T):mixed $callable Function to execute for side effects
     * @return Option<T>         This Some instance for method chaining
     */
    public function forAll($callable)
    {
        $callable($this->value);

        return $this;
    }

    /**
     * Execute a callable for debugging/inspection with the contained value.
     *
     * Executes the callable with the contained value for inspection purposes
     * (logging, debugging, etc.) and returns this Some unchanged.
     *
     * @param  callable(T):mixed $f Function to execute for inspection
     * @return Option<T>         This Some instance unchanged
     */
    public function inspect(callable $f): Option
    {
        $f($this->value);

        return $this;
    }

    /**
     * Transform the contained value using the provided callable.
     *
     * Applies the transformation function to the contained value and
     * returns a new Some containing the transformed result.
     *
     * @template S
     *
     * @param  callable(T):S $callable Function to transform the contained value
     * @return self<S>       New Some containing the transformed value
     */
    public function map($callable)
    {
        return new self($callable($this->value));
    }

    /**
     * Apply the callable to the contained value and return the result.
     *
     * Since Some contains a value, the callable is always applied
     * and its result is returned directly (not wrapped in an Option).
     *
     * @template S
     *
     * @param  callable(T):S $f Function to apply to the contained value
     * @return S             Result of applying the function to the value
     */
    public function mapOrDefault(callable $f)
    {
        return $f($this->value);
    }

    /**
     * Apply a function that returns an Option and return that Option directly.
     *
     * Executes the callable with the contained value, expecting it to return
     * an Option. That Option becomes the result without additional wrapping.
     * Essential for chaining Option-returning operations.
     *
     * @template S
     *
     * @param callable(T):Option<S> $callable Function that must return an Option
     *
     * @throws RuntimeException When callable doesn't return an Option
     *
     * @return Option<S> The Option returned by the callable
     */
    public function flatMap($callable)
    {
        /** @var mixed */
        $rs = $callable($this->value);

        if (!$rs instanceof Option) {
            throw new RuntimeException('Callables passed to flatMap() must return an Option. Maybe you should use map() instead?');
        }

        return $rs;
    }

    /**
     * Filter the contained value based on a predicate function.
     *
     * Tests the contained value with the predicate. If the predicate returns
     * true, returns this Some; if false, returns None.
     *
     * @param  callable(T):bool $callable Predicate function to test the value
     * @return Option<T>        This Some if predicate passes, None otherwise
     */
    public function filter($callable)
    {
        if (true === $callable($this->value)) {
            return $this;
        }

        return None::create();
    }

    /**
     * Filter the contained value based on the negation of a predicate.
     *
     * Tests the contained value with the predicate. If the predicate returns
     * false, returns this Some; if true, returns None.
     *
     * @param  callable(T):bool $callable Predicate function to test the value
     * @return Option<T>        This Some if predicate fails, None otherwise
     */
    public function filterNot($callable)
    {
        if (false === $callable($this->value)) {
            return $this;
        }

        return None::create();
    }

    /**
     * Create a clone of this Some and its contained value if it's an object.
     *
     * If the contained value is an object, it will be cloned using PHP's
     * clone operator. For scalar values, returns a new Some with the same value.
     *
     * @return self<T> New Some instance with cloned value
     *
     * @phpstan-return self<T>
     */
    public function cloned(): self
    {
        if (is_object($this->value)) {
            /** @phpstan-ignore return.type */
            return new self(clone $this->value);
        }

        return new self($this->value);
    }

    /**
     * Return this Some if it contains the specified value, None otherwise.
     *
     * Compares the contained value with the target using strict equality (===).
     * Returns this Some if they match, None if they don't.
     *
     * @param  T         $value Value to select for
     * @return Option<T> This Some if contains the value, None otherwise
     */
    public function select($value)
    {
        if ($this->value === $value) {
            return $this;
        }

        return None::create();
    }

    /**
     * Return None if this Some contains the specified value, this Some otherwise.
     *
     * Compares the contained value with the target using strict equality (===).
     * Returns None if they match, this Some if they don't.
     *
     * @param  T         $value Value to reject
     * @return Option<T> None if contains the value, this Some otherwise
     */
    public function reject($value)
    {
        if ($this->value === $value) {
            return None::create();
        }

        return $this;
    }

    /**
     * Get an iterator for the contained value.
     *
     * Returns an ArrayIterator containing the single value, allowing
     * Some to be used in foreach loops and other iterator contexts.
     *
     * @return ArrayIterator<int, T> Iterator containing the single value
     */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator([$this->value]);
    }

    /**
     * Apply a binary function to the initial value and contained value.
     *
     * Executes the binary function with the initial value and the contained
     * value, returning the result of the function call.
     *
     * @template S
     *
     * @param  S                $initialValue Starting value for the fold operation
     * @param  callable(S, T):S $callable     Binary function to apply
     * @return S                Result of applying the function
     */
    public function foldLeft($initialValue, $callable)
    {
        return $callable($initialValue, $this->value);
    }

    /**
     * Apply a binary function to the contained value and initial value.
     *
     * Executes the binary function with the contained value and the initial
     * value (in that order), returning the result of the function call.
     *
     * @template S
     *
     * @param  S                $initialValue Starting value for the fold operation
     * @param  callable(T, S):S $callable     Binary function to apply
     * @return S                Result of applying the function
     */
    public function foldRight($initialValue, $callable)
    {
        return $callable($this->value, $initialValue);
    }
}
