<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Monad\Option;

use App\Types\Result;
use ArrayAccess;
use Cline\Monad\Result\Err;
use Cline\Monad\Result\Ok;
use Exception;
use IteratorAggregate;
use RuntimeException;

use function abort_unless;
use function array_key_exists;
use function array_map;
use function array_reduce;
use function func_get_args;
use function gettype;
use function is_array;
use function is_callable;
use function is_object;

/**
 * Abstract base class for the Option type pattern in PHP.
 *
 * Option represents a value that may or may not be present, providing a type-safe
 * alternative to null values. It encodes the possibility of absence directly in
 * the type system, eliminating null pointer exceptions and making optional values
 * explicit in method signatures.
 *
 * The Option type has two concrete implementations:
 * - Some<T>: Contains a value of type T
 * - None: Represents the absence of a value
 *
 * Common usage patterns:
 *
 * ```php
 * // Creating Options
 * $some = new Some('hello');
 * $none = None::create();
 * $fromNullable = Option::fromNullable($mightBeNull);
 *
 * // Transforming values safely
 * $result = $option
 *     ->map('strtoupper')
 *     ->filter(fn($s) => strlen($s) > 3)
 *     ->unwrapOr('default');
 *
 * // Chaining operations
 * $user = Option::fromNullable($userId)
 *     ->flatMap(fn($id) => findUserById($id))
 *     ->filter(fn($user) => $user->isActive())
 *     ->unwrapOr(new GuestUser());
 * ```
 *
 * @template T The type of value that may be contained
 *
 * @implements IteratorAggregate<T>
 *
 * @author Brian Faust <brian@cline.sh>
 */
abstract class Option implements IteratorAggregate
{
    /**
     * Create an Option from a nullable value, treating null as None.
     *
     * This is the most common way to create Options from existing code
     * that uses null to represent absence.
     *
     * ```php
     * $user = Option::fromNullable(getCurrentUser()); // Some<User> or None
     * $config = Option::fromNullable($_ENV['CONFIG_PATH']); // Some<string> or None
     * ```
     *
     * @template S
     *
     * @param  null|S  $value The value to wrap, null becomes None
     * @return self<S> Some containing the value, or None if null
     */
    public static function fromNullable($value)
    {
        return self::fromValue($value, null);
    }

    /**
     * Create an Option from a value, with custom None sentinel.
     *
     * Allows you to specify which value should be treated as "None"
     * instead of the default null. This is useful when integrating with
     * APIs that use other values (like false, empty string, or -1) to
     * indicate absence.
     *
     * ```php
     * $result = Option::fromValue(strpos($text, 'needle'), false); // Some<int> or None
     * $user = Option::fromValue(findUser($id), 'not_found'); // Some<User> or None
     * ```
     *
     * @template S
     *
     * @param  S       $value     The actual return value to evaluate
     * @param  S       $noneValue The sentinel value that represents None (null by default)
     * @return self<S> Some containing the value, or None if it equals noneValue
     */
    public static function fromValue($value, $noneValue = null)
    {
        if ($value === $noneValue) {
            return None::create();
        }

        return new Some($value);
    }

    /**
     * Create an Option from an array key lookup.
     *
     * Safely access array or ArrayAccess values without worrying about
     * undefined keys or null values. Returns None if the key doesn't exist,
     * the container is not array-like, or the value at the key is null.
     *
     * ```php
     * $config = Option::fromArraysValue($_ENV, 'DATABASE_URL'); // Some<string> or None
     * $user = Option::fromArraysValue($users, $userId); // Some<User> or None
     * $setting = Option::fromArraysValue(new ArrayObject($data), 'theme'); // Some<string> or None
     * ```
     *
     * @template S
     *
     * @param  null|array<int|string, S>|ArrayAccess<int|string, S> $array Container to access
     * @param  null|int|string                                      $key   Key to look up
     * @return self<S>                                              Some containing the value, or None
     */
    public static function fromArraysValue($array, $key)
    {
        if ($key === null) {
            return None::create();
        }

        if (is_array($array)) {
            if (!array_key_exists($key, $array)) {
                return None::create();
            }

            $value = $array[$key];

            return $value === null ? None::create() : new Some($value);
        }

        if ($array instanceof ArrayAccess) {
            if (!$array->offsetExists($key)) {
                return None::create();
            }

            /** @var mixed $value */
            $value = $array[$key];

            return $value === null ? None::create() : new Some($value);
        }

        return None::create();
    }

