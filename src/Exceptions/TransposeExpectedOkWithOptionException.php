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
 * Thrown when Result::transpose is called with Ok containing a non-Option.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class TransposeExpectedOkWithOptionException extends TransposeException
{
    public static function fromValue(mixed $actual): self
    {
        return new self(sprintf(
            'Result::transpose expects Ok(Option), got Ok(%s)',
            get_debug_type($actual),
        ));
    }
}
