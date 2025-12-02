# Transpose - Complete Guide

## Overview

`transpose()` swaps nested monad types, converting between `Option<Result<T,E>>` and `Result<Option<T>,E>`. Essential for operations that can both fail and return optional values.

## Core Concept

```php
use Cline\Monad\Option\{Option, Some, None};
use Cline\Monad\Result\{Result, Ok, Err};

// Option::transpose()
// Option<Result<T, E>> -> Result<Option<T>, E>

// Result::transpose()
// Result<Option<T>, E> -> Option<Result<T, E>>
```

Use when reordering nested monads or changing error handling precedence.

## Basic Usage

### Option Transpose

```php
use Cline\Monad\Option\{Some, None};
use Cline\Monad\Result\{Ok, Err};

// Some(Ok(value)) -> Ok(Some(value))
$someOk = new Some(new Ok(42));
$okSome = $someOk->transpose();
// Result: Ok(Some(42))

// Some(Err(error)) -> Err(error)
$someErr = new Some(new Err('not found'));
$err = $someErr->transpose();
// Result: Err('not found')

// None -> Ok(None)
$none = None::create();
$okNone = $none->transpose();
// Result: Ok(None)
```

### Result Transpose

```php
// Ok(Some(value)) -> Some(Ok(value))
$okSome = new Ok(new Some(42));
$someOk = $okSome->transpose();
// Result: Some(Ok(42))

// Ok(None) -> None
$okNone = new Ok(None::create());
$none = $okNone->transpose();
// Result: None

// Err(error) -> Some(Err(error))
$err = new Err('database error');
$someErr = $err->transpose();
// Result: Some(Err('database error'))
```

## Real-World Examples

### 1. Optional Database Lookups with Error Handling

```php
final readonly class User
{
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
    ) {}
}

enum DatabaseError: string
{
    case CONNECTION_FAILED = 'CONNECTION_FAILED';
    case QUERY_FAILED = 'QUERY_FAILED';
    case TIMEOUT = 'TIMEOUT';
}

// Returns Result because query might fail
// Returns Option inside because user might not exist
function findUserById(int $id): Result
{
    try {
        $user = DB::table('users')->find($id);

        return new Ok(
            $user ? new Some($user) : None::create()
        );
    } catch (QueryException $e) {
        return new Err(DatabaseError::QUERY_FAILED);
    }
}

// WITHOUT transpose - awkward nested matching
$result = findUserById(123);
$message = $result->match(
    ok: fn($option) => $option->match(
        some: fn($user) => "Found: {$user->name}",
        none: fn() => 'User not found'
    ),
    err: fn($error) => "Database error: {$error->value}"
);

// WITH transpose - clean error-first handling
$result = findUserById(123)
    ->transpose(); // Result<Option<User>, Error> -> Option<Result<User, Error>>

$message = $result->match(
    some: fn($innerResult) => $innerResult->match(
        ok: fn($user) => "Found: {$user->name}",
        err: fn($error) => "Database error: {$error->value}"
    ),
    none: fn() => 'User not found'
);

// Or extract the error early
$userOption = findUserById(123)
    ->inspect(fn($opt) => Log::info('Query succeeded'))
    ->inspectErr(fn($err) => Log::error("DB error: {$err->value}"))
    ->transpose() // Option<Result<User, Error>>
    ->flatMap(fn($result) => $result->ok()); // Option<User>

$userName = $userOption
    ->map(fn($user) => $user->name)
    ->unwrapOr('Unknown');
```

### 2. API Calls with Optional Responses

