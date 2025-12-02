<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Monad\Either;

use Cline\Monad\Option\Option;
use Cline\Monad\Result\Result;
use Cline\Monad\Option\None;
use Cline\Monad\Option\Some;
use Cline\Monad\Result\Err;
use Cline\Monad\Result\Ok;
use Cline\Monad\Exceptions\CannotUnwrapRightFromLeftException;
use Cline\Monad\Exceptions\UnzipExpectedRightWithTupleException;
use IteratorAggregate;
use RuntimeException;
use Throwable;

use function array_key_exists;
use function is_array;




/**
 * Abstract base class for the Either type pattern in PHP.
 *
 * Either represents a value that can be one of two possible types, conventionally
 * called Left and Right. Unlike Result which has semantic meaning (error/success),
 * Either is a general-purpose sum type for representing values that can be one
 * thing or another.
 *
 * The Either type has two concrete implementations:
 * - Left<L>: Contains a value of type L (by convention, represents an error or alternative path)
 * - Right<R>: Contains a value of type R (by convention, represents the success or primary path)
 *
 * Common usage patterns:
 *
 * ```php
 * // Branch logic
 * function parseConfig(string $source): Either {
 *     return isUrl($source)
 *         ? new Right(['type' => 'url', 'value' => $source])
 *         : new Left(['type' => 'file', 'value' => $source]);
 * }
 *
 * // Error handling (similar to Result)
 * function divide($a, $b): Either {
 *     if ($b === 0) {
 *         return new Left('Division by zero');
 *     }
 *     return new Right($a / $b);
 * }
 *
 * // Handling Either
 * $result = divide(10, 2)
 *     ->map(fn($n) => $n * 2)  // Transform Right value
 *     ->mapLeft(fn($e) => "Error: $e")  // Transform Left value
 *     ->unwrapOr(0);  // Get Right value or default
 * ```
 *
 * @template L The type of Left value that may be contained
 * @template R The type of Right value that may be contained
 *
 * @implements IteratorAggregate<R>
 *
 * @author Brian Faust <brian@cline.sh>
 */
abstract class Either implements IteratorAggregate
{
    /**
     * Create an Either from a nullable value.
     *
     * Wraps a value in Right if non-null, Left if null.
     * The Left value defaults to null but can be customized.
     *
     * ```php
     * $user = Either::fromNullable(getCurrentUser()); // Right<User> or Left<null>
     * $config = Either::fromNullable($value, 'missing'); // Right<value> or Left<'missing'>
     * ```
     *
     * @template T
     * @template E
     *
     * @param  null|T     $value     The value to wrap
     * @param  E          $leftValue Value for Left if input is null (default: null)
     * @return self<E, T> Right if value is non-null, Left otherwise
     *
     * @phpstan-return self<E, T>
     */
    public static function fromNullable($value, $leftValue = null): self
    {
        /** @phpstan-ignore return.type */
        return $value !== null ? new Right($value) : new Left($leftValue);
    }

    /**
     * Create Either from a callable that may throw an exception.
     *
     * Executes the callable and wraps the result in Right. If an exception
     * is thrown, catches it and wraps the exception in Left.
     *
     * ```php
     * $result = Either::tryCatch(fn() => json_decode($json, true, 512, JSON_THROW_ON_ERROR));
     * // Right(['data']) or Left(JsonException)
     * ```
     *
     * @template T
     *
     * @param  callable():T       $callable Function that may throw
     * @return self<Throwable, T> Right with result or Left with exception
     *
     * @phpstan-return self<Throwable, T>
     */
    public static function tryCatch(callable $callable): self
    {
        try {
            /** @phpstan-ignore return.type */
            return new Right($callable());
        } catch (Throwable $throwable) {
            /** @phpstan-ignore return.type */
            return new Left($throwable);
        }
    }

    /**
     * Create Either from a condition.
     *
     * Evaluates a condition and creates Right/Left based on the result.
     * Useful for conditional logic that returns Either values.
     *
     * ```php
     * $result = Either::cond(
     *     $age >= 18,
     *     $user,
     *     'User must be 18 or older'
     * ); // Right<User> or Left<string>
     * ```
     *
     * @template T
     * @template E
     *
     * @param  bool       $condition  Condition to evaluate
     * @param  T          $rightValue Value for Right if true
     * @param  E          $leftValue  Value for Left if false
     * @return self<E, T> Right if condition true, Left if false
     *
     * @phpstan-return self<E, T>
     */
    public static function cond(bool $condition, $rightValue, $leftValue): self
    {
        /** @phpstan-ignore return.type */
        return $condition ? new Right($rightValue) : new Left($leftValue);
    }

