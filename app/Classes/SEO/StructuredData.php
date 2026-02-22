<?php

namespace App\Classes\SEO;

use App\Models\City;
use App\Models\Service;
use Illuminate\Support\Str;

final class StructuredData
{
    public static function getProfessionalService(City $city): array
    {
        $allCities = City::all();

        $areaServed = $allCities->map(function (City $c) {
            return [
                '@type' => 'City',
                'name' => $c->name,
            ];
        })->toArray();

        // Aggiungiamo Italia come area servita di default
        $areaServed[] = [
            '@type' => 'Country',
            'name' => 'Italia',
        ];

        return [
            '@context' => 'https://schema.org',
            '@type' => 'ProfessionalService',
            'name' => 'DanielPetrica - Consulente Laravel & DevOps',
            'image' => 'https://danielpetrica.com/content/images/size/w256h256/2024/11/-removal.ai-_61a0aadc-01f1-4bfd-95a7-0d9325ae20d0-media-copy.png',
            '@id' => 'https://danielpetrica.com',
            'url' => 'https://danielpetrica.com',
            'priceRange' => '$$-$$$$',
            'address' => [
                '@type' => 'PostalAddress',
                'streetAddress' => 'Piazza Giosue Carducci',
                'addressLocality' => 'Reggio Emilia',
                'addressRegion' => 'RE',
                'postalCode' => '42010',
                'addressCountry' => 'IT',
            ],
            'geo' => [
                '@type' => 'GeoCoordinates',
                'latitude' => '44.69825',
                'longitude' => '10.63125',
            ],
            'areaServed' => $areaServed,
            'hasOfferCatalog' => self::getOfferCatalog(),
        ];
    }

    private static function getOfferCatalog(): array
    {
        // Qui potremmo caricare i servizi dal database se necessario,
        // o mantenere una lista statica dei servizi principali offerti.
        $services = Service::where('is_active', true)->get();

        $itemListElement = $services->map(function (Service $service) {
            return [
                '@type' => 'Offer',
                'itemOffered' => [
                    '@type' => 'Service',
                    'name' => $service->name,
                    'description' => $service->seo_metadata['description'] ?? Str::limit(strip_tags($service->intro_content), 150),
                ],
            ];
        })->toArray();

        return [
            '@type' => 'OfferCatalog',
            'name' => 'Servizi Sviluppo Software e DevOps',
            'itemListElement' => $itemListElement,
        ];
    }
}