```php
enum ApiError: string
{
    case NETWORK_ERROR = 'NETWORK_ERROR';
    case INVALID_RESPONSE = 'INVALID_RESPONSE';
    case UNAUTHORIZED = 'UNAUTHORIZED';
}

final readonly class ApiResponse
{
    public function __construct(
        public int $statusCode,
        public mixed $data,
    ) {}
}

// API might fail (Result) and might return no data (Option)
function fetchUserProfile(string $userId): Result
{
    try {
        $response = Http::get("/api/users/{$userId}");

        if ($response->status() === 404) {
            return new Ok(None::create()); // User doesn't exist
        }

        if ($response->failed()) {
            return new Err(ApiError::INVALID_RESPONSE);
        }

        return new Ok(new Some($response->json()));
    } catch (ConnectionException $e) {
        return new Err(ApiError::NETWORK_ERROR);
    }
}

// Process user profile with transpose
function displayUserProfile(string $userId): string
{
    return fetchUserProfile($userId)
        ->transpose() // Option<Result<Profile, ApiError>>
        ->match(
            some: fn($result) => $result->match(
                ok: fn($profile) => renderProfile($profile),
                err: fn($error) => renderError($error)
            ),
            none: fn() => renderNotFound()
        );
}

// Alternative: error-first with early returns
function processUserProfile(string $userId): Result
{
    $resultOption = fetchUserProfile($userId); // Result<Option<Profile>, Error>

    // Handle API errors first
    if ($resultOption->isErr()) {
        return $resultOption; // Propagate error
    }

    // Extract Option<Profile>
    $profileOption = $resultOption->unwrap();

    // Handle missing profile
    if ($profileOption->isNone()) {
        return new Err('PROFILE_NOT_FOUND');
    }

    // Process profile
    $profile = $profileOption->unwrap();
    return new Ok(processProfile($profile));
}
```

### 3. Cache Lookups with Fallback

```php
// Cache might fail (Result) and might not have value (Option)
function getCached(string $key): Result
{
    try {
        $value = Cache::get($key);
        return new Ok(
            $value !== null ? new Some($value) : None::create()
        );
    } catch (RedisException $e) {
        return new Err('CACHE_ERROR');
    }
}

function getOrCompute(string $key, callable $compute): Result
{
    return getCached($key)
        ->transpose() // Option<Result<Value, Error>>
        ->orElse(fn() => new Some( // Cache miss, compute value
            Either::tryCatch($compute)
                ->toResult()
                ->inspect(fn($v) => Cache::put($key, $v))
        ))
        ->unwrap(); // Result<Value, Error>
}

// Usage
$result = getOrCompute('user:123', fn() =>
    DB::table('users')->find(123)
);

$user = $result->unwrapOr(null);
```

### 4. Validation with Optional Fields

```php
enum ValidationError: string
{
    case INVALID_EMAIL = 'INVALID_EMAIL';
    case INVALID_PHONE = 'INVALID_PHONE';
    case REQUIRED_FIELD = 'REQUIRED_FIELD';
}

// Validate optional email field
function validateOptionalEmail(?string $email): Result
{
    $emailOption = Option::fromNullable($email);

    // Option<string> -> Option<Result<string, ValidationError>>
    $validated = $emailOption->map(function($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL)
            ? new Ok($email)
            : new Err(ValidationError::INVALID_EMAIL);
    });

    // Option<Result<string, Error>> -> Result<Option<string>, Error>
    return $validated->transpose();
}

// Usage
$result = validateOptionalEmail($request->input('email'));

$result->match(
    ok: fn($optEmail) => $optEmail->match(
        some: fn($email) => Log::info("Valid email: {$email}"),
        none: fn() => Log::info('No email provided')
    ),
    err: fn($error) => Log::error("Invalid email: {$error->value}")
);

// Collect all validation results
function validateUser(array $data): Result
{
    $validations = [
        validateOptionalEmail($data['email'] ?? null),
        validateOptionalPhone($data['phone'] ?? null),
        validateOptionalWebsite($data['website'] ?? null),
    ];

    // All must be Ok, but can contain None
    foreach ($validations as $validation) {
        if ($validation->isErr()) {
            return $validation; // First error wins
        }
    }

    return new Ok([
        'email' => $validations[0]->unwrap(),
        'phone' => $validations[1]->unwrap(),
        'website' => $validations[2]->unwrap(),
    ]);
}
```

### 5. Multi-Stage Processing Pipeline

