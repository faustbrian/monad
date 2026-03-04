<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Monad\Exceptions;

/**
 * Thrown when a flatMap callback on Option does not return an Option.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class FlatMapMustReturnOptionException extends FlatMapException
{
    public static function create(): self
    {
        return new self('Callables passed to flatMap() must return an Option. Maybe you should use map() instead?');
    }
}
