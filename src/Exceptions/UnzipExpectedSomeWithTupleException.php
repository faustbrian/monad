<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Monad\Exceptions;

/**
 * Thrown when Option::unzip is called without Some containing a tuple.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class UnzipExpectedSomeWithTupleException extends UnzipException
{
    public static function create(): self
    {
        return new self('Option::unzip expects Some([a,b]).');
    }
}
