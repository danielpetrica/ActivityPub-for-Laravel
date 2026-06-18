<?php echo '<?xml version="1.0" encoding="UTF-8"?>'; ?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <url>
        <loc>{{ route('tools.docker-traefik-generator') }}</loc>
        <changefreq>monthly</changefreq>
        <priority>0.7</priority>
        <lastmod>{{ now()->toAtomString() }}</lastmod>
    </url>
    @foreach ($tools as $tool)
    <url>
        <loc>{{ route('tools.show', ['slug' => $tool->slug]) }}</loc>
        <lastmod>{{ $tool->updated_at->toAtomString() }}</lastmod>
        <changefreq>monthly</changefreq>
        <priority>0.7</priority>
    </url>
    @endforeach
</urlset>
