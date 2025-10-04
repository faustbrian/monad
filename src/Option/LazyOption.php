<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Monad\Option;

use Exception;
use InvalidArgumentException;
use RuntimeException;
use Traversable;

use function call_user_func_array;
use function is_callable;
use function sprintf;

/**
 * Lazy-evaluated Option wrapper for deferred computation.
 *
 * LazyOption defers the execution of a callback that returns an Option until
 * the value is actually needed. This is useful for expensive computations that
 * might not be necessary, or for creating chainable operations without immediate
 * evaluation. The callback is executed only once and the result is cached.
 *
 * ```php
 * $expensive = LazyOption::create(function () {
 *     return expensiveDatabaseQuery() ? new Some($result) : None::create();
 * });
 *
 * // Computation not executed until needed
 * $result = $expensive->map('processData')->unwrapOr('default');
 * ```
 *
 * @template T The type of value contained within the wrapped Option
 *
 * @extends Option<T>
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class LazyOption extends Option
{
    /** @var callable(mixed...):(Option<T>) Callback that produces an Option when executed */
    private mixed $callback;

    /** @var null|Option<T> Cached result of the callback execution */
    private mixed $option = null;

    /**
     * Create a new lazy-evaluated Option wrapper.
     *
     * @param callable(mixed...):(Option<T>) $callback  Callback that must return an Option when executed.
     *                                                  The callback is invoked lazily only when the Option value
     *                                                  is accessed, and the result is cached for subsequent calls.
     * @param array<int, mixed>              $arguments Optional arguments to pass to the callback when executed.
     *                                                  These arguments are stored and forwarded to the callback
     *                                                  during evaluation using call_user_func_array().
     *
     * @throws InvalidArgumentException When the provided callback is not callable
     */
    public function __construct(
        $callback,
        private readonly array $arguments = [],
    ) {
        if (!is_callable($callback)) {
            throw new InvalidArgumentException('Invalid callback given');
        }

        $this->callback = $callback;
    }

    /**
     * Create a new LazyOption with the specified callback and arguments.
     *
     * @template S
     *
     * @param  callable(mixed...):(Option<S>) $callback  Callback that produces an Option
     * @param  array<int, mixed>              $arguments Arguments to pass to the callback
     * @return self<S>                        New LazyOption instance wrapping the callback
     */
    public static function create($callback, array $arguments = []): self
    {
        return new self($callback, $arguments);
    }

    /**
     * Check if the contained Option has a defined value.
     *
     * Forces evaluation of the lazy callback if not already executed.
     *
     * @return bool True if the wrapped Option contains a value
     */
    public function isDefined(): bool
    {
        return $this->option()->isDefined();
    }

    /**
     * Check if the contained Option is empty.
     *
     * Forces evaluation of the lazy callback if not already executed.
     *
     * @return bool True if the wrapped Option is None
     */
    public function isEmpty(): bool
    {
        return $this->option()->isEmpty();
    }

    /**
     * Get the contained value from the wrapped Option.
     *
     * Forces evaluation of the lazy callback if not already executed.
     *
     * @throws RuntimeException When the wrapped Option is None
     *
     * @return T The contained value
     */
    public function get()
    {
        return $this->option()->get();
    }

    /**
     * Unwrap the contained value or return a default value.
     *
     * Forces evaluation of the lazy callback if not already executed.
     *
     * @template S
     *
     * @param  S   $default Default value to return if None
     * @return S|T The contained value or the default
     */
    public function unwrapOr($default)
    {
        return $this->option()->unwrapOr($default);
    }

    /**
     * Unwrap the contained value or compute a value using a callable.
     *
     * Forces evaluation of the lazy callback if not already executed.
     *
     * @template S
     *
     * @param  callable():S $callable Function to compute default value
     * @return S|T          The contained value or computed default
     */
    public function unwrapOrElse($callable)
    {
        return $this->option()->unwrapOrElse($callable);
    }

    /**
     * Unwrap the contained value or throw the provided exception.
     *
     * Forces evaluation of the lazy callback if not already executed.
     *
     * @param Exception $ex Exception to throw if None
     *
     * @throws Exception The provided exception if None
     *
     * @return T The contained value
     */
    public function unwrapOrThrow(Exception $ex)
    {
        return $this->option()->unwrapOrThrow($ex);
    }

    /**
     * Return this Option if it contains a value, otherwise return the result of the callable.
     *
     * Forces evaluation of the lazy callback if not already executed.
     *
     * @param  callable():Option<T> $else Callable that returns an alternative Option
     * @return Option<T>            This Option or the alternative
     */
    public function orElse(callable $else)
    {
        return $this->option()->orElse($else);
    }

    /**
     * Execute a callable if the Option contains a value.
     *
     * Forces evaluation of the lazy callback if not already executed.
     * This method is deprecated in favor of forAll().
     *
     * @deprecated Use forAll() instead
     *
     * @param callable(T):mixed $callable Function to execute with the contained value
     */
    public function ifDefined($callable): void
    {
        $this->option()->forAll($callable);
    }

    /**
     * Execute a callable for side effects if the Option contains a value.
     *
     * Forces evaluation of the lazy callback if not already executed.
     * Returns this Option for method chaining.
     *
     * @param  callable(T):mixed $callable Function to execute with the contained value
     * @return Option<T>         This Option for method chaining
     */
    public function forAll($callable)
    {
        return $this->option()->forAll($callable);
    }

    /**
     * Transform the contained value using the provided callable.
     *
     * Forces evaluation of the lazy callback if not already executed.
     *
     * @template S
     *
     * @param  callable(T):S $callable Function to transform the value
     * @return Option<S>     Option containing the transformed value or None
     */
    public function map($callable)
    {
        return $this->option()->map($callable);
    }

    /**
     * Map the contained value or return null if None.
     *
     * Forces evaluation of the lazy callback if not already executed.
     *
     * @template S
     *
     * @param  callable(T):S $f Function to transform the value
     * @return null|S        Transformed value or null if None
     */
    public function mapOrDefault(callable $f)
    {
        return $this->option()->mapOrDefault($f);
    }

    /**
     * Apply a function that returns an Option and flatten the result.
     *
     * Forces evaluation of the lazy callback if not already executed.
     *
     * @template S
     *
     * @param  callable(T):Option<S> $callable Function that returns an Option
     * @return Option<S>             Flattened Option result
     */
    public function flatMap($callable)
    {
        return $this->option()->flatMap($callable);
    }

    /**
     * Filter the Option based on a predicate function.
     *
     * Forces evaluation of the lazy callback if not already executed.
     *
     * @param  callable(T):bool $callable Predicate function to test the value
     * @return Option<T>        This Option if predicate passes, None otherwise
     */
    public function filter($callable)
    {
        return $this->option()->filter($callable);
    }

    /**
     * Filter the Option based on the negation of a predicate function.
     *
     * Forces evaluation of the lazy callback if not already executed.
     *
     * @param  callable(T):bool $callable Predicate function to test the value
     * @return Option<T>        This Option if predicate fails, None otherwise
     */
    public function filterNot($callable)
    {
        return $this->option()->filterNot($callable);
    }

    /**
     * Create a clone of this Option and its contained value if it's an object.
     *
     * Forces evaluation of the lazy callback if not already executed.
     *
     * @return Option<T> Cloned Option with cloned contained value
     */
    public function cloned(): Option
    {
        return $this->option()->cloned();
    }

    /**
     * Return this Option if it contains the specified value, None otherwise.
     *
     * Forces evaluation of the lazy callback if not already executed.
     *
     * @param  T         $value Value to select for
     * @return Option<T> This Option if it contains the value, None otherwise
     */
    public function select($value)
    {
        return $this->option()->select($value);
    }

    /**
     * Return None if this Option contains the specified value, this Option otherwise.
     *
     * Forces evaluation of the lazy callback if not already executed.
     *
     * @param  T         $value Value to reject
     * @return Option<T> None if contains the value, this Option otherwise
     */
    public function reject($value)
    {
        return $this->option()->reject($value);
    }

    /**
     * @return Traversable<T>
     */
    public function getIterator(): Traversable
    {
        return $this->option()->getIterator();
    }

    /**
     * Apply a binary function to the initial value and the contained value.
     *
     * Forces evaluation of the lazy callback if not already executed.
     *
     * @template S
     *
     * @param  S                $initialValue Starting value for the fold operation
     * @param  callable(S, T):S $callable     Binary function to apply
     * @return S                Result of the fold operation
     */
    public function foldLeft($initialValue, $callable)
    {
        return $this->option()->foldLeft($initialValue, $callable);
    }

    /**
     * Apply a binary function to the contained value and the initial value.
     *
     * Forces evaluation of the lazy callback if not already executed.
     *
     * @template S
     *
     * @param  S                $initialValue Starting value for the fold operation
     * @param  callable(T, S):S $callable     Binary function to apply
     * @return S                Result of the fold operation
     */
    public function foldRight($initialValue, $callable)
    {
        return $this->option()->foldRight($initialValue, $callable);
    }

    /**
     * Evaluate the lazy callback and cache the result.
     *
     * This method performs the actual evaluation of the lazy callback,
     * validates that it returns an Option instance, and caches the result
     * for subsequent calls. The callback is only executed once.
     *
     * @throws RuntimeException When callback doesn't return an Option instance
     *
     * @return Option<T> The Option returned by the callback
     */
    private function option(): Option
    {
        if (null === $this->option) {
            /** @var mixed */
            $option = call_user_func_array($this->callback, $this->arguments);

            if ($option instanceof Option) {
                $this->option = $option;
            } else {
                throw new RuntimeException(sprintf('Expected instance of %s', Option::class));
            }
        }

        return $this->option;
    }
}
