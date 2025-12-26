# Result Monad - Complete Guide

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
