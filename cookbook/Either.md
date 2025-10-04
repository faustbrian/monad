# Either Monad - Complete Guide

## Overview

`Either` is a powerful monad for handling computations that can return one of two possible types - conventionally a **Left** (representing failure/exception) or **Right** (representing success). Unlike throwing exceptions, Either makes exception handling explicit and type-safe.

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
