<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Monad\Exceptions;

/**
 * Thrown when attempting to call unwrapErr() on Ok.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class CannotUnwrapOkException extends UnwrapException
{
    public static function create(string $message = 'Called unwrapErr() on Ok'): self
    {
        return new self($message);
    }
}
