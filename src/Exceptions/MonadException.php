<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Monad\Exceptions;

use Throwable;

/**
 * Marker interface for all exceptions thrown by the Monad library.
 *
 * Allows consumers to catch all monad-related exceptions with:
 * catch (MonadException $e) { ... }
 *
 * @author Brian Faust <brian@cline.sh>
 */
interface MonadException extends Throwable {}
