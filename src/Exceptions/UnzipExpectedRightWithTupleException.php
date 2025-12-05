<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Monad\Exceptions;

/**
 * Thrown when Either::unzip is called without Right containing a tuple.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class UnzipExpectedRightWithTupleException extends UnzipException
{
    public static function create(): self
    {
        return new self('Either::unzip expects Right([a,b]).');
    }
}
