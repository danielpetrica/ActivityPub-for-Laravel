<?php echo '<?xml version="1.0" encoding="UTF-8"?>'; ?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    @foreach ($services as $service)
        @foreach ($cities as $city)
        <url>
            <loc>{{ route('services.local', ['service' => $service->slug, 'city' => $city->slug]) }}</loc>
            <changefreq>monthly</changefreq>
            <priority>0.6</priority>
        </url>
        @endforeach
    @endforeach
</urlset>
