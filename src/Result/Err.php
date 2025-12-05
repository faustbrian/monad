<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Monad\Result;

use Cline\Monad\Exceptions\CannotUnwrapErrException;

/**
 * Represents a failure case in the Result type pattern.
 *
 * Err is the "error" variant of the Result type, containing an error value
 * that describes what went wrong during an operation. It provides a type-safe
 * way to handle errors without throwing exceptions, making error handling
 * explicit and composable.
 *
 * Err instances are created when an operation fails and you want to return
 * error information to the caller:
 *
 * ```php
 * function divideNumbers($a, $b): Result {
 *     if ($b === 0) {
 *         return new Err('Division by zero');
 *     }
 *     return new Ok($a / $b);
 * }
 *
 * $result = divideNumbers(10, 0); // Err('Division by zero')
 * $value = $result->unwrapOr(-1); // -1
 * ```
 *
 * @author Brian Faust <brian@cline.sh>
 * @template E The type of error value contained within this Err
 * @extends Result<mixed, E>
 */
final class Err extends Result
{
    /**
     * Create a new Err instance containing the provided error value.
     *
     * @param E $error The error value to wrap in this Err
     */
    public function __construct(
        private readonly mixed $error,
    ) {}

    /**
     * Check if this Result represents a successful operation.
     *
     * Err always represents a failure, so this always returns false.
     *
     * @return bool Always returns false for Err
     */
    public function isOk(): bool
    {
        return false;
    }

    /**
     * Check if this Result represents a failed operation.
     *
     * Err always represents a failure, so this always returns true.
     *
     * @return bool Always returns true for Err
     */
    public function isErr(): bool
    {
        return true;
    }

    /**
     * Attempt to unwrap a success value from an Err.
     *
     * Since Err represents a failure, there is no success value to unwrap.
     * This method always throws an exception. Use unwrapOr() or unwrapOrElse()
     * for safe value extraction.
     *
     * @throws CannotUnwrapErrException Always thrown since Err contains no success value
     *
     * @return never This method never returns normally
     */
    public function unwrap(): never
    {
        throw CannotUnwrapErrException::create();
    }

    /**
     * Get the contained error value.
     *
     * Safe to call on Err as it always contains an error value.
     *
     * @return E The contained error value
     */
    public function unwrapErr()
    {
        return $this->error;
    }
}
