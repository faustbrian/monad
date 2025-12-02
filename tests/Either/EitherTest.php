<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Monad\Either\Either;
use Cline\Monad\Either\Left;
use Cline\Monad\Either\Right;
use Tests\Exceptions\SimulatedException;
use Tests\Exceptions\TestException;

function expensiveComputation(): int
{
    return 999;
}

describe('Either', function (): void {
    describe('Happy Paths', function (): void {
        test('creates Left and Right instances with correct values', function (): void {
            $left = new Left('error');
            $right = new Right('success');

            expect($left->isLeft())->toBeTrue();
            expect($left->isRight())->toBeFalse();
            expect($left->unwrapLeft())->toBe('error');

            expect($right->isRight())->toBeTrue();
            expect($right->isLeft())->toBeFalse();
            expect($right->unwrap())->toBe('success');
        });

        test('creates Either from nullable value using fromNullable', function (): void {
            $right = Either::fromNullable('value');
            $left = Either::fromNullable(null);
            $leftCustom = Either::fromNullable(null, 'custom error');

            expect($right->isRight())->toBeTrue();
            expect($right->unwrap())->toBe('value');

            expect($left->isLeft())->toBeTrue();
            expect($left->unwrapLeft())->toBeNull();

            expect($leftCustom->isLeft())->toBeTrue();
            expect($leftCustom->unwrapLeft())->toBe('custom error');
        });

        test('creates Either from callable using tryCatch', function (): void {
            $success = Either::tryCatch(fn (): string => 'result');
            $failure = Either::tryCatch(function (): void {
                throw SimulatedException::error();
            });

            expect($success->isRight())->toBeTrue();
            expect($success->unwrap())->toBe('result');

            expect($failure->isLeft())->toBeTrue();
            expect($failure->unwrapLeft())->toBeInstanceOf(RuntimeException::class);
            expect($failure->unwrapLeft()->getMessage())->toBe('error');
        });

        test('creates Either from condition using cond', function (): void {
            $right = Either::cond(true, 'success', 'failure');
            $left = Either::cond(false, 'success', 'failure');

            expect($right->isRight())->toBeTrue();
            expect($right->unwrap())->toBe('success');

            expect($left->isLeft())->toBeTrue();
            expect($left->unwrapLeft())->toBe('failure');
        });

        test('transforms Right value with map', function (): void {
            $right = new Right(10);
            $left = new Left('error');

            $mapped = $right->map(fn (int $x): int => $x * 2);
            expect($mapped->isRight())->toBeTrue();
            expect($mapped->unwrap())->toBe(20);

            $leftMapped = $left->map(fn ($x): int|float => $x * 2);
            expect($leftMapped->isLeft())->toBeTrue();
            expect($leftMapped->unwrapLeft())->toBe('error');
        });

        test('transforms Left value with mapLeft', function (): void {
            $left = new Left('error');
            $right = new Right(10);

            $mapped = $left->mapLeft(fn (string $e) => mb_strtoupper($e));
            expect($mapped->isLeft())->toBeTrue();
            expect($mapped->unwrapLeft())->toBe('ERROR');

            $rightMapped = $right->mapLeft(fn ($e) => mb_strtoupper($e));
            expect($rightMapped->isRight())->toBeTrue();
            expect($rightMapped->unwrap())->toBe(10);
        });

        test('transforms both Left and Right values with bimap', function (): void {
            $right = new Right(5);
            $left = new Left('error');

            $rightMapped = $right->bimap(
                fn (string $e) => mb_strtoupper($e),
                fn (int $x): int => $x * 2,
            );
            expect($rightMapped->isRight())->toBeTrue();
            expect($rightMapped->unwrap())->toBe(10);

            $leftMapped = $left->bimap(
                fn (string $e) => mb_strtoupper($e),
                fn (int $x): int => $x * 2,
            );
            expect($leftMapped->isLeft())->toBeTrue();
            expect($leftMapped->unwrapLeft())->toBe('ERROR');
        });

        test('chains Either-returning operations with flatMap', function (): void {
            $right = new Right(5);
            $left = new Left('error');

            $result = $right->flatMap(fn (int $x): Right => new Right($x * 2));
            expect($result->isRight())->toBeTrue();
            expect($result->unwrap())->toBe(10);

            $toLeft = $right->flatMap(fn (int $x): Left => new Left('failed'));
            expect($toLeft->isLeft())->toBeTrue();
            expect($toLeft->unwrapLeft())->toBe('failed');

            $leftResult = $left->flatMap(fn ($x): Right => new Right($x));
            expect($leftResult->isLeft())->toBeTrue();
            expect($leftResult->unwrapLeft())->toBe('error');
        });

        test('chains Either-returning operations with andThen alias', function (): void {
            $right = new Right(3);

            $result = $right->andThen(fn (int $x): Right => new Right($x + 2));
            expect($result->isRight())->toBeTrue();
            expect($result->unwrap())->toBe(5);
        });

        test('executes side effects with forAll on Right', function (): void {
            $called = 0;
            $value = null;
            $right = new Right(42);

            $result = $right->forAll(function (int $x) use (&$called, &$value): void {
                ++$called;
                $value = $x;
            });

            expect($result)->toBe($right);
            expect($called)->toBe(1);
            expect($value)->toBe(42);

            $left = new Left('error');
            $leftCalled = 0;
            $left->forAll(function () use (&$leftCalled): void {
                ++$leftCalled;
            });
            expect($leftCalled)->toBe(0);
        });

        test('executes side effects with forLeft on Left', function (): void {
            $called = 0;
            $value = null;
            $left = new Left('error');

            $result = $left->forLeft(function (string $e) use (&$called, &$value): void {
                ++$called;
                $value = $e;
            });

            expect($result)->toBe($left);
            expect($called)->toBe(1);
            expect($value)->toBe('error');

            $right = new Right(42);
            $rightCalled = 0;
            $right->forLeft(function () use (&$rightCalled): void {
                ++$rightCalled;
            });
            expect($rightCalled)->toBe(0);
        });

        test('inspects Right value for debugging', function (): void {
            $called = 0;
            $value = null;
            $right = new Right('data');

            $result = $right->inspect(function (string $x) use (&$called, &$value): void {
                ++$called;
                $value = $x;
            });

            expect($result)->toBe($right);
            expect($called)->toBe(1);
            expect($value)->toBe('data');

            $left = new Left('error');
            $leftCalled = 0;
            $left->inspect(function () use (&$leftCalled): void {
                ++$leftCalled;
            });
            expect($leftCalled)->toBe(0);
        });

        test('filters Right value based on predicate', function (): void {
            $right = new Right(10);

            $passes = $right->filter(fn (int $x): bool => $x > 5, 'too small');
            expect($passes->isRight())->toBeTrue();
            expect($passes->unwrap())->toBe(10);

            $fails = $right->filter(fn (int $x): bool => $x > 20, 'too small');
            expect($fails->isLeft())->toBeTrue();
            expect($fails->unwrapLeft())->toBe('too small');

            $left = new Left('error');
            $leftFiltered = $left->filter(fn ($x): true => true, 'never');
            expect($leftFiltered->isLeft())->toBeTrue();
            expect($leftFiltered->unwrapLeft())->toBe('error');
        });

        test('pattern matches with match', function (): void {
            $right = new Right('success');
            $left = new Left('error');

            $rightResult = $right->match(
                fn (string $e): string => 'Error: ' . $e,
                fn (string $v): string => 'Success: ' . $v,
            );
            expect($rightResult)->toBe('Success: success');

            $leftResult = $left->match(
                fn (string $e): string => 'Error: ' . $e,
                fn (string $v): string => 'Success: ' . $v,
            );
            expect($leftResult)->toBe('Error: error');
        });

        test('folds Either into single value', function (): void {
            $right = new Right(10);
            $left = new Left(5);

            $rightFolded = $right->fold(
                fn (int $l): int => $l * 2,
                fn (int $r): int => $r * 3,
            );
            expect($rightFolded)->toBe(30);

            $leftFolded = $left->fold(
                fn (int $l): int => $l * 2,
                fn (int $r): int => $r * 3,
            );
            expect($leftFolded)->toBe(10);
        });

        test('swaps Left and Right values', function (): void {
            $right = new Right('success');
            $left = new Left('error');

            $swappedRight = $right->swap();
            expect($swappedRight->isLeft())->toBeTrue();
            expect($swappedRight->unwrapLeft())->toBe('success');

            $swappedLeft = $left->swap();
            expect($swappedLeft->isRight())->toBeTrue();
            expect($swappedLeft->unwrap())->toBe('error');
        });

        test('returns Right value with unwrapOr fallback', function (): void {
            $right = new Right('value');
            $left = new Left('error');

            expect($right->unwrapOr('default'))->toBe('value');
            expect($left->unwrapOr('default'))->toBe('default');
        });

        test('returns Right value with unwrapOrElse computed fallback', function (): void {
            $right = new Right(10);
            $left = new Left('error');

            expect($right->unwrapOrElse(fn ($e): int => 0))->toBe(10);
            expect($left->unwrapOrElse(fn (string $e): int => mb_strlen($e)))->toBe(5);
        });

        test('checks if Right contains specific value', function (): void {
            $right = new Right('value');
            $left = new Left('error');

            expect($right->contains('value'))->toBeTrue();
            expect($right->contains('other'))->toBeFalse();
            expect($left->contains('value'))->toBeFalse();
        });

        test('checks if Left contains specific value', function (): void {
            $left = new Left('error');
            $right = new Right('value');

            expect($left->containsLeft('error'))->toBeTrue();
            expect($left->containsLeft('other'))->toBeFalse();
            expect($right->containsLeft('error'))->toBeFalse();
        });

        test('checks Right state with predicate using isRightAnd', function (): void {
            $right = new Right(10);
            $left = new Left('error');

            expect($right->isRightAnd(fn (int $x): bool => $x > 5))->toBeTrue();
            expect($right->isRightAnd(fn (int $x): bool => $x > 20))->toBeFalse();
            expect($left->isRightAnd(fn ($x): true => true))->toBeFalse();
        });

        test('checks Left state with predicate using isLeftAnd', function (): void {
            $left = new Left('error');
            $right = new Right(10);

            expect($left->isLeftAnd(fn (string $e): bool => str_starts_with($e, 'err')))->toBeTrue();
            expect($left->isLeftAnd(fn (string $e): bool => str_starts_with($e, 'warn')))->toBeFalse();
            expect($right->isLeftAnd(fn ($e): true => true))->toBeFalse();
        });

        test('flattens nested Either values', function (): void {
            $nested = new Right(
                new Right(42),
            );
            $flattened = $nested->flatten();

            expect($flattened->isRight())->toBeTrue();
            expect($flattened->unwrap())->toBe(42);

            $left = new Left('error');
            $leftFlattened = $left->flatten();
            expect($leftFlattened->isLeft())->toBeTrue();
            expect($leftFlattened->unwrapLeft())->toBe('error');

            $rightNonEither = new Right('value');
            $nonFlattened = $rightNonEither->flatten();
            expect($nonFlattened->isRight())->toBeTrue();
            expect($nonFlattened->unwrap())->toBe('value');
        });
    });

    describe('Sad Paths', function (): void {
        test('unwrap throws exception on Left', function (): void {
            $left = new Left('error');

            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('Cannot unwrap Right value from Left.');
            $left->unwrap();
        });

        test('unwrapLeft throws exception on Right', function (): void {
            $right = new Right('value');

            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('Cannot unwrap Left value from Right.');
            $right->unwrapLeft();
        });

        test('flatMap throws exception when callable does not return Either', function (): void {
            $right = new Right(10);

            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('Callables passed to flatMap() must return an Either. Maybe you should use map() instead?');
            $right->flatMap(fn (int $x): int => $x * 2);
        });
    });

    describe('Edge Cases', function (): void {
        test('clones Right with object value', function (): void {
            $obj = new stdClass();
            $obj->value = 'original';

            $right = new Right($obj);
            $cloned = $right->cloned();

            $clonedObj = $cloned->unwrap();
            $clonedObj->value = 'modified';

            expect($obj->value)->toBe('original');
            expect($clonedObj->value)->toBe('modified');
        });

        test('clones Right with scalar value', function (): void {
            $right = new Right(42);
            $cloned = $right->cloned();

            expect($cloned->unwrap())->toBe(42);
            expect($cloned)->not->toBe($right);
        });

        test('clones Left with object value', function (): void {
            $obj = new stdClass();
            $obj->error = 'original';

            $left = new Left($obj);
            $cloned = $left->cloned();

            $clonedObj = $cloned->unwrapLeft();
            $clonedObj->error = 'modified';

            expect($obj->error)->toBe('original');
            expect($clonedObj->error)->toBe('modified');
        });

        test('clones Left with scalar value', function (): void {
            $left = new Left('error');
            $cloned = $left->cloned();

            expect($cloned->unwrapLeft())->toBe('error');
            expect($cloned)->not->toBe($left);
        });

        test('Right iterator yields single value', function (): void {
            $right = new Right('value');
            $values = [];

            foreach ($right as $value) {
                $values[] = $value;
            }

            expect($values)->toBe(['value']);
        });

        test('Left iterator yields no values', function (): void {
            $left = new Left('error');
            $values = [];

            foreach ($left as $value) {
                $values[] = $value;
            }

            expect($values)->toBe([]);
        });

        test('handles deeply nested Either structures', function (): void {
            $deep = new Right(
                new Right(
                    new Right(42),
                ),
            );

            $once = $deep->flatten();
            expect($once->isRight())->toBeTrue();
            expect($once->unwrap())->toBeInstanceOf(Right::class);

            $twice = $once->flatten();
            expect($twice->isRight())->toBeTrue();
            expect($twice->unwrap())->toBe(42);
        });

        test('filter converts Right to Left on predicate failure', function (): void {
            $right = new Right(10);

            $result = $right
                ->filter(fn (int $x): bool => $x > 5, 'too small')
                ->filter(fn (int $x): bool => $x < 20, 'too large');

            expect($result->isRight())->toBeTrue();

            $failed = $right->filter(fn (int $x): bool => $x > 20, 'too small');
            expect($failed->isLeft())->toBeTrue();
            expect($failed->unwrapLeft())->toBe('too small');
        });

        test('chained transformations preserve Either type', function (): void {
            $result = new Right(5)
                ->map(fn (int $x): int => $x * 2)
                ->flatMap(fn (int $x): Right => new Right($x + 3))
                ->map(fn (int $x): int => $x - 1)
                ->filter(fn (int $x): bool => $x > 10, 'too small');

            expect($result->isRight())->toBeTrue();
            expect($result->unwrap())->toBe(12);

            $leftChain = new Left('initial error')
                ->map(fn ($x): int|float => $x * 2)
                ->flatMap(fn ($x): Right => new Right($x))
                ->mapLeft(fn (string $e) => mb_strtoupper($e));

            expect($leftChain->isLeft())->toBeTrue();
            expect($leftChain->unwrapLeft())->toBe('INITIAL ERROR');
        });

        test('swap twice returns original Either', function (): void {
            $right = new Right('value');
            $left = new Left('error');

            $rightSwapped = $right->swap()->swap();
            expect($rightSwapped->isRight())->toBeTrue();
            expect($rightSwapped->unwrap())->toBe('value');

            $leftSwapped = $left->swap()->swap();
            expect($leftSwapped->isLeft())->toBeTrue();
            expect($leftSwapped->unwrapLeft())->toBe('error');
        });

        test('handles null and empty values correctly', function (): void {
            $rightNull = new Right(null);
            $leftNull = new Left(null);
            $rightEmpty = new Right('');
            $leftEmpty = new Left('');

            expect($rightNull->unwrap())->toBeNull();
            expect($leftNull->unwrapLeft())->toBeNull();
            expect($rightEmpty->unwrap())->toBe('');
            expect($leftEmpty->unwrapLeft())->toBe('');

            expect($rightNull->contains(null))->toBeTrue();
            expect($leftNull->containsLeft(null))->toBeTrue();
            expect($rightEmpty->contains(''))->toBeTrue();
            expect($leftEmpty->containsLeft(''))->toBeTrue();
        });

        test('handles mixed type transformations', function (): void {
            $result = new Right(5)
                ->map(fn (int $x): string => (string) $x)
                ->map(fn (string $s): string => str_repeat($s, 2))
                ->map(fn (string $s): int => (int) $s);

            expect($result->isRight())->toBeTrue();
            expect($result->unwrap())->toBe(55);
        });
    });

    describe('Regressions', function (): void {
        test('Left operations never affect Right side', function (): void {
            $original = new Left('error');

            $result = $original
                ->mapLeft(fn (string $e) => mb_strtoupper($e))
                ->map(fn ($x): string => 'should never execute')
                ->flatMap(fn ($x): Right => new Right('should never execute'))
                ->filter(fn ($x): false => false, 'irrelevant');

            expect($result->isLeft())->toBeTrue();
            expect($result->unwrapLeft())->toBe('ERROR');
        });

        test('Right operations never affect Left side', function (): void {
            $original = new Right(10);

            $result = $original
                ->map(fn (int $x): int => $x * 2)
                ->mapLeft(fn ($e): string => 'should never execute')
                ->forLeft(fn ($e) => throw TestException::shouldNotBeCalled())
                ->filter(fn (int $x): bool => $x === 20, 'would fail');

            expect($result->isRight())->toBeTrue();
            expect($result->unwrap())->toBe(20);
        });

        test('ensures type consistency through transformations', function (): void {
            $right = new Right(5);
            $left = new Left('error');

            // Right remains Right through compatible operations
            $rightResult = $right
                ->map(fn (int $x): int => $x)
                ->flatMap(fn (int $x): Right => new Right($x))
                ->filter(fn (int $x): true => true, 'fail');

            expect($rightResult->isRight())->toBeTrue();

            // Left remains Left through compatible operations
            $leftResult = $left
                ->map(fn ($x): mixed => $x)
                ->flatMap(fn ($x): Right => new Right($x))
                ->mapLeft(fn (string $e): string => $e);

            expect($leftResult->isLeft())->toBeTrue();
        });

        test('contains and containsLeft use strict equality', function (): void {
            $right = new Right(0);
            $left = new Left(false);

            expect($right->contains(0))->toBeTrue();
            expect($right->contains(false))->toBeFalse();
            expect($right->contains(''))->toBeFalse();

            expect($left->containsLeft(false))->toBeTrue();
            expect($left->containsLeft(0))->toBeFalse();
            expect($left->containsLeft(''))->toBeFalse();
        });

        test('isRightAnd and isLeftAnd handle falsy predicate returns correctly', function (): void {
            $right = new Right(0);
            $left = new Left(0);

            // Predicate returns 0 (falsy but not false)
            expect($right->isRightAnd(fn (int $x): int => $x))->toBeFalse();
            expect($left->isLeftAnd(fn (int $e): int => $e))->toBeFalse();

            // Predicate returns true
            expect($right->isRightAnd(fn (int $x): true => true))->toBeTrue();
            expect($left->isLeftAnd(fn (int $e): true => true))->toBeTrue();

            // Predicate returns 1 (truthy)
            expect($right->isRightAnd(fn (int $x): int => 1))->toBeTrue();
            expect($left->isLeftAnd(fn (int $e): int => 1))->toBeTrue();
        });

        test('filter preserves strict boolean comparison', function (): void {
            $right = new Right('value');

            // Predicate returns truthy but not exactly true
            $result1 = $right->filter(fn (string $x): int => 1, 'fail');
            expect($result1->isLeft())->toBeTrue();

            // Predicate returns exactly true
            $result2 = $right->filter(fn (string $x): true => true, 'fail');
            expect($result2->isRight())->toBeTrue();

            // Predicate returns exactly false
            $result3 = $right->filter(fn (string $x): false => false, 'fail');
            expect($result3->isLeft())->toBeTrue();
        });

        test('unwrapOrElse receives correct Left value', function (): void {
            $left = new Left('custom error');
            $receivedError = null;

            $result = $left->unwrapOrElse(function ($e) use (&$receivedError): string {
                $receivedError = $e;

                return 'fallback';
            });

            expect($result)->toBe('fallback');
            expect($receivedError)->toBe('custom error');
        });

        test('flatMap properly chains with mixed Left and Right results', function (): void {
            $result = new Right(5)
                ->flatMap(fn (int $x): Right|Left => $x > 3 ? new Right($x * 2) : new Left('too small'))
                ->flatMap(fn (int $x): Left|Right => $x > 20 ? new Left('too large') : new Right($x + 5));

            expect($result->isRight())->toBeTrue();
            expect($result->unwrap())->toBe(15);

            $failure = new Right(2)
                ->flatMap(fn (int $x): Right|Left => $x > 3 ? new Right($x * 2) : new Left('too small'))
                ->flatMap(fn (int $x): Right => new Right($x + 5));

            expect($failure->isLeft())->toBeTrue();
            expect($failure->unwrapLeft())->toBe('too small');
        });
    });

    describe('Rust-style Operators', function (): void {
        describe('Happy Paths', function (): void {
            test('and returns other Either when this Either is Right', function (): void {
                $right1 = new Right(10);
                $right2 = new Right(20);

                $result = $right1->and($right2);

                expect($result->isRight())->toBeTrue();
                expect($result->unwrap())->toBe(20);
            });

            test('or returns this Either when this Either is Right', function (): void {
                $right = new Right(10);
                $fallback = new Right(20);

                $result = $right->or($fallback);

                expect($result->isRight())->toBeTrue();
                expect($result->unwrap())->toBe(10);
            });

            test('xor returns Right when exactly one Either is Right', function (): void {
                $right = new Right(10);
                $left = new Left('error');

                $result1 = $right->xor($left);
                expect($result1->isRight())->toBeTrue();
                expect($result1->unwrap())->toBe(10);

                $result2 = $left->xor($right);
                expect($result2->isRight())->toBeTrue();
                expect($result2->unwrap())->toBe(10);
            });
        });

        describe('Sad Paths', function (): void {
            test('and returns this Left when this Either is Left', function (): void {
                $left = new Left('error');
                $right = new Right(10);

                $result = $left->and($right);

                expect($result->isLeft())->toBeTrue();
                expect($result->unwrapLeft())->toBe('error');
            });

            test('or returns other Either when this Either is Left', function (): void {
                $left = new Left('primary error');
                $fallback = new Right(10);

                $result = $left->or($fallback);

                expect($result->isRight())->toBeTrue();
                expect($result->unwrap())->toBe(10);
            });

            test('or returns other Left when both are Left', function (): void {
                $left1 = new Left('error 1');
                $left2 = new Left('error 2');

                $result = $left1->or($left2);

                expect($result->isLeft())->toBeTrue();
                expect($result->unwrapLeft())->toBe('error 2');
            });
        });

        describe('Edge Cases', function (): void {
            test('xor returns Left when both Eithers are Right', function (): void {
                $right1 = new Right(10);
                $right2 = new Right(20);

                $result = $right1->xor($right2);

                expect($result->isLeft())->toBeTrue();
                // When both are Right, should contain first Right's value wrapped in Left
                expect($result->unwrapLeft())->toBe(10);
            });

            test('xor returns Left when both Eithers are Left', function (): void {
                $left1 = new Left('error 1');
                $left2 = new Left('error 2');

                $result = $left1->xor($left2);

                expect($result->isLeft())->toBeTrue();
                expect($result->unwrapLeft())->toBe('error 1');
            });

            test('and short-circuits on Left without evaluating other', function (): void {
                $left = new Left('error');
                $called = false;

                $other = new Right('should not be accessed');
                $result = $left->and($other);

                expect($result->isLeft())->toBeTrue();
                expect($result->unwrapLeft())->toBe('error');
            });

            test('or short-circuits on Right without evaluating other', function (): void {
                $right = new Right(10);
                $called = false;

                $other = new Left('should not be accessed');
                $result = $right->or($other);

                expect($result->isRight())->toBeTrue();
                expect($result->unwrap())->toBe(10);
            });

            test('chaining multiple and operations', function (): void {
                $result = new Right(1)
                    ->and(
                        new Right(2)
                    )
                    ->and(
                        new Right(3)
                    )
                    ->and(
                        new Right(4)
                    );

                expect($result->isRight())->toBeTrue();
                expect($result->unwrap())->toBe(4);

                $failure = new Right(1)
                    ->and(
                        new Right(2)
                    )
                    ->and(
                        new Left('failed')
                    )
                    ->and(
                        new Right(4)
                    );

                expect($failure->isLeft())->toBeTrue();
                expect($failure->unwrapLeft())->toBe('failed');
            });

            test('chaining multiple or operations with fallbacks', function (): void {
                $result = new Left('error 1')
                    ->or(
                        new Left('error 2')
                    )
                    ->or(
                        new Right(10)
                    )
                    ->or(
                        new Right(20)
                    );

                expect($result->isRight())->toBeTrue();
                expect($result->unwrap())->toBe(10);

                $allLeft = new Left('error 1')
                    ->or(
                        new Left('error 2')
                    )
                    ->or(
                        new Left('error 3')
                    );

                expect($allLeft->isLeft())->toBeTrue();
                expect($allLeft->unwrapLeft())->toBe('error 3');
            });
        });

        describe('Regressions', function (): void {
            test('and correctly short-circuits without touching other Either', function (): void {
                $left = new Left('error');
                $right = new Right(42);

                $result = $left->and($right);

                expect($result)->toBe($left);
                expect($result->unwrapLeft())->toBe('error');
            });

            test('or correctly short-circuits without touching other Either', function (): void {
                $right = new Right(42);
                $left = new Left('error');

                $result = $right->or($left);

                expect($result)->toBe($right);
                expect($result->unwrap())->toBe(42);
            });

            test('xor preserves first Left when both are Left', function (): void {
                $left1 = new Left('first error');
                $left2 = new Left('second error');

                $result = $left1->xor($left2);

                expect($result->isLeft())->toBeTrue();
                expect($result->unwrapLeft())->toBe('first error');
            });
        });
    });

    describe('Map with Defaults', function (): void {
        describe('Happy Paths', function (): void {
            test('mapOr transforms Right value with function', function (): void {
                $right = new Right(10);

                $result = $right->mapOr(0, fn (int $x): int => $x * 2);

                expect($result)->toBe(20);
            });

            test('mapOrElse transforms Right value with function', function (): void {
                $right = new Right('hello');

                $result = $right->mapOrElse(
                    fn ($err): int => 0,
                    fn (string $val): int => mb_strlen($val),
                );

                expect($result)->toBe(5);
            });
        });

        describe('Sad Paths', function (): void {
            test('mapOr returns default when Either is Left', function (): void {
                $left = new Left('error');

                $result = $left->mapOr(42, fn ($x): int|float => $x * 2);

                expect($result)->toBe(42);
            });

            test('mapOrElse computes default from Left value', function (): void {
                $left = new Left('error message');

                $result = $left->mapOrElse(
                    fn (string $err): int => mb_strlen($err),
                    fn ($val): int => 999,
                );

                expect($result)->toBe(13);
            });
        });

        describe('Edge Cases', function (): void {
            test('mapOr does not call function on Left', function (): void {
                $left = new Left('error');
                $called = false;

                $result = $left->mapOr('default', function ($x) use (&$called) {
                    $called = true;

                    return $x;
                });

                expect($called)->toBeFalse();
                expect($result)->toBe('default');
            });

            test('mapOrElse does not call Right function on Left', function (): void {
                $left = new Left('error');
                $rightCalled = false;

                $result = $left->mapOrElse(
                    fn ($err): string => 'handled',
                    function ($val) use (&$rightCalled) {
                        $rightCalled = true;

                        return $val;
                    },
                );

                expect($rightCalled)->toBeFalse();
                expect($result)->toBe('handled');
            });

            test('mapOr with expensive default computation', function (): void {
                $right = new Right(5);

                $result = $right->mapOr(expensiveComputation(), fn (int $x): int => $x * 2);

                expect($result)->toBe(10);
            });

            test('mapOrElse with lazy default avoids expensive computation', function (): void {
                $right = new Right(5);
                $defaultCalled = false;

                $result = $right->mapOrElse(
                    function () use (&$defaultCalled): int {
                        $defaultCalled = true;

                        return 999;
                    },
                    fn (int $x): int => $x * 2,
                );

                expect($defaultCalled)->toBeFalse();
                expect($result)->toBe(10);
            });

            test('mapOrElse receives correct Left value in default function', function (): void {
                $left = new Left(['code' => 404, 'message' => 'Not Found']);
                $receivedError = null;

                $result = $left->mapOrElse(
                    function ($err) use (&$receivedError): string {
                        $receivedError = $err;

                        return 'handled';
                    },
                    fn ($val): mixed => $val,
                );

                expect($receivedError)->toBe(['code' => 404, 'message' => 'Not Found']);
                expect($result)->toBe('handled');
            });
        });

        describe('Regressions', function (): void {
            test('mapOr does not evaluate function on Left', function (): void {
                $left = new Left('error');
                $evaluated = false;

                $result = $left->mapOr(100, function ($x) use (&$evaluated): int|float {
                    $evaluated = true;

                    return $x * 2;
                });

                expect($evaluated)->toBeFalse();
                expect($result)->toBe(100);
            });

            test('mapOrElse correctly passes Left value to default function', function (): void {
                $left = new Left('specific error');
                $received = null;

                $left->mapOrElse(
                    function ($err) use (&$received): string {
                        $received = $err;

                        return 'default';
                    },
                    fn ($val): string => 'success',
                );

                expect($received)->toBe('specific error');
            });

            test('mapOrElse return types are consistent', function (): void {
                $right = new Right(10);
                $left = new Left('error');

                $rightResult = $right->mapOrElse(
                    fn ($err): string => 'error: '.$err,
                    fn (int $val): string => 'value: '.$val,
                );

                $leftResult = $left->mapOrElse(
                    fn ($err): string => 'error: '.$err,
                    fn (int $val): string => 'value: '.$val,
                );

                expect($rightResult)->toBeString();
                expect($leftResult)->toBeString();
                expect($rightResult)->toBe('value: 10');
                expect($leftResult)->toBe('error: error');
            });
        });
    });

    describe('Zip Operations', function (): void {
        describe('Happy Paths', function (): void {
            test('zip combines two Right Eithers into tuple', function (): void {
                $right1 = new Right(10);
                $right2 = new Right(20);

                $result = $right1->zip($right2);

                expect($result->isRight())->toBeTrue();
                expect($result->unwrap())->toBe([10, 20]);
            });

            test('zipWith combines two Right Eithers with function', function (): void {
                $right1 = new Right(10);
                $right2 = new Right(20);

                $result = $right1->zipWith($right2, fn (int $a, int $b): int => $a + $b);

                expect($result->isRight())->toBeTrue();
                expect($result->unwrap())->toBe(30);
            });

            test('unzip splits Either of tuple into tuple of Eithers', function (): void {
                $tupleEither = new Right([10, 20]);

                [$either1, $either2] = $tupleEither->unzip();

                expect($either1->isRight())->toBeTrue();
                expect($either1->unwrap())->toBe(10);
                expect($either2->isRight())->toBeTrue();
                expect($either2->unwrap())->toBe(20);
            });
        });

        describe('Sad Paths', function (): void {
            test('zip returns first Left when first Either is Left', function (): void {
                $left = new Left('error 1');
                $right = new Right(10);

                $result = $left->zip($right);

                expect($result->isLeft())->toBeTrue();
                expect($result->unwrapLeft())->toBe('error 1');
            });

            test('zip returns second Left when first is Right and second is Left', function (): void {
                $right = new Right(10);
                $left = new Left('error 2');

                $result = $right->zip($left);

                expect($result->isLeft())->toBeTrue();
                expect($result->unwrapLeft())->toBe('error 2');
            });

            test('zip returns first Left when both are Left', function (): void {
                $left1 = new Left('error 1');
                $left2 = new Left('error 2');

                $result = $left1->zip($left2);

                expect($result->isLeft())->toBeTrue();
                expect($result->unwrapLeft())->toBe('error 1');
            });

            test('zipWith returns first Left when first Either is Left', function (): void {
                $left = new Left('error 1');
                $right = new Right(10);

                $result = $left->zipWith($right, fn ($a, $b): float|int => $a + $b);

                expect($result->isLeft())->toBeTrue();
                expect($result->unwrapLeft())->toBe('error 1');
            });

            test('zipWith returns second Left when first is Right and second is Left', function (): void {
                $right = new Right(10);
                $left = new Left('error 2');

                $result = $right->zipWith($left, fn ($a, $b): int|float => $a + $b);

                expect($result->isLeft())->toBeTrue();
                expect($result->unwrapLeft())->toBe('error 2');
            });

            test('unzip throws exception when Right value is not a proper array', function (): void {
                $invalidEither = new Right('not an array');

                $this->expectException(RuntimeException::class);
                $this->expectExceptionMessage('Either::unzip expects Right([a,b]).');
                $invalidEither->unzip();
            });

            test('unzip throws exception when Right array does not have both indices', function (): void {
                $invalidEither = new Right([10]);

                $this->expectException(RuntimeException::class);
                $this->expectExceptionMessage('Either::unzip expects Right([a,b]).');
                $invalidEither->unzip();
            });
        });

        describe('Edge Cases', function (): void {
            test('unzip on Left returns two Left Eithers with same value', function (): void {
                $left = new Left('error');

                [$either1, $either2] = $left->unzip();

                expect($either1->isLeft())->toBeTrue();
                expect($either1->unwrapLeft())->toBe('error');
                expect($either2->isLeft())->toBeTrue();
                expect($either2->unwrapLeft())->toBe('error');
            });

            test('zip with different types', function (): void {
                $right1 = new Right('hello');
                $right2 = new Right(42);

                $result = $right1->zip($right2);

                expect($result->isRight())->toBeTrue();
                expect($result->unwrap())->toBe(['hello', 42]);
            });

            test('zipWith with string concatenation', function (): void {
                $right1 = new Right('Hello');
                $right2 = new Right('World');

                $result = $right1->zipWith($right2, fn (string $a, string $b): string => sprintf('%s %s', $a, $b));

                expect($result->isRight())->toBeTrue();
                expect($result->unwrap())->toBe('Hello World');
            });

            test('zipWith does not call function when either Either is Left', function (): void {
                $left = new Left('error');
                $right = new Right(10);
                $called = false;

                $result = $left->zipWith($right, function ($a, $b) use (&$called): float|int {
                    $called = true;

                    return $a + $b;
                });

                expect($called)->toBeFalse();
                expect($result->isLeft())->toBeTrue();
            });

            test('unzip with associative array indices', function (): void {
                $tupleEither = new Right([0 => 'first', 1 => 'second']);

                [$either1, $either2] = $tupleEither->unzip();

                expect($either1->unwrap())->toBe('first');
                expect($either2->unwrap())->toBe('second');
            });

            test('chaining zip operations', function (): void {
                $right1 = new Right(10);
                $right2 = new Right(20);
                $right3 = new Right(30);

                $result = $right1
                    ->zip($right2)
                    ->zipWith($right3, fn (array $tuple, int $third): array => [...$tuple, $third]);

                expect($result->isRight())->toBeTrue();
                expect($result->unwrap())->toBe([10, 20, 30]);
            });
        });

        describe('Regressions', function (): void {
            test('zip short-circuits on first Left without evaluating function', function (): void {
                $left = new Left('error');
                $right = new Right(10);

                $result = $left->zip($right);

                expect($result)->toBe($left);
            });

            test('zipWith short-circuits on first Left without calling function', function (): void {
                $left = new Left('error');
                $right = new Right(10);
                $functionCalled = false;

                $result = $left->zipWith($right, function ($a, $b) use (&$functionCalled): float|int {
                    $functionCalled = true;

                    return $a + $b;
                });

                expect($functionCalled)->toBeFalse();
                expect($result->isLeft())->toBeTrue();
            });

            test('unzip preserves Left to both sides', function (): void {
                $left = new Left('shared error');

                [$either1, $either2] = $left->unzip();

                expect($either1->unwrapLeft())->toBe('shared error');
                expect($either2->unwrapLeft())->toBe('shared error');
            });
        });
    });

    describe('Expect Method', function (): void {
        describe('Happy Paths', function (): void {
            test('expect returns Right value when Either is Right', function (): void {
                $right = new Right(42);

                $result = $right->expect('Should be Right');

                expect($result)->toBe(42);
            });

            test('expect with different value types', function (): void {
                $rightString = new Right('value');
                $rightArray = new Right([1, 2, 3]);
                $rightObject = new Right((object) ['key' => 'value']);

                expect($rightString->expect('msg'))->toBe('value');
                expect($rightArray->expect('msg'))->toBe([1, 2, 3]);
                expect($rightObject->expect('msg'))->toEqual((object) ['key' => 'value']);
            });
        });

        describe('Sad Paths', function (): void {
            test('expect throws RuntimeException with custom message when Either is Left', function (): void {
                $left = new Left('error');

                $this->expectException(RuntimeException::class);
                $this->expectExceptionMessage('Expected valid configuration');
                $left->expect('Expected valid configuration');
            });

            test('expect throws with different custom messages', function (): void {
                $left = new Left('error');

                try {
                    $left->expect('Custom error message');
                    expect(true)->toBeFalse(); // Should not reach here
                } catch (RuntimeException $runtimeException) {
                    expect($runtimeException->getMessage())->toBe('Custom error message');
                }
            });
        });

        describe('Edge Cases', function (): void {
            test('expect with empty message', function (): void {
                $left = new Left('error');

                try {
                    $left->expect('');
                    expect(true)->toBeFalse(); // Should not reach here
                } catch (RuntimeException $runtimeException) {
                    expect($runtimeException->getMessage())->toBe('');
                }
            });

            test('expect in method chains', function (): void {
                $result = new Right(10)
                    ->map(fn (int $x): int => $x * 2)
                    ->expect('Should succeed');

                expect($result)->toBe(20);
            });
        });

        describe('Regressions', function (): void {
            test('expect does not modify Right value', function (): void {
                $right = new Right(['mutable' => 'value']);
                $result = $right->expect('msg');

                expect($result)->toBe(['mutable' => 'value']);
            });

            test('expect message is exact match', function (): void {
                $left = new Left('internal error');
                $message = 'Operation failed: expected valid input';

                try {
                    $left->expect($message);
                    expect(true)->toBeFalse(); // Should not reach here
                } catch (RuntimeException $runtimeException) {
                    expect($runtimeException->getMessage())->toBe($message);
                }
            });
        });
    });

    describe('Conversion Methods', function (): void {
        describe('Happy Paths', function (): void {
            test('toOption converts Right to Some', function (): void {
                $right = new Right(42);

                $option = $right->toOption();

                expect($option->isSome())->toBeTrue();
                expect($option->unwrap())->toBe(42);
            });

            test('toOption converts Left to None', function (): void {
                $left = new Left('error');

                $option = $left->toOption();

                expect($option->isNone())->toBeTrue();
            });

            test('toResult converts Right to Ok', function (): void {
                $right = new Right('success');

                $result = $right->toResult();

                expect($result->isOk())->toBeTrue();
                expect($result->unwrap())->toBe('success');
            });

            test('toResult converts Left to Err', function (): void {
                $left = new Left('error message');

                $result = $left->toResult();

                expect($result->isErr())->toBeTrue();
                expect($result->unwrapErr())->toBe('error message');
            });
        });

        describe('Sad Paths', function (): void {
            test('toOption discards Left error information', function (): void {
                $left = new Left('important error');

                $option = $left->toOption();

                expect($option->isNone())->toBeTrue();
            });
        });

        describe('Edge Cases', function (): void {
            test('toOption preserves Right value type', function (): void {
                $rightArray = new Right([1, 2, 3]);
                $rightObject = new Right((object) ['key' => 'value']);

                $optionArray = $rightArray->toOption();
                $optionObject = $rightObject->toOption();

                expect($optionArray->unwrap())->toBe([1, 2, 3]);
                expect($optionObject->unwrap())->toEqual((object) ['key' => 'value']);
            });

            test('toResult preserves value types', function (): void {
                $right = new Right(['data' => 'value']);
                $left = new Left(['code' => 404]);

                $okResult = $right->toResult();
                $errResult = $left->toResult();

                expect($okResult->unwrap())->toBe(['data' => 'value']);
                expect($errResult->unwrapErr())->toBe(['code' => 404]);
            });

            test('chaining conversions with transformations', function (): void {
                $result = new Right(10)
                    ->map(fn (int $x): int => $x * 2)
                    ->toOption()
                    ->map(fn (int $x): int => $x + 5);

                expect($result->isSome())->toBeTrue();
                expect($result->unwrap())->toBe(25);
            });
        });

        describe('Regressions', function (): void {
            test('toOption None cannot be unwrapped', function (): void {
                $left = new Left('error');
                $option = $left->toOption();

                $this->expectException(RuntimeException::class);
                $option->unwrap();
            });

            test('toResult Err contains original Left value', function (): void {
                $errorData = ['code' => 500, 'message' => 'Server error'];
                $left = new Left($errorData);

                $result = $left->toResult();

                expect($result->unwrapErr())->toBe($errorData);
            });
        });
    });

    describe('Collection Operations', function (): void {
        describe('Happy Paths', function (): void {
            test('sequence transforms array of all Right Eithers to Right of array', function (): void {
                $eithers = [
                    new Right(1),
                    new Right(2),
                    new Right(3),
                ];

                $result = Either::sequence($eithers);

                expect($result->isRight())->toBeTrue();
                expect($result->unwrap())->toBe([1, 2, 3]);
            });

            test('traverse maps and sequences array items', function (): void {
                $items = [1, 2, 3];

                $result = Either::traverse($items, fn (int $x): Right => new Right($x * 2));

                expect($result->isRight())->toBeTrue();
                expect($result->unwrap())->toBe([2, 4, 6]);
            });

            test('sequence with empty array', function (): void {
                $result = Either::sequence([]);

                expect($result->isRight())->toBeTrue();
                expect($result->unwrap())->toBe([]);
            });

            test('traverse with empty array', function (): void {
                $result = Either::traverse([], fn ($x): Right => new Right($x));

                expect($result->isRight())->toBeTrue();
                expect($result->unwrap())->toBe([]);
            });
        });

        describe('Sad Paths', function (): void {
            test('sequence returns first Left when any Either is Left', function (): void {
                $eithers = [
                    new Right(1),
                    new Left('error at 2'),
                    new Right(3),
                ];

                $result = Either::sequence($eithers);

                expect($result->isLeft())->toBeTrue();
                expect($result->unwrapLeft())->toBe('error at 2');
            });

            test('sequence returns first Left when multiple Eithers are Left', function (): void {
                $eithers = [
                    new Right(1),
                    new Left('first error'),
                    new Left('second error'),
                ];

                $result = Either::sequence($eithers);

                expect($result->isLeft())->toBeTrue();
                expect($result->unwrapLeft())->toBe('first error');
            });

            test('traverse returns first Left on failure', function (): void {
                $items = [1, 2, 3, 4];

                $result = Either::traverse($items, fn(int $x): Left|Right => $x === 3 ? new Left('failed at 3') : new Right($x * 2));

                expect($result->isLeft())->toBeTrue();
                expect($result->unwrapLeft())->toBe('failed at 3');
            });
        });

        describe('Edge Cases', function (): void {
            test('sequence with single Either', function (): void {
                $right = Either::sequence([new Right(42)]);
                $left = Either::sequence([new Left('error')]);

                expect($right->isRight())->toBeTrue();
                expect($right->unwrap())->toBe([42]);

                expect($left->isLeft())->toBeTrue();
                expect($left->unwrapLeft())->toBe('error');
            });

            test('traverse with validation logic', function (): void {
                $emails = ['valid@test.com', 'invalid', 'another@test.com'];

                $result = Either::traverse($emails, fn(string $email): Right|Left => str_contains($email, '@')
                    ? new Right($email)
                    : new Left('Invalid email: ' . $email));

                expect($result->isLeft())->toBeTrue();
                expect($result->unwrapLeft())->toBe('Invalid email: invalid');
            });

            test('sequence preserves array indices', function (): void {
                $eithers = [
                    new Right('a'),
                    new Right('b'),
                    new Right('c'),
                ];

                $result = Either::sequence($eithers);

                expect($result->unwrap())->toBe(['a', 'b', 'c']);
            });

            test('traverse with complex transformations', function (): void {
                $users = [
                    ['id' => 1, 'name' => 'Alice'],
                    ['id' => 2, 'name' => 'Bob'],
                ];

                $result = Either::traverse($users, fn(array $user): Right => isset($user['id'], $user['name'])
                    ? new Right(['userId' => $user['id'], 'userName' => $user['name']])
                    : new Left('Invalid user data'));

                expect($result->isRight())->toBeTrue();
                expect($result->unwrap())->toBe([
                    ['userId' => 1, 'userName' => 'Alice'],
                    ['userId' => 2, 'userName' => 'Bob'],
                ]);
            });
        });

        describe('Regressions', function (): void {
            test('sequence stops at first Left without processing remaining items', function (): void {
                $processedCount = 0;
                $eithers = [
                    new Right(1),
                    new Left('error'),
                    new Right(3),
                ];

                $result = Either::sequence($eithers);

                expect($result->isLeft())->toBeTrue();
                expect($result->unwrapLeft())->toBe('error');
            });

            test('traverse stops at first Left without processing remaining items', function (): void {
                $processedCount = 0;
                $items = [1, 2, 3, 4];

                $result = Either::traverse($items, function (int $x) use (&$processedCount): Left|Right {
                    ++$processedCount;

                    return $x === 2 ? new Left('error at 2') : new Right($x);
                });

                expect($result->isLeft())->toBeTrue();
                expect($processedCount)->toBe(2);
            });

            test('sequence with all Left Eithers returns first', function (): void {
                $eithers = [
                    new Left('error 1'),
                    new Left('error 2'),
                    new Left('error 3'),
                ];

                $result = Either::sequence($eithers);

                expect($result->isLeft())->toBeTrue();
                expect($result->unwrapLeft())->toBe('error 1');
            });
        });
    });
});
