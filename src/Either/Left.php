<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Monad\Either;

use EmptyIterator;
use RuntimeException;

use function is_object;

/**
 * Represents the Left variant of the Either type pattern.
 *
 * Left is conventionally used to represent an error, alternative path, or
 * the "other" value in a dual-value container. Operations on Right values
 * (map, flatMap) are skipped for Left, while Left-specific operations
 * (mapLeft, forLeft) are executed.
 *
 * ```php
 * $error = new Left('File not found');
 * $result = $error->map('strtoupper'); // Still Left('File not found')
 * $transformed = $error->mapLeft('strtoupper'); // Left('FILE NOT FOUND')
 * ```
 *
 * @template L The type of value contained within this Left
 * @template R The type of Right value (phantom type, never used)
 *
 * @extends Either<L, R>
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class Left extends Either
{
    /** @var L The value contained within this Left instance */
    private mixed $value;

    /**
     * Create a new Left instance containing the provided value.
     *
     * @param L $value The value to wrap in this Left
     */
    public function __construct($value)
    {
        $this->value = $value;
    }

    /**
     * Check if this Either is Left.
     *
     * @return bool Always returns true for Left
     */
    public function isLeft(): bool
    {
        return true;
    }

    /**
     * Check if this Either is Right.
     *
     * @return bool Always returns false for Left
     */
    public function isRight(): bool
    {
        return false;
    }

    /**
     * Attempt to get the Right value from Left.
     *
     * @throws RuntimeException Always thrown since Left has no Right value
     *
     * @return never This method never returns normally
     */
    public function unwrap(): void
    {
        throw new RuntimeException('Cannot unwrap Right value from Left.');
    }

    /**
     * Get the Left value.
     *
     * @return L The contained Left value
     */
    public function unwrapLeft()
    {
        return $this->value;
    }

    /**
     * Return the provided default value.
     *
     * @template T
     *
     * @param  T $default The default value to return
     * @return T The provided default value
     */
    public function unwrapOr($default)
    {
        return $default;
    }

    /**
     * Execute callable to compute default from Left value.
     *
     * @template T
     *
     * @param  callable(L):T $callable Function to compute default from Left value
     * @return T             Result of executing the callable with Left value
     */
    public function unwrapOrElse(callable $callable)
    {
        return $callable($this->value);
    }

    /**
     * Transform the Right value.
     *
     * Since Left has no Right value, this operation has no effect
     * and returns this Left instance unchanged.
     *
     * @template T
     *
     * @param  callable(R):T $callable Function that would transform Right value (ignored)
     * @return self<L, T>    This Left instance
     */
    public function map(callable $callable)
    {
        return $this;
    }

    /**
     * Transform the Left value.
     *
     * @template T
     *
     * @param  callable(L):T $callable Function to transform Left value
     * @return self<T, R>    New Left containing transformed value
     */
    public function mapLeft(callable $callable)
    {
        return new self($callable($this->value));
    }

    /**
     * Transform both Left and Right values.
     *
     * Since this is Left, only the left function is applied.
     *
     * @template T
     * @template U
     *
     * @param  callable(L):T $leftFn  Function to transform Left value
     * @param  callable(R):U $rightFn Function that would transform Right value (ignored)
     * @return self<T, U>    New Left with transformed value
     */
    public function bimap(callable $leftFn, callable $rightFn)
    {
        return new self($leftFn($this->value));
    }

    /**
     * Chain Either-returning operations.
     *
     * Since Left has no Right value, the callable is never executed
     * and this Left instance is returned unchanged.
     *
     * @template T
     *
     * @param  callable(R):Either<L, T> $callable Function that would return Either (ignored)
     * @return self<L, T>               This Left instance
     */
    public function flatMap(callable $callable)
    {
        return $this;
    }

    /**
     * Execute a function for side effects on the Right value.
     *
     * Since Left has no Right value, the callable is never executed.
     * Returns this Left instance for method chaining.
     *
     * @param  callable(R):mixed $callable Function that would be executed (ignored)
     * @return self<L, R>        This Left instance
     */
    public function forAll(callable $callable)
    {
        return $this;
    }

    /**
     * Execute a function for side effects on the Left value.
     *
     * Executes the callable with the contained Left value for side effects.
     * Returns this Left instance for method chaining.
     *
     * @param  callable(L):mixed $callable Function to execute with Left value
     * @return self<L, R>        This Left instance
     */
    public function forLeft(callable $callable)
    {
        $callable($this->value);

        return $this;
    }

    /**
     * Execute a function for debugging/inspection.
     *
     * Since Left has no Right value, the callable is never executed.
     * Returns this Left instance unchanged.
     *
     * @param  callable(R):mixed $callable Function that would be executed (ignored)
     * @return self<L, R>        This Left instance
     */
    public function inspect(callable $callable)
    {
        return $this;
    }

    /**
     * Filter the Right value based on a predicate.
     *
     * Since Left has no Right value to filter, this operation has no effect
     * and returns this Left instance unchanged.
     *
     * @param  callable(R):bool $predicate Function that would test Right value (ignored)
     * @param  L                $leftValue Value for Left if predicate fails (ignored)
     * @return self<L, R>       This Left instance
     */
    public function filter(callable $predicate, $leftValue)
    {
        return $this;
    }

    /**
     * Pattern matching for exhaustive Either handling.
     *
     * Since this is Left, executes the onLeft callback with the Left value.
     *
     * @template T
     *
     * @param  callable(L):T $onLeft  Function to call with Left value
     * @param  callable(R):T $onRight Function that would be called for Right (ignored)
     * @return T             Result of executing onLeft callback
     */
    public function match(callable $onLeft, callable $onRight)
    {
        return $onLeft($this->value);
    }

    /**
     * Swap Left and Right values.
     *
     * Converts this Left<L> into Right<L>.
     *
     * @return Right<L, L> Right containing the Left value
     */
    public function swap()
    {
        return new Right($this->value);
    }

    /**
     * Clone this Left and its contained value if it's an object.
     *
     * @return self<L, R> New Left instance with cloned value
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
     * Since this is Left, applies the left function to the Left value.
     *
     * @template T
     *
     * @param  callable(L):T $leftFn  Function to apply to Left value
     * @param  callable(R):T $rightFn Function that would apply to Right value (ignored)
     * @return T             Result of applying leftFn to the Left value
     */
    public function fold(callable $leftFn, callable $rightFn)
    {
        return $leftFn($this->value);
    }

    /**
     * Get an iterator for the contained value.
     *
     * Since Left represents an alternative path, returns an EmptyIterator
     * that yields no elements when used in foreach loops.
     *
     * @return EmptyIterator Empty iterator with no elements
     */
    public function getIterator(): EmptyIterator
    {
        return new EmptyIterator();
    }
}
