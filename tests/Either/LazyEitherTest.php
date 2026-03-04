<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Monad\Either\Either;
use Cline\Monad\Either\LazyEither;
use Cline\Monad\Either\Left;
use Cline\Monad\Either\Right;
use Illuminate\Support\Sleep;
use Tests\Exceptions\SimulatedException;

describe('LazyEither', function (): void {
    describe('Happy Paths', function (): void {
        test('creates LazyEither with deferred execution', function (): void {
            $executed = false;

            $lazy = LazyEither::create(function () use (&$executed): Right {
                $executed = true;

                return new Right(42);
            });

            expect($executed)->toBeFalse();

            $result = $lazy->unwrap();

            expect($executed)->toBeTrue();
            expect($result)->toBe(42);
        });

        test('creates LazyEither using Either::lazy static method', function (): void {
            $executed = false;

            $lazy = Either::lazy(function () use (&$executed): Right {
                $executed = true;

                return new Right('value');
            });

            expect($executed)->toBeFalse();

            $result = $lazy->unwrap();

            expect($executed)->toBeTrue();
            expect($result)->toBe('value');
        });

        test('isRight forces evaluation and returns correct result', function (): void {
            $lazy = LazyEither::create(fn (): Right => new Right(10));

            expect($lazy->isRight())->toBeTrue();
        });

        test('isLeft forces evaluation and returns correct result', function (): void {
            $lazy = LazyEither::create(fn (): Left => new Left('error'));

            expect($lazy->isLeft())->toBeTrue();
        });

        test('map transforms lazy Right value', function (): void {
            $lazy = LazyEither::create(fn (): Right => new Right(10));

            $result = $lazy->map(fn (int $x): int => $x * 2);

            expect($result->isRight())->toBeTrue();
            expect($result->unwrap())->toBe(20);
        });

        test('mapLeft transforms lazy Left value', function (): void {
            $lazy = LazyEither::create(fn (): Left => new Left('error'));

            $result = $lazy->mapLeft(fn (string $e) => mb_strtoupper($e));

            expect($result->isLeft())->toBeTrue();
            expect($result->unwrapLeft())->toBe('ERROR');
        });

        test('flatMap chains lazy Either operations', function (): void {
            $lazy = LazyEither::create(fn (): Right => new Right(5));

            $result = $lazy->flatMap(fn (int $x): Right => new Right($x * 3));

            expect($result->isRight())->toBeTrue();
            expect($result->unwrap())->toBe(15);
        });

        test('unwrapOr returns Right value from lazy Either', function (): void {
            $lazy = LazyEither::create(fn (): Right => new Right('value'));

            $result = $lazy->unwrapOr('default');

            expect($result)->toBe('value');
        });

        test('unwrapOr returns default when lazy Either is Left', function (): void {
            $lazy = LazyEither::create(fn (): Left => new Left('error'));

            $result = $lazy->unwrapOr('default');

            expect($result)->toBe('default');
        });

        test('unwrapOrElse computes fallback from lazy Left', function (): void {
            $lazy = LazyEither::create(fn (): Left => new Left('error'));

            $result = $lazy->unwrapOrElse(fn (string $e): string => 'handled: '.$e);

            expect($result)->toBe('handled: error');
        });
    });

    describe('Sad Paths', function (): void {
        test('unwrap throws exception when lazy Either is Left', function (): void {
            $lazy = LazyEither::create(fn (): Left => new Left('error'));

            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('Cannot unwrap Right value from Left.');
            $lazy->unwrap();
        });

        test('unwrapLeft throws exception when lazy Either is Right', function (): void {
            $lazy = LazyEither::create(fn (): Right => new Right(42));

            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('Cannot unwrap Left value from Right.');
            $lazy->unwrapLeft();
        });

        test('throws exception when callback does not return Either', function (): void {
            $lazy = LazyEither::create(fn (): string => 'not an Either');

            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('Expected instance of Cline\Monad\Either\Either');
            $lazy->isRight();
        });

        test('throws exception when callback is not callable', function (): void {
            $this->expectException(InvalidArgumentException::class);
            $this->expectExceptionMessage('Invalid callback given');
            new LazyEither('not a callable');
        });
    });

    describe('Edge Cases', function (): void {
        test('caches result after first evaluation', function (): void {
            $executionCount = 0;

            $lazy = LazyEither::create(function () use (&$executionCount): Right {
                ++$executionCount;

                return new Right(42);
            });

            $lazy->isRight();
            $lazy->unwrap();
            $lazy->isLeft();
            $lazy->unwrap();

            expect($executionCount)->toBe(1);
        });

        test('lazy Either with arguments', function (): void {
            $lazy = Either::lazy(
                fn (int $a, int $b): Right => new Right($a + $b),
                [10, 20],
            );

            $result = $lazy->unwrap();

            expect($result)->toBe(30);
        });

        test('nested lazy Eithers', function (): void {
            $innerExecuted = false;
            $outerExecuted = false;

            $innerLazy = Either::lazy(function () use (&$innerExecuted): Right {
                $innerExecuted = true;

                return new Right(10);
            });

            $outerLazy = Either::lazy(function () use ($innerLazy, &$outerExecuted) {
                $outerExecuted = true;

                return $innerLazy->map(fn (int $x): int => $x * 2);
            });

            expect($innerExecuted)->toBeFalse();
            expect($outerExecuted)->toBeFalse();

            $result = $outerLazy->unwrap();

            expect($innerExecuted)->toBeTrue();
            expect($outerExecuted)->toBeTrue();
            expect($result)->toBe(20);
        });

        test('bimap on lazy Either', function (): void {
            $lazyRight = LazyEither::create(fn (): Right => new Right(5));
            $lazyLeft = LazyEither::create(fn (): Left => new Left('error'));

            $rightResult = $lazyRight->bimap(
                fn (string $e) => mb_strtoupper($e),
                fn (int $x): int => $x * 2,
            );

            $leftResult = $lazyLeft->bimap(
                fn (string $e) => mb_strtoupper($e),
                fn (int $x): int => $x * 2,
            );

            expect($rightResult->unwrap())->toBe(10);
            expect($leftResult->unwrapLeft())->toBe('ERROR');
        });

        test('forAll executes side effect on lazy Right', function (): void {
            $sideEffect = null;
            $lazy = LazyEither::create(fn (): Right => new Right('value'));

            $result = $lazy->forAll(function (string $x) use (&$sideEffect): void {
                $sideEffect = $x;
            });

            expect($sideEffect)->toBe('value');
        });

        test('forLeft executes side effect on lazy Left', function (): void {
            $sideEffect = null;
            $lazy = LazyEither::create(fn (): Left => new Left('error'));

            $result = $lazy->forLeft(function (string $e) use (&$sideEffect): void {
                $sideEffect = $e;
            });

            expect($sideEffect)->toBe('error');
        });

        test('inspect for debugging lazy Right', function (): void {
            $inspected = null;
            $lazy = LazyEither::create(fn (): Right => new Right('debug'));

            $lazy->inspect(function (string $x) use (&$inspected): void {
                $inspected = $x;
            });

            expect($inspected)->toBe('debug');
        });

        test('filter on lazy Either', function (): void {
            $lazy = LazyEither::create(fn (): Right => new Right(10));

            $passes = $lazy->filter(fn (int $x): bool => $x > 5, 'too small');
            $fails = $lazy->filter(fn (int $x): bool => $x > 20, 'too small');

            expect($passes->isRight())->toBeTrue();
            expect($passes->unwrap())->toBe(10);

            expect($fails->isLeft())->toBeTrue();
            expect($fails->unwrapLeft())->toBe('too small');
        });

        test('match pattern on lazy Either', function (): void {
            $lazyRight = LazyEither::create(fn (): Right => new Right('success'));
            $lazyLeft = LazyEither::create(fn (): Left => new Left('error'));

            $rightResult = $lazyRight->match(
                fn (string $e): string => 'Error: '.$e,
                fn (string $v): string => 'Success: '.$v,
            );

            $leftResult = $lazyLeft->match(
                fn (string $e): string => 'Error: '.$e,
                fn (string $v): string => 'Success: '.$v,
            );

            expect($rightResult)->toBe('Success: success');
            expect($leftResult)->toBe('Error: error');
        });

        test('swap on lazy Either', function (): void {
            $lazyRight = LazyEither::create(fn (): Right => new Right('value'));
            $lazyLeft = LazyEither::create(fn (): Left => new Left('error'));

            $swappedRight = $lazyRight->swap();
            $swappedLeft = $lazyLeft->swap();

            expect($swappedRight->isLeft())->toBeTrue();
            expect($swappedRight->unwrapLeft())->toBe('value');

            expect($swappedLeft->isRight())->toBeTrue();
            expect($swappedLeft->unwrap())->toBe('error');
        });

        test('cloned on lazy Either', function (): void {
            $obj = (object) ['value' => 'original'];
            $lazy = LazyEither::create(fn (): Right => new Right($obj));

            $cloned = $lazy->cloned();
            $clonedValue = $cloned->unwrap();
            $clonedValue->value = 'modified';

            expect($obj->value)->toBe('original');
            expect($clonedValue->value)->toBe('modified');
        });

        test('fold on lazy Either', function (): void {
            $lazyRight = LazyEither::create(fn (): Right => new Right(10));
            $lazyLeft = LazyEither::create(fn (): Left => new Left(5));

            $rightFolded = $lazyRight->fold(
                fn (int $l): int => $l * 2,
                fn (int $r): int => $r * 3,
            );

            $leftFolded = $lazyLeft->fold(
                fn (int $l): int => $l * 2,
                fn (int $r): int => $r * 3,
            );

            expect($rightFolded)->toBe(30);
            expect($leftFolded)->toBe(10);
        });

        test('getIterator on lazy Right', function (): void {
            $lazy = LazyEither::create(fn (): Right => new Right('value'));
            $values = [];

            foreach ($lazy as $value) {
                $values[] = $value;
            }

            expect($values)->toBe(['value']);
        });

        test('getIterator on lazy Left', function (): void {
            $lazy = LazyEither::create(fn (): Left => new Left('error'));
            $values = [];

            foreach ($lazy as $value) {
                $values[] = $value;
            }

            expect($values)->toBe([]);
        });

        test('chaining multiple operations on lazy Either', function (): void {
            $executionCount = 0;

            $lazy = Either::lazy(function () use (&$executionCount): Right {
                ++$executionCount;

                return new Right(5);
            });

            $result = $lazy
                ->map(fn (int $x): int => $x * 2)
                ->flatMap(fn (int $x): Right => new Right($x + 3))
                ->filter(fn (int $x): bool => $x > 10, 'too small')
                ->map(fn (int $x): int => $x - 1);

            // map, flatMap, and filter on LazyEither force evaluation
            expect($executionCount)->toBe(1);

            $final = $result->unwrap();

            expect($executionCount)->toBe(1); // Still only executed once
            expect($final)->toBe(12);
        });

        test('expensive computation deferred until needed', function (): void {
            $expensive = false;

            $lazy = Either::lazy(function () use (&$expensive): Right {
                $expensive = true;
                Sleep::sleep(0); // Simulates expensive operation

                return new Right('computed');
            });

            expect($expensive)->toBeFalse();

            // Only execute when actually needed
            $result = $lazy->unwrapOr('default');

            expect($expensive)->toBeTrue();
            expect($result)->toBe('computed');
        });
    });

    describe('Regressions', function (): void {
        test('lazy Either only executes callback once regardless of access pattern', function (): void {
            $count = 0;

            $lazy = LazyEither::create(function () use (&$count): Right {
                ++$count;

                return new Right(42);
            });

            $lazy->isRight();
            $lazy->isLeft();
            $lazy->unwrap();
            $lazy->map(fn ($x): int => $x);
            $lazy->unwrap();

            expect($count)->toBe(1);
        });

        test('lazy Either preserves type through caching', function (): void {
            $lazy = LazyEither::create(fn (): Right => new Right(['key' => 'value']));

            $first = $lazy->unwrap();
            $second = $lazy->unwrap();

            expect($first)->toBe(['key' => 'value']);
            expect($second)->toBe(['key' => 'value']);
        });

        test('lazy Either with Left caches correctly', function (): void {
            $count = 0;

            $lazy = LazyEither::create(function () use (&$count): Left {
                ++$count;

                return new Left('error');
            });

            $lazy->isLeft();
            $lazy->unwrapOr('default');
            $lazy->isLeft();

            expect($count)->toBe(1);
        });

        test('nested lazy evaluation executes in correct order', function (): void {
            $order = [];

            $lazy1 = Either::lazy(function () use (&$order): Right {
                $order[] = 'lazy1';

                return new Right(1);
            });

            $lazy2 = Either::lazy(function () use ($lazy1, &$order) {
                $order[] = 'lazy2';

                return $lazy1->map(fn (int $x): int => $x + 1);
            });

            expect($order)->toBe([]);

            $result = $lazy2->unwrap();

            expect($order)->toBe(['lazy2', 'lazy1']);
            expect($result)->toBe(2);
        });

        test('lazy Either with arguments caches properly', function (): void {
            $count = 0;

            $lazy = Either::lazy(
                function (int $a, int $b) use (&$count): Right {
                    ++$count;

                    return new Right($a + $b);
                },
                [5, 10],
            );

            $lazy->unwrap();
            $lazy->unwrap();
            $lazy->unwrap();

            expect($count)->toBe(1);
        });

        test('lazy Either error handling preserves exception details', function (): void {
            $lazy = LazyEither::create(fn (): Left => new Left('specific error'));

            try {
                $lazy->unwrap();
                expect(true)->toBeFalse(); // Should not reach here
            } catch (RuntimeException $runtimeException) {
                expect($runtimeException->getMessage())->toBe('Cannot unwrap Right value from Left.');
            }
        });

        test('map on lazy Either forces evaluation to transform value', function (): void {
            $executed = false;

            $lazy = LazyEither::create(function () use (&$executed): Right {
                $executed = true;

                return new Right(10);
            });

            $mapped = $lazy->map(fn (int $x): int => $x * 2);

            // map on LazyEither forces evaluation to apply transformation
            expect($executed)->toBeTrue();

            $result = $mapped->unwrap();

            expect($executed)->toBeTrue();
            expect($result)->toBe(20);
        });

        test('flatMap on lazy Either forces evaluation', function (): void {
            $executed = false;

            $lazy = LazyEither::create(function () use (&$executed): Right {
                $executed = true;

                return new Right(5);
            });

            $result = $lazy->flatMap(fn (int $x): Right => new Right($x * 2));

            expect($executed)->toBeTrue(); // flatMap needs to evaluate to return Either
        });

        test('lazy Either with exception in callback', function (): void {
            $lazy = LazyEither::create(function (): void {
                throw SimulatedException::callbackError();
            });

            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('Callback error');
            $lazy->isRight();
        });
    });
});
