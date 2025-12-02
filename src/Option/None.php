<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Monad\Option;

use Cline\Monad\Exceptions\CannotUnwrapNoneException;
use EmptyIterator;
use Exception;
use RuntimeException;

/**
 * Represents the absence of a value in the Option type pattern.
 *
 * None is the "empty" variant of the Option type, indicating that no value
 * is present. It implements the null object pattern to provide safe operations
 * without null pointer exceptions. All transformation and extraction operations
 * on None result in predictable behavior without side effects.
 *
 * None is implemented as a singleton to ensure consistent identity and
 * memory efficiency across the application.
 *
 * ```php
 * $empty = None::create();
 * $result = $empty->map('strtoupper')->unwrapOr('default'); // 'default'
 * $empty->ifDefined(fn($x) => echo $x); // Does nothing
 * ```
 *
 * @extends Option<mixed>
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class None extends Option
{
    /** @var null|self Singleton instance to ensure consistent identity */
    private static ?None $instance = null;

    /**
     * Private constructor to enforce singleton pattern.
     *
     * Use None::create() to obtain an instance.
     */
    private function __construct() {}

    /**
     * Get the singleton None instance.
     *
     * Returns the same None instance every time to ensure consistent
     * identity checks and memory efficiency.
     *
     * @return self The singleton None instance
     */
    public static function create(): self
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Attempt to get a value from None.
     *
     * Since None represents the absence of a value, this method always
     * throws an exception. Use unwrapOr() or unwrapOrElse() for safe access.
     *
     * @throws RuntimeException Always thrown since None has no value
     *
     * @return never This method never returns normally
     */
    public function get(): never
    {
        throw CannotUnwrapNoneException::create();
    }

    /**
     * Execute a callable to compute a default value.
     *
     * Since None has no value, the provided callable is always executed
     * to compute a fallback value.
     *
     * @template S
     *
     * @param  callable():S $callable Function to compute the default value
     * @return S            Result of executing the callable
     */
    public function unwrapOrElse(callable $callable): mixed
    {
        return $callable();
    }

    /**
     * Return the provided default value.
     *
     * Since None has no value, the default value is always returned.
     *
     * @template S
     *
     * @param  S $default The default value to return
     * @return S The provided default value
     */
    public function unwrapOr(mixed $default): mixed
    {
        return $default;
    }

    /**
     * Throw the provided exception.
     *
     * Since None has no value, the provided exception is always thrown.
     *
     * @param Exception $ex The exception to throw
     *
     * @throws Exception Always throws the provided exception
     *
     * @return never This method never returns normally
     */
    public function unwrapOrThrow(Exception $ex): never
    {
        throw $ex;
    }

    /**
     * Check if this Option is empty.
     *
     * None always represents an empty state.
     *
     * @return bool Always returns true for None
     */
    public function isEmpty(): bool
    {
        return true;
    }

    /**
     * Check if this Option has a defined value.
     *
     * None never has a defined value.
     *
     * @return bool Always returns false for None
     */
    public function isDefined(): bool
    {
        return false;
    }

    /**
     * Execute a callable to provide an alternative Option.
     *
     * Since None has no value, the provided callable is always executed
     * to obtain an alternative Option.
     *
     * @param  callable():Option<mixed> $else Function that returns an alternative Option
     * @return Option<mixed>            Result of executing the callable
     */
    public function orElse(callable $else): Option
    {
        return $else();
    }

    /**
     * Execute a callable if the Option contains a value.
     *
     * Since None has no value, the callable is never executed.
     * This method has no effect for None instances.
     *
     * @param callable(mixed):mixed $callable Function that would be executed (ignored)
     */
    public function ifDefined(callable $callable): void
    {
        // Just do nothing in that case.
    }

    /**
     * Execute a callable for side effects if the Option contains a value.
     *
     * Since None has no value, the callable is never executed.
     * Returns this None instance for method chaining.
     *
     * @param  callable(mixed):mixed $callable Function that would be executed (ignored)
     * @return self                  This None instance
     */
    public function forAll(callable $callable): self
    {
        return $this;
    }

    /**
     * Execute a callable for debugging/inspection purposes.
     *
     * Since None has no value, the callable is never executed.
     * Returns this None instance unchanged.
     *
     * @param  callable(mixed):mixed $f Function that would be executed (ignored)
     * @return Option<mixed>         This None instance
     */
    public function inspect(callable $f): Option
    {
        return $this;
    }

    /**
     * Transform the contained value using the provided callable.
     *
     * Since None has no value to transform, this operation has no effect
     * and returns this None instance.
     *
     * @param  callable(mixed):mixed $callable Function that would transform the value (ignored)
     * @return self                  This None instance
     */
    public function map(callable $callable): self
    {
        return $this;
    }

    /**
     * Map the contained value or return null if None.
     *
     * Since this is None, always returns null without executing the callable.
     *
     * @param callable(mixed):mixed $f Function that would transform the value (ignored)
     */
    public function mapOrDefault(callable $f): null
    {
        return null;
    }

    /**
     * Apply a function that returns an Option and flatten the result.
     *
     * Since None has no value, the callable is never executed and
     * this None instance is returned.
     *
     * @param  callable(mixed):Option<mixed> $callable Function that would return an Option (ignored)
     * @return self                          This None instance
     */
    public function flatMap(callable $callable): self
    {
        return $this;
    }

    /**
     * Filter the Option based on a predicate function.
     *
     * Since None has no value to filter, this operation has no effect
     * and returns this None instance.
     *
     * @param  callable(mixed):bool $callable Predicate function (ignored)
     * @return self                 This None instance
     */
    public function filter(callable $callable): self
    {
        return $this;
    }

    /**
     * Filter the Option based on the negation of a predicate function.
     *
     * Since None has no value to filter, this operation has no effect
     * and returns this None instance.
     *
     * @param  callable(mixed):bool $callable Predicate function (ignored)
     * @return self                 This None instance
     */
    public function filterNot(callable $callable): self
    {
        return $this;
    }

    /**
     * Return this Option if it contains the specified value, None otherwise.
     *
     * Since None contains no value, it never matches any specified value
     * and returns this None instance.
     *
     * @param  mixed $value Value to select for (ignored)
     * @return self  This None instance
     */
    public function select(mixed $value): self
    {
        return $this;
    }

    /**
     * Return None if this Option contains the specified value, this Option otherwise.
     *
     * Since None contains no value, it never matches any specified value
     * and returns this None instance.
     *
     * @param  mixed $value Value to reject (ignored)
     * @return self  This None instance
     */
    public function reject(mixed $value): self
    {
        return $this;
    }

    /**
     * Create a clone of this Option.
     *
     * Since None is a singleton with no contained value, returns this
     * None instance without any cloning operation.
     *
     * @return Option<mixed> This None instance
     */
    public function cloned(): Option
    {
        return $this;
    }

    /**
     * Get an iterator for the contained value.
     *
     * Since None contains no value, returns an EmptyIterator that
     * yields no elements when used in foreach loops.
     *
     * @return EmptyIterator Empty iterator with no elements
     */
    public function getIterator(): EmptyIterator
    {
        return new EmptyIterator();
    }

    /**
     * Apply a binary function to the initial value and the contained value.
     *
     * Since None has no value, the callable is never executed and
     * the initial value is returned unchanged.
     *
     * @template S
     *
     * @param  S                    $initialValue Starting value for the fold operation
     * @param  callable(S, mixed):S $callable     Binary function (ignored)
     * @return S                    The unchanged initial value
     */
    public function foldLeft(mixed $initialValue, callable $callable): mixed
    {
        return $initialValue;
    }

    /**
     * Apply a binary function to the contained value and the initial value.
     *
     * Since None has no value, the callable is never executed and
     * the initial value is returned unchanged.
     *
     * @template S
     *
     * @param  S                    $initialValue Starting value for the fold operation
     * @param  callable(mixed, S):S $callable     Binary function (ignored)
     * @return S                    The unchanged initial value
     */
    public function foldRight(mixed $initialValue, callable $callable): mixed
    {
        return $initialValue;
    }
}
