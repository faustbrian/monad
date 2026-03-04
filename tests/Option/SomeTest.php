<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Monad\Option\None;
use Cline\Monad\Option\Some;
use Tests\Option\Fixtures\Repository;

describe('Some', function (): void {
    describe('Happy Paths', function (): void {
        test('returns wrapped value and ignores fallback arguments', function (): void {
            // Arrange
            $some = new Some('foo');

            // Act & Assert
            expect($some->get())->toBe('foo');
            expect($some->unwrapOr(null))->toBe('foo');
            expect($some->unwrapOrElse(fn (): string => 'does_not_exist'))->toBe('foo');
            expect($some->unwrapOrThrow(
                new RuntimeException('Not found'),
            ))->toBe('foo');
            expect($some->isEmpty())->toBeFalse();
        });

        test('creates Some instance with factory method', function (): void {
            // Arrange & Act
            $some = Some::create('foo');

            // Assert
            expect($some->get())->toBe('foo');
            expect($some->unwrapOr(null))->toBe('foo');
            expect($some->unwrapOrElse(fn (): string => 'does_not_exist'))->toBe('foo');
            expect($some->unwrapOrThrow(
                new RuntimeException('Not found'),
            ))->toBe('foo');
            expect($some->isEmpty())->toBeFalse();
        });

        test('returns itself when calling orElse regardless of alternative', function (): void {
            // Arrange
            $some = Some::create('foo');

            // Act & Assert
            expect($some->orElse(fn (): None => None::create()))->toBe($some);
            expect($some->orElse(fn (): Some => Some::create('bar')))->toBe($some);
        });

        test('executes callback with wrapped value when calling ifDefined', function (): void {
            // Arrange
            $called = false;
            $self = $this;
            $some = new Some('foo');

            // Act
            $some->ifDefined(function ($v) use (&$called, $self): void {
                $called = true;
                $self->assertSame('foo', $v);
            });

            // Assert
            expect($called)->toBeTrue();
        });

        test('executes callback and returns self when calling forAll', function (): void {
            // Arrange
            $called = false;
            $self = $this;
            $some = new Some('foo');

            // Act
            $result = $some->forAll(function ($v) use (&$called, $self): void {
                $called = true;
                $self->assertSame('foo', $v);
            });

            // Assert
            expect($result)->toBe($some);
            expect($called)->toBeTrue();
        });

        test('transforms wrapped value with map function', function (): void {
            // Arrange
            $some = new Some('foo');

            // Act
            $mapped = $some->map(fn ($v): string => mb_substr($v, 1, 1));

            // Assert
            expect($mapped->get())->toBe('o');
        });

        test('chains Option-returning transformations with flatMap', function (): void {
            // Arrange
            $repo = new Repository(['foo']);

            // Act
            $result = $repo->getLastRegisteredUsername()
                ->flatMap($repo->getUser(...))
                ->unwrapOrElse($repo->getDefaultUser(...));

            // Assert
            expect($result)->toBe(['name' => 'foo']);
        });

        test('returns self when filter predicate passes', function (): void {
            // Arrange
            $some = new Some('foo');

            // Act
            $result = $some->filter(fn ($v): bool => $v !== '');

            // Assert
            expect($result)->toBe($some);
        });

        test('returns self when filterNot predicate fails', function (): void {
            // Arrange
            $some = new Some('foo');

            // Act
            $result = $some->filterNot(fn ($v): bool => $v === '');

            // Assert
            expect($result)->toBe($some);
        });

        test('returns self when value matches selector', function (): void {
            // Arrange
            $some = new Some('foo');

            // Act
            $result = $some->select('foo');

            // Assert
            expect($result)->toBe($some);
        });

        test('returns self when value does not match rejection', function (): void {
            // Arrange
            $some = new Some('foo');

            // Act & Assert
            expect($some->reject(null))->toBe($some);
            expect($some->reject(true))->toBe($some);
        });

        test('folds wrapped value with initial accumulator using left associativity', function (): void {
            // Arrange
            $some = new Some(5);
            $testObj = $this;

            // Act
            $result = $some->foldLeft(1, function ($a, $b) use ($testObj): int {
                $testObj->assertSame(1, $a);
                $testObj->assertSame(5, $b);

                return $a + $b;
            });

            // Assert
            expect($result)->toBe(6);
        });

        test('folds wrapped value with initial accumulator using right associativity', function (): void {
            // Arrange
            $some = new Some(5);
            $testObj = $this;

            // Act
            $result = $some->foldRight(1, function ($a, $b) use ($testObj): int {
                $testObj->assertSame(1, $b);
                $testObj->assertSame(5, $a);

                return $a + $b;
            });

            // Assert
            expect($result)->toBe(6);
        });

        test('iterates once yielding wrapped value in foreach loop', function (): void {
            // Arrange
            $some = new Some('foo');
            $called = 0;
            $extractedValue = null;

            // Act
            foreach ($some as $value) {
                $extractedValue = $value;
                ++$called;
            }

            // Assert
            expect($extractedValue)->toBe('foo');
            expect($called)->toBe(1);
        });

        test('returns new Some with same scalar value when calling cloned on non-object', function (): void {
            // Arrange
            $some = new Some(42);

            // Act
            $cloned = $some->cloned();

            // Assert
            expect($cloned->get())->toBe(42);
            expect($cloned)->not->toBe($some);
        });

        test('executes inspect callback and returns self unchanged', function (): void {
            // Arrange
            $some = new Some('test');
            $inspected = null;

            // Act
            $result = $some->inspect(function ($v) use (&$inspected): void {
                $inspected = $v;
            });

            // Assert
            expect($result)->toBe($some);
            expect($inspected)->toBe('test');
        });

        test('applies map function and returns transformed value directly', function (): void {
            // Arrange
            $some = new Some('hello');

            // Act
            $result = $some->mapOrDefault('strtoupper');

            // Assert
            expect($result)->toBe('HELLO');
        });
    });

    describe('Sad Paths', function (): void {
        test('returns None when filter predicate fails', function (): void {
            // Arrange
            $some = new Some('foo');

            // Act
            $result = $some->filter(fn ($v): bool => '' === $v);

            // Assert
            expect($result)->toBeInstanceOf(None::class);
        });

        test('returns None when filterNot predicate passes', function (): void {
            // Arrange
            $some = new Some('foo');

            // Act
            $result = $some->filterNot(fn ($v): bool => $v !== '');

            // Assert
            expect($result)->toBeInstanceOf(None::class);
        });

        test('returns None when value does not match selector', function (): void {
            // Arrange
            $some = new Some('foo');

            // Act & Assert
            expect($some->select('bar'))->toBeInstanceOf(None::class);
            expect($some->select(true))->toBeInstanceOf(None::class);
        });

        test('returns None when value matches rejection', function (): void {
            // Arrange
            $some = new Some('foo');

            // Act
            $result = $some->reject('foo');

            // Assert
            expect($result)->toBeInstanceOf(None::class);
        });
    });
});
