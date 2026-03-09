## Table of Contents

1. Quick Reference (`docs/quick-reference.md`)
2. Either (`docs/either.md`)
3. Option (`docs/option.md`)
4. Result (`docs/result.md`)
5. Transpose (`docs/transpose.md`)

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

## Overview

`Either` handles computations that return one of two possible types: **Left** (failure/exception) or **Right** (success). Makes exception handling explicit and type-safe without throwing exceptions.

## Core Concept

```php
use Cline\Monad\Either\Either;
use Cline\Monad\Either\Left;
use Cline\Monad\Either\Right;

// Either<L, R> = Left<L> | Right<R>
```

- **Left**: Contains the exception/failure value
- **Right**: Contains the success value
- **Convention**: "Right is right" - success values go on the right

## Basic Usage

### Creating Either Values

```php
use Cline\Monad\Either\{Left, Right};

// Success case
$success = new Right(42);

// Failure case
$failure = new Left(new RuntimeException('Something went wrong'));
```

### Pattern Matching

```php
$result = $success->match(
    left: fn($exception) => "Failed: {$exception->getMessage()}",
    right: fn($value) => "Success: {$value}",
);
// result = "Success: 42"
```

## Real-World Examples

### 1. Laravel HTTP Client & API Integration

```php
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final readonly class ApiError
{
    public function __construct(
        public string $code,
        public string $message,
        public int $statusCode,
    ) {}
}

final readonly class User
{
    public function __construct(
        public string $id,
        public string $name,
        public string $email,
    ) {}
}

function fetchUser(string $userId): Either
{
    try {
        $response = Http::timeout(5)
            ->withToken(config('services.api.token'))
            ->get(config('services.api.url') . "/users/{$userId}");

        if (!$response->successful()) {
            return new Left(new ApiError(
                code: 'FETCH_ERROR',
                message: "HTTP {$response->status()}: {$response->reason()}",
                statusCode: $response->status(),
            ));
        }

        $data = $response->json();
        return new Right(new User(
            id: $data['id'],
            name: $data['name'],
            email: $data['email'],
        ));
    } catch (Throwable $e) {
        return new Left(new ApiError(
            code: 'NETWORK_ERROR',
            message: $e->getMessage(),
            statusCode: 0,
        ));
    }
}

// Usage in Controller
class UserController extends Controller
{
    public function show(string $userId)
    {
        return fetchUser($userId)->match(
            left: function (ApiError $error) {
                Log::error("Error {$error->code}: {$error->message}");

                return match ($error->statusCode) {
                    404 => abort(404, 'User not found'),
                    401, 403 => abort(403, 'Unauthorized'),
                    default => abort(500, 'Failed to fetch user'),
                };
            },
            right: function (User $user) {
                Log::info("Loaded user: {$user->name}");
                return view('users.show', compact('user'));
            },
        );
    }
}
```

### 2. Laravel Request Validation with Either

```php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

final readonly class ValidationError
{
    public function __construct(
        public string $field,
        public string $message,
    ) {}
}

final readonly class LoginData
{
    public function __construct(
        public string $email,
        public string $password,
    ) {}
}

function validateLoginRequest(Request $request): Either
{
    $validator = Validator::make($request->all(), [
        'email' => ['required', 'email', 'max:255'],
        'password' => ['required', 'string', 'min:8'],
    ]);

    if ($validator->fails()) {
        $firstError = $validator->errors()->first();
        $firstField = $validator->errors()->keys()[0];

        return new Left(new ValidationError(
            field: $firstField,
            message: $firstError,
        ));
    }

    return new Right(new LoginData(
        email: $request->input('email'),
        password: $request->input('password'),
    ));
}

// Usage in Controller
class AuthController extends Controller
{
    public function login(Request $request)
    {
        return validateLoginRequest($request)->match(
            left: function (ValidationError $error) {
                return back()
                    ->withErrors([$error->field => $error->message])
                    ->withInput();
            },
            right: function (LoginData $data) use ($request) {
                if (Auth::attempt(['email' => $data->email, 'password' => $data->password])) {
                    $request->session()->regenerate();
                    return redirect()->intended('dashboard');
                }

                return back()
                    ->withErrors(['email' => 'Invalid credentials'])
                    ->onlyInput('email');
            },
        );
    }
}
```

### 3. Laravel Storage Operations

```php
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

enum FileOperation: string
{
    case READ = 'read';
    case WRITE = 'write';
    case PARSE = 'parse';
    case DELETE = 'delete';
}

final readonly class FileError
{
    public function __construct(
        public FileOperation $operation,
        public string $path,
        public string $error,
        public ?string $disk = null,
    ) {}
}

function readJsonFromStorage(string $path, string $disk = 'local'): Either
{
    try {
        if (!Storage::disk($disk)->exists($path)) {
            return new Left(new FileError(
                operation: FileOperation::READ,
                path: $path,
                error: "File does not exist",
                disk: $disk,
            ));
        }

        $content = Storage::disk($disk)->get($path);

        try {
            $data = json_decode($content, associative: true, flags: JSON_THROW_ON_ERROR);
            return new Right($data);
        } catch (JsonException $e) {
            return new Left(new FileError(
                operation: FileOperation::PARSE,
                path: $path,
                error: "Invalid JSON: {$e->getMessage()}",
                disk: $disk,
            ));
        }
    } catch (Throwable $e) {
        return new Left(new FileError(
            operation: FileOperation::READ,
            path: $path,
            error: $e->getMessage(),
            disk: $disk,
        ));
    }
}

function writeJsonToStorage(string $path, array $data, string $disk = 'local'): Either
{
    try {
        $content = json_encode($data, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
        $result = Storage::disk($disk)->put($path, $content);

        if (!$result) {
            return new Left(new FileError(
                operation: FileOperation::WRITE,
                path: $path,
                error: "Failed to write file",
                disk: $disk,
            ));
        }

        return new Right($path);
    } catch (Throwable $e) {
        return new Left(new FileError(
            operation: FileOperation::WRITE,
            path: $path,
            error: $e->getMessage(),
            disk: $disk,
        ));
    }
}

// Usage: User settings management
final readonly class UserSettings
{
    public function __construct(
        public string $theme,
        public string $language,
        public bool $notifications,
    ) {}
}

function loadUserSettings(int $userId): Either
{
    return readJsonFromStorage("users/{$userId}/settings.json", 's3')
        ->map(fn(array $data) => new UserSettings(
            theme: $data['theme'] ?? 'light',
            language: $data['language'] ?? 'en',
            notifications: $data['notifications'] ?? true,
        ));
}

// In Controller
class SettingsController extends Controller
{
    public function show(Request $request)
    {
        return loadUserSettings($request->user()->id)->match(
            left: function (FileError $error) {
                Log::warning("Settings load failed: {$error->error}");
                // Return default settings
                return view('settings.show', [
                    'settings' => new UserSettings('light', 'en', true)
                ]);
            },
            right: fn(UserSettings $settings) => view('settings.show', compact('settings')),
        );
    }
}
```

### 4. Laravel Eloquent & Query Builder

```php
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\Post;

final readonly class DbError
{
    public function __construct(
        public string $operation,
        public string $code,
        public string $details,
        public ?string $model = null,
    ) {}
}

function findUserById(int $id): Either
{
    try {
        $user = User::find($id);

        if ($user === null) {
            return new Left(new DbError(
                operation: 'find',
                code: 'NOT_FOUND',
                details: 'User not found',
                model: 'User',
            ));
        }

        return new Right($user);
    } catch (Throwable $e) {
        return new Left(new DbError(
            operation: 'find',
            code: $e->getCode(),
            details: $e->getMessage(),
            model: 'User',
        ));
    }
}

function loadUserWithPosts(int $userId): Either
{
    return findUserById($userId)
        ->flatMap(function (User $user) {
            try {
                $user->load('posts');
                return new Right($user);
            } catch (Throwable $e) {
                return new Left(new DbError(
                    operation: 'load_relation',
                    code: $e->getCode(),
                    details: $e->getMessage(),
                    model: 'User',
                ));
            }
        });
}

// Usage in Controller
class UserController extends Controller
{
    public function show(int $id)
    {
        return loadUserWithPosts($id)->match(
            left: function (DbError $error) {
                Log::error("DB Error [{$error->code}]: {$error->details}");

                return match ($error->code) {
                    'NOT_FOUND' => abort(404, 'User not found'),
                    default => abort(500, 'Database error'),
                };
            },
            right: fn(User $user) => view('users.show', [
                'user' => $user,
                'posts' => $user->posts,
            ]),
        );
    }
}

// Transaction example
function transferFunds(int $fromId, int $toId, int $amount): Either
{
    try {
        DB::beginTransaction();

        $from = User::lockForUpdate()->find($fromId);
        $to = User::lockForUpdate()->find($toId);

        if (!$from || !$to) {
            DB::rollBack();
            return new Left(new DbError(
                operation: 'transfer',
                code: 'NOT_FOUND',
                details: 'User not found',
            ));
        }

        if ($from->balance < $amount) {
            DB::rollBack();
            return new Left(new DbError(
                operation: 'transfer',
                code: 'INSUFFICIENT_FUNDS',
                details: 'Insufficient balance',
            ));
        }

        $from->decrement('balance', $amount);
        $to->increment('balance', $amount);

        DB::commit();
        return new Right(['from' => $from, 'to' => $to]);
    } catch (Throwable $e) {
        DB::rollBack();
        return new Left(new DbError(
            operation: 'transfer',
            code: $e->getCode(),
            details: $e->getMessage(),
        ));
    }
}
```

