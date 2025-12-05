<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Monad\Result;

use Cline\Monad\Exceptions\CannotUnwrapOkException;
use RuntimeException;

/**
 * Represents a successful operation in the Result type pattern.
 *
 * Ok is the "success" variant of the Result type, containing a value that
 * represents the successful result of an operation. It provides a type-safe
 * way to handle success cases without relying on exceptions for control flow,
 * making success/failure handling explicit and composable.
 *
 * Ok instances are created when an operation succeeds and you want to return
 * the successful result to the caller:
 *
 * ```php
 * function parseNumber($str): Result {
 *     $num = filter_var($str, FILTER_VALIDATE_INT);
 *     if ($num === false) {
 *         return new Err('Invalid number format');
 *     }
 *     return new Ok($num);
 * }
 *
 * $result = parseNumber('42'); // Ok(42)
 * $value = $result->unwrap(); // 42
 * ```
 *
 * @author Brian Faust <brian@cline.sh>
 * @template T The type of success value contained within this Ok
 * @extends Result<T, mixed>
 */
final class Ok extends Result
{
    /**
     * Create a new Ok instance containing the provided success value.
     *
     * @param T $value The success value to wrap in this Ok
     */
    public function __construct(
        private readonly mixed $value,
    ) {}

    /**
     * Check if this Result represents a successful operation.
     *
     * Ok always represents a success, so this always returns true.
     *
     * @return bool Always returns true for Ok
     */
    public function isOk(): bool
    {
        return true;
    }

    /**
     * Check if this Result represents a failed operation.
     *
     * Ok always represents a success, so this always returns false.
     *
     * @return bool Always returns false for Ok
     */
    public function isErr(): bool
    {
        return false;
    }

    /**
     * Get the contained success value.
     *
     * Safe to call on Ok as it always contains a success value.
     *
     * @return T The contained success value
     */
    public function unwrap()
    {
        return $this->value;
    }

    /**
     * Attempt to unwrap an error value from an Ok.
     *
     * Since Ok represents a success, there is no error value to unwrap.
     * This method always throws an exception. Use mapErr() or other Result
     * methods to handle potential error cases.
     *
     * @throws RuntimeException Always thrown since Ok contains no error value
     *
     * @return never This method never returns normally
     */
    public function unwrapErr(): never
    {
        throw CannotUnwrapOkException::create();
    }
}