    /**
     * Create a lazy-evaluated Option from a callback.
     *
     * Defers execution of potentially expensive operations until the Option
     * value is actually needed. The callback is executed only once and the
     * result is cached for subsequent accesses.
     *
     * ```php
     * $expensive = Option::fromReturn(fn() => expensiveDbQuery(), [], null);
     * $fileContent = Option::fromReturn('file_get_contents', ['/path/to/file'], false);
     *
     * // Only executes when needed
     * $result = $expensive->map('processData')->unwrapOr('default');
     * ```
     *
     * @template S
     *
     * @param  callable      $callback  Function to execute for the value
     * @param  array         $arguments Arguments to pass to the callback
     * @param  S             $noneValue Value that represents None (null by default)
     * @return LazyOption<S> Lazy Option that will execute callback when needed
     */
    public static function fromReturn($callback, array $arguments = [], $noneValue = null)
    {
        return new LazyOption(static function () use ($callback, $arguments, $noneValue) {
            /** @var mixed */
            $return = $callback(...$arguments);

            if ($return === $noneValue) {
                return None::create();
            }

            return new Some($return);
        });
    }

    /**
     * Flexible Option factory that handles multiple input types.
     *
     * Automatically creates the appropriate Option type based on the input:
     * - If already an Option, returns it unchanged
     * - If callable, creates a LazyOption that will execute when needed
     * - Otherwise, wraps the value using fromValue()
     *
     * ```php
     * $existing = Option::ensure($someOption); // Returns $someOption unchanged
     * $lazy = Option::ensure(fn() => expensiveCall()); // LazyOption
     * $immediate = Option::ensure('hello'); // Some('hello')
     * ```
     *
     * @template S
     *
     * @param  callable|S|self<S>    $value     Value to ensure is an Option
     * @param  S                     $noneValue Value representing None for non-Option inputs
     * @return LazyOption<S>|self<S> Appropriate Option type for the input
     */
    public static function ensure($value, $noneValue = null)
    {
        if ($value instanceof self) {
            return $value;
        } elseif (is_callable($value)) {
            return new LazyOption(static function () use ($value, $noneValue) {
                /** @var mixed */
                $return = $value();

                if ($return instanceof self) {
                    return $return;
                } else {
                    return self::fromValue($return, $noneValue);
                }
            });
        } else {
            return self::fromValue($value, $noneValue);
        }
    }

    /**
     * Lift a function to work with Option parameters.
     *
     * Creates a new function that operates on Option values instead of raw values.
     * If any Option parameter is None, the lifted function returns None without
     * executing the original function. Otherwise, it unwraps all Option parameters,
     * calls the original function, and wraps the result in an Option.
     *
     * ```php
     * $add = fn($a, $b) => $a + $b;
     * $safeAdd = Option::lift($add);
     *
     * $result = $safeAdd(new Some(5), new Some(3)); // Some(8)
     * $result = $safeAdd(new Some(5), None::create()); // None
     * ```
     *
     * @template S
     *
     * @param  callable $callback  Function to lift to work with Options
     * @param  mixed    $noneValue Value representing None for function results
     * @return callable New function accepting Option parameters
     */
    public static function lift($callback, $noneValue = null)
    {
        return static function () use ($callback, $noneValue) {
            /** @var array<int, mixed> */
            $args = func_get_args();

            $reduced_args = array_reduce(
                $args,
                /** @param bool $status */
                static function ($status, self $o) {
                    return $o->isEmpty() ? true : $status;
                },
                false,
            );

            // if at least one parameter is empty, return None
            if ($reduced_args) {
                return None::create();
            }

            $args = array_map(
                /** @return T */
                static function (self $o) {
                    // it is safe to do so because the fold above checked
                    // that all arguments are of type Some
                    /** @var T */
                    return $o->get();
                },
                $args,
            );

            return self::ensure($callback(...$args), $noneValue);
        };
    }

