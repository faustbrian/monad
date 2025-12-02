<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Monad\Either\Left;
use Cline\Monad\Either\Right;
use Tests\Exceptions\TestException;

describe('Left', function (): void {
    describe('Happy Paths', function (): void {
        test('creates Left instance with value', function (): void {
            $left = new Left('error message');

            expect($left->isLeft())->toBeTrue();
            expect($left->isRight())->toBeFalse();
            expect($left->unwrapLeft())->toBe('error message');
        });

        test('returns default value with unwrapOr', function (): void {
            $left = new Left('error');

            expect($left->unwrapOr('default'))->toBe('default');
            expect($left->unwrapOr(0))->toBe(0);
            expect($left->unwrapOr(null))->toBeNull();
        });

        test('computes fallback value with unwrapOrElse', function (): void {
            $left = new Left('error');

            $result = $left->unwrapOrElse(fn (string $e): string => 'Handled: ' . $e);
            expect($result)->toBe('Handled: error');

            $lengthResult = $left->unwrapOrElse(fn (string $e): int => mb_strlen($e));
            expect($lengthResult)->toBe(5);
        });

        test('ignores map transformations', function (): void {
            $left = new Left('error');
            $called = false;

            $result = $left->map(function ($value) use (&$called) {
                $called = true;

                return $value;
            });

            expect($called)->toBeFalse();
            expect($result->isLeft())->toBeTrue();
            expect($result->unwrapLeft())->toBe('error');
        });

        test('applies mapLeft transformation', function (): void {
            $left = new Left('error');

            $result = $left->mapLeft(fn (string $e) => mb_strtoupper($e));

            expect($result->isLeft())->toBeTrue();
            expect($result->unwrapLeft())->toBe('ERROR');
        });

        test('applies only left function in bimap', function (): void {
            $left = new Left(5);
            $rightCalled = false;
            $leftCalled = false;

            $result = $left->bimap(
                function (int $l) use (&$leftCalled): int {
                    $leftCalled = true;

                    return $l * 2;
                },
                function ($r) use (&$rightCalled) {
                    $rightCalled = true;

                    return $r;
                },
            );

            expect($leftCalled)->toBeTrue();
            expect($rightCalled)->toBeFalse();
            expect($result->unwrapLeft())->toBe(10);
        });

        test('skips flatMap operation', function (): void {
            $left = new Left('error');
            $called = false;

            $result = $left->flatMap(function ($value) use (&$called): Right {
                $called = true;

                return new Right($value);
            });

            expect($called)->toBeFalse();
            expect($result->isLeft())->toBeTrue();
            expect($result->unwrapLeft())->toBe('error');
        });

        test('skips forAll side effect', function (): void {
            $left = new Left('error');
            $called = false;

            $result = $left->forAll(function ($value) use (&$called): void {
                $called = true;
            });

            expect($called)->toBeFalse();
            expect($result)->toBe($left);
        });

        test('executes forLeft side effect', function (): void {
            $left = new Left('error');
            $capturedValue = null;

            $result = $left->forLeft(function (string $e) use (&$capturedValue): void {
                $capturedValue = $e;
            });

            expect($capturedValue)->toBe('error');
            expect($result)->toBe($left);
        });

        test('skips inspect callback', function (): void {
            $left = new Left('error');
            $called = false;

            $result = $left->inspect(function ($value) use (&$called): void {
                $called = true;
            });

            expect($called)->toBeFalse();
            expect($result)->toBe($left);
        });

        test('ignores filter operation', function (): void {
            $left = new Left('error');
            $called = false;

            $result = $left->filter(function ($value) use (&$called): true {
                $called = true;

                return true;
            }, 'fallback');

            expect($called)->toBeFalse();
            expect($result->isLeft())->toBeTrue();
            expect($result->unwrapLeft())->toBe('error');
        });

        test('executes left branch in match', function (): void {
            $left = new Left('error');
            $rightCalled = false;
            $leftCalled = false;

            $result = $left->match(
                function (string $e) use (&$leftCalled): string {
                    $leftCalled = true;

                    return 'Left: ' . $e;
                },
                function (string $r) use (&$rightCalled): string {
                    $rightCalled = true;

                    return 'Right: ' . $r;
                },
            );

            expect($leftCalled)->toBeTrue();
            expect($rightCalled)->toBeFalse();
            expect($result)->toBe('Left: error');
        });

        test('executes left function in fold', function (): void {
            $left = new Left(10);
            $rightCalled = false;
            $leftCalled = false;

            $result = $left->fold(
                function (int $l) use (&$leftCalled): int {
                    $leftCalled = true;

                    return $l * 2;
                },
                function ($r) use (&$rightCalled) {
                    $rightCalled = true;

                    return $r;
                },
            );

            expect($leftCalled)->toBeTrue();
            expect($rightCalled)->toBeFalse();
            expect($result)->toBe(20);
        });

        test('swaps to Right', function (): void {
            $left = new Left('error');

            $result = $left->swap();

            expect($result->isRight())->toBeTrue();
            expect($result->unwrap())->toBe('error');
        });

        test('checks if contains left value', function (): void {
            $left = new Left('error');

            expect($left->containsLeft('error'))->toBeTrue();
            expect($left->containsLeft('other'))->toBeFalse();
            expect($left->contains('error'))->toBeFalse();
        });

        test('checks left state with predicate', function (): void {
            $left = new Left('error');

            expect($left->isLeftAnd(fn (string $e): bool => mb_strlen($e) === 5))->toBeTrue();
            expect($left->isLeftAnd(fn (string $e): bool => mb_strlen($e) > 10))->toBeFalse();
            expect($left->isRightAnd(fn ($r): true => true))->toBeFalse();
        });

        test('flattens returns self when not nested', function (): void {
            $left = new Left('error');

            $result = $left->flatten();

            expect($result)->toBe($left);
            expect($result->unwrapLeft())->toBe('error');
        });

        test('clones with object value', function (): void {
            $obj = new stdClass();
            $obj->message = 'original';

            $left = new Left($obj);
            $cloned = $left->cloned();

            $clonedObj = $cloned->unwrapLeft();
            $clonedObj->message = 'modified';

            expect($obj->message)->toBe('original');
            expect($clonedObj->message)->toBe('modified');
        });

        test('clones with scalar value', function (): void {
            $left = new Left('error');

            $cloned = $left->cloned();

            expect($cloned->unwrapLeft())->toBe('error');
            expect($cloned)->not->toBe($left);
        });

        test('returns empty iterator', function (): void {
            $left = new Left('error');
            $values = [];

            foreach ($left as $value) {
                $values[] = $value;
            }

            expect($values)->toBe([]);
        });
    });

    describe('Sad Paths', function (): void {
        test('throws when trying to unwrap Right value', function (): void {
            $left = new Left('error');

            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('Cannot unwrap Right value from Left.');
            $left->unwrap();
        });
    });

    describe('Edge Cases', function (): void {
        test('handles null as Left value', function (): void {
            $left = new Left(null);

            expect($left->isLeft())->toBeTrue();
            expect($left->unwrapLeft())->toBeNull();
            expect($left->containsLeft(null))->toBeTrue();
        });

        test('handles arrays as Left value', function (): void {
            $errors = ['field1' => 'error1', 'field2' => 'error2'];
            $left = new Left($errors);

            expect($left->unwrapLeft())->toBe($errors);
            expect($left->containsLeft($errors))->toBeTrue();
        });

        test('chains multiple mapLeft transformations', function (): void {
            $left = new Left('error');

            $result = $left
                ->mapLeft(fn (string $e) => mb_strtoupper($e))
                ->mapLeft(fn (string $e) => str_replace('ERROR', 'FAIL', $e))
                ->mapLeft(fn (string $e): string => sprintf('[%s]', $e));

            expect($result->unwrapLeft())->toBe('[FAIL]');
        });

        test('preserves Left through complex operations', function (): void {
            $left = new Left('original');

            $result = $left
                ->map(fn ($x): string => 'never')
                ->flatMap(fn ($x): Right => new Right('never'))
                ->filter(fn ($x): true => true, 'never')
                ->mapLeft(fn (string $e) => mb_strtoupper($e));

            expect($result->isLeft())->toBeTrue();
            expect($result->unwrapLeft())->toBe('ORIGINAL');
        });

        test('handles exception objects as Left value', function (): void {
            $exception = new RuntimeException('Something went wrong');
            $left = new Left($exception);

            expect($left->unwrapLeft())->toBe($exception);
            expect($left->unwrapLeft()->getMessage())->toBe('Something went wrong');

            $mapped = $left->mapLeft(fn (RuntimeException $e): string => $e->getMessage());
            expect($mapped->unwrapLeft())->toBe('Something went wrong');
        });
    });

    describe('Regressions', function (): void {
        test('unwrapOrElse always calls callable with Left value', function (): void {
            $left = new Left('error');
            $receivedValue = null;

            $result = $left->unwrapOrElse(function ($e) use (&$receivedValue): string {
                $receivedValue = $e;

                return 'fallback';
            });

            expect($receivedValue)->toBe('error');
            expect($result)->toBe('fallback');
        });

        test('forLeft always returns same instance', function (): void {
            $left = new Left('error');

            $result = $left->forLeft(fn ($e): null => null);

            expect($result)->toBe($left);
        });

        test('bimap only transforms Left value', function (): void {
            $left = new Left(5);

            $result = $left->bimap(
                fn (int $l): int => $l * 3,
                fn ($r) => throw TestException::shouldNotBeCalled(),
            );

            expect($result->unwrapLeft())->toBe(15);
        });

        test('match only calls left callback', function (): void {
            $left = new Left('error');

            $result = $left->match(
                fn (string $e): string => 'Error: ' . $e,
                fn ($r) => throw TestException::shouldNotBeCalled(),
            );

            expect($result)->toBe('Error: error');
        });

        test('fold only applies left function', function (): void {
            $left = new Left(10);

            $result = $left->fold(
                fn (int $l): int => $l + 5,
                fn ($r) => throw TestException::shouldNotBeCalled(),
            );

            expect($result)->toBe(15);
        });

        test('containsLeft uses strict equality', function (): void {
            $left = new Left(0);

            expect($left->containsLeft(0))->toBeTrue();
            expect($left->containsLeft(false))->toBeFalse();
            expect($left->containsLeft(''))->toBeFalse();
            expect($left->containsLeft(null))->toBeFalse();
        });

        test('isLeftAnd converts return value to boolean', function (): void {
            $left = new Left('value');

            expect($left->isLeftAnd(fn ($e): int => 0))->toBeFalse();
            expect($left->isLeftAnd(fn ($e): int => 1))->toBeTrue();
            expect($left->isLeftAnd(fn ($e): string => ''))->toBeFalse();
            expect($left->isLeftAnd(fn ($e): string => 'truthy'))->toBeTrue();
            expect($left->isLeftAnd(fn ($e): null => null))->toBeFalse();
        });
    });
});