### 5. Laravel Payment Processing with Cashier

```php
use Laravel\Cashier\Cashier;
use Stripe\Exception\CardException;
use Stripe\Exception\RateLimitException;
use Stripe\Exception\InvalidRequestException;
use App\Models\User;

enum PaymentErrorType: string
{
    case VALIDATION = 'validation';
    case CARD_DECLINED = 'card_declined';
    case PROCESSING = 'processing';
    case NETWORK = 'network';
}

final readonly class PaymentError
{
    public function __construct(
        public PaymentErrorType $type,
        public string $message,
        public bool $retryable,
        public ?string $stripeCode = null,
    ) {}
}

final readonly class PaymentResult
{
    public function __construct(
        public string $paymentIntentId,
        public int $amount,
        public string $currency,
        public string $status,
    ) {}
}

function chargeUser(User $user, int $amountInCents, string $paymentMethodId): Either
{
    // Validation
    if ($amountInCents <= 0) {
        return new Left(new PaymentError(
            type: PaymentErrorType::VALIDATION,
            message: 'Amount must be positive',
            retryable: false,
        ));
    }

    if (!$user->hasPaymentMethod()) {
        return new Left(new PaymentError(
            type: PaymentErrorType::VALIDATION,
            message: 'No payment method on file',
            retryable: false,
        ));
    }

    try {
        $payment = $user->charge($amountInCents, $paymentMethodId);

        return new Right(new PaymentResult(
            paymentIntentId: $payment->id,
            amount: $amountInCents,
            currency: 'usd',
            status: $payment->status,
        ));
    } catch (CardException $e) {
        return new Left(new PaymentError(
            type: PaymentErrorType::CARD_DECLINED,
            message: $e->getMessage(),
            retryable: false,
            stripeCode: $e->getStripeCode(),
        ));
    } catch (RateLimitException $e) {
        return new Left(new PaymentError(
            type: PaymentErrorType::NETWORK,
            message: 'Too many requests',
            retryable: true,
        ));
    } catch (Throwable $e) {
        return new Left(new PaymentError(
            type: PaymentErrorType::PROCESSING,
            message: $e->getMessage(),
            retryable: str_contains($e->getMessage(), 'api_error'),
        ));
    }
}

// Usage in Controller
class PaymentController extends Controller
{
    public function charge(Request $request)
    {
        $validated = $request->validate([
            'amount' => 'required|integer|min:100',
            'payment_method_id' => 'required|string',
        ]);

        return chargeUser(
            $request->user(),
            $validated['amount'],
            $validated['payment_method_id']
        )->match(
            left: function (PaymentError $error) {
                Log::error('Payment failed', [
                    'type' => $error->type->value,
                    'message' => $error->message,
                ]);

                return back()->withErrors([
                    'payment' => match ($error->type) {
                        PaymentErrorType::CARD_DECLINED => 'Your card was declined. Please try another payment method.',
                        PaymentErrorType::NETWORK => 'Network error. Please try again.',
                        default => 'Payment failed. Please try again or contact support.',
                    }
                ]);
            },
            right: function (PaymentResult $result) {
                Log::info('Payment successful', ['payment_id' => $result->paymentIntentId]);

                return redirect()->route('payment.success')
                    ->with('success', 'Payment processed successfully!');
            },
        );
    }
}
```

### 6. Pipeline Processing

```php
final readonly class ProcessingError
{
    public function __construct(
        public string $stage,
        public mixed $input,
        public string $reason,
    ) {}
}

final readonly class RawCustomer
{
    public function __construct(
        public string $id,
        public string $name,
        public string $email,
        public string $age,
    ) {}
}

final readonly class Customer
{
    public function __construct(
        public string $id,
        public string $name,
        public string $email,
        public int $age,
    ) {}
}

final readonly class EnrichedCustomer
{
    public function __construct(
        public string $id,
        public string $name,
        public string $email,
        public int $age,
        public string $tier,
        public string $joinDate,
    ) {}
}

// Transform raw CSV row to typed data
function parseCustomerRow(string $row): Either
{
    $fields = str_getcsv($row);

    if (count($fields) !== 4) {
        return new Left(new ProcessingError(
            stage: 'parse',
            input: $row,
            reason: "Expected 4 fields, got " . count($fields),
        ));
    }

    return new Right(new RawCustomer(
        id: $fields[0],
        name: $fields[1],
        email: $fields[2],
        age: $fields[3],
    ));
}

// Validate and transform to domain model
function validateCustomer(RawCustomer $raw): Either
{
    $age = (int) $raw->age;

    if ($age < 0 || $age > 150) {
        return new Left(new ProcessingError(
            stage: 'validate',
            input: $raw,
            reason: "Invalid age: {$raw->age}",
        ));
    }

    if (!str_contains($raw->email, '@')) {
        return new Left(new ProcessingError(
            stage: 'validate',
            input: $raw,
            reason: "Invalid email: {$raw->email}",
        ));
    }

    return new Right(new Customer(
        id: $raw->id,
        name: trim($raw->name),
        email: strtolower($raw->email),
        age: $age,
    ));
}

// Enrich with additional data
function enrichCustomer(Customer $customer): Either
{
    try {
        $metadata = fetchCustomerMetadata($customer->id);

        return new Right(new EnrichedCustomer(
            id: $customer->id,
            name: $customer->name,
            email: $customer->email,
            age: $customer->age,
            tier: $metadata['tier'],
            joinDate: $metadata['joinDate'],
        ));
    } catch (Throwable $e) {
        return new Left(new ProcessingError(
            stage: 'enrich',
            input: $customer,
            reason: $e->getMessage(),
        ));
    }
}

// Complete pipeline
function processCustomerData(array $csvRows): array
{
    $results = array_map(function (string $row) {
        $result = parseCustomerRow($row)
            ->flatMap(fn(RawCustomer $raw) => validateCustomer($raw))
            ->flatMap(fn(Customer $customer) => enrichCustomer($customer));

        return ['row' => $row, 'result' => $result];
    }, $csvRows);

    return [
        'successful' => array_map(
            fn($item) => $item['result']->unwrap(),
            array_filter($results, fn($item) => $item['result']->isRight())
        ),
        'failed' => array_map(
            fn($item) => [
                'row' => $item['row'],
                'error' => $item['result']->unwrapLeft(),
            ],
            array_filter($results, fn($item) => $item['result']->isLeft())
        ),
    ];
}
```

## Advanced Patterns

### Combining Multiple Eithers

```php
final readonly class UserProfile
{
    public function __construct(
        public User $user,
        public array $preferences,
        public array $settings,
    ) {}
}

function loadUserProfile(string $userId): Either
{
    $userResult = fetchUser($userId);
    $prefsResult = fetchPreferences($userId);
    $settingsResult = fetchSettings($userId);

    // Combine all results - if any fail, return the first error
    return Either::combine([$userResult, $prefsResult, $settingsResult])
        ->map(fn(array $results) => new UserProfile(
            user: $results[0],
            preferences: $results[1],
            settings: $results[2],
        ));
}
```

### Exception Recovery

```php
function fetchUserWithFallback(string $userId): Either
{
    return fetchUser($userId)
        ->recover(function (ApiError $exception) use ($userId) {
            Log::warning("Primary fetch failed: {$exception->message}");
            return fetchUserFromCache($userId);
        })
        ->recover(function () {
            Log::warning('Cache miss, using guest user');
            return new Right(getGuestUser());
        });
}
```

### Bimap - Transform Both Sides

```php
$result = fetchUser('123');

$transformed = $result->bimap(
    // Transform exception
    left: fn(ApiError $exception) => new ApiError(
        code: $exception->code,
        message: 'Unable to load user. Please try again.',
        statusCode: $exception->statusCode,
    ),
    // Transform success
    right: fn(User $user) => [
        ...(array) $user,
        'displayName' => "{$user->name} ({$user->email})",
    ],
);
```

## Best Practices

### 1. Use Specific Exception Types

```php
// ❌ Bad: Generic exceptions
function parseConfig(string $data): Either // Either<Throwable, Config>

// ✅ Good: Specific exception types
final readonly class ConfigError
{
    public function __construct(
        public ConfigErrorType $type,
        public ?string $field,
        public string $message,
    ) {}
}

enum ConfigErrorType: string
{
    case MISSING_FIELD = 'missing_field';
    case INVALID_FORMAT = 'invalid_format';
    case PARSE_ERROR = 'parse_error';
}

function parseConfig(string $data): Either // Either<ConfigError, Config>
```

### 2. Early Returns for Validation

