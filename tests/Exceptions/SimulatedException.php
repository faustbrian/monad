<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Exceptions;

use RuntimeException;

/**
 * Exception used in tests for simulating runtime errors.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class SimulatedException extends RuntimeException
{
    public static function callbackError(): self
    {
        return new self('Callback error');
    }

    public static function error(): self
    {
        return new self('error');
    }
}
