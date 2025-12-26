<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Monad\Exceptions;

/**
 * Thrown when an invalid callback is provided to LazyEither.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class InvalidLazyEitherCallbackException extends InvalidCallbackException
{
    public static function create(): self
    {
        return new self('Invalid callback given');
    }
}
