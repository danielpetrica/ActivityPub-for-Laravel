<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * One-time import of the header and footer links that were configured on
     * the old Ghost install (stage.danielpetrica.com). Add-only: existing
     * links are never touched or deleted.
     */
    public function up(): void
    {
        $links = [
            // Header
            ['position' => 'header', 'label' => 'Freelance services', 'url' => '/pages/work-with-me/', 'sort_order' => 1, 'is_external' => false],
            ['position' => 'header', 'label' => 'LaraPlugins.io', 'url' => 'https://laraplugins.io/', 'sort_order' => 2, 'is_external' => true],
            ['position' => 'header', 'label' => 'Seo Monitor!', 'url' => 'https://seo.danielpetrica.com/?utm_source=danielpetrica.com&utm_medium=header&utm_campaign=seo-monitor', 'sort_order' => 3, 'is_external' => true],
            ['position' => 'header', 'label' => 'About', 'url' => '/pages/about/', 'sort_order' => 4, 'is_external' => false],
            ['position' => 'header', 'label' => 'Traefik posts', 'url' => '/tag/traefik/', 'sort_order' => 5, 'is_external' => false],
            ['position' => 'header', 'label' => 'Laravel posts', 'url' => '/tag/laravel/', 'sort_order' => 6, 'is_external' => false],

            // Footer
            ['position' => 'footer', 'label' => 'SEO Monitor', 'url' => 'https://seo.danielpetrica.com/?utm_source=danielpetrica.com&utm_medium=footer&utm_campaign=seo-monitor', 'sort_order' => 1, 'is_external' => true],
            ['position' => 'footer', 'label' => 'Website Uptime software', 'url' => 'https://updown.io/r/zCJmJ', 'sort_order' => 2, 'is_external' => true],
            ['position' => 'footer', 'label' => 'Linkedin', 'url' => 'https://www.linkedin.com/in/petricadaniel', 'sort_order' => 3, 'is_external' => true],
            ['position' => 'footer', 'label' => 'Mastodon', 'url' => 'https://infosec.exchange/@danielpetrica', 'sort_order' => 4, 'is_external' => true],
            ['position' => 'footer', 'label' => 'Github', 'url' => 'https://github.com/danielpetrica', 'sort_order' => 5, 'is_external' => true],
            ['position' => 'footer', 'label' => 'About me', 'url' => '/pages/about/', 'sort_order' => 6, 'is_external' => false],
            ['position' => 'footer', 'label' => 'Photo Collection', 'url' => '/pages/my-photos/', 'sort_order' => 7, 'is_external' => false],
            ['position' => 'footer', 'label' => 'Remoteok', 'url' => 'https://remoteok.com/@danielpetrica', 'sort_order' => 8, 'is_external' => true],
            ['position' => 'footer', 'label' => 'Let me build your project!', 'url' => '/pages/work-with-me/', 'sort_order' => 9, 'is_external' => false],
        ];

        foreach ($links as $link) {
            $exists = DB::table('links')
                ->where('position', $link['position'])
                ->where('url', $link['url'])
                ->exists();

            if (! $exists) {
                DB::table('links')->insert($link + [
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $urls = [
            '/pages/work-with-me/',
            'https://laraplugins.io/',
            'https://seo.danielpetrica.com/?utm_source=danielpetrica.com&utm_medium=header&utm_campaign=seo-monitor',
            '/pages/about/',
            '/tag/traefik/',
            '/tag/laravel/',
            'https://seo.danielpetrica.com/?utm_source=danielpetrica.com&utm_medium=footer&utm_campaign=seo-monitor',
            'https://updown.io/r/zCJmJ',
            'https://www.linkedin.com/in/petricadaniel',
            'https://infosec.exchange/@danielpetrica',
            'https://github.com/danielpetrica',
            '/pages/my-photos/',
            'https://remoteok.com/@danielpetrica',
        ];

        DB::table('links')
            ->whereIn('url', $urls)
            ->delete();
    }
};
