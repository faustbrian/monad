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
 * Base exception for flatMap callback type errors.
 *
 * @author Brian Faust <brian@cline.sh>
 */
abstract class FlatMapException extends RuntimeException implements MonadException {}
