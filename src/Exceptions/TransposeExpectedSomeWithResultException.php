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
 * Thrown when Option::transpose is called with Some containing a non-Result.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class TransposeExpectedSomeWithResultException extends TransposeException
{
    public static function fromValue(mixed $actual): self
    {
        return new self(sprintf(
            'Option::transpose expects Some(Result), got Some(%s)',
            get_debug_type($actual),
        ));
    }
}
