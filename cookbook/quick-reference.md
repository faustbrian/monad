# Quick Reference

## Highlights

- **Option**: Null-safety with `fromNullable()`, Laravel `firstOption()` macros, controller `unwrapOrAbort()` helpers
- **Result**: `Ok`/`Err` semantics with full combinators and interop with Option
- **Either**: `Left`/`Right` branching for complex multi-path scenarios
- Full Rust-aligned API naming for unwrapping and chaining operations

## API Overview

### Option API
Create: `fromNullable()` `fromValue()` `ensure()` `fromReturn()` | Query: `isSome()` `isNone()` `contains()` | Unwrap: `unwrap()` `unwrapOr()` `unwrapOrAbort()` | Transform: `map()` `andThen()` `filter()` | Match: `match(someFn, noneFn)`

### Result API
Create: `Ok($v)` `Err($e)` | Query: `isOk()` `isErr()` `ok()` `err()` | Unwrap: `unwrap()` `unwrapOr()` `expect()` | Transform: `map()` `mapErr()` `andThen()` | Match: `match(okFn, errFn)`

### Either API
Create: `left($v)` `right($v)` | Query: `isLeft()` `isRight()` `left()` `right()` | Unwrap: `unwrapLeft()` `unwrapRight()` | Transform: `mapLeft()` `mapRight()` `bimap()` | Match: `match(leftFn, rightFn)`

See individual cookbook guides for comprehensive API documentation.
