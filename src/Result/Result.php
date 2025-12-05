<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Monad\Result;

use Cline\Monad\Exceptions\CannotUnwrapErrException;
use Cline\Monad\Exceptions\CannotUnwrapOkException;
use Cline\Monad\Exceptions\TransposeExpectedOkWithOptionException;
use Cline\Monad\Option\None;
use Cline\Monad\Option\Option;
use Cline\Monad\Option\Some;
use RuntimeException;

/**
 * Abstract base class for the Result type pattern in PHP.
 *
 * Result represents the outcome of an operation that may succeed or fail,
 * providing a type-safe alternative to exception-based error handling.
 * It encodes success/failure directly in the type system, making error
 * handling explicit and composable.
 *
 * The Result type has two concrete implementations:
 * - Ok<T>: Contains a success value of type T
 * - Err<E>: Contains an error value of type E
 *
 * Common usage patterns:
 *
 * ```php
 * // Creating Results
 * function divide($a, $b): Result {
 *     if ($b === 0) {
 *         return new Err('Division by zero');
 *     }
 *     return new Ok($a / $b);
 * }
 *
 * // Handling Results
 * $result = divide(10, 2)
 *     ->map(fn($n) => $n * 2)  // Transform success value
 *     ->mapErr(fn($e) => "Error: $e")  // Transform error
 *     ->unwrapOr(0);  // Get value or default
 *
 * // Chaining operations
 * $final = parseNumber($input)
 *     ->andThen(fn($n) => validateRange($n))
 *     ->andThen(fn($n) => processNumber($n))
 *     ->unwrapOrElse(fn($err) => handleError($err));
 * ```
 *
 * @author Brian Faust <brian@cline.sh>
 * @template T The type of success value that may be contained
 * @template E The type of error value that may be contained
 */
abstract class Result
{
    /**
     * Convert this Result into an Option containing the success value.
     *
     * If Ok, returns Some containing the success value. If Err, returns None.
     * Useful when you want to discard error information and work with Options.
     *
     * ```php
     * $maybeValue = $result->ok(); // Some(value) or None
     * $processed = $result->ok()->map('processValue')->unwrapOr('default');
     * ```
     *
     * @return Option<T> Some containing success value, or None if error
     */
    public function ok(): Option
    {
        return $this->isOk() ? new Some($this->unwrap()) : None::create();
    }

    /**
     * Convert this Result into an Option containing the error value.
     *
     * If Err, returns Some containing the error value. If Ok, returns None.
     * Useful when you want to extract error information for processing.
     *
     * ```php
     * $maybeError = $result->err(); // Some(error) or None
     * $logMessage = $result->err()->map('formatError')->unwrapOr('No error');
     * ```
     *
     * @return Option<E> Some containing error value, or None if success
     */
    public function err(): Option
    {
        return $this->isErr() ? new Some($this->unwrapErr()) : None::create();
    }

    /**
     * Alias for ok() with Rust-like naming convention.
     *
     * @return Option<T> Some containing success value, or None if error
     */
    public function intoOk(): Option
    {
        return $this->ok();
    }

    /**
     * Alias for err() with Rust-like naming convention.
     *
     * @return Option<E> Some containing error value, or None if success
     */
    public function intoErr(): Option
    {
        return $this->err();
    }

    /**
     * Transform the success value using a function, leaving errors unchanged.
     *
     * If Ok, applies the function to the success value and returns Ok with
     * the transformed result. If Err, returns the Err unchanged.
     *
     * ```php
     * $doubled = $number->map(fn($n) => $n * 2); // Ok(doubled) or Err(unchanged)
     * $upper = $text->map('strtoupper'); // Ok(UPPER) or Err(unchanged)
     * ```
     *
     * @template U
     *
     * @param  callable(T):U $f Function to transform the success value
     * @return self<U, E>    Result with transformed success or unchanged error
     */
    public function map(callable $f): self
    {
        if ($this->isOk()) {
            return new Ok($f($this->unwrap()));
        }

        return $this; // Err
    }

    /**
     * Transform the error value using a function, leaving success unchanged.
     *
     * If Err, applies the function to the error value and returns Err with
     * the transformed result. If Ok, returns the Ok unchanged.
     *
     * ```php
     * $formatted = $result->mapErr(fn($e) => "Error: $e"); // Ok(unchanged) or Err(formatted)
     * $logged = $result->mapErr(fn($e) => logAndReturn($e)); // Ok(unchanged) or Err(logged)
     * ```
     *
     * @template F
     *
     * @param  callable(E):F $f Function to transform the error value
     * @return self<T, F>    Result with unchanged success or transformed error
     */
    public function mapErr(callable $f): self
    {
        if ($this->isErr()) {
            return new Err($f($this->unwrapErr()));
        }

        return $this; // Ok
    }

