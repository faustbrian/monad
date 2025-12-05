# Option Monad - Complete Guide

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
