<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Monad\Option\Option;
use Illuminate\Support\Collection;
use Illuminate\Support\Traits\Macroable;

beforeEach(function (): void {
    // Register collection macro locally for unit testing (no app boot)
    if (!method_exists(Collection::class, 'firstOption') || !Collection::hasMacro('firstOption')) {
        Collection::macro('firstOption', function (?callable $p = null): Option {
            /** @var Collection $this */
            return Option::fromNullable($this->first($p));
        });
    }
});

describe('Option Helpers and Macros', function (): void {
    describe('Happy Paths', function (): void {
        test('returns Some when collection has first element', function (): void {
            // Arrange
            $collection = collect([1, 2, 3]);

            // Act
            $option = $collection->firstOption();

            // Assert
            expect($option->isDefined())->toBeTrue();
            expect($option->get())->toBe(1);
        });

        test('returns Some when macroable relation has first result', function (): void {
            // Arrange - Dummy using Macroable to emulate Laravel Relation
            $relation = new class('val')
            {
                use Macroable;

                public function __construct(
                    private $first,
                ) {}

                public function first()
                {
                    return $this->first;
                }
            };

            $relation::macro('firstOption', fn (): Option => Option::fromNullable($this->first()));

            // Act
            $option = $relation->firstOption();

            // Assert
            expect($option->isDefined())->toBeTrue();
            expect($option->get())->toBe('val');
        });
    });

    describe('Sad Paths', function (): void {
        test('returns None when collection is empty', function (): void {
            // Arrange
            $collection = collect();

            // Act
            $option = $collection->firstOption();

            // Assert
            expect($option->isEmpty())->toBeTrue();
        });

        test('returns None when macroable builder has no first result', function (): void {
            // Arrange - Dummy using Macroable to emulate Laravel Builder
            $builder = new class(null)
            {
                use Macroable;

                public function __construct(
                    private $first,
                ) {}

                public function first()
                {
                    return $this->first;
                }
            };

            $builder::macro('firstOption', fn (): Option => Option::fromNullable($this->first()));

            // Act
            $option = $builder->firstOption();

            // Assert
            expect($option->isEmpty())->toBeTrue();
        });
    });
});