    /**
     * Alias for flatMap providing monadic bind operation.
     *
     * @template S
     *
     * @param  callable(T):self<S> $f Function that returns an Option
     * @return self<S>             Result of the monadic bind operation
     */
    public function andThen(callable $f)
    {
        return $this->flatMap($f);
    }

    /**
     * Pattern matching for exhaustive Option handling.
     *
     * Provides a functional approach to handle both Some and None cases
     * explicitly. The appropriate callback is executed based on the Option state.
     *
     * ```php
     * $message = $user->match(
     *     fn($u) => "Hello, {$u->name}!",
     *     fn() => "Hello, guest!"
     * );
     * ```
     *
     * @template U
     *
     * @param  callable(T):U $onSome Function to call if Option contains a value
     * @param  callable():U  $onNone Function to call if Option is None
     * @return U             Result of the executed callback
     */
    public function match(callable $onSome, callable $onNone)
    {
        if ($this->isDefined()) {
            return $onSome($this->get());
        }

        return $onNone();
    }

    /**
     * Rust-style unwrap: return the value or throw an exception.
     *
     * Use with caution - only when you're certain the Option contains a value.
     * Prefer unwrapOr() or unwrapOrElse() for safer alternatives.
     *
     * @throws RuntimeException When called on None
     *
     * @return T The contained value
     */
    public function unwrap()
    {
        return $this->get();
    }

    /**
     * Rust-style expect: unwrap with a custom error message.
     *
     * Like unwrap() but allows you to provide a descriptive error message
     * for better debugging when the expectation fails.
     *
     * ```php
     * $config = Option::fromNullable($_ENV['DATABASE_URL'])
     *     ->expect('DATABASE_URL environment variable is required');
     * ```
     *
     * @param string $message Custom error message for the exception
     *
     * @throws RuntimeException With the custom message when called on None
     *
     * @return T The contained value
     */
    public function expect(string $message)
    {
        return $this->unwrapOrThrow(
            new RuntimeException($message),
        );
    }

    /**
     * Rust-style and: return other Option if this Option is Some, otherwise None.
     *
     * Useful for conditional chaining where you want to proceed with another
     * Option only if the current one has a value.
     *
     * ```php
     * $credentials = $username->and($password); // Only if both exist
     * $result = $option->and(performNextOperation()); // Short-circuit on None
     * ```
     *
     * @template S
     *
     * @param  self<S> $other Option to return if this Option is Some
     * @return self<S> The other Option if this is Some, None otherwise
     */
    public function and(self $other)
    {
        return $this->isDefined() ? $other : None::create();
    }

    /**
     * Rust-style or: return this Option if Some, otherwise return other Option.
     *
     * Provides a fallback Option when this Option is None. The first
     * Option with a value wins.
     *
     * ```php
     * $config = $userConfig->or($defaultConfig); // Fallback to default
     * $result = $cache->or($database->or($hardcodedDefault)); // Multiple fallbacks
     * ```
     *
     * @template S
     *
     * @param  self<S>   $other Fallback Option to use if this Option is None
     * @return self<S|T> This Option if Some, otherwise the other Option
     */
    public function or(self $other)
    {
        return $this->isDefined() ? $this : $other;
    }

    /**
     * Rust-style xor: return Some only if exactly one Option is Some.
     *
     * Returns Some if exactly one of the two Options contains a value,
     * None if both are Some or both are None. Useful for exclusive choices.
     *
     * ```php
     * $exclusive = $optionA->xor($optionB); // Some only if exactly one has value
     * ```
     *
     * @template S
     *
     * @param  self<S>   $other Other Option for exclusive comparison
     * @return self<S|T> Some if exactly one Option is Some, None otherwise
     */
    public function xor(self $other)
    {
        $a = $this->isDefined();
        $b = $other->isDefined();

        if ($a && !$b) {
            return $this;
        }

        if (!$a && $b) {
            return $other;
        }

        return None::create();
    }

