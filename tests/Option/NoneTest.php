<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Monad\Option\None;
use Cline\Monad\Option\Some;

describe('None', function (): void {
    describe('Happy Paths', function (): void {
        test('returns default value when calling unwrapOr on None', function (): void {
            $none = None::create();
            expect($none->unwrapOr('foo'))->toBe('foo');
        });

        test('returns result of fallback function when calling unwrapOrElse on None', function (): void {
            $none = None::create();
            expect($none->unwrapOrElse(function () {
                return 'foo';
            }))->toBe('foo');
        });

        test('returns true when checking isEmpty on None', function (): void {
            $none = None::create();
            expect($none)->isEmpty()->toBeTrue();
        });

        test('returns alternative Option when calling orElse on None', function (): void {
            $option = Some::create('foo');
            expect(None::create()->orElse(fn () => $option))->toBe($option);
        });

        test('does not execute callback when calling ifDefined on None', function (): void {
            $none = None::create();

            expect($none->ifDefined(function (): void {
                throw new LogicException('Should never be called.');
            }))->toBeNull();
        });

        test('does not execute callback and returns self when calling forAll on None', function (): void {
            $none = None::create();

            expect($none->forAll(function (): void {
                throw new LogicException('Should never be called.');
            }))->toBe($none);
        });

        test('does not execute map function and returns self when mapping None', function (): void {
            $none = None::create();

            expect($none->map(function (): void {
                throw new LogicException('Should not be called.');
            }))->toBe($none);
        });

        test('does not execute flatMap function and returns self when flatMapping None', function (): void {
            $none = None::create();

            expect($none->flatMap(function (): void {
                throw new LogicException('Should not be called.');
            }))->toBe($none);
        });

        test('does not execute filter predicate and returns self when filtering None', function (): void {
            $none = None::create();

            expect($none->filter(function (): void {
                throw new LogicException('Should not be called.');
            }))->toBe($none);
        });

        test('does not execute filterNot predicate and returns self when filtering None', function (): void {
            $none = None::create();

            expect($none->filterNot(function (): void {
                throw new LogicException('Should not be called.');
            }))->toBe($none);
        });

        test('returns self when calling select on None', function (): void {
            $none = None::create();

            expect($none->select(null))->toBe($none);
        });

        test('returns self when calling reject on None', function (): void {
            $none = None::create();

            expect($none->reject(null))->toBe($none);
        });

        test('does not iterate when using None in foreach loop', function (): void {
            $none = None::create();

            $called = 0;

            foreach ($none as $value) {
                $called++;
            }

            expect($called)->toBe(0);
        });

        test('returns initial accumulator without calling fold function on None', function (): void {
            $none = None::create();

            expect($none->foldLeft(1, function (): void {
                $this->fail();
            }))->toBe(1);

            expect($none->foldRight(1, function (): void {
                $this->fail();
            }))->toBe(1);
        });
    });

    describe('Sad Paths', function (): void {
        test('throws RuntimeException when calling get on None', function (): void {
            if (method_exists($this, 'expectException')) {
                $this->expectException('RuntimeException');
            } else {
                $this->expectException('RuntimeException');
            }

            $none = None::create();
            $none->get();
        });

        test('throws custom exception when calling unwrapOrThrow on None', function (): void {
            if (method_exists($this, 'expectException')) {
                $this->expectException('RuntimeException');
                $this->expectExceptionMessage('Not Found!');
            } else {
                $this->expectException('RuntimeException');
                $this->expectExceptionMessage('Not Found!');
            }

            None::create()->unwrapOrThrow(
                new RuntimeException('Not Found!'),
            );
        });
    });
});
