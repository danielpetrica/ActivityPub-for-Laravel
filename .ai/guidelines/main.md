# Project Context: danielpetrica.com

Personal blog project for Daniel Petrica, focusing on tech articles.

## Core Technical Stack
- **PHP:** 8.5
- **Framework:** Laravel 13
- **Admin Panel:** Filament 5
- **Frontend:** Blade + Tailwind CSS 4
- **Server:** Laravel Octane 2

## Key Features
- Blog posts, static pages, tags
- Custom tools (e.g., HTML encode)
- Link submission system
- All managed via Filament

## Architecture
- Static route group with custom middleware for CDN caching
- Session-less frontend pages
- Image proxy from private S3
- Horizon 5 for queue management
- Nightwatch 1 for monitoring

## Deployment
Tag a new version and push to GitHub: `git tag v1.x.x && git push origin --tags`. GitHub Actions handles the rest.
