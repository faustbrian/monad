<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Monad\Either;

use ArrayIterator;
use RuntimeException;

use function is_object;

/**
 * Represents the Right variant of the Either type pattern.
 *
 * Right is conventionally used to represent the success, primary path, or
 * the "main" value in a dual-value container. Operations on Right values
 * (map, flatMap, forAll) are executed for Right, while Left-specific
 * operations (mapLeft, forLeft) are skipped.
 *
 * ```php
 * $success = new Right(42);
 * $doubled = $success->map(fn($n) => $n * 2); // Right(84)
 * $result = $success->unwrapOr(0); // 42
 * ```
 *
 * @template L The type of Left value (phantom type, never used)
 * @template R The type of value contained within this Right
 *
 * @extends Either<L, R>
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class Right extends Either
{
    /** @var R The value contained within this Right instance */
    private mixed $value;

    /**
     * Create a new Right instance containing the provided value.
     *
     * @param R $value The value to wrap in this Right
     */
    public function __construct($value)
    {
        $this->value = $value;
    }

    /**
     * Check if this Either is Left.
     *
     * @return bool Always returns false for Right
     */
    public function isLeft(): bool
    {
        return false;
    }

    /**
     * Check if this Either is Right.
     *
     * @return bool Always returns true for Right
     */
    public function isRight(): bool
    {
        return true;
    }

    /**
     * Get the Right value.
     *
     * @return R The contained Right value
     */
    public function unwrap()
    {
        return $this->value;
    }

    /**
     * Attempt to get the Left value from Right.
     *
     * @throws RuntimeException Always thrown since Right has no Left value
     *
     * @return never This method never returns normally
     */
    public function unwrapLeft(): void
    {
        throw new RuntimeException('Cannot unwrap Left value from Right.');
    }

    /**
     * Return the contained Right value, ignoring the default.
     *
     * @template T
     *
     * @param  T $default Default value (ignored for Right)
     * @return R The contained Right value
     */
    public function unwrapOr($default)
    {
        return $this->value;
    }

    /**
     * Return the contained Right value, not executing the callable.
     *
     * @template T
     *
     * @param  callable(L):T $callable Function that would compute default (ignored)
     * @return R             The contained Right value
     */
    public function unwrapOrElse(callable $callable)
    {
        return $this->value;
    }

    /**
     * Transform the Right value.
     *
     * Applies the transformation function to the contained Right value
     * and returns a new Right containing the transformed result.
     *
     * @template T
     *
     * @param  callable(R):T $callable Function to transform the Right value
     * @return self<L, T>    New Right containing transformed value
     */
    public function map(callable $callable)
    {
        return new self($callable($this->value));
    }

    /**
     * Transform the Left value.
     *
     * Since Right has no Left value, this operation has no effect
     * and returns this Right instance unchanged.
     *
     * @template T
     *
     * @param  callable(L):T $callable Function that would transform Left value (ignored)
     * @return self<T, R>    This Right instance
     */
    public function mapLeft(callable $callable)
    {
        return $this;
    }

    /**
     * Transform both Left and Right values.
     *
     * Since this is Right, only the right function is applied.
     *
     * @template T
     * @template U
     *
     * @param  callable(L):T $leftFn  Function that would transform Left value (ignored)
     * @param  callable(R):U $rightFn Function to transform Right value
     * @return self<T, U>    New Right with transformed value
     */
    public function bimap(callable $leftFn, callable $rightFn)
    {
        return new self($rightFn($this->value));
    }

    /**
     * Chain Either-returning operations.
     *
     * Executes the callable with the contained Right value, expecting it to
     * return an Either. That Either becomes the result without additional wrapping.
     * Essential for chaining Either-returning operations.
     *
     * @template T
     *
     * @param callable(R):Either<L, T> $callable Function that must return an Either
     *
     * @throws RuntimeException When callable doesn't return an Either
     *
     * @return Either<L, T> The Either returned by the callable
     */
    public function flatMap(callable $callable)
    {
        /** @var mixed */
        $rs = $callable($this->value);

        if (!$rs instanceof Either) {
            throw new RuntimeException('Callables passed to flatMap() must return an Either. Maybe you should use map() instead?');
        }

        return $rs;
    }

    /**
     * Execute a function for side effects on the Right value.
     *
     * Executes the callable with the contained Right value for side effects.
     * Returns this Right instance for method chaining.
     *
     * @param  callable(R):mixed $callable Function to execute with Right value
     * @return self<L, R>        This Right instance
     */
    public function forAll(callable $callable)
    {
        $callable($this->value);

        return $this;
    }

    /**
     * Execute a function for side effects on the Left value.
     *
     * Since Right has no Left value, the callable is never executed.
     * Returns this Right instance for method chaining.
     *
     * @param  callable(L):mixed $callable Function that would be executed (ignored)
     * @return self<L, R>        This Right instance
     */
    public function forLeft(callable $callable)
    {
        return $this;
    }

    /**
     * Execute a function for debugging/inspection.
     *
     * Executes the callable with the contained Right value for inspection purposes
     * (logging, debugging, etc.) and returns this Right unchanged.
     *
     * @param  callable(R):mixed $callable Function to execute for inspection
     * @return self<L, R>        This Right instance unchanged
     */
    public function inspect(callable $callable)
    {
        $callable($this->value);

        return $this;
    }

    /**
     * Filter the Right value based on a predicate.
     *
     * Tests the contained Right value with the predicate. If the predicate
     * returns true, returns this Right; if false, returns Left with the
     * provided left value.
     *
     * @param  callable(R):bool $predicate Function to test the Right value
     * @param  L                $leftValue Value for Left if predicate fails
     * @return self<L, R>       This Right if predicate passes, Left otherwise
     */
    public function filter(callable $predicate, $leftValue)
    {
        if (true === $predicate($this->value)) {
            return $this;
        }

        return new Left($leftValue);
    }

    /**
     * Pattern matching for exhaustive Either handling.
     *
     * Since this is Right, executes the onRight callback with the Right value.
     *
     * @template T
     *
     * @param  callable(L):T $onLeft  Function that would be called for Left (ignored)
     * @param  callable(R):T $onRight Function to call with Right value
     * @return T             Result of executing onRight callback
     */
    public function match(callable $onLeft, callable $onRight)
    {
        return $onRight($this->value);
    }

    /**
     * Swap Left and Right values.
     *
     * Converts this Right<R> into Left<R>.
     *
     * @return Left<R, R> Left containing the Right value
     */
    public function swap()
    {
        return new Left($this->value);
    }

    /**
     * Clone this Right and its contained value if it's an object.
     *
     * @return self<L, R> New Right instance with cloned value
     */
    public function cloned()
    {
        if (is_object($this->value)) {
            return new self(clone $this->value);
        }

        return new self($this->value);
    }

    /**
     * Fold the Either into a single value.
     *
     * Since this is Right, applies the right function to the Right value.
     *
     * @template T
     *
     * @param  callable(L):T $leftFn  Function that would apply to Left value (ignored)
     * @param  callable(R):T $rightFn Function to apply to Right value
     * @return T             Result of applying rightFn to the Right value
     */
    public function fold(callable $leftFn, callable $rightFn)
    {
        return $rightFn($this->value);
    }

    /**
     * Get an iterator for the contained value.
     *
     * Returns an ArrayIterator containing the single Right value, allowing
     * Right to be used in foreach loops and other iterator contexts.
     *
     * @return ArrayIterator<int, R> Iterator containing the single value
     */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator([$this->value]);
    }
}
