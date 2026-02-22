## Project Coding Guidelines (Reformatted + Extended)
These guidelines prioritize **readability**, **consistency**, and **maintainability** across controllers, commands, jobs, and web/API code.
---

## 1) Formatting & Consistency

### 1.1 Always format with Laravel Pint
- Keep formatting automated and consistent.
- Don’t hand-format; let Pint enforce the baseline style.

### 1.2 Prefer explicit, scannable code
- Choose clarity over cleverness.
- Add small comments even when the code feels “obvious” to help future scanning.

---

## 2) Function Calls: Named Parameters + Line Break Rules

### 2.1 Always use named parameters
Named parameters make code easier to read and safer to refactor.

```php
<?php

use Illuminate\Support\Facades\Cache;

$value = Cache::remember(
    key: 'posts.homepage',
    ttl: now()->addMinutes(10),
    callback: fn () => ['...']
);
```


### 2.2 Split long calls or calls with 3+ parameters across lines
This improves diffs and reduces horizontal scrolling.

```php
<?php

$response = Http::withHeaders([
        'Accept' => 'application/json',
    ])
    ->timeout(seconds: 10)
    ->retry(
        times: 3,
        sleepMilliseconds: 200,
        when: fn ($exception) => true
    )
    ->get(url: $url);
```


---

## 3) Prefer Laravel Native Features Over “Raw PHP”

Use Laravel helpers/collections/query builder/resources before custom PHP loops/arrays, **unless Laravel doesn’t offer a clean option**.

### Example: prefer Collections over manual array loops

```php
<?php

$names = collect($users)
    ->pluck('name')
    ->filter()
    ->values();
```


---

## 4) Enums: Centralize Domain Choices

### 4.1 Store enums in `app/Enum` (and TTL enums in `app/Enums`)
Use enums to replace “magic strings” and “mystery integers”.

```php
<?php

namespace App\Enum;

enum PostStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
}
```


### 4.2 Cache TTL: use a dedicated TTL enum
Keeps caching consistent and avoids scattered time values.

```php
<?php

namespace App\Enums;

enum CacheTtl: int
{
    case Short = 60;        // 1 minute
    case Medium = 600;      // 10 minutes
    case Long = 3600;       // 1 hour
}
```


---

## 5) Comments & Docblocks (Especially for Eloquent Models)

### 5.1 Add docblocks with model properties (`@property type $name`)
This improves IDE support, static analysis, and future maintenance.

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $title
 * @property string|null $excerpt
 * @property \Carbon\CarbonInterface $created_at
 * @property \Carbon\CarbonInterface $updated_at
 */
final class Post extends Model
{
    // ...
}
```


### 5.2 Prefer short, useful comments that explain “why”
Good comments clarify intent, edge cases, and assumptions.

```php
<?php

// We cache this because homepage traffic is high and the query is expensive.
// The controller/resource layer handles presentation.
```


---

## 6) Business Logic: Static Helpers in `app/Classes/Business`

### 6.1 Keep business logic out of controllers
- Controllers should orchestrate (validate → call business logic → return response).
- Business classes should return **data** or throw **exceptions**.
- Business classes must not return views.

```php
<?php

namespace App\Classes\Business;

use App\Models\Post;
use Illuminate\Database\Eloquent\ModelNotFoundException;

final class PostBusiness
{
    public static function findPublishedOrFail(int $postId): Post
    {
        // Keep this logic centralized so it stays consistent everywhere.
        $post = Post::query()
            ->where('id', '=', $postId)
            ->where('status', '=', 'published')
            ->first();

        if (! $post) {
            throw new ModelNotFoundException(message: 'Published post not found.');
        }

        return $post;
    }
}
```


Controller usage stays thin:

```php
<?php

namespace App\Http\Controllers;

use App\Classes\Business\PostBusiness;
use App\Http\Resources\PostResource;

final class PostController
{
    public function show(int $postId): PostResource
    {
        $post = PostBusiness::findPublishedOrFail(postId: $postId);

        return new PostResource(resource: $post);
    }
}
```


---

## 7) API Output: Prefer Resources for Formatting & Safety

Resources provide a consistent contract and reduce accidental data leaks.

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class PostResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'title' => $this->resource->title,
            'excerpt' => $this->resource->excerpt,
        ];
    }
}
```