```php
function createOrder(OrderData $data): Either
{
    $itemsValidation = validateItems($data->items);
    if ($itemsValidation->isLeft()) {
        return $itemsValidation;
    }

    $addressValidation = validateAddress($data->address);
    if ($addressValidation->isLeft()) {
        return $addressValidation;
    }

    return new Right(buildOrder($data));
}
```

### 3. Use flatMap for Sequential Operations

```php
// Chain operations that depend on previous success
function updateUserEmail(string $userId, string $newEmail): Either
{
    return findUser($userId)
        ->flatMap(fn(User $user) => validateEmail($newEmail)->map(fn() => $user))
        ->flatMap(fn(User $user) => saveUser(new User(
            id: $user->id,
            name: $user->name,
            email: $newEmail,
        )))
        ->flatMap(fn(User $user) => sendConfirmationEmail($user->email)->map(fn() => $user));
}
```

### 4. Consistent Exception Handling

```php
// Create a standard exception handler
function handleApiError(ApiError $exception): void
{
    match ($exception->code) {
        'NOT_FOUND' => showNotFoundPage(),
        'UNAUTHORIZED' => redirectToLogin(),
        'NETWORK_ERROR' => showOfflineMessage(),
        default => showGenericError($exception->message),
    };
}

// Use consistently across the app
$userResult->match(
    left: handleApiError(...),
    right: displayUser(...),
);
```

## When to Use Either

✅ **Use Either when:**
- You need to handle both success and failure paths explicitly
- Exceptions contain meaningful context that callers should handle
- You want to compose/chain operations that might fail
- You're building pipelines with multiple transformation steps
- You need type-safe exception handling without throwing exceptions

❌ **Don't use Either when:**
- Simple null checks suffice (use Option instead)
- You only care about success (use Option or Result)
- Exceptions are more idiomatic for your codebase
- The added type complexity doesn't provide value

## Either vs Result vs Option

- **Either**: When you need different left/right types and want to handle both paths equally
- **Result**: When you specifically want Ok/Err semantics (Result is a specialized Either)
- **Option**: When you only care about presence/absence (Some/None)

## API Reference

```php
abstract class Either
{
    // Construction
    public static function left(mixed $value): Left;
    public static function right(mixed $value): Right;
    public static function combine(array $eithers): Either;

    // Checking
    public function isLeft(): bool;
    public function isRight(): bool;

    // Unwrapping (unsafe)
    public function unwrap(): mixed;
    public function unwrapLeft(): mixed;
    public function unwrapOr(mixed $defaultValue): mixed;

    // Transformation
    public function map(callable $fn): Either;
    public function mapLeft(callable $fn): Either;
    public function bimap(callable $left, callable $right): Either;
    public function flatMap(callable $fn): Either;

    // Matching
    public function match(callable $left, callable $right): mixed;

    // Exception recovery
    public function recover(callable $fn): Either;
}
```


## Overview

`Option` is a monad for representing values that may or may not exist, providing a type-safe alternative to `null`. It forces explicit handling of absence, eliminating null pointer exceptions and making code intent clearer.

## Core Concept

```php
use Cline\Monad\Option\Option;
use Cline\Monad\Option\Some;
use Cline\Monad\Option\None;

// Option<T> = Some<T> | None
```

- **Some**: Contains a value of type `T`
- **None**: Represents absence of a value
- **Null safety**: Eliminates `null` from your domain logic

## Basic Usage

### Creating Option Values

```php
use Cline\Monad\Option\{Some, None, Option};

// Value present
$present = new Some(42);

// Value absent
$absent = new None();

// From nullable value
$fromNullable = Option::fromNullable($maybeString);
```

### Pattern Matching

```php
$message = $present->match(
    some: fn($value) => "Found: {$value}",
    none: fn() => 'Not found',
);
```

## Real-World Examples

### 1. Safe Array Access

```php
function first(array $array): Option
{
    return count($array) > 0 ? new Some($array[0]) : new None();
}

function last(array $array): Option
{
    return count($array) > 0 ? new Some($array[array_key_last($array)]) : new None();
}

function at(array $array, int $index): Option
{
    return isset($array[$index]) ? new Some($array[$index]) : new None();
}

// Usage
$numbers = [1, 2, 3, 4, 5];

$firstNum = first($numbers);
$firstNum->match(
    some: fn($n) => Log::info("First number: {$n}"), // "First number: 1"
    none: fn() => Log::info('Array is empty'),
);

$tenthNum = at($numbers, 10);
$doubled = $tenthNum->map(fn($n) => $n * 2); // Returns none(), no error thrown

$doubled->match(
    some: fn($n) => Log::info("Doubled: {$n}"),
    none: fn() => Log::info('Index out of bounds'), // This executes
);

// Safe chaining
$emptyArray = [];
$result = first($emptyArray)
    ->map(fn($n) => $n * 2)
    ->map(fn($n) => $n + 10)
    ->unwrapOr(0); // Returns 0, never throws
```

### 2. Array/Collection Lookups

```php
final readonly class User
{
    public function __construct(
        public string $id,
        public string $name,
        public string $email,
    ) {}
}

$cache = [
    '123' => new User(id: '123', name: 'Alice', email: 'alice@example.com'),
    '456' => new User(id: '456', name: 'Bob', email: 'bob@example.com'),
];

function findUser(string $userId): Option
{
    global $cache;
    return Option::fromNullable($cache[$userId] ?? null);
}

function getUserEmail(string $userId): Option
{
    return findUser($userId)->map(fn(User $user) => $user->email);
}

// Usage
$email = getUserEmail('123');
$email->match(
    some: fn($e) => Log::info("Email: {$e}"), // "Email: alice@example.com"
    none: fn() => Log::info('User not found'),
);

// Provide default
$displayName = findUser('999')
    ->map(fn(User $user) => $user->name)
    ->unwrapOr('Guest User');

echo $displayName; // "Guest User"
```

### 3. Laravel Request Input Handling

```php
use Illuminate\Http\Request;

function getInput(Request $request, string $name): Option
{
    return Option::fromNullable($request->input($name));
}

function getIntInput(Request $request, string $name): Option
{
    return getInput($request, $name)
        ->flatMap(function ($value) {
            $int = filter_var($value, FILTER_VALIDATE_INT);
            return $int !== false ? new Some($int) : new None();
        });
}

function getBoolInput(Request $request, string $name): bool
{
    return getInput($request, $name)
        ->map(fn($value) => in_array($value, ['true', '1', 'yes', 'on'], true))
        ->unwrapOr(false);
}

// Usage in Controller: Multiple contact methods
class ContactController extends Controller
{
    public function store(Request $request)
    {
        $contact = getInput($request, 'email')
            ->orElse(fn() => getInput($request, 'phone'))
            ->orElse(fn() => getInput($request, 'twitter'));

        $contact->match(
            some: fn($value) => $this->saveContact($value),
            none: fn() => back()->withErrors(['contact' => 'At least one contact method required']),
        );
    }
}

// Query parameter handling
function getPaginationPage(Request $request): int
{
    return getIntInput($request, 'page')
        ->filter(fn($page) => $page > 0)
        ->unwrapOr(1);
}
```

### 4. Configuration Management

```php
final readonly class AppConfig
{
    public function __construct(
        public ?string $apiUrl = null,
        public ?int $timeout = null,
        public ?int $retries = null,
        public ?bool $debugMode = null,
    ) {}
}

final class Config
{
    public function __construct(
        private AppConfig $config,
    ) {}

    public function getApiUrl(): Option
    {
        return Option::fromNullable($this->config->apiUrl);
    }

    public function getTimeout(): int
    {
        return Option::fromNullable($this->config->timeout)->unwrapOr(5000);
    }

    public function getRetries(): int
    {
        return Option::fromNullable($this->config->retries)->unwrapOr(3);
    }

    public function isDebugMode(): bool
    {
        return Option::fromNullable($this->config->debugMode)->unwrapOr(false);
    }

    public function getApiConfig(): array
    {
        return [
            'url' => $this->getApiUrl()->unwrapOr('https://api.example.com'),
            'timeout' => $this->getTimeout(),
            'retries' => $this->getRetries(),
        ];
    }
}

// Usage
$userConfig = new AppConfig(
    timeout: 10000,
    debugMode: true,
);

$config = new Config($userConfig);

$config->getApiUrl()->match(
    some: fn($url) => Log::info("Using custom API: {$url}"),
    none: fn() => Log::info('Using default API'),
);

$apiConfig = $config->getApiConfig();
print_r($apiConfig);
// ['url' => 'https://api.example.com', 'timeout' => 10000, 'retries' => 3]
```

### 5. Search and Filter Operations