    /**
     * Rust-style map_or: transform value with function or return default.
     *
     * Combines mapping and default value provision in a single operation.
     * If Some, applies the function to the value; if None, returns the default.
     *
     * ```php
     * $length = $text->mapOr(0, 'strlen'); // String length or 0
     * $display = $user->mapOr('Guest', fn($u) => $u->name); // User name or 'Guest'
     * ```
     *
     * @template U
     *
     * @param  U             $default Default value to return if None
     * @param  callable(T):U $f       Function to apply to the value if Some
     * @return U             Transformed value or default
     */
    public function mapOr($default, callable $f)
    {
        return $this->isDefined() ? $f($this->get()) : $default;
    }

    /**
     * Rust-style map_or_else: transform value or compute default lazily.
     *
     * Like mapOr but the default value is computed lazily only when needed.
     * This is more efficient when the default computation is expensive.
     *
     * ```php
     * $result = $option->mapOrElse(
     *     fn() => expensiveDefaultComputation(),
     *     fn($value) => processValue($value)
     * );
     * ```
     *
     * @template U
     *
     * @param  callable():U  $default Function to compute default value if None
     * @param  callable(T):U $f       Function to apply to the value if Some
     * @return U             Transformed value or computed default
     */
    public function mapOrElse(callable $default, callable $f)
    {
        return $this->isDefined() ? $f($this->get()) : $default();
    }

    /**
     * Rust-style zip: combine two Options into an Option of a tuple.
     *
     * If both Options contain values, returns Some with a tuple of both values.
     * If either Option is None, returns None. Useful for combining related values.
     *
     * ```php
     * $combined = $username->zip($password); // Some(['user', 'pass']) or None
     * $coordinates = $latitude->zip($longitude); // Some([lat, lng]) or None
     * ```
     *
     * @template S
     *
     * @param  self<S>               $other Second Option to combine with
     * @return self<array{0:T, 1:S}> Option containing tuple of both values, or None
     */
    public function zip(self $other)
    {
        if ($this->isDefined() && $other->isDefined()) {
            return new Some([$this->get(), $other->get()]);
        }

        return None::create();
    }

    /**
     * Rust-style zip_with: combine two Options using a function.
     *
     * If both Options contain values, applies the function to both values
     * and returns Some with the result. If either is None, returns None.
     *
     * ```php
     * $sum = $num1->zipWith($num2, fn($a, $b) => $a + $b); // Some(sum) or None
     * $fullName = $first->zipWith($last, fn($f, $l) => "$f $l"); // Some(name) or None
     * ```
     *
     * @template U
     *
     * @param  self<mixed>          $other Other Option to combine with
     * @param  callable(T, mixed):U $f     Function to combine the values
     * @return self<U>              Option containing the combined result, or None
     */
    public function zipWith(self $other, callable $f)
    {
        if ($this->isDefined() && $other->isDefined()) {
            return new Some($f($this->get(), $other->get()));
        }

        return None::create();
    }

    /**
     * Rust-style unzip: split Option of tuple into tuple of Options.
     *
     * Converts Option<[A,B]> into [Option<A>, Option<B>]. If this Option
     * contains a two-element array, returns Some for each element.
     * If this Option is None or doesn't contain a proper tuple, returns None for both.
     *
     * ```php
     * [$optA, $optB] = $tupleOption->unzip(); // Split into individual Options
     * ```
     *
     * @throws RuntimeException When the contained value is not a proper two-element array
     *
     * @return array{0:self<mixed>, 1:self<mixed>} Tuple of Options
     */
    public function unzip(): array
    {
        if ($this->isEmpty()) {
            return [None::create(), None::create()];
        }

        $v = $this->get();

        if (is_array($v) && array_key_exists(0, $v) && array_key_exists(1, $v)) {
            return [self::fromNullable($v[0]), self::fromNullable($v[1])];
        }

        throw new RuntimeException('Option::unzip expects Some([a,b]).');
    }

    /**
     * Unwrap the value or return null as default.
     *
     * PHP-friendly version of Rust's unwrap_or_default() that uses null
     * as the default value, which is idiomatic in PHP contexts.
     *
     * @return null|T The contained value or null
     */
    public function unwrapOrDefault()
    {
        return $this->unwrapOr(null);
    }