    /**
     * Transform success value with function or return default for error.
     *
     * If Ok, applies the function to the success value and returns the result.
     * If Err, returns the provided default value without executing the function.
     *
     * ```php
     * $length = $text->mapOr(0, 'strlen'); // strlen(text) or 0
     * $display = $user->mapOr('Guest', fn($u) => $u->name); // User name or 'Guest'
     * ```
     *
     * @template U
     *
     * @param  U             $default Default value to return if Err
     * @param  callable(T):U $f       Function to apply to success value
     * @return U             Transformed success value or default
     */
    public function mapOr($default, callable $f)
    {
        return $this->isOk() ? $f($this->unwrap()) : $default;
    }

    /**
     * Transform success value or compute default from error lazily.
     *
     * If Ok, applies the success function to the value. If Err, applies
     * the error function to the error value. Both functions must return
     * the same type.
     *
     * ```php
     * $message = $result->mapOrElse(
     *     fn($error) => "Failed: $error",
     *     fn($value) => "Success: $value"
     * );
     * ```
     *
     * @template U
     *
     * @param  callable(E):U $defaultFromErr Function to transform error value
     * @param  callable(T):U $f              Function to transform success value
     * @return U             Result of either transformation
     */
    public function mapOrElse(callable $defaultFromErr, callable $f)
    {
        return $this->isOk() ? $f($this->unwrap()) : $defaultFromErr($this->unwrapErr());
    }

    /**
     * Check if this Result is Ok and the value matches a predicate.
     *
     * Returns true only if this Result is Ok and the success value
     * satisfies the provided predicate function.
     *
     * ```php
     * $isPositive = $number->isOkAnd(fn($n) => $n > 0); // true if Ok(positive)
     * $isValidEmail = $email->isOkAnd('filter_var', FILTER_VALIDATE_EMAIL);
     * ```
     *
     * @param  callable(T):bool $predicate Function to test the success value
     * @return bool             True if Ok and predicate passes
     */
    public function isOkAnd(callable $predicate): bool
    {
        return $this->isOk() && (bool) $predicate($this->unwrap());
    }

    /**
     * Check if this Result is Err and the error matches a predicate.
     *
     * Returns true only if this Result is Err and the error value
     * satisfies the provided predicate function.
     *
     * ```php
     * $isTimeout = $result->isErrAnd(fn($e) => $e instanceof TimeoutException);
     * $isNotFound = $result->isErrAnd(fn($e) => str_contains($e, 'not found'));
     * ```
     *
     * @param  callable(E):bool $predicate Function to test the error value
     * @return bool             True if Err and predicate passes
     */
    public function isErrAnd(callable $predicate): bool
    {
        return $this->isErr() && (bool) $predicate($this->unwrapErr());
    }

    /**
     * Flatten nested Results into a single Result.
     *
     * If this Result is Ok and contains another Result, returns the inner Result.
     * If this Result is Err or the Ok value is not a Result, returns unchanged.
     * Useful for eliminating multiple levels of Result nesting.
     *
     * ```php
     * $nested = new Ok(new Ok('value')); // Result<Result<string, E>, E>
     * $flat = $nested->flatten(); // Result<string, E>
     * ```
     *
     * @return self<mixed, E> Flattened Result with one less level of nesting
     */
    public function flatten(): self
    {
        if ($this->isOk()) {
            $v = $this->unwrap();

            if ($v instanceof self) {
                return $v;
            }
        }

        return $this;
    }

    /**
     * Chain another Result-returning operation, short-circuiting on error.
     *
     * Monadic bind operation for Results. If Ok, applies the function to the
     * success value (which must return a Result). If Err, returns the Err
     * without executing the function. Essential for chaining fallible operations.
     *
     * ```php
     * $final = parseInput($data)
     *     ->andThen(fn($parsed) => validateData($parsed))
     *     ->andThen(fn($valid) => processData($valid));
     * ```
     *
     * @template U
     *
     * @param  callable(T):self<U, E> $f Function that returns a Result
     * @return self<U, E>             Result of the chained operation
     */
    public function andThen(callable $f): self
    {
        if ($this->isOk()) {
            return $f($this->unwrap());
        }

        return $this; // Err
    }