```php
final readonly class Product
{
    public function __construct(
        public string $id,
        public string $name,
        public string $category,
        public float $price,
        public bool $inStock,
    ) {}
}

$products = [
    new Product(id: '1', name: 'Laptop', category: 'electronics', price: 999, inStock: true),
    new Product(id: '2', name: 'Mouse', category: 'electronics', price: 29, inStock: true),
    new Product(id: '3', name: 'Desk', category: 'furniture', price: 299, inStock: false),
];

function findProductById(string $id): Option
{
    global $products;
    $found = array_filter($products, fn(Product $p) => $p->id === $id);
    return count($found) > 0 ? new Some(array_values($found)[0]) : new None();
}

function findProductByName(string $name): Option
{
    global $products;
    $found = array_filter(
        $products,
        fn(Product $p) => strtolower($p->name) === strtolower($name)
    );
    return count($found) > 0 ? new Some(array_values($found)[0]) : new None();
}

function findCheapestInCategory(string $category): Option
{
    global $products;
    $categoryProducts = array_filter($products, fn(Product $p) => $p->category === $category);

    if (count($categoryProducts) === 0) {
        return new None();
    }

    $cheapest = array_reduce(
        $categoryProducts,
        fn(?Product $min, Product $p) => $min === null || $p->price < $min->price ? $p : $min
    );

    return new Some($cheapest);
}

function findInStockProduct(string $productId): Option
{
    return findProductById($productId)->flatMap(
        fn(Product $product) => $product->inStock ? new Some($product) : new None()
    );
}

// Usage
$laptop = findProductById('1');
$laptop->match(
    some: fn(Product $p) => Log::info("Found: {$p->name} - \${$p->price}"),
    none: fn() => Log::info('Product not found'),
);

// Chain operations
$cheapElectronics = findCheapestInCategory('electronics')
    ->map(fn(Product $p) => ['name' => $p->name, 'price' => $p->price])
    ->unwrapOr(['name' => 'None available', 'price' => 0]);

print_r($cheapElectronics); // ['name' => 'Mouse', 'price' => 29]

// Filter by availability
$desk = findInStockProduct('3');
$desk->match(
    some: fn(Product $p) => Log::info("Available: {$p->name}"),
    none: fn() => Log::info('Out of stock or not found'),
);
```

### 6. Laravel Session and Cache Operations

```php
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Cache;

function getSession(string $key): Option
{
    return Option::fromNullable(Session::get($key));
}

function getSessionAsJson(string $key): Option
{
    return getSession($key)->flatMap(function ($json) {
        try {
            $data = json_decode($json, associative: true, flags: JSON_THROW_ON_ERROR);
            return new Some($data);
        } catch (JsonException) {
            return new None();
        }
    });
}

function getCached(string $key): Option
{
    return Option::fromNullable(Cache::get($key));
}

function getCachedOrCompute(string $key, callable $compute, int $ttl = 3600): mixed
{
    return getCached($key)
        ->unwrapOr(function () use ($key, $compute, $ttl) {
            $value = $compute();
            Cache::put($key, $value, $ttl);
            return $value;
        });
}

// Usage: User preferences
final readonly class UserPreferences
{
    public function __construct(
        public string $theme,
        public int $fontSize,
        public bool $notifications,
    ) {}
}

class PreferenceController extends Controller
{
    private function defaultPreferences(): UserPreferences
    {
        return new UserPreferences(
            theme: config('app.default_theme', 'light'),
            fontSize: 16,
            notifications: true,
        );
    }

    public function show(Request $request)
    {
        $prefs = getSessionAsJson('user_preferences')
            ->map(fn(array $data) => new UserPreferences(
                theme: $data['theme'] ?? 'light',
                fontSize: $data['fontSize'] ?? 16,
                notifications: $data['notifications'] ?? true,
            ))
            ->unwrapOr($this->defaultPreferences());

        return view('preferences.show', compact('prefs'));
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'theme' => 'required|in:light,dark',
            'fontSize' => 'required|integer|min:10|max:24',
            'notifications' => 'required|boolean',
        ]);

        Session::put('user_preferences', json_encode($validated));

        return back()->with('success', 'Preferences saved');
    }
}

// Cache with fallback
function getExpensiveData(): array
{
    return getCachedOrCompute('expensive_data', function () {
        // Expensive computation or API call
        return computeExpensiveData();
    }, ttl: 3600);
}
```

### 7. Query Parameters

```php
function getQueryParam(string $name): Option
{
    return Option::fromNullable(request()->query($name));
}

function getQueryParamAsInt(string $name): Option
{
    return getQueryParam($name)->flatMap(function ($value) {
        $int = filter_var($value, FILTER_VALIDATE_INT);
        return $int !== false ? new Some($int) : new None();
    });
}

function getQueryParamAsBool(string $name): bool
{
    return getQueryParam($name)
        ->map(fn($value) => in_array($value, ['true', '1'], true))
        ->unwrapOr(false);
}

// Usage: Pagination from URL
final readonly class PaginationParams
{
    public function __construct(
        public int $page,
        public int $limit,
        public ?string $sort = null,
    ) {}
}

function getPaginationFromUrl(): PaginationParams
{
    return new PaginationParams(
        page: getQueryParamAsInt('page')->unwrapOr(1),
        limit: getQueryParamAsInt('limit')->unwrapOr(20),
        sort: getQueryParam('sort')->unwrapOr(null),
    );
}

// Usage: Filter parameters
$searchQuery = getQueryParam('q');
$searchQuery->match(
    some: fn($query) => performSearch($query),
    none: fn() => showRecentSearches(),
);

// Get user ID from URL with validation
$userId = getQueryParam('userId')
    ->filter(fn($id) => strlen($id) > 0)
    ->filter(fn($id) => preg_match('/^[a-zA-Z0-9]+$/', $id) === 1);

$userId->match(
    some: fn($id) => loadUser($id),
    none: fn() => redirect()->route('home'),
);
```

### 8. API Response Handling

```php
function fetchUser(string $userId): Option
{
    try {
        $response = Http::get("/api/users/{$userId}");

        if (!$response->successful()) {
            return new None();
        }

        $data = $response->json();
        return Option::fromNullable($data['data'] ?? null);
    } catch (Throwable) {
        return new None();
    }
}

function fetchUserPosts(string $userId): array
{
    return fetchUser($userId)
        ->map(function (array $user) {
            $response = Http::get("/api/users/{$user['id']}/posts");
            return $response->json();
        })
        ->unwrapOr([]);
}

// Usage: Display user profile
$userId = '123';
$user = fetchUser($userId);

$user->match(
    some: function (array $u) {
        view()->share('userName', $u['name']);
        view()->share('userEmail', $u['email']);
    },
    none: fn() => abort(404, 'User not found'),
);

// Chain multiple API calls
function getUserWithProfile(string $userId): Option
{
    return fetchUser($userId)
        ->map(function (array $user) {
            $posts = fetchUserPosts($user['id']);
            return [
                ...$user,
                'posts' => $posts,
                'postCount' => count($posts),
            ];
        });
}
```

### 9. Validation and Parsing

```php
function parseEmail(string $input): Option
{
    $trimmed = trim($input);
    return filter_var($trimmed, FILTER_VALIDATE_EMAIL)
        ? new Some(strtolower($trimmed))
        : new None();
}

function parseAge(string $input): Option
{
    $age = (int) $input;
    return $age >= 0 && $age <= 150 ? new Some($age) : new None();
}

function parseUrl(string $input): Option
{
    $url = filter_var($input, FILTER_VALIDATE_URL);
    return $url !== false ? new Some($url) : new None();
}

function parseDate(string $input): Option
{
    try {
        $date = new DateTime($input);
        return new Some($date);
    } catch (Exception) {
        return new None();
    }
}

// Usage: Form validation
final readonly class UserForm
{
    public function __construct(
        public string $email,
        public string $age,
        public string $website,
    ) {}
}

final readonly class ValidatedUser
{
    public function __construct(
        public string $email,
        public int $age,
        public string $website,
    ) {}
}

function validateForm(UserForm $form): Option
{
    $email = parseEmail($form->email);
    $age = parseAge($form->age);
    $website = parseUrl($form->website);

    // All fields must be valid
    return $email->flatMap(fn($e) =>
        $age->flatMap(fn($a) =>
            $website->map(fn($w) => new ValidatedUser(
                email: $e,
                age: $a,
                website: $w,
            ))
        )
    );
}

$formData = new UserForm(
    email: 'user@EXAMPLE.COM',
    age: '25',
    website: 'https://example.com',
);

$validated = validateForm($formData);

$validated->match(
    some: function (ValidatedUser $user) {
        Log::info('Valid user:', (array) $user);
        submitForm($user);
    },
    none: function () {
        Log::error('Validation failed');
        showValidationErrors();
    },
);

// Individual field validation with feedback
function validateEmailField(string $input): array
{
    $result = parseEmail($input);

    return $result->match(
        some: fn($email) => ['valid' => true, 'value' => $email],
        none: fn() => ['valid' => false],
    );
}
```

### 10. Nested Optional Properties

