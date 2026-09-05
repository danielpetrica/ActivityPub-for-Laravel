---
paths:
  - '**/.env*'
---

# General

## Production APP_ENV must be production
When `APP_ENV=local` or `APP_DEBUG=true` in production, Laravel's `@vite` directive connects to the Vite dev server instead of reading `public/build/manifest.json`. Always verify `APP_ENV=production` and `APP_DEBUG=false` on the server, then run `php artisan config:cache`.
