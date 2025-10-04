<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Monad\Option\Option;
use Cline\Monad\Result\Err;
use Cline\Monad\Result\Ok;

describe('Result', function (): void {
    describe('Happy Paths', function (): void {
        test('distinguishes Ok and Err states with correct value extraction', function (): void {
            $ok = new Ok('v');
            expect($ok->isOk())->toBeTrue();
            expect($ok->isErr())->toBeFalse();
            expect($ok->unwrap())->toBe('v');
            expect($ok->err()->isEmpty())->toBeTrue();
            expect($ok->ok()->get())->toBe('v');

            $err = new Err('e');
            expect($err->isErr())->toBeTrue();
            expect($err->isOk())->toBeFalse();
            expect($err->unwrapErr())->toBe('e');
            expect($err->ok()->isEmpty())->toBeTrue();
            expect($err->err()->get())->toBe('e');
        });

        test('converts Option to Result with error fallback using okOr and okOrElse', function (): void {
            $some = Option::fromNullable('x');
            $none = Option::fromNullable(null);

            $r1 = $some->okOr('e');
            expect($r1->isOk())->toBeTrue();
            expect($r1->unwrap())->toBe('x');

            $r2 = $none->okOr('e');
            expect($r2->isErr())->toBeTrue();
            expect($r2->unwrapErr())->toBe('e');

            $r3 = $none->okOrElse(fn () => 'gen');
            expect($r3->isErr())->toBeTrue();
            expect($r3->unwrapErr())->toBe('gen');
        });

        test('transforms Ok or Err values with map mapErr and andThen', function (): void {
            $ok = new Ok(2);
            $err = new Err('e');

            // map
            $m1 = $ok->map(fn (int $v): int => $v * 5);
            expect($m1->isOk())->toBeTrue();
            expect($m1->unwrap())->toBe(10);
            $m2 = $err->map(fn ($v) => 'never');
            expect($m2->isErr())->toBeTrue();
            expect($m2->unwrapErr())->toBe('e');

            // mapErr
            $e1 = $err->mapErr(fn (string $e): string => mb_strtoupper($e));
            expect($e1->isErr())->toBeTrue();
            expect($e1->unwrapErr())->toBe('E');
            $e2 = $ok->mapErr(fn ($e) => 'never');
            expect($e2->isOk())->toBeTrue();
            expect($e2->unwrap())->toBe(2);

            // andThen
            $a1 = $ok->andThen(fn (int $v) => new Ok($v + 3));
            expect($a1->isOk())->toBeTrue();
            expect($a1->unwrap())->toBe(5);

            $a2 = $ok->andThen(fn (int $v) => new Err('x'));
            expect($a2->isErr())->toBeTrue();
            expect($a2->unwrapErr())->toBe('x');

            $called = 0;
            $a3 = $err->andThen(function () use (&$called) {
                $called++;

                return new Ok('nope');
            });
            expect($a3->isErr())->toBeTrue();
            expect($a3->unwrapErr())->toBe('e');
            expect($called, 'andThen must not call on Err')->toBe(0);
        });

        test('maps Ok value with default fallback for Err using mapOr and mapOrElse', function (): void {
            $ok = new Ok(3);
            $err = new Err('x');

            expect($ok->mapOr(0, fn (int $v): int => $v * $v))->toBe(9);
            expect($err->mapOr(0, fn (int $v): int => $v * $v))->toBe(0);

            expect($ok->mapOrElse(fn (string $e): int => mb_strlen($e), fn (int $v): int => $v * $v))->toBe(9);
            expect($err->mapOrElse(fn (string $e): int => mb_strlen($e), fn (int $v): int => $v * $v))->toBe(1);
        });

        test('checks Result state with predicate using isOkAnd and isErrAnd', function (): void {
            $ok = new Ok(2);
            $err = new Err('e');

            expect($ok->isOkAnd(fn (int $v): bool => $v > 1))->toBeTrue();
            expect($ok->isOkAnd(fn (int $v): bool => $v > 3))->toBeFalse();
            expect($err->isOkAnd(fn ($v): bool => true))->toBeFalse();

            expect($err->isErrAnd(fn (string $e): bool => $e === 'e'))->toBeTrue();
            expect($err->isErrAnd(fn (string $e): bool => $e === 'x'))->toBeFalse();
            expect($ok->isErrAnd(fn ($e): bool => true))->toBeFalse();
        });

        test('flattens nested Result structures into single Result', function (): void {
            $nestedOk = new Ok(
                new Ok('v'),
            )->flatten();
            expect($nestedOk->isOk())->toBeTrue();
            expect($nestedOk->unwrap())->toBe('v');

            $nestedErr = new Ok(
                new Err('e'),
            )->flatten();
            expect($nestedErr->isErr())->toBeTrue();
            expect($nestedErr->unwrapErr())->toBe('e');

            $plainErr = new Err('x')->flatten();
            expect($plainErr->isErr())->toBeTrue();
            expect($plainErr->unwrapErr())->toBe('x');

            // Ok(non-Result) is returned unchanged
            $plainOk = new Ok('z')->flatten();
            expect($plainOk->isOk())->toBeTrue();
            expect($plainOk->unwrap())->toBe('z');
        });

        test('performs logical operations and unwraps values with fallbacks', function (): void {
            $ok = new Ok(1);
            $err = new Err('e');

            // and / or
            expect($ok->and(
                new Ok(2),
            )->isOk())->toBeTrue();
            expect($ok->and(
                new Ok(2),
            )->unwrap())->toBe(2);
            expect($err->and(
                new Ok(2),
            )->isErr())->toBeTrue();
            expect($ok->or(
                new Err('x'),
            )->isOk())->toBeTrue();
            expect($err->or(
                new Ok(3),
            )->isOk())->toBeTrue();
            expect($err->or(
                new Ok(3),
            )->unwrap())->toBe(3);

            // orElse
            $r = $err->orElse(fn (string $e) => new Ok(mb_strlen($e)));
            expect($r->isOk())->toBeTrue();
            expect($r->unwrap())->toBe(1);

            // unwrapOr / unwrapOrElse
            expect($ok->unwrapOr(0))->toBe(1);
            expect($err->unwrapOr(7))->toBe(7);
            expect($err->unwrapOrElse(fn (string $e) => mb_strlen($e) + 4))->toBe(5);
        });

        test('checks value containment and inspects without consuming Result', function (): void {
            $ok = new Ok('v');
            $err = new Err('e');

            expect($ok->contains('v'))->toBeTrue();
            expect($ok->contains('x'))->toBeFalse();
            expect($err->containsErr('e'))->toBeTrue();
            expect($err->containsErr('x'))->toBeFalse();

            $seen = '';
            $ok->inspect(function ($v) use (&$seen): void {
                $seen = $v;
            });
            expect($seen)->toBe('v');

            $seenErr = '';
            $err->inspectErr(function ($e) use (&$seenErr): void {
                $seenErr = $e;
            });
            expect($seenErr)->toBe('e');
        });

        test('transposes Result of Option into Option of Result', function (): void {
            $okSome = new Ok(Option::fromNullable('x'));
            $okNone = new Ok(Option::fromNullable(null));
            $err = new Err('e');

            $a = $okSome->transpose();
            expect($a->isDefined())->toBeTrue();
            expect($a->get()->isOk())->toBeTrue();
            expect($a->get()->unwrap())->toBe('x');

            $b = $okNone->transpose();
            expect($b->isEmpty())->toBeTrue();

            $c = $err->transpose();
            expect($c->isDefined())->toBeTrue();
            expect($c->get()->isErr())->toBeTrue();
            expect($c->get()->unwrapErr())->toBe('e');
        });

        test('converts Result to Option using intoOk and intoErr facades', function (): void {
            $ok = new Ok('v');
            $err = new Err('e');

            expect($ok->intoOk()->isDefined())->toBeTrue();
            expect($ok->intoOk()->get())->toBe('v');
            expect($ok->intoErr()->isEmpty())->toBeTrue();

            expect($err->intoErr()->isDefined())->toBeTrue();
            expect($err->intoErr()->get())->toBe('e');
            expect($err->intoOk()->isEmpty())->toBeTrue();
        });
    });

    describe('Sad Paths', function (): void {
        test('unwraps Ok value or throws RuntimeException with custom message', function (): void {
            $ok = new Ok('v');
            $err = new Err('e');

            expect($ok->expect('nope'))->toBe('v');

            $this->expectException(RuntimeException::class);
            $err->expect('boom');
        });

        test('unwraps Err value or throws RuntimeException with custom message', function (): void {
            $err = new Err('e');
            expect($err->expectErr('nope'))->toBe('e');

            $this->expectException(RuntimeException::class);
            new Ok('v')->expectErr('boom');
        });
    });
});