```php
final readonly class Address
{
    public function __construct(
        public ?string $street = null,
        public ?string $city = null,
        public ?string $country = null,
        public ?string $postalCode = null,
    ) {}
}

final readonly class UserProfile
{
    public function __construct(
        public string $name,
        public ?Address $address = null,
    ) {}
}

function getPostalCode(UserProfile $profile): Option
{
    return Option::fromNullable($profile->address)
        ->flatMap(fn(Address $addr) => Option::fromNullable($addr->postalCode))
        ->filter(fn($code) => strlen($code) > 0);
}

function getFullAddress(UserProfile $profile): string
{
    $street = Option::fromNullable($profile->address)
        ->flatMap(fn(Address $a) => Option::fromNullable($a->street))
        ->unwrapOr('Unknown Street');

    $city = Option::fromNullable($profile->address)
        ->flatMap(fn(Address $a) => Option::fromNullable($a->city))
        ->unwrapOr('Unknown City');

    $country = Option::fromNullable($profile->address)
        ->flatMap(fn(Address $a) => Option::fromNullable($a->country))
        ->unwrapOr('Unknown Country');

    return "{$street}, {$city}, {$country}";
}

// Usage
$profile1 = new UserProfile(
    name: 'Alice',
    address: new Address(
        street: '123 Main St',
        city: 'New York',
        country: 'USA',
        postalCode: '10001',
    ),
);

$profile2 = new UserProfile(name: 'Bob');

echo getPostalCode($profile1)->unwrapOr('No postal code'); // "10001"
echo getPostalCode($profile2)->unwrapOr('No postal code'); // "No postal code"
echo getFullAddress($profile2); // "Unknown Street, Unknown City, Unknown Country"
```

### 11. Combining Options

```php
function combineOptions(array $options): Option
{
    $values = [];

    foreach ($options as $opt) {
        if ($opt->isNone()) {
            return new None();
        }
        $values[] = $opt->unwrap();
    }

    return new Some($values);
}

// Usage: Load multiple required resources
function loadApplicationData(): Option
{
    $user = fetchCurrentUser();
    $settings = fetchSettings();
    $permissions = fetchPermissions();

    return combineOptions([$user, $settings, $permissions])
        ->map(fn(array $results) => [
            'user' => $results[0],
            'settings' => $results[1],
            'permissions' => $results[2],
        ]);
}

$appData = loadApplicationData();

$appData->match(
    some: fn(array $data) => initializeApp($data),
    none: fn() => abort(500, 'Failed to load application data'),
);
```

### 12. Laravel Eloquent Database Query Results

```php
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Post;

function findUserByEmail(string $email): Option
{
    $user = User::where('email', $email)->first();
    return Option::fromNullable($user);
}

function findPostById(int $id): Option
{
    $post = Post::find($id);
    return Option::fromNullable($post);
}

function getLatestPost(): Option
{
    $post = Post::latest()->first();
    return Option::fromNullable($post);
}

// Usage with relationships
function getUserWithPosts(string $email): Option
{
    return findUserByEmail($email)
        ->map(function (User $user) {
            $user->load('posts');
            return $user;
        });
}

// Controller usage
class ProfileController extends Controller
{
    public function show(string $email)
    {
        return getUserWithPosts($email)->match(
            some: fn(User $user) => view('profile.show', [
                'user' => $user,
                'posts' => $user->posts,
                'postCount' => $user->posts->count(),
            ]),
            none: fn() => abort(404, 'User not found'),
        );
    }
}

// Safe eager loading
function getUserWithRelations(int $id, array $relations = []): Option
{
    return Option::fromNullable(User::with($relations)->find($id));
}

// Usage
getUserWithRelations(1, ['posts', 'comments'])->match(
    some: fn(User $user) => view('user.detail', compact('user')),
    none: fn() => redirect()->route('users.index')->with('error', 'User not found'),
);
```

## Advanced Patterns

### Option vs Nullable Conversion

```php
// Convert Option to nullable
$maybeValue = new Some('hello');
$nullable = $maybeValue->unwrapOr(null);

// Convert nullable to Option
$value = getSomeValue(); // string|null
$option = Option::fromNullable($value);
```

### Filter and Map Chains

```php
$users = getUsers();

// Filter premium emails
$premiumEmails = array_filter(
    array_map(
        fn($user) => Option::fromNullable($user['email'])
            ->filter(fn($email) => str_ends_with($email, '@premium.com'))
            ->unwrapOr(null),
        $users
    ),
    fn($email) => $email !== null
);
```

### Lazy Evaluation with OrElse

```php
function findInCache(string $key): Option
{
    return Option::fromNullable(Cache::get($key));
}

function findInDatabase(string $key): Option
{
    $result = DB::table('data')->where('key', $key)->first();
    return Option::fromNullable($result);
}

function findData(string $key): Option
{
    return findInCache($key)->match(
        some: fn($data) => new Some($data),
        none: function () use ($key) {
            $dbResult = findInDatabase($key);
            $dbResult->match(
                some: function ($data) use ($key) {
                    Cache::put($key, $data, 3600);
                    return $data;
                },
                none: fn() => null,
            );
            return $dbResult;
        },
    );
}
```

## Best Practices

### 1. Use Option Instead of Null Checks

```php
// ❌ Bad: Null checks everywhere
function getUsername(?User $user): string
{
    if ($user === null) {
        return 'Guest';
    }
    if ($user->name === null) {
        return 'Guest';
    }
    return $user->name;
}

// ✅ Good: Option chains
function getUsername(Option $user): string
{
    return $user
        ->flatMap(fn(User $u) => Option::fromNullable($u->name))
        ->unwrapOr('Guest');
}
```

### 2. Prefer unwrapOr to unwrap

```php
// ❌ Bad: Can throw
$value = $option->unwrap();

// ✅ Good: Always safe
$value = $option->unwrapOr($defaultValue);

// ✅ Also good: Explicit handling
$option->match(
    some: fn($v) => useValue($v),
    none: fn() => useDefault(),
);
```

### 3. Use Filter for Conditional Logic

```php
// ✅ Good: Filter maintains Option chain
$adult = parseAge($input)
    ->filter(fn($age) => $age >= 18)
    ->map(fn($age) => ['age' => $age, 'status' => 'adult']);

$adult->match(
    some: fn($person) => Log::info('Adult:', $person),
    none: fn() => Log::info('Not an adult or invalid age'),
);
```

### 4. Avoid Nested Options

```php
// ❌ Bad: Option<Option<User>>
$nested = new Some(findUser('123'));

// ✅ Good: Flatten with flatMap
$flat = (new Some('123'))->flatMap(fn($id) => findUser($id));
```

## When to Use Option

✅ **Use Option when:**
- Representing values that may be absent
- Replacing `null` in domain logic
- Chaining operations where intermediate values might be missing
- Working with collections that might be empty
- Parsing or validating user input

❌ **Don't use Option when:**
- You need exception context (use Result or Either instead)
- The value is guaranteed to exist
- You're interfacing with libraries that expect `null`
- The added verbosity doesn't improve safety

## Option vs Result vs Either

- **Option**: For presence/absence - no exception information needed
- **Result**: For operations that succeed or fail - includes exception context
- **Either**: For two equally valid outcomes - most general form

## API Reference

```php
abstract class Option
{
    // Construction
    public static function some(mixed $value): Some;
    public static function none(): None;
    public static function fromNullable(mixed $value): Option;

    // Checking
    public function isSome(): bool;
    public function isNone(): bool;

    // Unwrapping
    public function unwrap(): mixed;
    public function unwrapOr(mixed $defaultValue): mixed;
    public function expect(string $message): mixed;

    // Transformation
    public function map(callable $fn): Option;
    public function flatMap(callable $fn): Option;
    public function filter(callable $predicate): Option;

    // Combining
    public function orElse(callable $fn): Option;
    public function and(Option $other): Option;
    public function or(Option $other): Option;

    // Matching
    public function match(callable $some, callable $none): mixed;

    // Conversion
    public function okOr(mixed $exception): Result;
    public function toArray(): array;
}
```

## Common Patterns Summary

1. **Safe array access**: Use first, last, at instead of direct indexing
2. **Collection lookups**: Wrap in Option to avoid null checks
3. **Request/session data**: Make input retrieval safe by default
4. **Config loading**: Provide defaults via unwrapOr
5. **Chained access**: Use flatMap for nested optional properties
6. **Validation**: Return Option for parse operations
7. **Database queries**: Wrap nullable results in Option
8. **Fallback chains**: Use orElse for multiple sources
9. **Combining multiple**: Use combineOptions or manual flatMap chains
10. **API responses**: Handle missing data gracefully


## Overview

`Result` is a specialized Either monad designed specifically for exception handling with Ok/Err semantics. It's semantically clearer than Either when representing operations that succeed or fail, making it the preferred choice for most exception handling scenarios.

## Core Concept

```php
use Cline\Monad\Result\Result;
use Cline\Monad\Result\Ok;
use Cline\Monad\Result\Err;

// Result<T, E> = Ok<T> | Err<E>
```

- **Ok**: Contains the success value of type `T`
- **Err**: Contains the exception value of type `E`
- **Semantics**: Makes success/failure intent explicit in code

## Basic Usage

### Creating Result Values

```php
use Cline\Monad\Result\{Ok, Err};

// Success case
$success = new Ok(42);

// Failure case
$failure = new Err(new RuntimeException('Operation failed'));

// With custom exception type
final readonly class AppError
{
    public function __construct(
        public string $code,
        public string $message,
    ) {}
}

$customException = new Err(new AppError(
    code: 'USER_NOT_FOUND',
    message: 'User does not exist',
));
```

### Pattern Matching

