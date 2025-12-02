<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Monad\Option\None;
use Cline\Monad\Option\Option;
use Cline\Monad\Option\Some;

describe('Option::ensure()', function (): void {
    describe('Happy Paths', function (): void {
        test('wraps non-null value in Some', function (): void {
            // Arrange & Act
            $option = ensure(1);

            // Assert
            expect($option->isDefined())->toBeTrue();
            expect($option->get())->toBe(1);
        });

        test('wraps callable return value in Option based on result', function (): void {
            // Arrange
            $callable = fn (): int => 1;

            // Act
            $option = ensure($callable);

            // Assert
            expect($option->isDefined())->toBeTrue();
            expect($option->get())->toBe(1);
        });

        test('returns same Option instance when Option is passed', function (): void {
            // Arrange
            $originalOption = ensure(1);

            // Act
            $result = ensure($originalOption);

            // Assert
            expect($result)->toBe($originalOption);
        });

        test('unwraps Option returned from closure', function (): void {
            // Arrange
            $someClosure = fn (): Some => Some::create(1);

            // Act
            $option = ensure($someClosure);

            // Assert
            expect($option->isDefined())->toBeTrue();
            expect($option->get())->toBe(1);
        });

        test('wraps closure returned from closure as Some', function (): void {
            // Arrange
            $closureFactory = fn (): Closure => function (): void {};

            // Act
            $option = ensure($closureFactory);

            // Assert
            expect($option->isDefined())->toBeTrue();
            expect($option->get())->toBeInstanceOf('Closure');
        });
    });

    describe('Sad Paths', function (): void {
        test('returns None when null value is passed', function (): void {
            // Arrange & Act
            $option = ensure(null);

            // Assert
            expect($option->isDefined())->toBeFalse();
        });

        test('returns None when callable returns void', function (): void {
            // Arrange
            $callable = function (): void {};

            // Act
            $option = ensure($callable);

            // Assert
            expect($option->isDefined())->toBeFalse();
        });

        test('returns None when value matches custom none value', function (): void {
            // Arrange & Act
            $option = ensure(1, 1);

            // Assert
            expect($option->isDefined())->toBeFalse();
        });

        test('returns None when callable return value matches custom none value', function (): void {
            // Arrange
            $callable = fn (): int => 1;

            // Act
            $option = ensure($callable, 1);

            // Assert
            expect($option->isDefined())->toBeFalse();
        });

        test('returns None when closure returns None instance', function (): void {
            // Arrange
            $noneClosure = fn (): None => None::create();

            // Act
            $option = ensure($noneClosure);

            // Assert
            expect($option->isDefined())->toBeFalse();
        });
    });
});

function ensure($value, $noneValue = null): Option
{
    $option = Option::ensure($value, $noneValue);
    expect($option)->toBeInstanceOf(Option::class);

    return $option;
}
