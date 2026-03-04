<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Monad\Exceptions;

/**
 * Thrown when attempting to unwrap Right value from Left.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class CannotUnwrapRightFromLeftException extends UnwrapException
{
    public static function create(string $message = 'Cannot unwrap Right value from Left.'): self
    {
        return new self($message);
    }
}