```php
$message = $success->match(
    ok: fn($value) => "Success: {$value}",
    err: fn($exception) => "Error: {$exception->getMessage()}",
);
```

## Real-World Examples

### 1. Safe Division and Math Operations

```php
enum MathError: string
{
    case DIVISION_BY_ZERO = 'DIVISION_BY_ZERO';
    case INVALID_INPUT = 'INVALID_INPUT';
    case OVERFLOW = 'OVERFLOW';
}

function safeDivide(float $a, float $b): Result
{
    if (!is_finite($a) || !is_finite($b)) {
        return new Err(MathError::INVALID_INPUT);
    }

    if ($b === 0.0) {
        return new Err(MathError::DIVISION_BY_ZERO);
    }

    $result = $a / $b;

    if (!is_finite($result)) {
        return new Err(MathError::OVERFLOW);
    }

    return new Ok($result);
}

// Chain math operations safely
function calculateRatio(float $total, float $count): Result
{
    return safeDivide($total, $count)
        ->map(fn($ratio) => $ratio * 100)
        ->map(fn($percentage) => number_format($percentage, 2) . '%');
}

// Usage
$ratio = calculateRatio(100, 4);
$ratio->match(
    ok: fn($value) => Log::info("Ratio: {$value}"), // "Ratio: 25.00%"
    err: fn($error) => Log::error("Math error: {$error->value}"),
);

// Handle division by zero gracefully
$invalid = calculateRatio(100, 0);
$invalid->match(
    ok: fn($value) => Log::info($value),
    err: fn($error) => Log::error("Cannot calculate: {$error->value}"), // "Cannot calculate: DIVISION_BY_ZERO"
);
```

### 2. JSON Parsing with Validation

```php
enum ParseErrorType: string
{
    case INVALID_JSON = 'INVALID_JSON';
    case VALIDATION_FAILED = 'VALIDATION_FAILED';
}

final readonly class ParseError
{
    public function __construct(
        public ParseErrorType $type,
        public string $message,
        public ?string $path = null,
    ) {}
}

function parseJson(string $json): Result
{
    try {
        $data = json_decode($json, associative: true, flags: JSON_THROW_ON_ERROR);
        return new Ok($data);
    } catch (JsonException $e) {
        return new Err(new ParseError(
            type: ParseErrorType::INVALID_JSON,
            message: $e->getMessage(),
        ));
    }
}

final readonly class UserConfig
{
    public function __construct(
        public string $apiKey,
        public string $endpoint,
        public int $timeout,
    ) {}
}

function validateUserConfig(mixed $data): Result
{
    if (!is_array($data)) {
        return new Err(new ParseError(
            type: ParseErrorType::VALIDATION_FAILED,
            message: 'Config must be an array',
        ));
    }

    if (!isset($data['apiKey']) || !is_string($data['apiKey'])) {
        return new Err(new ParseError(
            type: ParseErrorType::VALIDATION_FAILED,
            message: 'apiKey is required and must be a string',
            path: 'apiKey',
        ));
    }

    if (!isset($data['endpoint']) || !is_string($data['endpoint'])) {
        return new Err(new ParseError(
            type: ParseErrorType::VALIDATION_FAILED,
            message: 'endpoint is required and must be a string',
            path: 'endpoint',
        ));
    }

    if (!isset($data['timeout']) || !is_int($data['timeout']) || $data['timeout'] < 0) {
        return new Err(new ParseError(
            type: ParseErrorType::VALIDATION_FAILED,
            message: 'timeout must be a positive integer',
            path: 'timeout',
        ));
    }

    return new Ok(new UserConfig(
        apiKey: $data['apiKey'],
        endpoint: $data['endpoint'],
        timeout: $data['timeout'],
    ));
}

function loadConfig(string $jsonString): Result
{
    return parseJson($jsonString)->flatMap(fn($data) => validateUserConfig($data));
}

// Usage
$configJson = '{"apiKey":"abc123","endpoint":"https://api.example.com","timeout":5000}';
$config = loadConfig($configJson);

$config->match(
    ok: function (UserConfig $cfg) {
        Log::info('Config loaded:', (array) $cfg);
        // Initialize app with config
    },
    err: function (ParseError $error) {
        Log::error("Config error [{$error->type->value}]: {$error->message}");
        if ($error->path) {
            Log::error("  at: {$error->path}");
        }
        // Use default config
    },
);
```

### 3. Laravel Database Transactions

```php
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Account;
use App\Models\Transaction;

enum DbErrorType: string
{
    case CONNECTION_FAILED = 'CONNECTION_FAILED';
    case QUERY_FAILED = 'QUERY_FAILED';
    case CONSTRAINT_VIOLATION = 'CONSTRAINT_VIOLATION';
    case TRANSACTION_FAILED = 'TRANSACTION_FAILED';
    case NOT_FOUND = 'NOT_FOUND';
}

final readonly class DbError
{
    public function __construct(
        public DbErrorType $type,
        public string $message,
        public ?string $model = null,
        public ?string $constraint = null,
    ) {}
}

function transferFunds(int $fromAccountId, int $toAccountId, int $amountInCents): Result
{
    try {
        return DB::transaction(function () use ($fromAccountId, $toAccountId, $amountInCents) {
            // Lock accounts for update
            $fromAccount = Account::lockForUpdate()->find($fromAccountId);
            $toAccount = Account::lockForUpdate()->find($toAccountId);

            if (!$fromAccount) {
                return new Err(new DbError(
                    type: DbErrorType::NOT_FOUND,
                    message: 'Source account not found',
                    model: 'Account',
                ));
            }

            if (!$toAccount) {
                return new Err(new DbError(
                    type: DbErrorType::NOT_FOUND,
                    message: 'Destination account not found',
                    model: 'Account',
                ));
            }

            if ($fromAccount->balance_cents < $amountInCents) {
                return new Err(new DbError(
                    type: DbErrorType::CONSTRAINT_VIOLATION,
                    message: 'Insufficient funds',
                    constraint: 'balance_check',
                ));
            }

            // Perform transfer
            $fromAccount->decrement('balance_cents', $amountInCents);
            $toAccount->increment('balance_cents', $amountInCents);

            // Create transaction record
            Transaction::create([
                'from_account_id' => $fromAccountId,
                'to_account_id' => $toAccountId,
                'amount_cents' => $amountInCents,
                'type' => 'transfer',
            ]);

            return new Ok([
                'from' => $fromAccount->fresh(),
                'to' => $toAccount->fresh(),
            ]);
        });
    } catch (Throwable $e) {
        return new Err(new DbError(
            type: DbErrorType::TRANSACTION_FAILED,
            message: $e->getMessage(),
        ));
    }
}

// Usage in Controller
class TransferController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'from_account_id' => 'required|exists:accounts,id',
            'to_account_id' => 'required|exists:accounts,id',
            'amount' => 'required|integer|min:1',
        ]);

        return transferFunds(
            $validated['from_account_id'],
            $validated['to_account_id'],
            $validated['amount']
        )->match(
            ok: function (array $accounts) {
                Log::info('Transfer completed successfully');

                return redirect()->route('accounts.show', $accounts['from']->id)
                    ->with('success', 'Transfer completed successfully');
            },
            err: function (DbError $error) {
                Log::error("Transfer failed: {$error->type->value}", [
                    'message' => $error->message,
                ]);

                return back()->withErrors([
                    'transfer' => match ($error->type) {
                        DbErrorType::NOT_FOUND => 'Account not found',
                        DbErrorType::CONSTRAINT_VIOLATION => $error->message,
                        default => 'Transfer failed. Please try again later.',
                    }
                ])->withInput();
            },
        );
    }
}
```

### 4. Laravel File Upload with Validation

