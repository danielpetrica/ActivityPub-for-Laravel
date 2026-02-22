<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->string('meta_title')->nullable()->after('status');
            $table->text('meta_description')->nullable()->after('meta_title');
            $table->string('og_title')->nullable()->after('meta_description');
            $table->text('og_description')->nullable()->after('og_title');
            $table->string('og_image')->nullable()->after('og_description');
            $table->string('twitter_title')->nullable()->after('og_image');
            $table->text('twitter_description')->nullable()->after('twitter_title');
            $table->string('twitter_image')->nullable()->after('twitter_description');
            $table->string('canonical_url')->nullable()->after('twitter_image');

            $table->string('feature_image_path')->nullable()->after('canonical_url');
            $table->string('feature_image_alt')->nullable()->after('feature_image_path');
            $table->text('feature_image_caption')->nullable()->after('feature_image_alt');

            $table->text('codeinjection_head')->nullable()->after('feature_image_caption');
            $table->text('codeinjection_foot')->nullable()->after('codeinjection_head');

            $table->boolean('show_title_and_feature_image')->default(true)->after('codeinjection_foot');
            $table->text('excerpt')->nullable()->after('show_title_and_feature_image');
            $table->string('ghost_uuid')->nullable()->after('excerpt');
        });

        Schema::table('pages', function (Blueprint $table) {
            $table->string('meta_title')->nullable()->after('status');
            $table->text('meta_description')->nullable()->after('meta_title');
            $table->string('og_title')->nullable()->after('meta_description');
            $table->text('og_description')->nullable()->after('og_title');
            $table->string('og_image')->nullable()->after('og_description');
            $table->string('twitter_title')->nullable()->after('og_image');
            $table->text('twitter_description')->nullable()->after('twitter_title');
            $table->string('twitter_image')->nullable()->after('twitter_description');
            $table->string('canonical_url')->nullable()->after('twitter_image');

            $table->string('feature_image_path')->nullable()->after('canonical_url');
            $table->string('feature_image_alt')->nullable()->after('feature_image_path');
            $table->text('feature_image_caption')->nullable()->after('feature_image_alt');

            $table->text('codeinjection_head')->nullable()->after('feature_image_caption');
            $table->text('codeinjection_foot')->nullable()->after('codeinjection_head');

            $table->boolean('show_title_and_feature_image')->default(true)->after('codeinjection_foot');
            $table->text('excerpt')->nullable()->after('show_title_and_feature_image');
            $table->string('ghost_uuid')->nullable()->after('excerpt');
        });

        Schema::table('tags', function (Blueprint $table) {
            $table->string('meta_title')->nullable()->after('description');
            $table->text('meta_description')->nullable()->after('meta_title');
            $table->string('og_title')->nullable()->after('meta_description');
            $table->text('og_description')->nullable()->after('og_title');
            $table->string('og_image')->nullable()->after('og_description');
            $table->string('twitter_title')->nullable()->after('og_image');
            $table->text('twitter_description')->nullable()->after('twitter_title');
            $table->string('twitter_image')->nullable()->after('twitter_description');
            $table->string('accent_color')->nullable()->after('twitter_image');
            $table->string('canonical_url')->nullable()->after('accent_color');
        });

        // Optional backfill from existing JSON seo_metadata where present.
        // This keeps existing data during schema transition.
        if (Schema::hasColumn('posts', 'seo_metadata')) {
            DB::table('posts')->orderBy('id')->lazyById()->each(function ($post) {
                if (! $post->seo_metadata) {
                    return;
                }
                $meta = json_decode($post->seo_metadata, true);
                if (! is_array($meta)) {
                    return;
                }
                DB::table('posts')->where('id', $post->id)->update([
                    'meta_title' => $meta['meta_title'] ?? null,
                    'meta_description' => $meta['meta_description'] ?? null,
                    'og_title' => $meta['og_title'] ?? null,
                    'og_description' => $meta['og_description'] ?? null,
                    'og_image' => $meta['og_image'] ?? null,
                    'twitter_title' => $meta['twitter_title'] ?? null,
                    'twitter_description' => $meta['twitter_description'] ?? null,
                    'twitter_image' => $meta['twitter_image'] ?? null,
                    'canonical_url' => $meta['canonical_url'] ?? null,
                    'feature_image_path' => $meta['feature_image'] ?? null,
                    'feature_image_alt' => $meta['feature_image_alt'] ?? null,
                    'feature_image_caption' => $meta['feature_image_caption'] ?? null,
                    'codeinjection_head' => $meta['codeinjection_head'] ?? null,
                    'codeinjection_foot' => $meta['codeinjection_foot'] ?? null,
                    'show_title_and_feature_image' => array_key_exists('show_title_and_feature_image', $meta) ? (bool) $meta['show_title_and_feature_image'] : true,
                    'excerpt' => $meta['excerpt'] ?? null,
                    'ghost_uuid' => $meta['ghost_uuid'] ?? null,
                ]);
            });
        }

        if (Schema::hasColumn('pages', 'seo_metadata')) {
            DB::table('pages')->orderBy('id')->lazyById()->each(function ($page) {
                if (! $page->seo_metadata) {
                    return;
                }
                $meta = json_decode($page->seo_metadata, true);
                if (! is_array($meta)) {
                    return;
                }
                DB::table('pages')->where('id', $page->id)->update([
                    'meta_title' => $meta['meta_title'] ?? null,
                    'meta_description' => $meta['meta_description'] ?? null,
                    'og_title' => $meta['og_title'] ?? null,
                    'og_description' => $meta['og_description'] ?? null,
                    'og_image' => $meta['og_image'] ?? null,
                    'twitter_title' => $meta['twitter_title'] ?? null,
                    'twitter_description' => $meta['twitter_description'] ?? null,
                    'twitter_image' => $meta['twitter_image'] ?? null,
                    'canonical_url' => $meta['canonical_url'] ?? null,
                    'feature_image_path' => $meta['feature_image'] ?? null,
                    'feature_image_alt' => $meta['feature_image_alt'] ?? null,
                    'feature_image_caption' => $meta['feature_image_caption'] ?? null,
                    'codeinjection_head' => $meta['codeinjection_head'] ?? null,
                    'codeinjection_foot' => $meta['codeinjection_foot'] ?? null,
                    'show_title_and_feature_image' => array_key_exists('show_title_and_feature_image', $meta) ? (bool) $meta['show_title_and_feature_image'] : true,
                    'excerpt' => $meta['excerpt'] ?? null,
                    'ghost_uuid' => $meta['ghost_uuid'] ?? null,
                ]);
            });
        }

        if (Schema::hasColumn('tags', 'seo_metadata')) {
            DB::table('tags')->orderBy('id')->lazyById()->each(function ($tag) {
                if (! $tag->seo_metadata) {
                    return;
                }
                $meta = json_decode($tag->seo_metadata, true);
                if (! is_array($meta)) {
                    return;
                }
                DB::table('tags')->where('id', $tag->id)->update([
                    'meta_title' => $meta['meta_title'] ?? null,
                    'meta_description' => $meta['meta_description'] ?? null,
                    'og_title' => $meta['og_title'] ?? null,
                    'og_description' => $meta['og_description'] ?? null,
                    'og_image' => $meta['og_image'] ?? null,
                    'twitter_title' => $meta['twitter_title'] ?? null,
                    'twitter_description' => $meta['twitter_description'] ?? null,
                    'twitter_image' => $meta['twitter_image'] ?? null,
                    'accent_color' => $meta['accent_color'] ?? null,
                    'canonical_url' => $meta['canonical_url'] ?? null,
                ]);
            });
        }
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn([
                'meta_title', 'meta_description', 'og_title', 'og_description', 'og_image',
                'twitter_title', 'twitter_description', 'twitter_image', 'canonical_url',
                'feature_image_path', 'feature_image_alt', 'feature_image_caption',
                'codeinjection_head', 'codeinjection_foot', 'show_title_and_feature_image',
                'excerpt', 'ghost_uuid',
            ]);
        });

        Schema::table('pages', function (Blueprint $table) {
            $table->dropColumn([
                'meta_title', 'meta_description', 'og_title', 'og_description', 'og_image',
                'twitter_title', 'twitter_description', 'twitter_image', 'canonical_url',
                'feature_image_path', 'feature_image_alt', 'feature_image_caption',
                'codeinjection_head', 'codeinjection_foot', 'show_title_and_feature_image',
                'excerpt', 'ghost_uuid',
            ]);
        });

        Schema::table('tags', function (Blueprint $table) {
            $table->dropColumn([
                'meta_title', 'meta_description', 'og_title', 'og_description', 'og_image',
                'twitter_title', 'twitter_description', 'twitter_image', 'accent_color', 'canonical_url',
            ]);
        });
    }
};
