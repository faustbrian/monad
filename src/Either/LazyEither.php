<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Monad\Either;

use InvalidArgumentException;
use RuntimeException;
use Traversable;

use function call_user_func_array;
use function is_callable;
use function sprintf;

/**
 * Lazy-evaluated Either wrapper for deferred computation.
 *
 * LazyEither defers the execution of a callback that returns an Either until
 * the value is actually needed. This is useful for expensive computations that
 * might not be necessary, or for creating chainable operations without immediate
 * evaluation. The callback is executed only once and the result is cached.
 *
 * ```php
 * $expensive = LazyEither::create(function () {
 *     return expensiveDatabaseQuery()
 *         ? new Right($result)
 *         : new Left('Database error');
 * });
 *
 * // Computation not executed until needed
 * $result = $expensive->map('processData')->unwrapOr('default');
 * ```
 *
 * @template L The type of Left value contained within the wrapped Either
 * @template R The type of Right value contained within the wrapped Either
 *
 * @extends Either<L, R>
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class LazyEither extends Either
{
    /** @var callable(mixed...):(Either<L, R>) Callback that produces an Either when executed */
    private mixed $callback;

    /** @var null|Either<L, R> Cached result of the callback execution */
    private mixed $either = null;

    /**
     * Create a new lazy-evaluated Either wrapper.
     *
     * @param callable(mixed...):(Either<L, R>) $callback  Callback that must return an Either when executed.
     *                                                     The callback is invoked lazily only when the Either value
     *                                                     is accessed, and the result is cached for subsequent calls.
     * @param array<int, mixed>                 $arguments Optional arguments to pass to the callback when executed.
     *                                                     These arguments are stored and forwarded to the callback
     *                                                     during evaluation using call_user_func_array().
     *
     * @throws InvalidArgumentException When the provided callback is not callable
     */
    public function __construct(
        $callback,
        private readonly array $arguments = [],
    ) {
        /** @phpstan-ignore function.alreadyNarrowedType */
        if (!is_callable($callback)) {
            throw new InvalidArgumentException('Invalid callback given');
        }

        $this->callback = $callback;
    }

    /**
     * Create a new LazyEither with the specified callback and arguments.
     *
     * @template U
     * @template V
     *
     * @param  callable(mixed...):(Either<U, V>) $callback  Callback that produces an Either
     * @param  array<int, mixed>                 $arguments Arguments to pass to the callback
     * @return self<U, V>                        New LazyEither instance wrapping the callback
     */
    public static function create($callback, array $arguments = []): self
    {
        return new self($callback, $arguments);
    }

    /**
     * Check if the contained Either is Left.
     *
     * Forces evaluation of the lazy callback if not already executed.
     *
     * @return bool True if the wrapped Either is Left
     */
    public function isLeft(): bool
    {
        return $this->either()->isLeft();
    }

    /**
     * Check if the contained Either is Right.
     *
     * Forces evaluation of the lazy callback if not already executed.
     *
     * @return bool True if the wrapped Either is Right
     */
    public function isRight(): bool
    {
        return $this->either()->isRight();
    }

    /**
     * Get the Right value from the wrapped Either.
     *
     * Forces evaluation of the lazy callback if not already executed.
     *
     * @throws RuntimeException When the wrapped Either is Left
     *
     * @return R The Right value
     */
    public function unwrap()
    {
        return $this->either()->unwrap();
    }

    /**
     * Get the Left value from the wrapped Either.
     *
     * Forces evaluation of the lazy callback if not already executed.
     *
     * @throws RuntimeException When the wrapped Either is Right
     *
     * @return L The Left value
     */
    public function unwrapLeft()
    {
        return $this->either()->unwrapLeft();
    }

    /**
     * Unwrap the Right value or return a default value.
     *
     * Forces evaluation of the lazy callback if not already executed.
     *
     * @template T
     *
     * @param  T   $default Default value to return if Left
     * @return R|T The Right value or the default
     */
    public function unwrapOr($default)
    {
        return $this->either()->unwrapOr($default);
    }

    /**
     * Unwrap the Right value or compute a value using a callable.
     *
     * Forces evaluation of the lazy callback if not already executed.
     *
     * @template T
     *
     * @param  callable(L):T $callable Function to compute default from Left value
     * @return R|T           The Right value or computed default
     */
    public function unwrapOrElse(callable $callable)
    {
        return $this->either()->unwrapOrElse($callable);
    }

    /**
     * Transform the Right value using the provided callable.
     *
     * Forces evaluation of the lazy callback if not already executed.
     *
     * @template T
     *
     * @param  callable(R):T $callable Function to transform the Right value
     * @return Either<L, T>  Either containing the transformed value or Left
     */
    public function map(callable $callable)
    {
        return $this->either()->map($callable);
    }

    /**
     * Transform the Left value using the provided callable.
     *
     * Forces evaluation of the lazy callback if not already executed.
     *
     * @template T
     *
     * @param  callable(L):T $callable Function to transform the Left value
     * @return Either<T, R>  Either containing the transformed Left or Right
     */
    public function mapLeft(callable $callable)
    {
        return $this->either()->mapLeft($callable);
    }

    /**
     * Transform both Left and Right values.
     *
     * Forces evaluation of the lazy callback if not already executed.
     *
     * @template T
     * @template U
     *
     * @param  callable(L):T $leftFn  Function to transform Left value
     * @param  callable(R):U $rightFn Function to transform Right value
     * @return Either<T, U>  Either with transformed value
     */
    public function bimap(callable $leftFn, callable $rightFn)
    {
        return $this->either()->bimap($leftFn, $rightFn);
    }

    /**
     * Apply a function that returns an Either and flatten the result.
     *
     * Forces evaluation of the lazy callback if not already executed.
     *
     * @template T
     *
     * @param  callable(R):Either<L, T> $callable Function that returns an Either
     * @return Either<L, T>             Flattened Either result
     */
    public function flatMap(callable $callable)
    {
        return $this->either()->flatMap($callable);
    }

    /**
     * Execute a function for side effects on the Right value.
     *
     * Forces evaluation of the lazy callback if not already executed.
     *
     * @param  callable(R):mixed $callable Function to execute with Right value
     * @return Either<L, R>      This Either for method chaining
     */
    public function forAll(callable $callable)
    {
        return $this->either()->forAll($callable);
    }

    /**
     * Execute a function for side effects on the Left value.
     *
     * Forces evaluation of the lazy callback if not already executed.
     *
     * @param  callable(L):mixed $callable Function to execute with Left value
     * @return Either<L, R>      This Either for method chaining
     */
    public function forLeft(callable $callable)
    {
        return $this->either()->forLeft($callable);
    }

    /**
     * Execute a function for debugging/inspection.
     *
     * Forces evaluation of the lazy callback if not already executed.
     *
     * @param  callable(R):mixed $callable Function to execute on Right
     * @return Either<L, R>      This Either unchanged
     */
    public function inspect(callable $callable)
    {
        return $this->either()->inspect($callable);
    }

    /**
     * Filter the Right value based on a predicate.
     *
     * Forces evaluation of the lazy callback if not already executed.
     *
     * @param  callable(R):bool $predicate Function to test Right value
     * @param  L                $leftValue Value for Left if predicate fails
     * @return Either<L, R>     This Either if Right and predicate passes, Left otherwise
     */
    public function filter(callable $predicate, $leftValue)
    {
        return $this->either()->filter($predicate, $leftValue);
    }

    /**
     * Pattern matching for exhaustive Either handling.
     *
     * Forces evaluation of the lazy callback if not already executed.
     *
     * @template T
     *
     * @param  callable(L):T $onLeft  Function to call if Left
     * @param  callable(R):T $onRight Function to call if Right
     * @return T             Result of the executed callback
     */
    public function match(callable $onLeft, callable $onRight)
    {
        return $this->either()->match($onLeft, $onRight);
    }

    /**
     * Swap Left and Right values.
     *
     * Forces evaluation of the lazy callback if not already executed.
     *
     * @return Either<R, L> Either with swapped values
     */
    public function swap()
    {
        return $this->either()->swap();
    }

    /**
     * Clone this Either and its contained value if it's an object.
     *
     * Forces evaluation of the lazy callback if not already executed.
     *
     * @return Either<L, R> Cloned Either with cloned value
     */
    public function cloned()
    {
        return $this->either()->cloned();
    }

    /**
     * Fold the Either into a single value.
     *
     * Forces evaluation of the lazy callback if not already executed.
     *
     * @template T
     *
     * @param  callable(L):T $leftFn  Function to apply to Left value
     * @param  callable(R):T $rightFn Function to apply to Right value
     * @return T             Result of applying the appropriate function
     */
    public function fold(callable $leftFn, callable $rightFn)
    {
        return $this->either()->fold($leftFn, $rightFn);
    }

    /**
     * Get an iterator for the contained value.
     *
     * Forces evaluation of the lazy callback if not already executed.
     *
     * @return Traversable<R> Iterator for the Right value or empty iterator
     */
    public function getIterator(): Traversable
    {
        return $this->either()->getIterator();
    }

    /**
     * Evaluate the lazy callback and cache the result.
     *
     * This method performs the actual evaluation of the lazy callback,
     * validates that it returns an Either instance, and caches the result
     * for subsequent calls. The callback is only executed once.
     *
     * @throws RuntimeException When callback doesn't return an Either instance
     *
     * @return Either<L, R> The Either returned by the callback
     */
    private function either(): Either
    {
        if (null === $this->either) {
            /** @var mixed */
            $either = call_user_func_array($this->callback, $this->arguments);

            if ($either instanceof Either) {
                $this->either = $either;
            } else {
                throw new RuntimeException(sprintf('Expected instance of %s', Either::class));
            }
        }

        return $this->either;
    }
}