```php
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

enum UploadErrorType: string
{
    case FILE_TOO_LARGE = 'FILE_TOO_LARGE';
    case INVALID_TYPE = 'INVALID_TYPE';
    case UPLOAD_FAILED = 'UPLOAD_FAILED';
    case VALIDATION_FAILED = 'VALIDATION_FAILED';
}

final readonly class UploadError
{
    public function __construct(
        public UploadErrorType $type,
        public string $message,
        public ?int $maxSizeKb = null,
        public ?int $actualSizeKb = null,
        public ?array $allowedTypes = null,
    ) {}
}

final readonly class FileUploadResult
{
    public function __construct(
        public string $path,
        public string $url,
        public string $fileName,
        public int $sizeBytes,
        public string $mimeType,
    ) {}
}

function validateUploadedFile(UploadedFile $file, int $maxSizeKb = 10240): Result
{
    // Check file is valid
    if (!$file->isValid()) {
        return new Err(new UploadError(
            type: UploadErrorType::VALIDATION_FAILED,
            message: 'Invalid file upload',
        ));
    }

    // Check size
    $sizeKb = $file->getSize() / 1024;
    if ($sizeKb > $maxSizeKb) {
        return new Err(new UploadError(
            type: UploadErrorType::FILE_TOO_LARGE,
            message: 'File too large',
            maxSizeKb: $maxSizeKb,
            actualSizeKb: (int) $sizeKb,
        ));
    }

    // Check type
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
    if (!in_array($file->getMimeType(), $allowedTypes, true)) {
        return new Err(new UploadError(
            type: UploadErrorType::INVALID_TYPE,
            message: 'Invalid file type',
            allowedTypes: $allowedTypes,
        ));
    }

    return new Ok($file);
}

function storeFile(UploadedFile $file, string $disk = 's3', string $directory = 'uploads'): Result
{
    try {
        $path = $file->store($directory, $disk);

        if (!$path) {
            return new Err(new UploadError(
                type: UploadErrorType::UPLOAD_FAILED,
                message: 'Failed to store file',
            ));
        }

        return new Ok(new FileUploadResult(
            path: $path,
            url: Storage::disk($disk)->url($path),
            fileName: $file->getClientOriginalName(),
            sizeBytes: $file->getSize(),
            mimeType: $file->getMimeType(),
        ));
    } catch (Throwable $e) {
        return new Err(new UploadError(
            type: UploadErrorType::UPLOAD_FAILED,
            message: $e->getMessage(),
        ));
    }
}

function uploadFile(UploadedFile $file): Result
{
    return validateUploadedFile($file)
        ->flatMap(fn($validFile) => storeFile($validFile));
}

// Usage in Controller
class FileUploadController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'file' => 'required|file',
        ]);

        return uploadFile($request->file('file'))->match(
            ok: function (FileUploadResult $result) {
                Log::info('File uploaded successfully', ['path' => $result->path]);

                return response()->json([
                    'success' => true,
                    'file' => [
                        'url' => $result->url,
                        'name' => $result->fileName,
                        'size' => $result->sizeBytes,
                    ],
                ]);
            },
            err: function (UploadError $error) {
                Log::error('File upload failed', [
                    'type' => $error->type->value,
                    'message' => $error->message,
                ]);

                $message = match ($error->type) {
                    UploadErrorType::FILE_TOO_LARGE => sprintf(
                        'File too large (max %dMB)',
                        $error->maxSizeKb / 1024
                    ),
                    UploadErrorType::INVALID_TYPE => sprintf(
                        'Invalid file type. Allowed: %s',
                        implode(', ', $error->allowedTypes)
                    ),
                    default => 'Upload failed: ' . $error->message,
                };

                return response()->json(['error' => $message], 400);
            },
        );
    }
}
```

### 5. Environment Variable Loading

```php
enum EnvErrorType: string
{
    case MISSING_REQUIRED = 'MISSING_REQUIRED';
    case INVALID_FORMAT = 'INVALID_FORMAT';
    case OUT_OF_RANGE = 'OUT_OF_RANGE';
}

final readonly class EnvError
{
    public function __construct(
        public EnvErrorType $type,
        public string $key,
        public ?string $expected = null,
        public ?string $actual = null,
        public ?int $min = null,
        public ?int $max = null,
    ) {}
}

function getEnv(string $key): Result
{
    $value = env($key);

    if ($value === null) {
        return new Err(new EnvError(
            type: EnvErrorType::MISSING_REQUIRED,
            key: $key,
        ));
    }

    return new Ok($value);
}

function getEnvAsInt(string $key): Result
{
    return getEnv($key)->flatMap(function ($value) use ($key) {
        if (!is_numeric($value)) {
            return new Err(new EnvError(
                type: EnvErrorType::INVALID_FORMAT,
                key: $key,
                expected: 'integer',
                actual: (string) $value,
            ));
        }

        return new Ok((int) $value);
    });
}

function getEnvAsPort(string $key): Result
{
    return getEnvAsInt($key)->flatMap(function (int $port) use ($key) {
        if ($port < 1 || $port > 65535) {
            return new Err(new EnvError(
                type: EnvErrorType::OUT_OF_RANGE,
                key: $key,
                min: 1,
                max: 65535,
            ));
        }
        return new Ok($port);
    });
}

function getEnvAsUrl(string $key): Result
{
    return getEnv($key)->flatMap(function ($value) use ($key) {
        if (!filter_var($value, FILTER_VALIDATE_URL)) {
            return new Err(new EnvError(
                type: EnvErrorType::INVALID_FORMAT,
                key: $key,
                expected: 'URL',
                actual: (string) $value,
            ));
        }

        return new Ok($value);
    });
}

final readonly class AppEnv
{
    public function __construct(
        public string $appEnv,
        public int $port,
        public string $databaseUrl,
        public string $apiKey,
        public string $logLevel,
    ) {}
}

function loadEnvironment(): Result
{
    $appEnv = getEnv('APP_ENV')->unwrapOr('production');

    $portResult = getEnvAsPort('APP_PORT');
    if ($portResult->isErr()) {
        return $portResult;
    }

    $dbUrlResult = getEnvAsUrl('DATABASE_URL');
    if ($dbUrlResult->isErr()) {
        return $dbUrlResult;
    }

    $apiKeyResult = getEnv('API_KEY');
    if ($apiKeyResult->isErr()) {
        return $apiKeyResult;
    }

    $logLevel = getEnv('LOG_LEVEL')->unwrapOr('info');

    return new Ok(new AppEnv(
        appEnv: $appEnv,
        port: $portResult->unwrap(),
        databaseUrl: $dbUrlResult->unwrap(),
        apiKey: $apiKeyResult->unwrap(),
        logLevel: $logLevel,
    ));
}

// Usage: Bootstrap Laravel application
$env = loadEnvironment();

$env->match(
    ok: function (AppEnv $config) {
        Log::info('Environment loaded successfully');

        // Configure Laravel app with validated environment
        config(['app.env' => $config->appEnv]);
        config(['app.log_level' => $config->logLevel]);

        // Continue application bootstrap...
    },
    err: function (EnvError $error) {
        $message = match ($error->type) {
            EnvErrorType::MISSING_REQUIRED => "Missing required variable: {$error->key}",
            EnvErrorType::INVALID_FORMAT => sprintf(
                'Invalid format for %s: expected %s, got "%s"',
                $error->key,
                $error->expected,
                $error->actual
            ),
            EnvErrorType::OUT_OF_RANGE => sprintf(
                'Value for %s out of range (%d-%d)',
                $error->key,
                $error->min,
                $error->max
            ),
        };

        Log::error('Environment configuration error: ' . $message);
        exit(1);
    },
);
```

### 6. Laravel HTTP Client with Retries

```php
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Client\RequestException;

final readonly class HttpError
{
    public function __construct(
        public int $status,
        public string $statusText,
        public string $url,
        public ?string $body,
        public bool $retryable,
    ) {}
}

final readonly class HttpResponse
{
    public function __construct(
        public int $status,
        public mixed $data,
        public array $headers,
    ) {}
}

function httpGet(string $url, array $options = []): Result
{
    try {
        $response = Http::timeout($options['timeout'] ?? 10)
            ->retry($options['retries'] ?? 3, $options['retryDelay'] ?? 100)
            ->get($url);

        if (!$response->successful()) {
            return new Err(new HttpError(
                status: $response->status(),
                statusText: $response->reason(),
                url: $url,
                body: $response->body(),
                retryable: $response->status() >= 500 || $response->status() === 429,
            ));
        }

        return new Ok(new HttpResponse(
            status: $response->status(),
            data: $response->json() ?? $response->body(),
            headers: $response->headers(),
        ));
    } catch (RequestException $e) {
        return new Err(new HttpError(
            status: $e->response?->status() ?? 0,
            statusText: 'Request Failed',
            url: $url,
            body: $e->getMessage(),
            retryable: true,
        ));
    } catch (Throwable $e) {
        return new Err(new HttpError(
            status: 0,
            statusText: 'Network Error',
            url: $url,
            body: $e->getMessage(),
            retryable: true,
        ));
    }
}

// Usage in a Service
class GithubService
{
    public function getRepository(string $owner, string $repo): Result
    {
        $url = "https://api.github.com/repos/{$owner}/{$repo}";

        return httpGet($url, [
            'timeout' => 5,
            'retries' => 3,
            'retryDelay' => 200,
        ])->map(fn(HttpResponse $response) => $response->data);
    }
}

// Usage in Controller
class GithubController extends Controller
{
    public function show(string $owner, string $repo, GithubService $github)
    {
        return $github->getRepository($owner, $repo)->match(
            ok: function (array $data) {
                Log::info("Fetched repo: {$data['name']}");

                return view('github.show', [
                    'name' => $data['name'],
                    'description' => $data['description'],
                    'stars' => $data['stargazers_count'],
                    'url' => $data['html_url'],
                ]);
            },
            err: function (HttpError $error) {
                Log::error("Failed to fetch GitHub repo", [
                    'status' => $error->status,
                    'url' => $error->url,
                ]);

                return match ($error->status) {
                    404 => abort(404, 'Repository not found'),
                    403 => abort(429, 'Rate limit exceeded'),
                    default => abort(500, 'Failed to fetch repository'),
                };
            },
        );
    }
}
```

### 7. Laravel Artisan Command Validation

