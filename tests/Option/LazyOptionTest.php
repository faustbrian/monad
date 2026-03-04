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

describe('LazyOption', function (): void {
    describe('Happy Paths', function (): void {
        test('lazily evaluates callback with arguments using create method', function (): void {
            // Arrange
            $subject = createSubject();

            // Act
            $option = LazyOption::create([$subject, 'execute'], ['foo']);

            // Assert
            expect($option->get())->toBe('foo');
            expect($option->unwrapOr(null))->toBe('foo');
            expect($option->unwrapOrElse(fn (): string => 'does_not_exist'))->toBe('foo');
            expect($option->unwrapOrThrow(
                new RuntimeException('does_not_exist'),
            ))->toBe('foo');
            expect($option->isEmpty())->toBeFalse();
        });

        test('lazily evaluates callback with arguments using constructor', function (): void {
            // Arrange
            $subject = createSubject();

            // Act
            $option = new LazyOption([$subject, 'execute'], ['foo']);

            // Assert
            expect($option->get())->toBe('foo');
            expect($option->unwrapOr(null))->toBe('foo');
            expect($option->unwrapOrElse(fn (): string => 'does_not_exist'))->toBe('foo');
            expect($option->unwrapOrThrow(
                new RuntimeException('does_not_exist'),
            ))->toBe('foo');
            expect($option->isEmpty())->toBeFalse();
        });

        test('lazily evaluates callback without arguments using constructor', function (): void {
            // Arrange
            $subject = createSubject('foo');

            // Act
            $option = new LazyOption([$subject, 'execute']);

            // Assert
            expect($option->get())->toBe('foo');
            expect($option->unwrapOr(null))->toBe('foo');
            expect($option->unwrapOrElse(fn (): string => 'does_not_exist'))->toBe('foo');
            expect($option->unwrapOrThrow(
                new RuntimeException('does_not_exist'),
            ))->toBe('foo');
            expect($option->isEmpty())->toBeFalse();
        });

        test('lazily evaluates callback without arguments using create method', function (): void {
            // Arrange
            $subject = createSubject('foo');

            // Act
            $option = LazyOption::create([$subject, 'execute']);

            // Assert
            expect($option->isDefined())->toBeTrue();
            expect($option->isEmpty())->toBeFalse();
            expect($option->get())->toBe('foo');
            expect($option->unwrapOr(null))->toBe('foo');
            expect($option->unwrapOrElse(fn (): string => 'does_not_exist'))->toBe('foo');
            expect($option->unwrapOrThrow(
                new RuntimeException('does_not_exist'),
            ))->toBe('foo');
        });

        test('executes callback when calling ifDefined on LazyOption containing Some', function (): void {
            // Arrange
            $called = false;
            $self = $this;
            $lazyOption = LazyOption::create(fn (): Some => new Some('foo'));

            // Act
            $lazyOption->ifDefined(function ($v) use (&$called, $self): void {
                $called = true;
                $self->assertSame('foo', $v);
            });

            // Assert
            expect($called)->toBeTrue();
        });

        test('delegates getIterator to wrapped Option', function (): void {
            // Arrange
            $lazyOption = LazyOption::create(fn (): Some => new Some('value'));

            // Act
            $iterator = $lazyOption->getIterator();
            $values = iterator_to_array($iterator);

            // Assert
            expect($values)->toBe(['value']);
        });

        test('delegates select and reject operations to wrapped Option', function (): void {
            // Arrange
            $lazySome = LazyOption::create(fn (): Some => new Some('foo'));
            $lazyOther = LazyOption::create(fn (): Some => new Some('bar'));

            // Act & Assert - select
            expect($lazySome->select('foo'))->toBeInstanceOf(Some::class);
            expect($lazySome->select('bar'))->toBeInstanceOf(None::class);

            // Act & Assert - reject
            expect($lazyOther->reject('foo'))->toBeInstanceOf(Some::class);
            expect($lazyOther->reject('bar'))->toBeInstanceOf(None::class);
        });

        test('delegates map operations to wrapped Option', function (): void {
            // Arrange
            $lazyOption = LazyOption::create(fn (): Some => new Some('hello'));

            // Act
            $result = $lazyOption->map('strtoupper');

            // Assert
            expect($result)->toBeInstanceOf(Some::class);
            expect($result->get())->toBe('HELLO');
        });

        test('delegates mapOrDefault to wrapped Option', function (): void {
            // Arrange
            $lazyOption = LazyOption::create(fn (): Some => new Some('test'));

            // Act
            $result = $lazyOption->mapOrDefault('strtoupper');

            // Assert
            expect($result)->toBe('TEST');
        });

        test('delegates flatMap to wrapped Option', function (): void {
            // Arrange
            $lazyOption = LazyOption::create(fn (): Some => new Some(5));

            // Act
            $result = $lazyOption->flatMap(fn ($v): Some => new Some($v * 2));

            // Assert
            expect($result)->toBeInstanceOf(Some::class);
            expect($result->get())->toBe(10);
        });

        test('delegates filter operations to wrapped Option', function (): void {
            // Arrange
            $lazyOption = LazyOption::create(fn (): Some => new Some(10));

            // Act & Assert - filter
            expect($lazyOption->filter(fn ($v): bool => $v > 5))->toBeInstanceOf(Some::class);
            expect($lazyOption->filter(fn ($v): bool => $v < 5))->toBeInstanceOf(None::class);
        });

        test('delegates filterNot operations to wrapped Option', function (): void {
            // Arrange
            $lazyOption = LazyOption::create(fn (): Some => new Some(10));

            // Act & Assert - filterNot
            expect($lazyOption->filterNot(fn ($v): bool => $v < 5))->toBeInstanceOf(Some::class);
            expect($lazyOption->filterNot(fn ($v): bool => $v > 5))->toBeInstanceOf(None::class);
        });

        test('delegates cloned to wrapped Option', function (): void {
            // Arrange
            $obj = new stdClass();
            $obj->value = 42;

            $lazyOption = LazyOption::create(fn (): Some => new Some($obj));

            // Act
            $cloned = $lazyOption->cloned();

            // Assert
            expect($cloned)->toBeInstanceOf(Some::class);
            $clonedObj = $cloned->get();
            expect($clonedObj)->not->toBe($obj);
            expect($clonedObj->value)->toBe(42);
        });

        test('executes callback and returns wrapped Option when calling forAll on LazyOption', function (): void {
            // Arrange
            $called = false;
            $self = $this;
            $lazyOption = LazyOption::create(fn (): Some => new Some('foo'));

            // Act
            $result = $lazyOption->forAll(function ($v) use (&$called, $self): void {
                $called = true;
                $self->assertSame('foo', $v);
            });

            // Assert
            expect($result)->toBeInstanceOf(Some::class);
            expect($called)->toBeTrue();
        });

        test('returns itself when calling orElse on Some', function (): void {
            // Arrange
            $some = Some::create('foo');
            $lazy = LazyOption::create(fn (): Some => $some);

            // Act & Assert
            expect($lazy->orElse(fn (): None => None::create()))->toBe($some);
            expect($lazy->orElse(fn (): Some => Some::create('bar')))->toBe($some);
        });

        test('delegates fold operations to wrapped Option', function (): void {
            // Arrange
            $some = Some::create(10);
            $lazyOption = new LazyOption(fn (): Some => $some);

            // Act & Assert - foldLeft
            $foldLeftResult = $lazyOption->foldLeft(5, fn (int $acc, int $v): int => $acc + $v);
            expect($foldLeftResult)->toBe(15);

            // Act & Assert - foldRight
            $foldRightResult = $lazyOption->foldRight(5, fn (int $v, int $acc): int => $v - $acc);
            expect($foldRightResult)->toBe(5);
        });
    });

    describe('Sad Paths', function (): void {
        test('throws RuntimeException when calling get on None from null callback', function (): void {
            // Arrange
            $option = LazyOption::create([createSubject(), 'execute']);

            // Assert initial state
            expect($option->isDefined())->toBeFalse();
            expect($option->isEmpty())->toBeTrue();
            expect($option->unwrapOr('alt'))->toBe('alt');
            expect($option->unwrapOrElse(fn (): string => 'alt'))->toBe('alt');

            // Act & Assert exception
            $this->expectException('RuntimeException');
            $this->expectExceptionMessage('None has no value');
            $option->get();
        });

        test('throws RuntimeException when callback returns non-Option value', function (): void {
            // Arrange
            $option = LazyOption::create([createInvalidSubject(), 'execute']);

            // Act & Assert
            $this->expectException('RuntimeException');
            $this->expectExceptionMessage('Expected instance of Cline\Monad\Option\Option');
            $option->isDefined();
        });

        test('throws TypeError for invalid callback in constructor', function (): void {
            // Act & Assert
            $this->expectException(TypeError::class);
            $this->expectExceptionMessage('must be of type callable');
            new LazyOption('invalidCallback');
        });

        test('throws TypeError for invalid callback in create method', function (): void {
            // Act & Assert
            $this->expectException(TypeError::class);
            $this->expectExceptionMessage('must be of type callable');
            LazyOption::create('invalidCallback');
        });
    });
});

function createSubject($default = null): object
{
    return new class($default)
    {
        public function __construct(
            public readonly mixed $default,
        ) {}

        public function execute($v = null): Option
        {
            return Option::fromValue($v ?? $this->default);
        }
    };
}

function createInvalidSubject(): object
{
    return new class()
    {
        public function execute($v = null): null
        {
            return null;
        }
    };
}
