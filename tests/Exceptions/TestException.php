<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Exceptions;

use LogicException;

/**
 * Exception used in tests for simulating errors.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class TestException extends LogicException
{
    public static function shouldNotBeCalled(): self
    {
        return new self('Should not be called.');
    }

    public static function shouldNeverBeCalled(): self
    {
        return new self('Should never be called.');
    }
}