    /**
     * Flatten nested Options into a single Option.
     *
     * If this Option contains another Option, returns the inner Option.
     * If this Option is None or contains a non-Option value, returns unchanged.
     * Useful for eliminating multiple levels of Option nesting.
     *
     * ```php
     * $nested = new Some(new Some('value')); // Option<Option<string>>
     * $flat = $nested->flatten(); // Option<string>
     * ```
     *
     * @return self<mixed> Flattened Option with one less level of nesting
     */
    public function flatten()
    {
        if ($this->isEmpty()) {
            return $this; // None
        }

        $v = $this->get();

        return $v instanceof self ? $v : $this;
    }

    /**
     * Check if this Option contains the specified value.
     *
     * Returns true only if this Option is Some and its contained value
     * is strictly equal (===) to the provided value.
     *
     * ```php
     * $some = new Some('hello');
     * $some->contains('hello'); // true
     * $some->contains('world'); // false
     * None::create()->contains('hello'); // false
     * ```
     *
     * @param  mixed $value Value to check for
     * @return bool  True if Option contains the exact value
     */
    public function contains($value): bool
    {
        return $this->isDefined() && $this->get() === $value;
    }

    /**
     * Check if this Option is Some and the value matches a predicate.
     *
     * Returns true only if this Option contains a value and that value
     * satisfies the provided predicate function.
     *
     * ```php
     * $number = new Some(42);
     * $number->isSomeAnd(fn($n) => $n > 40); // true
     * $number->isSomeAnd(fn($n) => $n < 10); // false
     * None::create()->isSomeAnd(fn($n) => true); // false
     * ```
     *
     * @param  callable(T):bool $predicate Function to test the contained value
     * @return bool             True if Some and predicate passes
     */
    public function isSomeAnd(callable $predicate): bool
    {
        return $this->isDefined() && (bool) $predicate($this->get());
    }

    /**
     * Check if this Option is None or the value matches a predicate.
     *
     * Returns true if this Option is None, or if it's Some and the
     * contained value satisfies the predicate. Useful for "allow if absent
     * or if condition met" logic.
     *
     * ```php
     * $age = new Some(25);
     * $age->isNoneOr(fn($a) => $a >= 18); // true (adult)
     * None::create()->isNoneOr(fn($a) => false); // true (None allowed)
     * ```
     *
     * @param  callable(T):bool $predicate Function to test the contained value
     * @return bool             True if None or predicate passes
     */
    public function isNoneOr(callable $predicate): bool
    {
        return $this->isEmpty() || (bool) $predicate($this->get());
    }

    /**
     * Transpose Option<Result<T,E>> into Result<Option<T>,E>.
     *
     * Swaps the order of Option and Result types. If this Option is None,
     * returns Ok(None). If this Option contains a Result, returns that Result
     * with the success value wrapped in Some.
     *
     * ```php
     * $optResult = new Some(new Ok('value')); // Option<Result<string, Error>>
     * $resultOpt = $optResult->transpose(); // Result<Option<string>, Error>
     * ```
     *
     * @throws RuntimeException When Option doesn't contain a Result
     *
     * @return \Cline\Monad\Result\Result Transposed Result containing Option
     */
    public function transpose()
    {
        // None => Ok(None)
        if ($this->isEmpty()) {
            return new Ok(None::create());
        }

        $value = $this->get();

        if ($value instanceof \Cline\Monad\Result\Result) {
            if ($value->isOk()) {
                return new Ok(
                    new Some($value->unwrap()),
                );
            }

            return new Err($value->unwrapErr());
        }

        throw new RuntimeException('Option::transpose expects Some(Result), got '.(is_object($value) ? $value::class : gettype($value)));
    }

    /**
     * Convert Option<T> to Result<T,E>, mapping None to Err(E).
     *
     * Transforms the Option into a Result type. If Some, returns Ok with
     * the contained value. If None, returns Err with the provided error value.
     *
     * ```php
     * $user = findUser($id)->okOr('User not found');
     * // Some(user) -> Ok(user), None -> Err('User not found')
     * ```
     *
     * @template E
     *
     * @param  E                          $err Error value to use if None
     * @return \Cline\Monad\Result\Result Ok with value if Some, Err with error if None
     */
    public function okOr($err)
    {
        if ($this->isDefined()) {
            return new Ok($this->get());
        }

        return new Err($err);
    }