    /**
     * Transform an array of Eithers into an Either of an array.
     *
     * If all Eithers are Right, returns Right containing an array of all Right values.
     * If any Either is Left, returns the first Left encountered.
     * Useful for batch validation or parallel operations.
     *
     * ```php
     * $validations = [
     *     validateEmail($email),    // Right('valid@email.com')
     *     validatePhone($phone),    // Right('+1234567890')
     *     validateAge($age),        // Left('too young')
     * ];
     *
     * $result = Either::sequence($validations);
     * // Left('too young') - fails on first Left
     * ```
     *
     * @template U
     * @template V
     *
     * @param  array<int, self<U, V>> $eithers Array of Eithers to sequence
     * @return self<U, array<int, V>> Right with array of values or first Left
     *
     * @phpstan-return self<U, array<int, V>>
     */
    public static function sequence(array $eithers): self
    {
        $results = [];

        foreach ($eithers as $either) {
            if ($either->isLeft()) {
                /** @phpstan-ignore return.type */
                return $either;
            }

            $results[] = $either->unwrap();
        }

        /** @phpstan-ignore return.type */
        return new Right($results);
    }

    /**
     * Map over an array and sequence the results.
     *
     * Applies a function to each element that returns an Either, then sequences
     * the results. If all succeed, returns Right with array of values. If any fail,
     * returns the first Left. Combines map and sequence in one operation.
     *
     * ```php
     * $userIds = [1, 2, 3];
     * $users = Either::traverse($userIds, fn($id) => findUser($id));
     * // Right([User, User, User]) or Left('User not found')
     * ```
     *
     * @template T
     * @template U
     * @template V
     *
     * @param  array<int, T>          $items Array of items to transform
     * @param  callable(T):self<U, V> $f     Function that returns an Either for each item
     * @return self<U, array<int, V>> Right with array of values or first Left
     *
     * @phpstan-return self<U, array<int, V>>
     */
    public static function traverse(array $items, callable $f): self
    {
        $results = [];

        foreach ($items as $item) {
            $either = $f($item);

            if ($either->isLeft()) {
                /** @phpstan-ignore return.type */
                return $either;
            }

            $results[] = $either->unwrap();
        }

        /** @phpstan-ignore return.type */
        return new Right($results);
    }

    /**
     * Create a LazyEither from a callback.
     *
     * Defers execution of potentially expensive operations until the Either
     * value is actually needed. The callback is executed only once and the
     * result is cached for subsequent accesses.
     *
     * ```php
     * $expensive = Either::lazy(fn() => expensiveApiCall());
     *
     * // Only executes when needed
     * $result = $expensive->map('processData')->unwrapOr('default');
     * ```
     *
     * @template U
     * @template V
     *
     * @param  callable():self<U, V> $callback  Function to execute for the Either
     * @param  array<int, mixed>     $arguments Arguments to pass to the callback
     * @return LazyEither<U, V>      Lazy Either that will execute callback when needed
     */
    public static function lazy(callable $callback, array $arguments = [])
    {
        return new LazyEither($callback, $arguments);
    }

    /**
     * Alias for flatMap with Rust-like naming.
     *
     * @template T
     *
     * @param  callable(R):self<L, T> $callable Function returning Either
     * @return self<L, T>             Flattened Either result
     */
    public function andThen(callable $callable)
    {
        return $this->flatMap($callable);
    }

    /**
     * Check if this Either is Right and the value matches a predicate.
     *
     * @param  callable(R):bool $predicate Function to test the Right value
     * @return bool             True if Right and predicate passes
     */
    public function isRightAnd(callable $predicate): bool
    {
        return $this->isRight() && (bool) $predicate($this->unwrap());
    }

    /**
     * Check if this Either is Left and the value matches a predicate.
     *
     * @param  callable(L):bool $predicate Function to test the Left value
     * @return bool             True if Left and predicate passes
     */
    public function isLeftAnd(callable $predicate): bool
    {
        return $this->isLeft() && (bool) $predicate($this->unwrapLeft());
    }

    /**
     * Flatten nested Either into a single Either.
     *
     * @return self<L, mixed> Flattened Either with one less level of nesting
     */
    public function flatten()
    {
        if ($this->isRight()) {
            $v = $this->unwrap();

            return $v instanceof self ? $v : $this;
        }

        return $this;
    }

