# danielpetrica.com

Personal blog and tech hub for Daniel Petrica. Designed for speed, performance, and SEO.

## 🚀 Overview

This is a personal blog project built with **Laravel 12** and **Filament PHP v5**. The primary goal is to provide a fast, user-friendly platform for sharing tech articles, interactive tools, and community-driven links, while maintaining an architecture optimized for edge CDN caching.

## 🛠 Tech Stack

- **Backend:** [Laravel 12](https://laravel.com)
- **Admin Panel:** [Filament PHP v5](https://filamentphp.com)
- **Frontend:** Blade templates & Tailwind CSS (No heavy JS frameworks)
- **Database:** SQLite (default)
- **Testing:** Pest PHP

## 🏗 Key Features

- **Personal Blog:** Full-featured blog with tag management.
- **Static Pages:** Easily manageable static content.
- **Interactive Tools:** Custom-built small tools (e.g., HTML encode/decode) integrated directly into pages.
- **Community Links:** A dedicated section where users can submit and share interesting links.
- **SEO Optimized:** Clean HTML, fast load times, and optimized metadata.

## ⚡ Architecture & Performance

One of the unique aspects of this project is its **session-less frontend**.
- **Edge CDN Caching:** All frontend routes are designed to set no sessions or cookies.
- **Custom Middleware:** A specialized middleware stack handles frontend requests to ensure they remain anonymous and cacheable at the edge.
- **Performance:** Minimalist design with no JavaScript framework overhead.

## 💻 Getting Started

### Prerequisites

- PHP 8.2+
- Composer
- Node.js & NPM
- SQLite (or your preferred database engine)

### Quick Start

The project includes a convenient setup script:

```bash
composer setup
```

This will:
1. Install Composer dependencies.
2. Create your `.env` file.
3. Generate the application key.
4. Run database migrations.
5. Install and build frontend assets.

### Local Development

To start the development server (includes Laravel server, Vite, and workers):

```bash
composer dev
```

Alternatively, if you prefer using **Laravel Sail**:

```bash
./vendor/bin/sail up
```

## 🧪 Testing & Quality

We use **Pest** for testing and **Laravel Pint** for code styling.

- **Run tests:** `composer test` or `php artisan test`
- **Lint code:** `vendor/bin/pint`

## 👤 Author

- **Daniel Petrica** - [danielpetrica.com](https://danielpetrica.com)

## 📄 License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