    /**
     * Convert Option<T> to Result<T,E>, lazily computing the error.
     *
     * Like okOr() but the error value is computed lazily only when needed.
     * More efficient when error computation is expensive.
     *
     * ```php
     * $result = $option->okOrElse(fn() => new UserNotFoundError($id));
     * ```
     *
     * @template E
     *
     * @param  callable():E               $err Function to compute error value if None
     * @return \Cline\Monad\Result\Result Ok with value if Some, Err with computed error if None
     */
    public function okOrElse(callable $err)
    {
        if ($this->isDefined()) {
            return new Ok($this->get());
        }

        return new Err($err());
    }

    /**
     * Unwrap the value or abort the request (Laravel integration).
     *
     * Laravel-specific method that uses the abort() helper to terminate
     * the request with an HTTP error if the Option is None.
     *
     * ```php
     * $user = findUser($id)->unwrapOrAbort(404, 'User not found');
     * ```
     *
     * @param int         $status  HTTP status code to abort with (default: 404)
     * @param null|string $message Optional error message
     *
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException When Option is None
     *
     * @return T The contained value
     */
    public function unwrapOrAbort(int $status = 404, ?string $message = null)
    {
        abort_unless($this->isDefined(), $status, $message ?? '');

        return $this->get();
    }

    /**
     * Unwrap the value if present and condition holds, otherwise abort (Laravel).
     *
     * Laravel-specific method that checks both Option presence and an additional
     * condition. Aborts if the Option is None or if the condition fails.
     *
     * ```php
     * $user = findUser($id)->unwrapOrAbortUnless(
     *     fn($u) => $u->isActive(),
     *     403,
     *     'User account is disabled'
     * );
     * ```
     *
     * @param bool|callable(T):bool $condition Condition to check (boolean or callable)
     * @param int                   $status    HTTP status code to abort with
     * @param null|string           $message   Optional error message
     *
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException When Option is None or condition fails
     *
     * @return T The contained value
     */
    public function unwrapOrAbortUnless($condition, int $status = 404, ?string $message = null)
    {
        abort_unless($this->isDefined(), $status, $message ?? '');

        $value = $this->get();
        $ok = is_callable($condition) ? (bool) $condition($value) : (bool) $condition;

        abort_unless($ok, $status, $message ?? '');

        return $value;
    }

    /**
     * Convert the Option back to a nullable value.
     *
     * Extracts the contained value if Some, or returns null if None.
     * Useful when interfacing with code that expects nullable values.
     *
     * ```php
     * $nullableValue = $option->toNullable(); // T or null
     * ```
     *
     * @return null|T The contained value or null
     */
    public function toNullable()
    {
        return $this->unwrapOr(null);
    }

    /**
     * Check if this Option is Some (contains a value).
     *
     * Alias for isDefined() with Rust-like naming convention.
     *
     * @return bool True if this Option contains a value
     */
    public function isSome(): bool
    {
        return $this->isDefined();
    }

    /**
     * Check if this Option is None (empty).
     *
     * Alias for isEmpty() with Rust-like naming convention.
     *
     * @return bool True if this Option is empty
     */
    public function isNone(): bool
    {
        return $this->isEmpty();
    }

    /**
     * Return the contained value or a default value.
     *
     * Safe way to extract values from Options without throwing exceptions.
     * If Some, returns the contained value; if None, returns the default.
     *
     * ```php
     * $username = $user->unwrapOr('guest'); // User name or 'guest'
     * $count = $items->unwrapOr(0); // Item count or 0
     * ```
     *
     * @template S
     *
     * @param  S   $default Default value to return if None
     * @return S|T The contained value or the default
     */
    abstract public function unwrapOr($default);

