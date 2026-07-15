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