```php
enum ProcessingError: string
{
    case PARSE_ERROR = 'PARSE_ERROR';
    case TRANSFORM_ERROR = 'TRANSFORM_ERROR';
    case VALIDATION_ERROR = 'VALIDATION_ERROR';
}

// Parse might fail, and result might be empty
function parseInput(string $input): Result
{
    try {
        $data = json_decode($input, true, 512, JSON_THROW_ON_ERROR);

        return new Ok(
            empty($data) ? None::create() : new Some($data)
        );
    } catch (JsonException $e) {
        return new Err(ProcessingError::PARSE_ERROR);
    }
}

// Transform data (also can fail or be empty)
function transformData(array $data): Result
{
    try {
        $transformed = array_map(
            fn($item) => processItem($item),
            $data
        );

        return new Ok(
            empty($transformed) ? None::create() : new Some($transformed)
        );
    } catch (Exception $e) {
        return new Err(ProcessingError::TRANSFORM_ERROR);
    }
}

// Full pipeline with transpose
function processPipeline(string $input): Result
{
    return parseInput($input)
        ->transpose() // Option<Result<Data, Error>>
        ->flatMap(fn($parseResult) =>
            $parseResult->andThen(fn($data) =>
                transformData($data)
                    ->transpose() // Option<Result<Transformed, Error>>
            )
        )
        ->unwrapOr(new Ok(None::create())); // Result<Option<Final>, Error>
}

// Usage
$result = processPipeline($jsonInput);

$output = $result->match(
    ok: fn($dataOption) => $dataOption->match(
        some: fn($data) => json_encode($data),
        none: fn() => 'No data to process'
    ),
    err: fn($error) => "Processing failed: {$error->value}"
);
```

## Decision Guide

### Use `transpose()` when:

1. **Nested monads** - You have `Option<Result>` or `Result<Option>` and need to reorder
2. **Error precedence** - You need to check errors before checking presence
3. **Optional operations that can fail** - API calls, DB queries, file reads that might not exist
4. **Validation of optional fields** - Email, phone, etc. that may be absent but must be valid if present

### Don't use `transpose()` when:

1. **Single monad** - Use `map()`, `flatMap()` instead
2. **Sequential operations** - Use `andThen()` for chaining
3. **Either monad needed** - Use `Either` for non-error branching
4. **Simple null checks** - Use `Option::fromNullable()` directly

## Common Patterns

### Pattern 1: Error-First Validation

```php
// Result<Option<T>, E> -> check errors before checking presence
function validateAndExtract(mixed $input): Result
{
    return parse($input) // Result<Option<Data>, ParseError>
        ->inspect(fn() => Log::debug('Parse succeeded'))
        ->transpose() // Option<Result<Data, ParseError>>
        ->unwrapOr(new Ok(defaultData())); // Result<Data, ParseError>
}
```

### Pattern 2: Optional API Response

```php
// Handle 404s gracefully while catching real errors
function fetchResource(string $id): Result
{
    return apiCall($id) // Result<Option<Resource>, ApiError>
        ->transpose() // Option<Result<Resource, ApiError>>
        ->match(
            some: fn($result) => $result, // Has data or real error
            none: fn() => new Ok(null) // 404 is ok, return null
        );
}
```

### Pattern 3: Chain Optional Operations

```php
// Each step might fail AND might return nothing
function loadUserWithPreferences(int $userId): Result
{
    return findUser($userId) // Result<Option<User>, DbError>
        ->andThen(fn($userOpt) =>
            $userOpt->transpose() // Option<Result<User, DbError>>
                ->flatMap(fn($userResult) =>
                    $userResult->andThen(fn($user) =>
                        loadPreferences($user->id)
                    )
                )
                ->unwrapOr(new Ok(defaultUser()))
        );
}
```

## Performance Considerations

- `transpose()` is zero-cost - it just reorders the monad structure
- No additional allocations beyond the new monad wrapper
- Use freely when the semantic clarity is valuable
- Consider early returns if deeply nested transposes hurt readability

## See Also

- [Option Guide](Option.md) - Core Option operations
- [Result Guide](Result.md) - Core Result operations
- [Either Guide](Either.md) - When to use Either vs Result
