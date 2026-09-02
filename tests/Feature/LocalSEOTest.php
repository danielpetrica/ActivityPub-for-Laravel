<?php

use App\Models\CaseStudy;
use App\Models\City;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('replaces placeholders in service content correctly', function () {
    $city = City::create([
        'name' => 'Modena',
        'slug' => 'modena',
        'province' => 'MO',
        'region' => 'Emilia-Romagna',
        'description' => 'Città dei motori.',
    ]);

    $city2 = City::create([
        'name' => 'Carpi',
        'slug' => 'carpi',
        'province' => 'MO',
        'region' => 'Emilia-Romagna',
    ]);

    $service = Service::create([
        'name' => 'Sviluppo Laravel',
        'slug' => 'sviluppo-laravel',
        'intro_content' => 'Lavoro a {city_name} ({city_name_slug}). {city_description}',
        'main_content' => 'Altre città: {same_province_list}',
        'is_active' => true,
    ]);

    $response = $this->get(route('services.local', ['service' => $service->slug, 'city' => $city->slug]));

    $response->assertStatus(200);
    $response->assertSee('Lavoro a Modena (modena)');
    $response->assertSee('Città dei motori');
    $response->assertSee('Carpi'); // Dovrebbe essere nella list della stessa provincia
});

test('includes structured data with all cities', function () {
    $city = City::create([
        'name' => 'Modena',
        'slug' => 'modena',
        'province' => 'MO',
        'region' => 'Emilia-Romagna',
    ]);

    City::create([
        'name' => 'Reggio Emilia',
        'slug' => 'reggio-emilia',
        'province' => 'RE',
        'region' => 'Emilia-Romagna',
    ]);

    $service = Service::create([
        'name' => 'Sviluppo Laravel',
        'slug' => 'sviluppo-laravel',
        'is_active' => true,
    ]);

    $response = $this->get(route('services.local', ['service' => $service->slug, 'city' => $city->slug]));

    $response->assertStatus(200);
    $response->assertSee('Modena');
    $response->assertSee('Reggio Emilia');
    $response->assertSee('ProfessionalService');
});

test('shows related case studies', function () {
    $city = City::create([
        'name' => 'Modena',
        'slug' => 'modena',
        'province' => 'MO',
        'region' => 'Emilia-Romagna',
    ]);

    $service = Service::create([
        'name' => 'Sviluppo Laravel',
        'slug' => 'sviluppo-laravel',
        'is_active' => true,
    ]);

    $caseStudy = CaseStudy::create([
        'title' => 'Progetto Innovativo',
        'slug' => 'progetto-innovativo',
        'description' => 'Descrizione del progetto.',
        'is_active' => true,
    ]);

    $service->caseStudies()->attach($caseStudy->id);

    $response = $this->get(route('services.local', ['service' => $service->slug, 'city' => $city->slug]));

    $response->assertStatus(200);
    $response->assertSee('Progetto Innovativo');
    $response->assertSee('Descrizione del progetto');
});

test('replaces city_name in SEO metadata and hero title for each city', function () {
    $modena = City::create([
        'name' => 'Modena',
        'slug' => 'modena',
        'province' => 'MO',
        'region' => 'Emilia-Romagna',
    ]);

    $carpi = City::create([
        'name' => 'Carpi',
        'slug' => 'carpi',
        'province' => 'MO',
        'region' => 'Emilia-Romagna',
    ]);

    $service = Service::create([
        'name' => 'Consulente Laravel a city_name',
        'slug' => 'consulente-laravel',
        'intro_content' => '<p>Consulente Laravel freelance a city_name.</p>',
        'is_active' => true,
        'seo_metadata' => [
            'title' => 'Consulente Laravel a city_name | Daniel Petrica',
            'description' => 'Consulente Laravel freelance a city_name. Sviluppo web, API, DevOps.',
        ],
    ]);

    $modenaResponse = $this->get(route('services.local', ['service' => $service->slug, 'city' => $modena->slug]));
    $carpiResponse = $this->get(route('services.local', ['service' => $service->slug, 'city' => $carpi->slug]));

    $modenaResponse->assertStatus(200);
    $carpiResponse->assertStatus(200);

    // Il <title> deve contenere la città reale e NON il segnaposto letterale city_name
    $modenaResponse->assertSee('<title>Consulente Laravel a Modena | Daniel Petrica</title>', false);
    $modenaResponse->assertDontSee('<title>Consulente Laravel a city_name | Daniel Petrica</title>');
    $carpiResponse->assertSee('<title>Consulente Laravel a Carpi | Daniel Petrica</title>', false);
    $carpiResponse->assertDontSee('<title>Consulente Laravel a city_name | Daniel Petrica</title>');

    // Le due descrizioni meta devono essere diverse e ciascuna contenere la propria città
    $modenaResponse->assertSee('name="description" content="Consulente Laravel freelance a Modena. Sviluppo web, API, DevOps."', false);
    $carpiResponse->assertSee('name="description" content="Consulente Laravel freelance a Carpi. Sviluppo web, API, DevOps."', false);
    $modenaResponse->assertDontSee('name="description" content="Consulente Laravel freelance a Carpi');
    $carpiResponse->assertDontSee('name="description" content="Consulente Laravel freelance a Modena');

    // L'H1 dell'hero deve contenere "a Modena" senza doppia "a" né segnaposto letterale
    $modenaResponse->assertSee('Consulente Laravel a Modena', false);
    $modenaResponse->assertDontSee('a Modena a');
});
