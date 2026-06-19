# Remaining Improvements

Items from the code review not yet implemented, grouped by priority.

---

## Medium Priority

### Duplicate TTL Enums
- `app/Enums/CacheTtl.php` and `app/Enums/TTLEnum.php` overlap (both have 3600s entries).
- Consolidate into one enum; `TTLEnum` is only used by `SetCacheControlHeader`.

### Horizon Gate Empty
- `app/Providers/HorizonServiceProvider.php:30-34` — `viewHorizon` gate checks an empty array. No one can access Horizon in non-local environments.
- Intended or needs a real admin email?

### OgImageBusiness Code Duplication
- `app/Classes/Business/OgImageBusiness.php` — `generateForPost()`, `generateForPage()`, `generateForTag()` share ~90% logic.
- Refactor into a single `generateForModel()` with a config array.

### Business Logic in Controller
- `app/Http/Controllers/LocalServiceController.php:31-72` — `replacePlaceholders()` and `generateCityLinks()` are business logic inside a controller.
- Extract into `app/Classes/Business/LocalServiceBusiness.php`.

### Structured Data Hardcoded in Controller
- `app/Http/Controllers/StaticController.php:104-137` — 33-line structured data array inline in `dockerTraefikGenerator()`.
- Move to `App\Classes\SEO\StructuredData::getSoftwareApplication()`.

### ContactController Missing Error Handling
- `app/Http/Controllers/ContactController.php:14-19` — `Mail::send()` has no try/catch; SMTP failures propagate as 500s.

### No-op Middleware
- `app/Http/Middleware/LogMcpRequest.php` and `AnalyticsMiddleware.php` — both are stubs that just call `$next($request)`.
- Either implement or remove.

### NewsletterSubscriptionController Dead Code
- `app/Http/Controllers/NewsletterSubscriptionController.php:32-34` — empty `->withInput()` call and comment referencing `?subscribed=0` that's never constructed.

### Redis Persistent Connections
- `config/database.php:153` — `REDIS_PERSISTENT=false` default. Enable for Octane.

### No Queue Jobs Exist
- `config/horizon.php:209` — `tries: 1` is too low if jobs are added later. Consider `tries: 3`.
- `config/queue.php` — `after_commit: false` everywhere; set to `true` when adding jobs.

---

## Low Priority

### Code Style & Consistency
- **Named parameters inconsistency** — `TrackerController`, `LocalServiceController`, `AnnouncementBusiness`, `StructuredData`, `GhostImportBusiness` use positional params where the rest of the codebase uses named.
- **9 models use old `$casts` property** instead of `casts()` method (Laravel 11+ convention). Models: Post, Page, Tag, Tool, Comment, NewsletterForm, City, Service, CaseStudy.
- **API controllers inconsistently extend Controller** — `Api\AuthController`, `LikeController`, `CommentController`, `TrackerController` don't extend `App\Http\Controllers\Controller` while `Api\PostController` and `PageController` do.
- **Italian comments** in `LocalServiceController.php:14,20,27,30`.
- **PageResource missing `status` field** while `PostResource` has it.

### Models & Migrations
- **Zero query scopes** — no `scopePublished()`, `scopeApproved()`, `scopeActive()` on any model.
- **`StoreCommentRequest` missing `authorize()` method** — other FormRequests have it explicitly.
- **`Like` model has no casts at all**.
- **`PageViewFactory` uses hardcoded IDs** (`viewable_id => 1`) instead of `Post::factory()`.
- **`agent_conversations` missing FK constraints** on `user_id` and `conversation_id`.
- **Like deduplication is IP-based only** — same IP+post+24h. Easily bypassed with VPNs/proxies.
- **Inconsistent pivot table patterns** — `post_tag` has no `id`/timestamps while `announcement_tag` and `case_study_service` do.

### SEO / Accessibility
- **`twitter:site`** uses `config('app.url')` instead of a Twitter handle in `app.blade.php:38`.
- **`/tag/{slug}` path is singular, route name is plural** (`tags.show`) — `static.php:20`.
- **Footer `<h4>` skips heading levels** — `footer.blade.php:16,35,57`. Should be `<h2>` or styled `<p>`.
- **Vue CDN script not deferred** — `docker-traefik-generator.blade.php:146`.
- **Google Fonts via `@import`** is render-blocking — `app.blade.php:56-58`. Use `<link rel="preconnect">` + `<link rel="stylesheet">` instead.
- **No `rel="preload"` for critical fonts**.
- **Welcome page has no structured data** — `welcome.blade.php` passes no `:structuredData`.
- **Hardcoded domain** in `StructuredData.php:31-34` — uses `https://danielpetrica.com` instead of `config('app.url')`.

### Config / Infrastructure
- **No CSP header** configured — defense-in-depth against XSS.
- **Sitemap `index` missing `<lastmod>`** on the external `random.danielpetrica.com` reference.
- **Robots.txt missing `Sitemap:`** directive — `public/robots.txt`.
- **Legacy HTML files in `public/`** — `homepage.html`, `tagArchive.html`, `comunity_links.html`, `singlePost.html`. Old design mockups; could be indexed by search engines.

### Cache & Performance
- **Cache key naming inconsistency** — mix of kebab-case (`posts.top-viewed`), dot notation (`tags.popular`), and dashes (`og-image.homepage`).
- **`OgImageBusiness` uses `now()->addDay()`** instead of `CacheTtl::Day->value`.
- **SitemapController has no caching** — 7 uncached `->get()` calls on sitemap endpoints.
- **`StructuredData::getOfferCatalog`** — uncached `Service::where('is_active', true)->get()`.
- **`StructuredData::getProfessionalService`** — uncached `City::all()`.

### XSS / Output Safety (Admin-Only Trusted Content)
- `tool-show.blade.php:14` — `{!! $tool->html_content !!}` — raw HTML from plain `<textarea>`, no sanitization at all.
- `post-show.blade.php:91` / `page-show.blade.php:47` — Tiptap JSON → HTML via `RenderPostHtmlAction` with no sanitization.
- `components/ui/announcement-bar.blade.php:11` — `{!! $announcement->text !!}` with no sanitization.
- `components/services/case-study.blade.php:21` — `{!! $caseStudy->description !!}` with no sanitization.
- `components/layouts/hero.blade.php:37,99,104` — `{!! $title !!}` and `{!! $excerpt !!}` are raw.
- Consider `stevebauman/purify` or `mews/purifier` as defense-in-depth against compromised admin account.