    /**
     * Check if this Either contains the specified Right value.
     *
     * @param  R    $value Value to check for
     * @return bool True if Right contains the exact value
     */
    public function contains($value): bool
    {
        return $this->isRight() && $this->unwrap() === $value;
    }

    /**
     * Check if this Either contains the specified Left value.
     *
     * @param  L    $value Value to check for
     * @return bool True if Left contains the exact value
     */
    public function containsLeft($value): bool
    {
        return $this->isLeft() && $this->unwrapLeft() === $value;
    }

    /**
     * Rust-style and: return other Either if this Either is Right, otherwise Left.
     *
     * Useful for conditional chaining where you want to proceed with another
     * Either only if the current one is Right.
     *
     * ```php
     * $validation = $emailCheck->and($passwordCheck); // Only if both succeed
     * $result = $either->and(performNextOperation()); // Short-circuit on Left
     * ```
     *
     * @template T
     *
     * @param  self<L, T> $other Either to return if this Either is Right
     * @return self<L, T> The other Either if this is Right, this Left otherwise
     */
    public function and(self $other)
    {
        return $this->isRight() ? $other : $this;
    }

    /**
     * Rust-style or: return this Either if Right, otherwise return other Either.
     *
     * Provides a fallback Either when this Either is Left. The first
     * Right Either wins.
     *
     * ```php
     * $result = $primary->or($fallback); // Use fallback if primary is Left
     * $config = $cache->or($database->or($default)); // Multiple fallbacks
     * ```
     *
     * @template T
     *
     * @param  self<T, R> $other Fallback Either to use if this Either is Left
     * @return self<T, R> This Either if Right, otherwise the other Either
     */
    public function or(self $other)
    {
        return $this->isRight() ? $this : $other;
    }

    /**
     * Rust-style xor: return Right only if exactly one Either is Right.
     *
     * Returns Right if exactly one of the two Eithers is Right,
     * Left if both are Right or both are Left. Useful for exclusive choices.
     *
     * ```php
     * $exclusive = $optionA->xor($optionB); // Right only if exactly one succeeds
     * ```
     *
     * @template T
     *
     * @param  self<L, T>   $other Other Either for exclusive comparison
     * @return self<L, R|T> Right if exactly one Either is Right, Left otherwise
     *
     * @phpstan-return self<L, R|T>
     */
    public function xor(self $other): self
    {
        $a = $this->isRight();
        $b = $other->isRight();

        if ($a && !$b) {
            return $this;
        }

        if (!$a && $b) {
            /** @phpstan-ignore return.type */
            return $other;
        }

        // Both Right or both Left - return Left
        /** @phpstan-ignore return.type */
        return $this->isLeft() ? $this : new Left($this->unwrap());
    }

    /**
     * Rust-style map_or: transform Right value with function or return default.
     *
     * Combines mapping and default value provision in a single operation.
     * If Right, applies the function to the value; if Left, returns the default.
     *
     * ```php
     * $length = $text->mapOr(0, 'strlen'); // String length or 0
     * $display = $user->mapOr('Guest', fn($u) => $u->name); // User name or 'Guest'
     * ```
     *
     * @template U
     *
     * @param  U             $default Default value to return if Left
     * @param  callable(R):U $f       Function to apply to the Right value if Right
     * @return U             Transformed value or default
     */
    public function mapOr($default, callable $f)
    {
        return $this->isRight() ? $f($this->unwrap()) : $default;
    }

    /**
     * Rust-style map_or_else: transform Right value or compute default lazily.
     *
     * Like mapOr but the default value is computed lazily only when needed.
     * This is more efficient when the default computation is expensive.
     *
     * ```php
     * $result = $either->mapOrElse(
     *     fn($err) => handleError($err),
     *     fn($value) => processValue($value)
     * );
     * ```
     *
     * @template U
     *
     * @param  callable(L):U $default Function to compute default value from Left if Left
     * @param  callable(R):U $f       Function to apply to the Right value if Right
     * @return U             Transformed value or computed default
     */
    public function mapOrElse(callable $default, callable $f)
    {
        return $this->isRight() ? $f($this->unwrap()) : $default($this->unwrapLeft());
    }

