<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Option\Fixtures;

use ArrayAccess;
use ReturnTypeWillChange;

use function array_key_exists;

/**
 * @author Brian Faust <brian@cline.sh>
 */
final class SomeArrayObject implements ArrayAccess
{
    private $data = [];

    #[ReturnTypeWillChange()]
    public function offsetExists($offset)
    {
        return array_key_exists((string) $offset, $this->data);
    }

    #[ReturnTypeWillChange()]
    public function offsetGet($offset)
    {
        return $this->data[$offset];
    }

    #[ReturnTypeWillChange()]
    public function offsetSet($offset, $value): void
    {
        $this->data[$offset] = $value;
    }

    #[ReturnTypeWillChange()]
    public function offsetUnset($offset): void
    {
        unset($this->data[$offset]);
    }
}