    /**
     * Return the contained value or compute a default lazily.
     *
     * Like unwrapOr() but the default value is computed only when needed.
     * Preferred when default computation is expensive or has side effects.
     *
     * ```php
     * $user = $cached->unwrapOrElse(fn() => loadFromDatabase($id));
     * $config = $env->unwrapOrElse(fn() => parseConfigFile());
     * ```
     *
     * @template S
     *
     * @param  callable():S $callable Function to compute default value
     * @return S|T          The contained value or computed default
     */
    abstract public function unwrapOrElse($callable);

    /**
     * Return the contained value or throw an exception.
     *
     * Unsafe method that should only be used when you're certain the Option
     * contains a value. Prefer unwrapOr() or unwrapOrElse() for safer access.
     *
     * ```php
     * $value = $option->get(); // Throws if None
     * ```
     *
     * @throws RuntimeException When called on None
     *
     * @return T The contained value
     */
    abstract public function get();

    /**
     * Return the contained value or throw a custom exception.
     *
     * Like get() but allows you to specify the exact exception to throw,
     * providing better error context and handling.
     *
     * ```php
     * $user = $option->unwrapOrThrow(new UserNotFoundException($id));
     * ```
     *
     * @param Exception $ex Exception to throw if None
     *
     * @throws Exception The provided exception when called on None
     *
     * @return T The contained value
     */
    abstract public function unwrapOrThrow(Exception $ex);

    /**
     * Check if this Option contains no value.
     *
     * Returns true for None, false for Some. Use this to test for the
     * absence of a value before attempting to extract it.
     *
     * @return bool True if None, false if Some
     */
    abstract public function isEmpty();

    /**
     * Check if this Option contains a value.
     *
     * Returns true for Some, false for None. Use this to test for the
     * presence of a value before attempting to extract it.
     *
     * @return bool True if Some, false if None
     */
    abstract public function isDefined();

    /**
     * Return this Option if non-empty, otherwise execute callable for alternative.
     *
     * Provides a way to chain multiple Option sources, trying each in turn
     * until one succeeds. The callable is only executed if this Option is None.
     *
     * ```php
     * $result = $cache->orElse(fn() => $database->findUser($id))
     *                 ->orElse(fn() => $defaultUser);
     * ```
     *
     * @param  callable():self<T> $else Function returning alternative Option
     * @return self<T>            This Option if Some, otherwise result of callable
     */
    abstract public function orElse(callable $else);

    /**
     * Execute a callable if the Option contains a value (deprecated).
     *
     * Executes the provided callable with the contained value if Some,
     * does nothing if None. The return value of the callable is discarded.
     *
     * @deprecated Use forAll() instead for side-effect operations
     *
     * ```php
     * $user->ifDefined(fn($u) => logUserAccess($u)); // Deprecated
     * $user->forAll(fn($u) => logUserAccess($u)); // Preferred
     * ```
     *
     * @param callable(T):mixed $callable Function to execute with the value
     */
    abstract public function ifDefined($callable): void;

    /**
     * Execute a callable for side effects if the Option contains a value.
     *
     * Preferred method for operations with side effects (logging, I/O, etc.).
     * The callable is executed only if Some, and this Option is returned unchanged
     * for method chaining. Use map() for pure transformations.
     *
     * ```php
     * $user->forAll(fn($u) => logUserAccess($u))
     *      ->forAll(fn($u) => updateLastSeen($u));
     * ```
     *
     * @param  callable(T):mixed $callable Function to execute for side effects
     * @return self<T>           This Option unchanged for chaining
     */
    abstract public function forAll($callable);

    /**
     * Transform the contained value using a function.
     *
     * Core functional programming operation that applies a transformation
     * to the contained value if present. If None, returns None without
     * executing the function. The result is automatically wrapped in Some.
     *
     * ```php
     * $upper = $text->map('strtoupper'); // Some('HELLO') or None
     * $length = $name->map('strlen'); // Some(5) or None
     * ```
     *
     * @template S
     *
     * @param  callable(T):S $callable Function to transform the value
     * @return self<S>       Option containing transformed value or None
     */
    abstract public function map($callable);