    /**
     * Rust-style zip: combine two Eithers into an Either of a tuple.
     *
     * If both Eithers are Right, returns Right with a tuple of both values.
     * If either Either is Left, returns the first Left encountered. Useful for
     * combining related values.
     *
     * ```php
     * $combined = $username->zip($password); // Right(['user', 'pass']) or Left
     * $coords = $latitude->zip($longitude); // Right([lat, lng]) or Left
     * ```
     *
     * @template T
     *
     * @param  self<L, T>               $other Second Either to combine with
     * @return self<L, array{0:R, 1:T}> Either containing tuple of both values, or first Left
     *
     * @phpstan-return self<L, array{0:R, 1:T}>
     */
    public function zip(self $other): self
    {
        if ($this->isRight() && $other->isRight()) {
            /** @phpstan-ignore return.type */
            return new Right([$this->unwrap(), $other->unwrap()]);
        }

        /** @phpstan-ignore return.type */
        return $this->isLeft() ? $this : $other;
    }

    /**
     * Rust-style zip_with: combine two Eithers using a function.
     *
     * If both Eithers are Right, applies the function to both values
     * and returns Right with the result. If either is Left, returns the first Left.
     *
     * ```php
     * $sum = $num1->zipWith($num2, fn($a, $b) => $a + $b); // Right(sum) or Left
     * $fullName = $first->zipWith($last, fn($f, $l) => "$f $l"); // Right(name) or Left
     * ```
     *
     * @template T
     * @template U
     *
     * @param  self<L, T>       $other Other Either to combine with
     * @param  callable(R, T):U $f     Function to combine the values
     * @return self<L, U>       Either containing the combined result, or first Left
     *
     * @phpstan-return self<L, U>
     */
    public function zipWith(self $other, callable $f)
    {
        if ($this->isRight() && $other->isRight()) {
            /** @phpstan-ignore return.type */
            return new Right($f($this->unwrap(), $other->unwrap()));
        }

        /** @phpstan-ignore return.type */
        return $this->isLeft() ? $this : $other;
    }

    /**
     * Rust-style unzip: split Either of tuple into tuple of Eithers.
     *
     * Converts Either<L, [A,B]> into [Either<L,A>, Either<L,B>]. If this Either
     * is Right and contains a two-element array, returns Right for each element.
     * If this Either is Left, returns Left for both.
     *
     * ```php
     * [$eitherA, $eitherB] = $tupleEither->unzip(); // Split into individual Eithers
     * ```
     *
     * @throws RuntimeException When Right value is not a proper two-element array
     *
     * @return array{0:self<L, mixed>, 1:self<L, mixed>} Tuple of Eithers
     *
     * @phpstan-return array{0:self<L, mixed>, 1:self<L, mixed>}
     */
    public function unzip(): array
    {
        if ($this->isLeft()) {
            $left = $this->unwrapLeft();

            return [new Left($left), new Left($left)];
        }

        $v = $this->unwrap();

        if (is_array($v) && array_key_exists(0, $v) && array_key_exists(1, $v)) {
            /** @phpstan-ignore return.type */
            return [new Right($v[0]), new Right($v[1])];
        }

        throw UnzipExpectedRightWithTupleException::create();
    }

    /**
     * Rust-style expect: unwrap with a custom error message.
     *
     * Like unwrap() but allows you to provide a descriptive error message
     * for better debugging when the expectation fails.
     *
     * ```php
     * $value = $either->expect('Expected valid configuration');
     * ```
     *
     * @param string $message Custom error message for the exception
     *
     * @throws RuntimeException With the custom message when called on Left
     *
     * @return R The Right value
     */
    public function expect(string $message)
    {
        if ($this->isRight()) {
            return $this->unwrap();
        }

        throw CannotUnwrapRightFromLeftException::create($message);
    }

    /**
     * Convert this Either into an Option containing the Right value.
     *
     * If Right, returns Some containing the Right value. If Left, returns None.
     * Useful when you want to discard error information and work with Options.
     *
     * ```php
     * $maybeValue = $either->toOption(); // Some(value) or None
     * $processed = $either->toOption()->map('processValue')->unwrapOr('default');
     * ```
     *
     * @return Option<R> Some containing Right value, or None if Left
     */
    public function toOption()
    {
        return $this->isRight()
            ? new Some($this->unwrap())
            : None::create();
    }

