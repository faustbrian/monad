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
use Tests\Option\Fixtures\TestOption;

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
            expect($option->unwrapOrElse('does_not_exist'))->toBe('foo');
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
            expect($option->unwrapOrElse('does_not_exist'))->toBe('foo');
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
            expect($option->unwrapOrElse('does_not_exist'))->toBe('foo');
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
            expect($option->unwrapOrElse('does_not_exist'))->toBe('foo');
            expect($option->unwrapOrThrow(
                new RuntimeException('does_not_exist'),
            ))->toBe('foo');
        });

        test('executes callback when calling ifDefined on Some', function (): void {
            // Arrange
            $called = false;
            $self = $this;

            // Act
            LazyOption::fromValue('foo')->ifDefined(function ($v) use (&$called, $self): void {
                $called = true;
                $self->assertSame('foo', $v);
            });

            // Assert
            expect($called)->toBeTrue();
        });

        test('executes callback and returns Some when calling forAll', function (): void {
            // Arrange
            $called = false;
            $self = $this;

            // Act
            $result = LazyOption::fromValue('foo')->forAll(function ($v) use (&$called, $self): void {
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
            $lazy = LazyOption::create(function () use ($some) {
                return $some;
            });

            // Act & Assert
            expect($lazy->orElse(fn () => None::create()))->toBe($some);
            expect($lazy->orElse(fn () => Some::create('bar')))->toBe($some);
        });

        test('delegates fold operations to wrapped Option', function (): void {
            // Arrange
            $callback = function (): void {};
            $option = self::createPartialMock(TestOption::class, ['foldLeft', 'foldRight']);

            // Act - foldLeft
            $option->expects(self::once())
                ->method('foldLeft')
                ->with(5, $callback)
                ->willReturn(6);
            $lazyOption = new LazyOption(function () use ($option) {
                return $option;
            });

            // Assert
            expect($lazyOption->foldLeft(5, $callback))->toBe(6);

            // Act - foldRight
            $option->expects(self::once())
                ->method('foldRight')
                ->with(5, $callback)
                ->willReturn(6);
            $lazyOption = new LazyOption(function () use ($option) {
                return $option;
            });

            // Assert
            expect($lazyOption->foldRight(5, $callback))->toBe(6);
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
            expect($option->unwrapOrElse(function () {
                return 'alt';
            }))->toBe('alt');

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

        test('throws InvalidArgumentException for invalid callback in constructor', function (): void {
            // Act & Assert
            $this->expectException('InvalidArgumentException');
            $this->expectExceptionMessage('Invalid callback given');
            new LazyOption('invalidCallback');
        });

        test('throws InvalidArgumentException for invalid callback in create method', function (): void {
            // Act & Assert
            $this->expectException('InvalidArgumentException');
            $this->expectExceptionMessage('Invalid callback given');
            LazyOption::create('invalidCallback');
        });
    });
});

function createSubject($default = null): object
{
    return new class($default)
    {
        public mixed $default;

        public function __construct($default)
        {
            $this->default = $default;
        }

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
        public function execute($v = null)
        {
            return null;
        }
    };
}
