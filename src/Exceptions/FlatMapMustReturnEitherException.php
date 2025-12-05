<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Monad\Exceptions;

/**
 * Thrown when a flatMap callback on Either does not return an Either.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class FlatMapMustReturnEitherException extends FlatMapException
{
    public static function create(): self
    {
        return new self('Callables passed to flatMap() must return an Either. Maybe you should use map() instead?');
    }
}
