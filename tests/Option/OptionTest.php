<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Monad\Option\LazyOption;
use Cline\Monad\Option\None;
use Cline\Monad\Option\Option;
use Cline\Monad\Option\Some;
use Cline\Monad\Result\Err;
use Cline\Monad\Result\Ok;
use Illuminate\Container\Container;
use Illuminate\Foundation\Application;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Exceptions\TestException;
use Tests\Option\Fixtures\SimpleBox;
use Tests\Option\Fixtures\SomeArrayObject;

describe('Option', function (): void {
    describe('Happy Paths', function (): void {
        test('returns None when value is null and Some for non-null values', function (): void {
            expect(Option::fromValue(null))->toBeInstanceOf(None::class);
            expect(Option::fromValue('value'))->toBeInstanceOf(Some::class);
        });

        test('returns None when value matches custom none value', function (): void {
            expect(Option::fromValue(false, false))->toBeInstanceOf(None::class);
            expect(Option::fromValue('value', false))->toBeInstanceOf(Some::class);
            expect(Option::fromValue(null, false))->toBeInstanceOf(Some::class);
        });

        test('extracts value from array or ArrayAccess object by key', function (): void {
            expect(Option::fromArraysValue('foo', 'bar'))->toEqual(None::create());
            expect(Option::fromArraysValue(null, 'bar'))->toEqual(None::create());
            expect(Option::fromArraysValue(['foo' => 'bar'], 'baz'))->toEqual(None::create());
            expect(Option::fromArraysValue(['foo' => null], 'foo'))->toEqual(None::create());
            expect(Option::fromArraysValue(['foo' => 'bar'], null))->toEqual(None::create());
            expect(Option::fromArraysValue(['foo' => 'foo'], 'foo'))->toEqual(
                new Some('foo'),
            );
            expect(Option::fromArraysValue([13 => 'foo'], 13))->toEqual(
                new Some('foo'),
            );

            $object = new SomeArrayObject();
            $object['foo'] = 'foo';
            expect(Option::fromArraysValue($object, 'foo'))->toEqual(
                new Some('foo'),
            );

            $object = new SomeArrayObject();
            $object[13] = 'foo';
            expect(Option::fromArraysValue($object, 13))->toEqual(
                new Some('foo'),
            );

            // ArrayAccess with non-existent key
            $emptyObject = new SomeArrayObject();
            expect(Option::fromArraysValue($emptyObject, 'missing'))->toEqual(None::create());
        });

        test('wraps callable return value as Option based on none value', function (): void {
            $null = function (): void {};
            $false = fn (): false => false;
            $some = fn (): string => 'foo';

            expect(Option::fromReturn($null)->isEmpty())->toBeTrue();
            expect(Option::fromReturn($false)->isEmpty())->toBeFalse();
            expect(Option::fromReturn($false, [], false)->isEmpty())->toBeTrue();
            expect(Option::fromReturn($some)->isDefined())->toBeTrue();
            expect(Option::fromReturn($some, [], 'foo')->isDefined())->toBeFalse();
        });

        test('returns original Some when calling orElse', function (): void {
            $a = new Some('a');
            $b = new Some('b');

            expect($a->orElse(fn (): Some => $b)->get())->toBe('a');
        });

        test('returns alternative Option when None calls orElse', function (): void {
            $a = None::create();
            $b = new Some('b');

            expect($a->orElse(fn (): Some => $b)->get())->toBe('b');
        });

        test('does not evaluate lazy alternative when Some calls orElse', function (): void {
            $throws = function (): void {
                throw TestException::shouldNeverBeCalled();
            };

            $a = new Some('a');
            $b = new LazyOption($throws);

            expect($a->orElse(fn (): LazyOption => $b)->get())->toBe('a');
        });

        test('chains orElse calls until finding first Some', function (): void {
            $throws = new LazyOption(function (): void {
                throw TestException::shouldNeverBeCalled();
            });
            $returns = fn (): Some => new Some('foo');

            $a = None::create();

            expect($a->orElse($returns)->orElse(fn (): LazyOption => $throws)->get())->toBe('foo');
        });

        test('lifts binary function to work with Option arguments', function (): void {
            $f = fn ($a, $b): float|int|array => $a + $b;

            $fL = Option::lift($f);

            $a = new Some(1);
            $b = new Some(5);
            $n = None::create();

            expect($fL($a, $b)->get())->toBe(6);
            expect($fL($b, $a)->get())->toBe(6);
            expect($fL($a, $n))->toBe($n);
            expect($fL($n, $a))->toBe($n);
            expect($fL($n, $n))->toBe($n);
        });

        test('lifts void function to return None or Some based on none value', function (): void {
            $f = function (): void {};

            $fL1 = Option::lift($f);
            $fL2 = Option::lift($f, false);

            expect($fL1())->toEqual(None::create());
            expect($fL2())->toEqual(Some::create(null));
        });

        test('chains Option-returning functions with andThen', function (): void {
            $some = Option::fromNullable(2);
            $res = $some->andThen(fn (int $i): Option => Option::fromNullable($i + 3));
            expect($res->isDefined())->toBeTrue();
            expect($res->get())->toBe(5);

            $none = Option::fromNullable(null);
            $res2 = $none->andThen(fn ($v): Option => Option::fromNullable('never'));
            expect($res2->isEmpty())->toBeTrue();
        });

        test('executes appropriate callback based on Some or None state', function (): void {
            $some = Option::fromNullable('ok');
            $none = Option::fromNullable(null);

            $a = $some->match(fn (string $v): string => mb_strtoupper($v), fn (): string => 'none');
            $b = $none->match(fn (string $v): string => mb_strtoupper($v), fn (): string => 'none');

            expect($a)->toBe('OK');
            expect($b)->toBe('none');
        });

        test('unwraps Some value or aborts with HTTP exception for None', function (): void {
            $some = Option::fromNullable('value');
            expect($some->unwrapOrAbort())->toBe('value');

            // Minimal container for abort_unless path
            $previous = Container::getInstance();
            Container::setInstance(
                new Application(dirname(__DIR__, 3)),
            );

            try {
                $this->expectException(HttpException::class);
                Option::fromNullable(null)->unwrapOrAbort(404, 'missing');
            } finally {
                Container::setInstance($previous);
            }
        });

        test('unwraps value when condition passes or aborts otherwise', function (): void {
            $some = Option::fromNullable('value');

            // Returns on truthy boolean
            expect($some->unwrapOrAbortUnless(true))->toBe('value');

            // Returns when predicate matches
            expect($some->unwrapOrAbortUnless(fn (string $v): bool => $v === 'value'))->toBe('value');

            // Predicate fails — aborts (with minimal container)
            $previous = Container::getInstance();
            Container::setInstance(
                new Application(dirname(__DIR__, 3)),
            );

            try {
                $this->expectException(HttpException::class);
                $some->unwrapOrAbortUnless(fn (string $v): bool => $v === 'nope', 422, 'invalid');
            } finally {
                Container::setInstance($previous);
            }
        });

        test('unwraps Some value or throws custom exception for None', function (): void {
            $some = Option::fromNullable('ok');
            expect($some->unwrapOrThrow(
                new LogicException('should not throw'),
            ))->toBe('ok');

            $this->expectException(LogicException::class);
            Option::fromNullable(null)->unwrapOrThrow(
                new LogicException('fail'),
            );
        });

        test('unwraps Some value or throws RuntimeException with custom message', function (): void {
            $some = Option::fromNullable('v');
            expect($some->unwrap())->toBe('v');

            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('boom');
            Option::fromNullable(null)->expect('boom');
        });

        test('performs logical and or xor operations on Options', function (): void {
            $a = Option::fromNullable('a');
            $b = Option::fromNullable('b');
            $n = Option::fromNullable(null);

            expect($a->and($b)->isDefined())->toBeTrue();
            expect($a->and($b)->get())->toBe('b');
            expect($a->and($n)->isEmpty())->toBeTrue();
            expect($n->and($b)->isEmpty())->toBeTrue();

            expect($a->or($b)->get())->toBe('a');
            expect($n->or($b)->get())->toBe('b');
            expect($n->or($n)->isEmpty())->toBeTrue();

            expect($a->xor($n)->isDefined())->toBeTrue();
            expect($a->xor($n)->get())->toBe('a');
            expect($n->xor($b)->isDefined())->toBeTrue();
            expect($n->xor($b)->get())->toBe('b');
            expect($a->xor($b)->isEmpty())->toBeTrue();
            expect($n->xor($n)->isEmpty())->toBeTrue();
        });

        test('maps Some value with default fallback for None', function (): void {
            $some = Option::fromNullable(3);
            $none = Option::fromNullable(null);

            expect($some->mapOr(0, fn (int $i): int => $i * $i))->toBe(9);
            expect($none->mapOr(0, fn (int $i): int => $i * $i))->toBe(0);

            expect($some->mapOrElse(fn (): int => -1, fn (int $i): int => $i * $i))->toBe(9);
            expect($none->mapOrElse(fn (): int => -1, fn (int $i): int => $i * $i))->toBe(-1);
        });

        test('combines two Options into tuple or applies function to both values', function (): void {
            $a = Option::fromNullable(1);
            $b = Option::fromNullable(2);
            $n = Option::fromNullable(null);

            $z = $a->zip($b);
            expect($z->isDefined())->toBeTrue();
            expect($z->get())->toBe([1, 2]);
            expect($a->zip($n)->isEmpty())->toBeTrue();

            $zw = $a->zipWith($b, fn (int $x, int $y): int => $x + $y);
            expect($zw->isDefined())->toBeTrue();
            expect($zw->get())->toBe(3);
            expect($a->zipWith($n, fn ($x, $y): null => null)->isEmpty())->toBeTrue();
        });

        test('flattens nested Options and checks value containment', function (): void {
            $nested = Option::fromNullable(Option::fromNullable('x'));
            $flat = $nested->flatten();
            expect($flat->isDefined())->toBeTrue();
            expect($flat->get())->toBe('x');

            // Flatten None returns itself
            $none = Option::fromNullable(null);
            expect($none->flatten())->toBe($none);

            $some = Option::fromNullable('x');
            expect($some->contains('x'))->toBeTrue();
            expect($some->contains('y'))->toBeFalse();
            expect($some->isSomeAnd(fn (string $v): bool => $v === 'x'))->toBeTrue();
            expect($some->isSomeAnd(fn (string $v): bool => $v === 'y'))->toBeFalse();
            expect(Option::fromNullable(null)->isSomeAnd(fn ($v): true => true))->toBeFalse();

            // unwrapOrDefault convenience
            expect($some->unwrapOrDefault())->toBe('x');
            expect(Option::fromNullable(null)->unwrapOrDefault())->toBeNull();
        });

        test('inspects value without consuming Option and supports various utility methods', function (): void {
            $called = 0;
            $val = null;
            $some = Option::fromNullable('y');
            $same = $some->inspect(function ($v) use (&$called, &$val): void {
                ++$called;
                $val = $v;
            });
            expect($same)->toBe($some);
            expect($called)->toBe(1);
            expect($val)->toBe('y');

            expect(Option::fromNullable(null)->isNoneOr(fn ($v): false => false))->toBeTrue();
            expect(Option::fromNullable('z')->isNoneOr(fn ($v): bool => $v === 'z'))->toBeTrue();
            expect(Option::fromNullable('z')->isNoneOr(fn ($v): bool => $v === 'nope'))->toBeFalse();

            expect(Option::fromNullable('zzzz')->mapOrDefault(fn (string $s): int => mb_strlen($s)))->toBe(4);
            expect(Option::fromNullable(null)->mapOrDefault(fn ($v): int => 123))->toBeNull();

            // cloned
            $box = new SimpleBox(1);
            $cloneOpt = Option::fromNullable($box)->cloned();
            $clone = $cloneOpt->unwrap();
            $clone->value = 2;

            expect($box->value)->toBe(1);
        });

        test('transposes Option of Result into Result of Option', function (): void {
            $ok = new Ok('v');
            $err = new Err('e');

            $a = Option::fromNullable($ok)->transpose();
            expect($a->isOk())->toBeTrue();
            expect($a->ok()->isDefined())->toBeTrue();
            expect($a->ok()->get()->get())->toBe('v');

            $b = Option::fromNullable(null)->transpose();
            expect($b->isOk())->toBeTrue();
            expect($b->ok()->get()->isEmpty())->toBeTrue();

            $c = Option::fromNullable($err)->transpose();
            expect($c->isErr())->toBeTrue();
            expect($c->unwrapErr())->toBe('e');

            $this->expectException(RuntimeException::class);
            Option::fromNullable('not-a-result')->transpose();
        });

        test('converts Option to nullable value and provides state check aliases', function (): void {
            $some = Option::fromNullable(10);
            $none = Option::fromNullable(null);

            expect($some->toNullable())->toBe(10);
            expect($none->toNullable())->toBeNull();
            expect($some->isSome())->toBeTrue();
            expect($some->isNone())->toBeFalse();
            expect($none->isSome())->toBeFalse();
            expect($none->isNone())->toBeTrue();
        });
    });

    describe('Edge Cases', function (): void {
        test('unzips Option of tuple into tuple of Options', function (): void {
            $ab = Option::fromNullable([1, 'a']);
            [$optA, $optB] = $ab->unzip();
            expect($optA->isDefined())->toBeTrue();
            expect($optB->isDefined())->toBeTrue();
            expect($optA->get())->toBe(1);
            expect($optB->get())->toBe('a');

            $none = Option::fromNullable(null);
            [$na, $nb] = $none->unzip();
            expect($na->isEmpty())->toBeTrue();
            expect($nb->isEmpty())->toBeTrue();

            $this->expectException(RuntimeException::class);
            Option::fromNullable('not-a-pair')->unzip();
        });
    });
});