---

## 8) Caching: Use Protected Callback Methods for Scan-Friendly Code

When caching, keep the callback logic in a dedicated method so the main flow stays readable.

```php
<?php

use App\Enums\CacheTtl;
use Illuminate\Support\Facades\Cache;

final class HomepageService
{
    public function featuredPosts(): array
    {
        return Cache::remember(
            key: 'homepage.featured-posts',
            ttl: CacheTtl::Medium->value,
            callback: fn () => $this->featuredPostsCallback()
        );
    }

    protected function featuredPostsCallback(): array
    {
        // Query + mapping live here, easy to find and modify.
        return [
            // ...
        ];
    }
}
```


---

## 9) Logging for Debugging (Use Structured Context)

Use:

```php
<?php

use Illuminate\Support\Facades\Log;

Log::debug('message', ['context' => 'arrayData']);
```


Example:

```php
<?php

Log::debug('PostBusiness: resolving published post', [
    'postId' => $postId,
    'scope' => 'published-only',
]);
```


---

## 10) Commands

### 10.1 Custom artisan commands must use the `app:` signature namespace
Keeps the command list organized and predictable.

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

final class RebuildSearchIndex extends Command
{
    protected $signature = 'app:rebuild-search-index {--force : Run even if index looks healthy}';

    protected $description = 'Rebuild the search index.';

    public function handle(): int
    {
        // ...
        return self::SUCCESS;
    }
}
```


---

## 11) Testing Workflow

- Prefer **Pest** tests.
- Do **not** use PHPUnit directly.
- When running Pest, do **not** use `--no-interaction` or `--colors=always`.

(Keep test runs simple and compatible with the project’s expectations.)

---

## 12) Blade Templates: Don’t Escape Blade by Accident

Never write `@{{ ... }}` for regular Blade output, because it escapes Blade rendering.

Use normal Blade echo:

```blade
{{-- Good: Blade renders it --}}
<div>{{ $title }}</div>
```

### Components use
Use blade components to avoid duplicating code where it make sense. 
Organize components in `resources/views/components/{category}/` diving them by component category.
We should aim at having a component for every small piece of UI like a ui system. 
Example of component categories are:
 - Forms (inputs, buttons, selects, etc.)
 - Alerts (success, error, warning, etc.)
 - Modals (confirmations, etc.) use native html modal html tag. 
 - Ui elements (cards, tables, etc.) 
 - Layouts (header, footer, Hero, etc.)
 - Keep things modular with the use of slots and parameters. 
 - Use type hinting if we need to pass data to the component. 


---

## 13) HTML: SEO + Accessibility Best Practices

### 13.1 SEO: use `itemprop` where appropriate
```blade
<article itemscope itemtype="https://schema.org/BlogPosting">
    <h1 itemprop="headline">{{ $post->title }}</h1>
    <p itemprop="description">{{ $post->excerpt }}</p>
</article>
```


### 13.2 Accessibility: prefer semantic HTML and standard attributes
```blade
<nav aria-label="Pagination">
    {{-- ... --}}
</nav>

<button type="button" aria-label="Close dialog">
    Close
</button>
```


---

## 14) Naming & Size Guidelines

### 14.1 Use descriptive names
Prefer:

- `findPublishedOrFail()` over `getPost()`
- `featuredPostsCallback()` over `handle()`

### 14.2 Prefer smaller methods (and static methods when appropriate)
- Short methods are easier to test and scan.
- Extract private/protected helpers when a method starts doing “two jobs”.

---

## 15) Suggested Development Loop (Practical Checklist)

1. Implement with clear naming + small methods.
2. Add docblocks (especially model `@property`).
3. Add structured logs where debugging might be needed.
4. Format with Pint.
5. Run PHPStan.
6. Run Pest tests.

---

If you want, I can turn this into a clean `guidelines/main.md` structure (with a short “Do / Don’t” section at the top) and a separate `guidelines/examples.md` so the rules stay short and the examples stay discoverable.
