<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Monad\Exceptions;

/**
 * Thrown when attempting to unwrap a None value.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class CannotUnwrapNoneException extends UnwrapException
{
    public static function create(string $message = 'None has no value.'): self
    {
        return new self($message);
    }
}