    /**
     * Convert this Either into a Result.
     *
     * If Right, returns Ok with the Right value. If Left, returns Err with the Left value.
     * Natural conversion since Either semantics align with Result (Left=error, Right=success).
     *
     * ```php
     * $result = $either->toResult(); // Ok(value) or Err(error)
     * ```
     *
     * @return Result<R, L> Ok with Right value, or Err with Left value
     */
    public function toResult()
    {
        return $this->isRight()
            ? new Ok($this->unwrap())
            : new Err($this->unwrapLeft());
    }

    /**
     * Check if this Either is Left.
     *
     * @return bool True if Left, false if Right
     */
    abstract public function isLeft(): bool;

    /**
     * Check if this Either is Right.
     *
     * @return bool True if Right, false if Left
     */
    abstract public function isRight(): bool;

    /**
     * Get the Right value or throw an exception.
     *
     * @throws RuntimeException When called on Left
     *
     * @return R The Right value
     */
    abstract public function unwrap();

    /**
     * Get the Left value or throw an exception.
     *
     * @throws RuntimeException When called on Right
     *
     * @return L The Left value
     */
    abstract public function unwrapLeft();

    /**
     * Get the Right value or return a default.
     *
     * @template T
     *
     * @param  T   $default Default value if Left
     * @return R|T The Right value or default
     */
    abstract public function unwrapOr($default);

    /**
     * Get the Right value or compute a default.
     *
     * @template T
     *
     * @param  callable(L):T $callable Function to compute default from Left value
     * @return R|T           The Right value or computed default
     */
    abstract public function unwrapOrElse(callable $callable);

    /**
     * Transform the Right value.
     *
     * @template T
     *
     * @param  callable(R):T $callable Function to transform Right value
     * @return self<L, T>    Either with transformed Right or unchanged Left
     */
    abstract public function map(callable $callable);

    /**
     * Transform the Left value.
     *
     * @template T
     *
     * @param  callable(L):T $callable Function to transform Left value
     * @return self<T, R>    Either with transformed Left or unchanged Right
     */
    abstract public function mapLeft(callable $callable);

    /**
     * Transform both Left and Right values.
     *
     * @template T
     * @template U
     *
     * @param  callable(L):T $leftFn  Function to transform Left value
     * @param  callable(R):U $rightFn Function to transform Right value
     * @return self<T, U>    Either with transformed value
     */
    abstract public function bimap(callable $leftFn, callable $rightFn);

    /**
     * Chain Either-returning operations.
     *
     * @template T
     *
     * @param  callable(R):self<L, T> $callable Function returning Either
     * @return self<L, T>             Flattened Either result
     */
    abstract public function flatMap(callable $callable);

    /**
     * Execute a function for side effects on the Right value.
     *
     * @param  callable(R):mixed $callable Function to execute
     * @return self<L, R>        This Either unchanged
     */
    abstract public function forAll(callable $callable);

    /**
     * Execute a function for side effects on the Left value.
     *
     * @param  callable(L):mixed $callable Function to execute
     * @return self<L, R>        This Either unchanged
     */
    abstract public function forLeft(callable $callable);

    /**
     * Execute a function for debugging/inspection.
     *
     * @param  callable(R):mixed $callable Function to execute on Right
     * @return self<L, R>        This Either unchanged
     */
    abstract public function inspect(callable $callable);

    /**
     * Filter the Right value based on a predicate.
     *
     * @param  callable(R):bool $predicate Function to test Right value
     * @param  L                $leftValue Value for Left if predicate fails
     * @return self<L, R>       This Either if Right and predicate passes, Left otherwise
     */
    abstract public function filter(callable $predicate, $leftValue);

    /**
     * Pattern matching for exhaustive Either handling.
     *
     * @template T
     *
     * @param  callable(L):T $onLeft  Function to call if Left
     * @param  callable(R):T $onRight Function to call if Right
     * @return T             Result of the executed callback
     */
    abstract public function match(callable $onLeft, callable $onRight);

    /**
     * Swap Left and Right values.
     *
     * @return self<R, L> Either with swapped values
     */
    abstract public function swap();

    /**
     * Clone this Either and its contained value if it's an object.
     *
     * @return self<L, R> New Either with cloned value
     */
    abstract public function cloned();

    /**
     * Fold the Either into a single value.
     *
     * @template T
     *
     * @param  callable(L):T $leftFn  Function to apply to Left value
     * @param  callable(R):T $rightFn Function to apply to Right value
     * @return T             Result of applying the appropriate function
     */
    abstract public function fold(callable $leftFn, callable $rightFn);
}
