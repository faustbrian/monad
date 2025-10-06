<p align="center">
    <a href="https://github.com/faustbrian/monad/actions"><img alt="GitHub Workflow Status (master)" src="https://github.com/faustbrian/monad/actions/workflows/tests.yml/badge.svg"></a>
    <a href="https://packagist.org/packages/cline/monad"><img alt="Total Downloads" src="https://img.shields.io/packagist/dt/cline/monad"></a>
    <a href="https://packagist.org/packages/cline/monad"><img alt="Latest Version" src="https://img.shields.io/packagist/v/cline/monad"></a>
    <a href="https://packagist.org/packages/cline/monad"><img alt="License" src="https://img.shields.io/packagist/l/cline/monad"></a>
</p>

------

This package provides a Rust-aligned `Option`, `Result`, and `Either` types providing expressive, type-safe error handling and ergonomic controller helpers for Laravel applications.

## Requirements

> **Requires [PHP 8.4+](https://php.net/releases/)**

## Installation

```bash
composer require cline/monad
```

## Documentation

- **[Option Guide](cookbook/Option.md)** - Complete guide with real-world examples for null-safe operations
- **[Result Guide](cookbook/Result.md)** - Comprehensive error handling patterns with Ok/Err semantics
- **[Either Guide](cookbook/Either.md)** - Advanced Left/Right branching for complex scenarios
- **[Transpose Guide](cookbook/Transpose.md)** - Swapping nested monads (Option<Result> ↔ Result<Option>)

## Highlights

- **Option**: Null-safety with `fromNullable()`, Laravel `firstOption()` macros, controller `unwrapOrAbort()` helpers
- **Result**: `Ok`/`Err` semantics with full combinators and interop with Option
- **Either**: `Left`/`Right` branching for complex multi-path scenarios
- Full Rust-aligned API naming for unwrapping and chaining operations

## Quick Reference

### Option API
Create: `fromNullable()` `fromValue()` `ensure()` `fromReturn()` | Query: `isSome()` `isNone()` `contains()` | Unwrap: `unwrap()` `unwrapOr()` `unwrapOrAbort()` | Transform: `map()` `andThen()` `filter()` | Match: `match(someFn, noneFn)`

### Result API
Create: `Ok($v)` `Err($e)` | Query: `isOk()` `isErr()` `ok()` `err()` | Unwrap: `unwrap()` `unwrapOr()` `expect()` | Transform: `map()` `mapErr()` `andThen()` | Match: `match(okFn, errFn)`

### Either API
Create: `left($v)` `right($v)` | Query: `isLeft()` `isRight()` `left()` `right()` | Unwrap: `unwrapLeft()` `unwrapRight()` | Transform: `mapLeft()` `mapRight()` `bimap()` | Match: `match(leftFn, rightFn)`

See individual cookbook guides for comprehensive API documentation.

## Development

Keep a modern codebase with **PHP CS Fixer**:
```bash
composer lint
```

Run refactors using **Rector**
```bash
composer refactor
```

Run static analysis using **PHPStan**:
```bash
composer test:types
```

Run unit tests using **PEST**
```bash
composer test:unit
```

Run the entire test suite:
```bash
composer test
```

## Credits

**Monad** was created by **[Brian Faust](https://github.com/faustbrian)** under the **[MIT license](https://opensource.org/licenses/MIT)**.
