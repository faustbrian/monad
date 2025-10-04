<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Monad\Either\Left;
use Cline\Monad\Either\Right;

describe('Right', function (): void {
    describe('Happy Paths', function (): void {
        test('creates Right instance with value', function (): void {
            $right = new Right('success value');

            expect($right->isRight())->toBeTrue();
            expect($right->isLeft())->toBeFalse();
            expect($right->unwrap())->toBe('success value');
        });

        test('returns contained value with unwrapOr', function (): void {
            $right = new Right('value');

            expect($right->unwrapOr('default'))->toBe('value');
            expect($right->unwrapOr(null))->toBe('value');
        });

        test('returns contained value without calling unwrapOrElse', function (): void {
            $right = new Right('value');
            $called = false;

            $result = $right->unwrapOrElse(function ($e) use (&$called) {
                $called = true;

                return 'fallback';
            });

            expect($called)->toBeFalse();
            expect($result)->toBe('value');
        });

        test('applies map transformation', function (): void {
            $right = new Right(10);

            $result = $right->map(fn (int $x) => $x * 2);

            expect($result->isRight())->toBeTrue();
            expect($result->unwrap())->toBe(20);
        });

        test('ignores mapLeft transformation', function (): void {
            $right = new Right('value');
            $called = false;

            $result = $right->mapLeft(function ($e) use (&$called) {
                $called = true;

                return $e;
            });

            expect($called)->toBeFalse();
            expect($result->isRight())->toBeTrue();
            expect($result->unwrap())->toBe('value');
        });

        test('applies only right function in bimap', function (): void {
            $right = new Right(5);
            $rightCalled = false;
            $leftCalled = false;

            $result = $right->bimap(
                function ($l) use (&$leftCalled) {
                    $leftCalled = true;

                    return $l;
                },
                function (int $r) use (&$rightCalled) {
                    $rightCalled = true;

                    return $r * 2;
                },
            );

            expect($rightCalled)->toBeTrue();
            expect($leftCalled)->toBeFalse();
            expect($result->unwrap())->toBe(10);
        });

        test('executes flatMap operation', function (): void {
            $right = new Right(5);

            $result = $right->flatMap(fn (int $x) => new Right($x * 2));

            expect($result->isRight())->toBeTrue();
            expect($result->unwrap())->toBe(10);

            $toLeft = $right->flatMap(fn (int $x) => new Left('error'));
            expect($toLeft->isLeft())->toBeTrue();
            expect($toLeft->unwrapLeft())->toBe('error');
        });

        test('executes forAll side effect', function (): void {
            $right = new Right(42);
            $capturedValue = null;

            $result = $right->forAll(function (int $x) use (&$capturedValue): void {
                $capturedValue = $x;
            });

            expect($capturedValue)->toBe(42);
            expect($result)->toBe($right);
        });

        test('skips forLeft side effect', function (): void {
            $right = new Right('value');
            $called = false;

            $result = $right->forLeft(function ($e) use (&$called): void {
                $called = true;
            });

            expect($called)->toBeFalse();
            expect($result)->toBe($right);
        });

        test('executes inspect callback', function (): void {
            $right = new Right('data');
            $capturedValue = null;

            $result = $right->inspect(function (string $x) use (&$capturedValue): void {
                $capturedValue = $x;
            });

            expect($capturedValue)->toBe('data');
            expect($result)->toBe($right);
        });

        test('applies filter with predicate', function (): void {
            $right = new Right(10);

            $passes = $right->filter(fn (int $x) => $x > 5, 'too small');
            expect($passes->isRight())->toBeTrue();
            expect($passes->unwrap())->toBe(10);

            $fails = $right->filter(fn (int $x) => $x > 20, 'too small');
            expect($fails->isLeft())->toBeTrue();
            expect($fails->unwrapLeft())->toBe('too small');
        });

        test('executes right branch in match', function (): void {
            $right = new Right('success');
            $rightCalled = false;
            $leftCalled = false;

            $result = $right->match(
                function ($e) use (&$leftCalled) {
                    $leftCalled = true;

                    return "Left: {$e}";
                },
                function (string $r) use (&$rightCalled) {
                    $rightCalled = true;

                    return "Right: {$r}";
                },
            );

            expect($rightCalled)->toBeTrue();
            expect($leftCalled)->toBeFalse();
            expect($result)->toBe('Right: success');
        });

        test('executes right function in fold', function (): void {
            $right = new Right(10);
            $rightCalled = false;
            $leftCalled = false;

            $result = $right->fold(
                function ($l) use (&$leftCalled) {
                    $leftCalled = true;

                    return $l;
                },
                function (int $r) use (&$rightCalled) {
                    $rightCalled = true;

                    return $r * 2;
                },
            );

            expect($rightCalled)->toBeTrue();
            expect($leftCalled)->toBeFalse();
            expect($result)->toBe(20);
        });

        test('swaps to Left', function (): void {
            $right = new Right('success');

            $result = $right->swap();

            expect($result->isLeft())->toBeTrue();
            expect($result->unwrapLeft())->toBe('success');
        });

        test('checks if contains right value', function (): void {
            $right = new Right('value');

            expect($right->contains('value'))->toBeTrue();
            expect($right->contains('other'))->toBeFalse();
            expect($right->containsLeft('value'))->toBeFalse();
        });

        test('checks right state with predicate', function (): void {
            $right = new Right(10);

            expect($right->isRightAnd(fn (int $x) => $x > 5))->toBeTrue();
            expect($right->isRightAnd(fn (int $x) => $x > 20))->toBeFalse();
            expect($right->isLeftAnd(fn ($l) => true))->toBeFalse();
        });

        test('flattens nested Right value', function (): void {
            $nested = new Right(
                new Right('value')
            );

            $result = $nested->flatten();

            expect($result->isRight())->toBeTrue();
            expect($result->unwrap())->toBe('value');
        });

        test('flattens returns self when value is not Either', function (): void {
            $right = new Right('value');

            $result = $right->flatten();

            expect($result)->toBe($right);
            expect($result->unwrap())->toBe('value');
        });

        test('clones with object value', function (): void {
            $obj = new stdClass();
            $obj->data = 'original';

            $right = new Right($obj);
            $cloned = $right->cloned();

            $clonedObj = $cloned->unwrap();
            $clonedObj->data = 'modified';

            expect($obj->data)->toBe('original');
            expect($clonedObj->data)->toBe('modified');
        });

        test('clones with scalar value', function (): void {
            $right = new Right('value');

            $cloned = $right->cloned();

            expect($cloned->unwrap())->toBe('value');
            expect($cloned)->not->toBe($right);
        });

        test('returns iterator with single value', function (): void {
            $right = new Right('value');
            $values = [];

            foreach ($right as $value) {
                $values[] = $value;
            }

            expect($values)->toBe(['value']);
        });
    });

    describe('Sad Paths', function (): void {
        test('throws when trying to unwrap Left value', function (): void {
            $right = new Right('success');

            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('Cannot unwrap Left value from Right.');
            $right->unwrapLeft();
        });

        test('flatMap throws when callable does not return Either', function (): void {
            $right = new Right(10);

            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('Callables passed to flatMap() must return an Either. Maybe you should use map() instead?');
            $right->flatMap(fn (int $x) => $x * 2);
        });
    });

    describe('Edge Cases', function (): void {
        test('handles null as Right value', function (): void {
            $right = new Right(null);

            expect($right->isRight())->toBeTrue();
            expect($right->unwrap())->toBeNull();
            expect($right->contains(null))->toBeTrue();
        });

        test('handles arrays as Right value', function (): void {
            $data = ['key1' => 'value1', 'key2' => 'value2'];
            $right = new Right($data);

            expect($right->unwrap())->toBe($data);
            expect($right->contains($data))->toBeTrue();
        });

        test('chains multiple map transformations', function (): void {
            $right = new Right(5);

            $result = $right
                ->map(fn (int $x) => $x * 2)
                ->map(fn (int $x) => $x + 3)
                ->map(fn (int $x) => (string) $x);

            expect($result->unwrap())->toBe('13');
        });

        test('chains flatMap transformations', function (): void {
            $right = new Right(5);

            $result = $right
                ->flatMap(fn (int $x) => new Right($x * 2))
                ->flatMap(fn (int $x) => new Right($x + 1))
                ->flatMap(fn (int $x) => new Right((string) $x));

            expect($result->unwrap())->toBe('11');
        });

        test('preserves Right through operations that do not affect it', function (): void {
            $right = new Right('original');

            $result = $right
                ->mapLeft(fn ($e) => 'never')
                ->forLeft(fn ($e) => throw new RuntimeException('never'))
                ->map(fn (string $x) => mb_strtoupper($x));

            expect($result->isRight())->toBeTrue();
            expect($result->unwrap())->toBe('ORIGINAL');
        });

        test('handles callables as Right value', function (): void {
            $callable = fn () => 'result';
            $right = new Right($callable);

            expect($right->unwrap())->toBe($callable);
            expect($right->unwrap()())->toBe('result');
        });

        test('filter with strict true comparison', function (): void {
            $right = new Right(10);

            // Returns exactly true
            $strictTrue = $right->filter(fn (int $x) => true, 'fail');
            expect($strictTrue->isRight())->toBeTrue();

            // Returns truthy value (not exactly true)
            $truthyValue = $right->filter(fn (int $x) => 1, 'fail');
            expect($truthyValue->isLeft())->toBeTrue();

            // Returns false
            $falseValue = $right->filter(fn (int $x) => false, 'fail');
            expect($falseValue->isLeft())->toBeTrue();
        });

        test('deeply nested flatMap chain', function (): void {
            $result = new Right(1)
                ->flatMap(fn (int $x) => new Right($x + 1))
                ->flatMap(fn (int $x) => new Right($x * 2))
                ->flatMap(fn (int $x) => new Right($x - 1))
                ->flatMap(fn (int $x) => new Right($x * 3));

            expect($result->unwrap())->toBe(9);
        });

        test('flatMap chain that transitions to Left', function (): void {
            $result = new Right(5)
                ->flatMap(fn (int $x) => new Right($x * 2))
                ->flatMap(fn (int $x) => $x > 8 ? new Left('too large') : new Right($x))
                ->flatMap(fn (int $x) => new Right($x + 100)); // Never executed

            expect($result->isLeft())->toBeTrue();
            expect($result->unwrapLeft())->toBe('too large');
        });
    });

    describe('Regressions', function (): void {
        test('unwrapOr ignores default when Right', function (): void {
            $right = new Right('value');

            expect($right->unwrapOr('default'))->toBe('value');
            expect($right->unwrapOr(null))->toBe('value');
        });

        test('unwrapOrElse does not call callable when Right', function (): void {
            $right = new Right('value');
            $called = false;

            $result = $right->unwrapOrElse(function () use (&$called) {
                $called = true;

                return 'fallback';
            });

            expect($called)->toBeFalse();
            expect($result)->toBe('value');
        });

        test('forAll always returns same instance', function (): void {
            $right = new Right('value');

            $result = $right->forAll(fn ($x) => null);

            expect($result)->toBe($right);
        });

        test('inspect always returns same instance', function (): void {
            $right = new Right('value');

            $result = $right->inspect(fn ($x) => null);

            expect($result)->toBe($right);
        });

        test('bimap only transforms Right value', function (): void {
            $right = new Right(5);

            $result = $right->bimap(
                fn ($l) => throw new RuntimeException('Should not be called'),
                fn (int $r) => $r * 3,
            );

            expect($result->unwrap())->toBe(15);
        });

        test('match only calls right callback', function (): void {
            $right = new Right('value');

            $result = $right->match(
                fn ($l) => throw new RuntimeException('Should not be called'),
                fn (string $r) => "Result: {$r}",
            );

            expect($result)->toBe('Result: value');
        });

        test('fold only applies right function', function (): void {
            $right = new Right(10);

            $result = $right->fold(
                fn ($l) => throw new RuntimeException('Should not be called'),
                fn (int $r) => $r + 5,
            );

            expect($result)->toBe(15);
        });

        test('contains uses strict equality', function (): void {
            $right = new Right(0);

            expect($right->contains(0))->toBeTrue();
            expect($right->contains(false))->toBeFalse();
            expect($right->contains(''))->toBeFalse();
            expect($right->contains(null))->toBeFalse();
        });

        test('isRightAnd converts return value to boolean', function (): void {
            $right = new Right('value');

            expect($right->isRightAnd(fn ($r) => 0))->toBeFalse();
            expect($right->isRightAnd(fn ($r) => 1))->toBeTrue();
            expect($right->isRightAnd(fn ($r) => ''))->toBeFalse();
            expect($right->isRightAnd(fn ($r) => 'truthy'))->toBeTrue();
            expect($right->isRightAnd(fn ($r) => null))->toBeFalse();
        });

        test('flatten only affects nested Either values', function (): void {
            $nested = new Right(
                new Right(
                    new Right('value')
                )
            );

            $onceFlattened = $nested->flatten();
            expect($onceFlattened->unwrap())->toBeInstanceOf(Right::class);

            $twiceFlattened = $onceFlattened->flatten();
            expect($twiceFlattened->unwrap())->toBe('value');
        });

        test('flatMap properly propagates types', function (): void {
            $result = new Right(5)
                ->flatMap(fn (int $x) => new Right((string) $x))
                ->flatMap(fn (string $x) => new Right([$x]))
                ->flatMap(fn (array $x) => new Right(count($x)));

            expect($result->unwrap())->toBe(1);
        });
    });
});
