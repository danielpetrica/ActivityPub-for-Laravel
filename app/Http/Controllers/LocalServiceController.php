<?php

namespace App\Http\Controllers;

use App\Classes\SEO\StructuredData;
use App\Models\City;
use App\Models\Service;
use Illuminate\View\View;

final class LocalServiceController extends Controller
{
    public function show(Service $service, City $city): View
    {
        // Otteniamo le città nella stessa provincia (escludendo quella attuale)
        $sameProvinceCities = City::query()
            ->where('province', '=', $city->province)
            ->where('id', '!=', $city->id)
            ->get();

        // Otteniamo i capoluoghi nella stessa regione (escludendo quella attuale se lo è)
        $sameRegionCapitals = City::query()
            ->where('region', '=', $city->region)
            ->where('is_capital', '=', true)
            ->where('id', '!=', $city->id)
            ->get();

        // Carichiamo i casi studio correlati al servizio
        $service->load('caseStudies');

        // Elaboriamo il contenuto con i segnaposto
        $introContent = $this->replacePlaceholders($service->intro_content, $city, $sameProvinceCities, $sameRegionCapitals, $service);
        $mainContent = $this->replacePlaceholders($service->main_content, $city, $sameProvinceCities, $sameRegionCapitals, $service);

        return view('services.local-show', [
            'service' => $service,
            'city' => $city,
            'introContent' => $introContent,
            'mainContent' => $mainContent,
            'caseStudies' => $service->caseStudies->where('is_active', true),
            'structuredData' => StructuredData::getProfessionalService($city),
        ]);
    }

    protected function replacePlaceholders(?string $content, City $city, $sameProvinceCities, $sameRegionCapitals, Service $service): string
    {
        if (! $content) {
            return '';
        }

        $placeholders = [
            'city_name' => $city->name,
            'city_name_slug' => $city->slug,
            'city_description' => $city->description ?? '',
            'same_province_list' => $this->generateCityLinks($sameProvinceCities, $service),
            'same_region_big_cities' => $this->generateCityLinks($sameRegionCapitals, $service),
        ];

        foreach ($placeholders as $key => $value) {
            // Replace both {city_name} and bare city_name for backwards compatibility
            $content = str_replace(
                search: ['{'.$key.'}', $key],
                replace: $value,
                subject: $content,
            );
        }

        return $content;
    }

    protected function generateCityLinks($cities, Service $service): string
    {
        if ($cities->isEmpty()) {
            return '';
        }

        return '<ul>'.$cities->map(fn (City $c) => '<li><a href="'.route('services.local', ['service' => $service->slug, 'city' => $c->slug]).'" class="text-primary-600 hover:underline">'.$c->name.'</a></li>'
        )->implode('').'</ul>';
    }
}
