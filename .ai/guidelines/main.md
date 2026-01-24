# Project Context: danielpetrica.com

Personal blog project for Daniel Petrica, focusing on tech articles.

## Core Technical Stack
- **Framework:** Laravel 12
- **Admin Panel:** Filament PHP v5
- **Frontend:** Blade templates with Tailwind CSS (No JavaScript framework)
- **Styling:** Clean theme, performance-optimized, SEO-friendly

## Key Features
- **Content Management:** Blog posts, static pages, and tags.
- **Interactive Tools:** Custom small tools (e.g., HTML encode) integrated into pages.
- **Community:** Link submission system for users to share links on the main site.
- **Backend Management:** All content and tools managed via Filament.

## Architecture & Performance
- **CDN Caching:** Use a static route group with a customized middleware stack.
- **Session-less Frontend:** Frontend pages must not set sessions or cookies to allow for edge CDN caching.
- **Performance:** Optimized for fast delivery and high performance.

## Authoring
- **Single Author:** Personal articles only; no external authors for now.
