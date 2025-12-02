<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Option\Fixtures;

use function end;
use function in_array;
use Cline\Monad\Option\None;

use Cline\Monad\Option\Some;
use Cline\Monad\Option\Option;

/**
 * @author Brian Faust <brian@cline.sh>
 */
final class Repository
{
    public function __construct(
        private array $users = [],
    ) {}

    // A fast ID lookup, probably cached, sometimes we might not need the entire user.
    public function getLastRegisteredUsername(): Option
    {
        if ($this->users === []) {
            return None::create();
        }

        return new Some(end($this->users));
    }

    // Returns a user object (we will live with an array here).
    public function getUser($name): Option
    {
        if (in_array($name, $this->users, true)) {
            return new Some(['name' => $name]);
        }

        return None::create();
    }

    public function getDefaultUser(): array
    {
        return ['name' => 'muhuhu'];
    }
}
