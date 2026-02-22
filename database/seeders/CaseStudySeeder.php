<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CaseStudySeeder extends Seeder
{
    /**
     * @var array<int, array{title: string, description: string}>
     */
    private array $caseStudies = [
        [
            'title' => 'Portale annunci immobiliari multi-tenant',
            'description' => 'Piattaforma Laravel che gestisce migliaia di annunci e la distribuzione verso portali terzi, con gestione multi-sito e copertura su più province (Reggio Emilia, Ferrara, Bologna, Modena, Parma, Rimini).',
        ],
        [
            'title' => 'Portale multi-servizi per amministrazioni locali',
            'description' => 'Digitalizzazione di pagamenti, rateizzazioni e affissioni per enti pubblici locali. Risultato: produttività incrementata del 1.100% (volume annuale di pratiche gestito in un solo mese). Stack: Laravel, Vue.js, WordPress, MySQL, Salesforce API. DevOps: infrastruttura Linux, CI/CD, zero-downtime deploy.',
        ],
        [
            'title' => 'Portale letture contatori smart',
            'description' => 'Contributo a manutenzione ed evoluzione di un\'app Laravel che gestisce grandi volumi di letture mensili, con attenzione a performance e affidabilità.',
        ],
        [
            'title' => 'SaaS accorciamento link',
            'description' => 'Applicativo in abbonamento sviluppato con Laravel, orientato a risoluzione rapida dei link e supporto multilingua; sviluppo end-to-end di sviluppo, infrastruttura e aggiornamenti.',
        ],
    ];

    public function run(): void
    {
        foreach ($this->caseStudies as $caseStudy) {
            DB::table('case_studies')->insertOrIgnore([
                'title' => $caseStudy['title'],
                'slug' => Str::slug($caseStudy['title']),
                'description' => $caseStudy['description'],
                'images' => json_encode([]),
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
