<?php

use App\Classes\Business\RedirectBusiness;
use App\Http\Controllers\LocalServiceController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\StaticController;
use Illuminate\Support\Facades\Route;

Route::get('/', [StaticController::class, 'welcome'])->name('welcome');

Route::get('/posts/{slug}', [StaticController::class, 'showPost'])->name('posts.show');

Route::get('/pages/{slug}', [StaticController::class, 'showPage'])->name('pages.show');

Route::get('/tag/{slug}', [StaticController::class, 'showTag'])->name('tags.show');

Route::get('/allposts/{page?}', [StaticController::class, 'postIndex'])
    ->where(name: ['page' => '[0-9]+'])
    ->name('posts.index');

Route::get('/tools/docker-traefik-generator', [StaticController::class, 'dockerTraefikGenerator'])->name('tools.docker-traefik-generator');
Route::get('/tools/{slug}', [StaticController::class, 'showTool'])->name('tools.show');

Route::get('/servizi/{service:slug}/{city:slug}', [LocalServiceController::class, 'show'])
    ->name('services.local')
    ->withoutScopedBindings();

Route::get('/demo', [StaticController::class, 'demo'])->name('demo');
// Sitemaps
Route::get('/talks/100-container-vps', [StaticController::class, 'vpsContainersTalk'])->name('talks.vps-containers');
Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap.index');
Route::get('/sitemap-pages.xml', [SitemapController::class, 'pages'])->name('sitemap.pages');
Route::get('/sitemap-posts.xml', [SitemapController::class, 'posts'])->name('sitemap.posts');
Route::get('/sitemap-tags.xml', [SitemapController::class, 'tags'])->name('sitemap.tags');
Route::get('/sitemap-tools.xml', [SitemapController::class, 'tools'])->name('sitemap.tools');
Route::get('/sitemap-services.xml', [SitemapController::class, 'services'])->name('sitemap.services');

// Register custom redirects from cache
foreach (RedirectBusiness::getActiveRedirects() as $redirect) {
    Route::redirect($redirect->path, $redirect->destination_url);
}