    /**
     * Return other Result if this Result is Ok, otherwise return this Err.
     *
     * Useful for conditional chaining where you want to proceed with another
     * Result only if the current one is successful.
     *
     * ```php
     * $credentials = $username->and($password); // Only if both Ok
     * $result = $validation->and(performOperation()); // Short-circuit on error
     * ```
     *
     * @param  self<mixed, E> $other Result to return if this is Ok
     * @return self<mixed, E> The other Result if Ok, this Result if Err
     */
    public function and(self $other): self
    {
        return $this->isOk() ? $other : $this;
    }

    /**
     * Return this Result if Ok, otherwise return the other Result.
     *
     * Provides a fallback Result when this Result is Err. The first
     * successful Result wins.
     *
     * ```php
     * $result = $primary->or($fallback); // Try primary, then fallback
     * $final = $cache->or($database->or($hardcoded)); // Multiple fallbacks
     * ```
     *
     * @param  self<T, mixed> $other Fallback Result to use if this is Err
     * @return self<T, mixed> This Result if Ok, otherwise the other Result
     */
    public function or(self $other): self
    {
        return $this->isOk() ? $this : $other;
    }

    /**
     * Handle errors by calling a function that returns a Result.
     *
     * If Err, applies the function to the error value to potentially recover.
     * If Ok, returns this Result unchanged. Useful for error recovery and
     * providing alternative computation paths.
     *
     * ```php
     * $recovered = $result->orElse(fn($err) => tryAlternativeMethod($err));
     * $withDefault = $result->orElse(fn($err) => new Ok(getDefaultValue()));
     * ```
     *
     * @param  callable(E):self<T, mixed> $f Function to handle error and return Result
     * @return self<T, mixed>             This Result if Ok, or result of error handler
     */
    public function orElse(callable $f): self
    {
        if ($this->isErr()) {
            return $f($this->unwrapErr());
        }

        return $this;
    }

    /**
     * Extract the success value or return a default value.
     *
     * Safe way to extract values from Results without throwing exceptions.
     * If Ok, returns the success value; if Err, returns the default.
     *
     * ```php
     * $value = $result->unwrapOr(42); // Success value or 42
     * $name = $user->unwrapOr('Anonymous'); // User name or 'Anonymous'
     * ```
     *
     * @template U
     *
     * @param  U   $default Default value to return if Err
     * @return T|U The success value or the default
     */
    public function unwrapOr($default)
    {
        return $this->isOk() ? $this->unwrap() : $default;
    }

    /**
     * Extract the success value or compute a value from the error.
     *
     * Like unwrapOr() but the default value is computed from the error when needed.
     * More flexible when you need to handle different error types differently.
     *
     * ```php
     * $value = $result->unwrapOrElse(fn($err) => handleError($err));
     * $fallback = $result->unwrapOrElse(fn($err) => getDefaultFor($err));
     * ```
     *
     * @param  callable(E):mixed $f Function to compute fallback from error
     * @return mixed|T           The success value or computed fallback
     */
    public function unwrapOrElse(callable $f)
    {
        return $this->isOk() ? $this->unwrap() : $f($this->unwrapErr());
    }

    /**
     * Extract the success value or throw an exception with custom message.
     *
     * Like unwrap() but allows you to provide a descriptive error message
     * for better debugging when the expectation fails.
     *
     * ```php
     * $config = parseConfig($file)->expect('Configuration file is required');
     * $user = findUser($id)->expect('User must exist for this operation');
     * ```
     *
     * @param string $message Custom error message for the exception
     *
     * @throws RuntimeException With the custom message when called on Err
     *
     * @return T The success value
     */
    public function expect(string $message)
    {
        if ($this->isOk()) {
            return $this->unwrap();
        }

        throw CannotUnwrapErrException::create($message);
    }

    /**
     * Extract the error value or throw an exception with custom message.
     *
     * Like unwrapErr() but allows you to provide a descriptive error message.
     * Useful when you expect a failure and want to extract the error details.
     *
     * ```php
     * $error = $result->expectErr('Operation should have failed for testing');
     * ```
     *
     * @param string $message Custom error message for the exception
     *
     * @throws RuntimeException With the custom message when called on Ok
     *
     * @return E The error value
     */
    public function expectErr(string $message)
    {
        if ($this->isErr()) {
            return $this->unwrapErr();
        }

        throw CannotUnwrapOkException::create($message);
    }

