<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Monad\Exceptions;

use RuntimeException;

/**
 * Thrown when a filter callback returns a non-boolean value.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class FilterException extends RuntimeException implements MonadException
{
    public static function callableMustReturnBoolean(): self
    {
        return new self('Callables passed to filter() must return a boolean.');
    }
}
