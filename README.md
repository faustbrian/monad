# Monad - Functional Error Handling for PHP/Laravel

Rust-aligned `Option`, `Result`, and `Either` types providing expressive, type-safe error handling and ergonomic controller helpers for Laravel applications.

## Installation

```bash
composer require cline/monad
```

## Documentation

- **[Option Guide](cookbook/Option.md)** - Complete guide with real-world examples for null-safe operations
- **[Result Guide](cookbook/Result.md)** - Comprehensive error handling patterns with Ok/Err semantics
- **[Either Guide](cookbook/Either.md)** - Advanced Left/Right branching for complex scenarios

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

## License

This package is open-sourced software licensed under the [MIT license](LICENSE).

## Credits

- [Brian Faust](https://github.com/faustbrian)
- [All Contributors](../../contributors)
