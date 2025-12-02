<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Monad\Exceptions;

use InvalidArgumentException;

/**
 * Base exception for invalid callback errors in lazy monads.
 *
 * @author Brian Faust <brian@cline.sh>
 */
abstract class InvalidCallbackException extends InvalidArgumentException implements MonadException {}