    /**
     * Apply a function that returns an Option and flatten the result.
     *
     * Monadic bind operation that prevents nested Options. The function must
     * return an Option, which becomes the final result without additional wrapping.
     * Essential for chaining Option-returning operations.
     *
     * ```php
     * $user = $userId->flatMap(fn($id) => findUser($id))
     *               ->flatMap(fn($u) => getActiveProfile($u));
     * ```
     *
     * @template S
     *
     * @param  callable(T):self<S> $callable Function returning an Option
     * @return self<S>             Flattened Option result
     */
    abstract public function flatMap($callable);

    /**
     * Filter the Option based on a predicate function.
     *
     * If Some and the predicate returns true, returns this Option unchanged.
     * If Some but predicate returns false, or if None, returns None.
     * Useful for conditional processing based on value properties.
     *
     * ```php
     * $adult = $age->filter(fn($a) => $a >= 18); // Some(age) or None
     * $validEmail = $email->filter('filter_var', FILTER_VALIDATE_EMAIL);
     * ```
     *
     * @param  callable(T):bool $callable Predicate function to test the value
     * @return self<T>          This Option if predicate passes, None otherwise
     */
    abstract public function filter($callable);

    /**
     * Filter the Option based on the inverse of a predicate function.
     *
     * If Some and the predicate returns false, returns this Option unchanged.
     * If Some but predicate returns true, or if None, returns None.
     * Useful for excluding values that match certain criteria.
     *
     * ```php
     * $nonEmpty = $text->filterNot(fn($s) => empty($s)); // Exclude empty strings
     * $validUser = $user->filterNot(fn($u) => $u->isBanned()); // Exclude banned users
     * ```
     *
     * @param  callable(T):bool $callable Predicate function to test the value
     * @return self<T>          This Option if predicate fails, None otherwise
     */
    abstract public function filterNot($callable);

    /**
     * Return this Option only if it contains the specified value.
     *
     * If Some and the contained value strictly equals the target value,
     * returns this Option. Otherwise returns None. Useful for selecting
     * specific values from a chain of operations.
     *
     * ```php
     * $admin = $role->select('admin'); // Some('admin') or None
     * $zero = $number->select(0); // Some(0) or None
     * ```
     *
     * @param  T       $value Value to select for
     * @return self<T> This Option if contains value, None otherwise
     */
    abstract public function select($value);

    /**
     * Return None if this Option contains the specified value, otherwise this Option.
     *
     * If Some and the contained value strictly equals the target value,
     * returns None. Otherwise returns this Option unchanged. Useful for
     * excluding specific values from processing.
     *
     * ```php
     * $nonGuest = $user->reject('guest'); // None if 'guest', otherwise Some(user)
     * $nonZero = $number->reject(0); // None if 0, otherwise Some(number)
     * ```
     *
     * @param  T       $value Value to reject
     * @return self<T> None if contains value, this Option otherwise
     */
    abstract public function reject($value);

    /**
     * Apply a binary function to accumulate a result.
     *
     * If None, returns the initial value unchanged. If Some, applies the
     * binary function with the initial value and contained value to produce
     * a result. Useful for reduction operations and aggregations.
     *
     * ```php
     * $sum = $number->foldLeft(0, fn($acc, $n) => $acc + $n); // 0 + number or 0
     * $text = $word->foldLeft('', fn($acc, $w) => $acc . $w); // '' . word or ''
     * ```
     *
     * @template S
     *
     * @param  S                $initialValue Starting accumulator value
     * @param  callable(S, T):S $callable     Binary function (accumulator, value) -> result
     * @return S                Final accumulated result
     */
    abstract public function foldLeft($initialValue, $callable);

    /**
     * Apply a binary function with reversed argument order.
     *
     * Like foldLeft() but the binary function receives the contained value
     * first, then the accumulator. Useful when the operation is not commutative
     * and you need the value as the first argument.
     *
     * ```php
     * $result = $text->foldRight('', fn($str, $acc) => $str . $acc);
     * ```
     *
     * @template S
     *
     * @param  S                $initialValue Starting accumulator value
     * @param  callable(T, S):S $callable     Binary function (value, accumulator) -> result
     * @return S                Final accumulated result
     */
    abstract public function foldRight($initialValue, $callable);
}