```php
use Illuminate\Console\Command;

enum CommandErrorType: string
{
    case MISSING_REQUIRED = 'MISSING_REQUIRED';
    case INVALID_VALUE = 'INVALID_VALUE';
    case VALIDATION_FAILED = 'VALIDATION_FAILED';
}

final readonly class CommandError
{
    public function __construct(
        public CommandErrorType $type,
        public string $field,
        public ?string $value = null,
        public ?string $reason = null,
    ) {}
}

final readonly class DeploymentConfig
{
    public function __construct(
        public string $environment,
        public bool $migrateDatabase,
        public bool $clearCache,
        public string $branch,
    ) {}
}

function validateDeploymentInput(array $input): Result
{
    // Validate environment
    if (!isset($input['environment'])) {
        return new Err(new CommandError(
            type: CommandErrorType::MISSING_REQUIRED,
            field: 'environment',
        ));
    }

    $validEnvs = ['staging', 'production'];
    if (!in_array($input['environment'], $validEnvs, true)) {
        return new Err(new CommandError(
            type: CommandErrorType::INVALID_VALUE,
            field: 'environment',
            value: $input['environment'],
            reason: 'Must be staging or production',
        ));
    }

    // Validate branch for production
    if ($input['environment'] === 'production' && ($input['branch'] ?? 'main') !== 'main') {
        return new Err(new CommandError(
            type: CommandErrorType::VALIDATION_FAILED,
            field: 'branch',
            value: $input['branch'],
            reason: 'Production must deploy from main branch',
        ));
    }

    return new Ok(new DeploymentConfig(
        environment: $input['environment'],
        migrateDatabase: $input['migrate'] ?? false,
        clearCache: $input['clear-cache'] ?? true,
        branch: $input['branch'] ?? 'main',
    ));
}

// Usage in Artisan Command
class DeployCommand extends Command
{
    protected $signature = 'app:deploy
        {environment : The environment to deploy to}
        {--branch=main : Git branch to deploy}
        {--migrate : Run database migrations}
        {--clear-cache : Clear application cache}';

    protected $description = 'Deploy the application';

    public function handle(): int
    {
        $result = validateDeploymentInput([
            'environment' => $this->argument('environment'),
            'branch' => $this->option('branch'),
            'migrate' => $this->option('migrate'),
            'clear-cache' => $this->option('clear-cache'),
        ]);

        return $result->match(
            ok: function (DeploymentConfig $config) {
                $this->info("Deploying to {$config->environment}...");

                if ($config->migrateDatabase) {
                    $this->call('migrate', ['--force' => true]);
                }

                if ($config->clearCache) {
                    $this->call('cache:clear');
                }

                $this->info('Deployment completed successfully!');
                return Command::SUCCESS;
            },
            err: function (CommandError $error) {
                $message = match ($error->type) {
                    CommandErrorType::MISSING_REQUIRED => "Missing required field: {$error->field}",
                    CommandErrorType::INVALID_VALUE => sprintf(
                        'Invalid %s: "%s" - %s',
                        $error->field,
                        $error->value,
                        $error->reason
                    ),
                    CommandErrorType::VALIDATION_FAILED => "{$error->field}: {$error->reason}",
                };

                $this->error($message);
                return Command::FAILURE;
            },
        );
    }
}
```

## Advanced Patterns

### Collecting Results

```php
function collectResults(array $results): Result
{
    $successes = [];
    $failures = [];

    foreach ($results as $result) {
        $result->match(
            ok: fn($value) => $successes[] = $value,
            err: fn($error) => $failures[] = $error,
        );
    }

    if (count($failures) > 0) {
        return new Err($failures);
    }

    return new Ok($successes);
}

// Usage: Process multiple files
$fileResults = array_map(fn($file) => processFile($file), $files);

$collected = collectResults($fileResults);

$collected->match(
    ok: fn($processed) => Log::info('Successfully processed ' . count($processed) . ' files'),
    err: function ($errors) {
        Log::error('Failed to process ' . count($errors) . ' files:');
        foreach ($errors as $e) {
            Log::error("  - {$e->message}");
        }
    },
);
```

### Early Exit Pattern

```php
function validateAndProcess(array $data): Result
{
    $nameResult = validateName($data['name']);
    if ($nameResult->isErr()) {
        return $nameResult;
    }

    $emailResult = validateEmail($data['email']);
    if ($emailResult->isErr()) {
        return $emailResult;
    }

    $ageResult = validateAge($data['age']);
    if ($ageResult->isErr()) {
        return $ageResult;
    }

    return new Ok([
        'name' => $nameResult->unwrap(),
        'email' => $emailResult->unwrap(),
        'age' => $ageResult->unwrap(),
    ]);
}
```

### Fallback Chain

```php
function loadUserData(string $userId): Result
{
    $primary = fetchFromPrimary($userId);
    if ($primary->isOk()) {
        return $primary;
    }

    Log::warning('Primary source failed, trying cache...');
    $cached = fetchFromCache($userId);
    if ($cached->isOk()) {
        return $cached;
    }

    Log::warning('Cache miss, trying backup database...');
    $backup = fetchFromBackup($userId);
    if ($backup->isOk()) {
        return $backup;
    }

    Log::warning('All sources failed, using guest user');
    return new Ok(getGuestUser());
}
```

### Converting to Option

```php
$result = fetchUser('123');

// Convert Result to Option, discarding error
$maybeUser = $result->ok();

$maybeUser->match(
    some: fn($user) => Log::info('Found user: ' . $user->name),
    none: fn() => Log::info('User not found'),
);
```

## Best Practices

### 1. Use Enums for Exception Types

```php
// ✅ Good: Enum-based discriminated exceptions
enum ApiErrorType: string
{
    case NETWORK = 'NETWORK';
    case AUTH = 'AUTH';
    case VALIDATION = 'VALIDATION';
}

final readonly class ApiError
{
    public function __construct(
        public ApiErrorType $type,
        public string $message,
        public mixed $context = null,
    ) {}
}

function handleError(ApiError $exception): void
{
    match ($exception->type) {
        ApiErrorType::NETWORK => retry(),
        ApiErrorType::AUTH => refreshToken($exception->context),
        ApiErrorType::VALIDATION => highlightFields($exception->context),
    };
}
```

### 2. Make Exceptions Actionable

```php
// ✅ Good: Exception includes recovery information
final readonly class PaymentError
{
    public function __construct(
        public string $code,
        public string $message,
        public bool $retryable,
        public ?int $retryAfter = null,
        public ?array $supportedMethods = null,
    ) {}
}
```

### 3. Use flatMap for Sequential Operations

```php
function registerUser(array $data): Result
{
    return validateUserData($data)
        ->flatMap(fn($valid) => createUser($valid))
        ->flatMap(fn($user) => sendWelcomeEmail($user->email)->map(fn() => $user))
        ->flatMap(fn($user) => createDefaultPreferences($user->id)->map(fn() => $user));
}
```

### 4. Provide Context in Exceptions

```php
function processPayment(Payment $payment): Result
{
    return validateAmount($payment->amount)
        ->mapErr(fn($exception) => new PaymentError(
            code: $exception->code,
            message: $exception->message,
            context: [
                'paymentId' => $payment->id,
                'timestamp' => now()->toIso8601String(),
            ],
        ))
        ->flatMap(fn($amount) => chargeCard($payment->card, $amount));
}
```

## When to Use Result

✅ **Use Result when:**
- You need clear success/failure semantics
- Exceptions should be handled explicitly
- You want to chain operations that might fail
- Exception types contain useful recovery information
- You prefer Ok/Err over Left/Right naming

❌ **Don't use Result when:**
- You only care about presence/absence (use Option)
- Exceptions are more appropriate
- You need equal treatment of both branches (use Either)
- Simple null checks suffice

## Result vs Either vs Option

- **Result**: For operations that succeed (Ok) or fail (Err) - semantically clearest for exceptions
- **Either**: For operations with two equally valid outcomes - more general than Result
- **Option**: For operations that may or may not have a value - no exception context needed

## API Reference

```php
abstract class Result
{
    // Construction
    public static function ok(mixed $value): Ok;
    public static function err(mixed $error): Err;

    // Checking
    public function isOk(): bool;
    public function isErr(): bool;

    // Unwrapping (use with caution)
    public function unwrap(): mixed;
    public function unwrapErr(): mixed;
    public function unwrapOr(mixed $defaultValue): mixed;
    public function expect(string $message): mixed;

    // Transformation
    public function map(callable $fn): Result;
    public function mapErr(callable $fn): Result;
    public function flatMap(callable $fn): Result;

    // Conversion
    public function ok(): Option;
    public function err(): Option;

    // Matching
    public function match(callable $ok, callable $err): mixed;

    // Exception recovery
    public function recover(callable $fn): Result;
}
```

## Common Patterns Summary

1. **Validation pipelines**: Chain multiple validation steps with `flatMap`
2. **Database transactions**: Wrap operations in Result for rollback on exception
3. **File operations**: Make I/O exceptions explicit and recoverable
4. **API calls**: Handle network, parsing, and business logic exceptions uniformly
5. **Configuration loading**: Fail fast with clear exception messages
6. **Retry logic**: Use `retryable` flag in exceptions for smart retry behavior
7. **Early returns**: Exit validation chains as soon as exception is encountered
8. **Exception aggregation**: Collect multiple exceptions for batch processing


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
