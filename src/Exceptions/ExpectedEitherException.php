<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Monad\Exceptions;

use function get_debug_type;
use function sprintf;

/**
 * Thrown when a lazy Either callback returns an unexpected type.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class ExpectedEitherException extends ExpectedTypeException
{
    public static function fromValue(mixed $actual): self
    {
        return new self(sprintf(
            'Expected instance of Cline\Monad\Either\Either, got %s',
            get_debug_type($actual),
        ));
    }
}