    /**
     * Check if this Result is Ok and contains the specified value.
     *
     * Returns true only if this Result is Ok and its success value
     * is strictly equal (===) to the provided value.
     *
     * ```php
     * $isFortyTwo = $number->contains(42); // true if Ok(42)
     * $isHello = $text->contains('hello'); // true if Ok('hello')
     * ```
     *
     * @param  mixed $value Value to check for
     * @return bool  True if Ok and contains the exact value
     */
    public function contains($value): bool
    {
        return $this->isOk() && $this->unwrap() === $value;
    }

    /**
     * Check if this Result is Err and contains the specified error.
     *
     * Returns true only if this Result is Err and its error value
     * is strictly equal (===) to the provided error.
     *
     * ```php
     * $isNotFound = $result->containsErr('not_found'); // true if Err('not_found')
     * $isTimeout = $result->containsErr($timeoutError); // true if Err($timeoutError)
     * ```
     *
     * @param  mixed $err Error value to check for
     * @return bool  True if Err and contains the exact error
     */
    public function containsErr($err): bool
    {
        return $this->isErr() && $this->unwrapErr() === $err;
    }

    /**
     * Execute a function for side effects if this Result is Ok.
     *
     * Calls the function with the success value for debugging, logging,
     * or other side effects. Returns this Result unchanged for chaining.
     *
     * ```php
     * $result->inspect(fn($value) => logger()->info("Success: $value"))
     *        ->inspect(fn($value) => updateMetrics($value));
     * ```
     *
     * @param  callable(T):mixed $f Function to execute for side effects
     * @return self<T, E>        This Result unchanged for chaining
     */
    public function inspect(callable $f): self
    {
        if ($this->isOk()) {
            $f($this->unwrap());
        }

        return $this;
    }

    /**
     * Execute a function for side effects if this Result is Err.
     *
     * Calls the function with the error value for debugging, logging,
     * or other side effects. Returns this Result unchanged for chaining.
     *
     * ```php
     * $result->inspectErr(fn($error) => logger()->error("Failed: $error"))
     *        ->inspectErr(fn($error) => reportError($error));
     * ```
     *
     * @param  callable(E):mixed $f Function to execute for side effects
     * @return self<T, E>        This Result unchanged for chaining
     */
    public function inspectErr(callable $f): self
    {
        if ($this->isErr()) {
            $f($this->unwrapErr());
        }

        return $this;
    }

    /**
     * Transpose Result<Option<T>,E> into Option<Result<T,E>>.
     *
     * Swaps the order of Result and Option types. If Ok(Some(value)), returns
     * Some(Ok(value)). If Ok(None), returns None. If Err(error), returns
     * Some(Err(error)).
     *
     * ```php
     * $resultOpt = new Ok(new Some('value')); // Result<Option<string>, E>
     * $optResult = $resultOpt->transpose(); // Option<Result<string, E>>
     * ```
     *
     * @throws RuntimeException When Ok doesn't contain an Option
     *
     * @return Option Transposed Option containing Result
     *
     * @phpstan-ignore missingType.generics
     */
    public function transpose(): Option
    {
        if ($this->isOk()) {
            $ok = $this->unwrap();

            if ($ok instanceof Option) {
                return $ok->match(
                    fn ($v): Some => new Some(
                        new Ok($v),
                    ),
                    fn (): None => None::create(),
                );
            }

            throw TransposeExpectedOkWithOptionException::fromValue($ok);
        }

        // Err(e) -> Some(Err(e))
        return new Some(
            new Err($this->unwrapErr()),
        );
    }

    /**
     * Check if this Result represents a successful operation.
     *
     * @return bool True if Ok, false if Err
     */
    abstract public function isOk();

    /**
     * Check if this Result represents a failed operation.
     *
     * @return bool True if Err, false if Ok
     */
    abstract public function isErr();

    /**
     * Extract the success value or throw an exception.
     *
     * Unsafe method that should only be used when you're certain the Result
     * is Ok. Prefer unwrapOr() or unwrapOrElse() for safer access.
     *
     * @throws RuntimeException When called on Err
     *
     * @return T The success value
     */
    abstract public function unwrap();

    /**
     * Extract the error value or throw an exception.
     *
     * Used when you expect an error and want to extract the error details.
     * Throws if called on Ok.
     *
     * @throws RuntimeException When called on Ok
     *
     * @return E The error value
     */
    abstract public function unwrapErr();
}
