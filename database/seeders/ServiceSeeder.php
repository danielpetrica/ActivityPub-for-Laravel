<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ServiceSeeder extends Seeder
{
    /**
     * @var array<int, array{name: string, intro_content: string, main_content: string, seo_title: string, seo_description: string}>
     */
    private array $services = [
        [
            'name' => 'Consulente Laravel a city_name',
            'intro_content' => '<p>Sono un consulente e sviluppatore Laravel freelance con oltre 10 anni di esperienza, attivo a <strong>city_name</strong> e in tutta Italia da remoto. city_description</p>
<p>Lavoro con aziende e team di sviluppo per consegnare software Laravel migliore: più veloce da rilasciare, con meno bug e con deploy affidabili. Ho clienti in Italia, Europa, USA e Canada.</p>
<p>Se hai un progetto Laravel a city_name da avviare o da ottimizzare, <a href="#contact">contattami per una call conoscitiva gratuita</a>.</p>',
            'main_content' => '<h2>Cosa posso fare per il tuo team a city_name</h2>
<ul>
<li>Sviluppo feature e integrazioni su Laravel (nuovi moduli, API, backoffice, automazioni).</li>
<li>Refactor e stabilizzazione di progetti esistenti (debito tecnico, test, upgrade).</li>
<li>Performance e scalabilità (query, caching, Redis, codebase, architettura).</li>
<li>DevOps e delivery (Docker, CI/CD, deploy zero-downtime, observability/monitoring).</li>
<li>Supporto continuativo (manutenzione, bugfix, interventi su richiesta).</li>
<li>Integrazione AI nel tuo progetto con server MCP per agenti software.</li>
</ul>

<h2>Dove lavoro</h2>
<p>Opero principalmente in Emilia-Romagna e sono disponibile a city_name e provincia. Lavoro anche da remoto per clienti in tutta Italia e all\'estero.</p>
<p>Altre città della provincia: same_province_list</p>
<p>Altre città della regione: same_region_big_cities</p>',
            'seo_title' => 'Consulente Laravel a city_name | Daniel Petrica',
            'seo_description' => 'Consulente Laravel freelance a city_name. Sviluppo web, API, backoffice, DevOps e ottimizzazione performance. Disponibile anche da remoto.',
        ],
        [
            'name' => 'Sviluppo gestionali in city_name',
            'intro_content' => '<p>Sviluppo gestionali personalizzati con Laravel per aziende a <strong>city_name</strong>. city_description</p>
<p>Un gestionale su misura ti permette di automatizzare i processi aziendali, ridurre gli errori manuali e avere dati sempre aggiornati. Lavoro con Laravel dal 2015 e ho realizzato gestionali per settori diversi: immobiliare, pubblica amministrazione, utilities e SaaS.</p>
<p>Contattami per discutere le esigenze del tuo gestionale a city_name.</p>',
            'main_content' => '<h2>Gestionali Laravel personalizzati per city_name</h2>
<p>Ogni azienda ha processi unici. Per questo sviluppo gestionali su misura, non soluzioni preconfezionate. Il gestionale viene costruito attorno ai tuoi flussi di lavoro, non il contrario.</p>

<h3>Tecnologie utilizzate</h3>
<ul>
<li>Laravel come backend robusto e scalabile.</li>
<li>FilamentPHP per backoffice e pannelli di amministrazione.</li>
<li>Vue.js e Alpine.js per interfacce reattive.</li>
<li>MySQL e PostgreSQL per la gestione dei dati.</li>
<li>Docker e CI/CD per deploy affidabili.</li>
</ul>

<h3>Esempi di gestionali realizzati</h3>
<ul>
<li>Portale multi-servizi per amministrazioni locali con +1.100% di produttività.</li>
<li>Piattaforma immobiliare multi-tenant per migliaia di annunci.</li>
<li>SaaS per accorciamento link con supporto multilingua.</li>
</ul>

<h2>Dove lavoro</h2>
<p>Sviluppo gestionali per aziende a city_name e in tutta la provincia. Disponibile anche da remoto per tutta Italia.</p>
<p>Altre città della provincia: same_province_list</p>
<p>Altre città della regione: same_region_big_cities</p>',
            'seo_title' => 'Sviluppo Gestionali in city_name | Daniel Petrica',
            'seo_description' => 'Sviluppo gestionali personalizzati con Laravel a city_name. Backoffice, automazioni, integrazioni API. Esperienza dal 2015.',
        ],
        [
            'name' => 'Sviluppo web in city_name',
            'intro_content' => '<p>Sviluppo web professionale a <strong>city_name</strong> con Laravel, Tailwind CSS e tecnologie moderne. city_description</p>
<p>Realizzo applicazioni web performanti, sicure e ottimizzate per i motori di ricerca. Lavoro sia su nuovi progetti che su applicazioni esistenti da migliorare o modernizzare.</p>
<p>Sono disponibile per progetti a city_name e in tutta Italia da remoto. <a href="#contact">Parliamo del tuo progetto</a>.</p>',
            'main_content' => '<h2>Sviluppo web a city_name: cosa realizzo</h2>
<ul>
<li>Applicazioni web Laravel scalabili e performanti.</li>
<li>API RESTful per integrazioni con sistemi terzi.</li>
<li>Backoffice e pannelli di amministrazione con FilamentPHP.</li>
<li>Frontend moderni con Vue.js, Alpine.js e Tailwind CSS.</li>
<li>Ottimizzazione SEO e performance (Core Web Vitals).</li>
<li>Integrazione con servizi cloud: AWS S3, Google Cloud.</li>
</ul>

<h2>Il mio approccio allo sviluppo web</h2>
<p>Ogni progetto inizia con un\'analisi delle esigenze. Poi si procede con uno sviluppo iterativo, test automatizzati e deploy continuo. Il risultato è software affidabile che cresce con il tuo business.</p>
<p>Gestisco server Linux dal 2014 e mi occupo anche di infrastruttura, CI/CD e monitoring.</p>

<h2>Dove lavoro</h2>
<p>Sviluppo web per clienti a city_name e provincia. Disponibile da remoto per tutta Italia e internazionalmente.</p>
<p>Altre città della provincia: same_province_list</p>
<p>Altre città della regione: same_region_big_cities</p>',
            'seo_title' => 'Sviluppo Web a city_name | Daniel Petrica',
            'seo_description' => 'Sviluppo web professionale a city_name con Laravel, Vue.js e Tailwind CSS. Applicazioni scalabili, API, backoffice e ottimizzazione SEO.',
        ],
        [
            'name' => 'Sviluppo MVP veloce in city_name',
            'intro_content' => '<p>Hai un\'idea di prodotto e vuoi validarla velocemente? Sviluppo MVP (Minimum Viable Product) con Laravel per startup e aziende a <strong>city_name</strong>. city_description</p>
<p>Un MVP ben costruito ti permette di testare il mercato in settimane, non mesi. Uso Laravel per la velocità di sviluppo, FilamentPHP per il backoffice e tecnologie collaudate per ridurre i rischi.</p>
<p>Contattami per discutere la tua idea e definire insieme lo scope del tuo MVP a city_name.</p>',
            'main_content' => '<h2>Perché scegliere Laravel per il tuo MVP a city_name</h2>
<p>Laravel è il framework PHP più popolare al mondo per una ragione: permette di costruire prodotti solidi in tempi rapidi. Con il giusto approccio, un MVP funzionante può essere pronto in 4-8 settimane.</p>

<h3>Il mio processo per gli MVP</h3>
<ol>
<li><strong>Discovery:</strong> Definizione delle funzionalità core e dello scope minimo.</li>
<li><strong>Architettura:</strong> Scelta dello stack tecnologico ottimale per scalare.</li>
<li><strong>Sviluppo iterativo:</strong> Sprint brevi con rilasci frequenti e feedback continuo.</li>
<li><strong>Deploy:</strong> Infrastruttura Docker con CI/CD per deploy rapidi e affidabili.</li>
<li><strong>Monitoring:</strong> Setup di observability per monitorare il comportamento reale.</li>
</ol>

<h3>Tecnologie per MVP veloci</h3>
<ul>
<li>Laravel + FilamentPHP per backoffice immediato.</li>
<li>Tailwind CSS per UI rapida e professionale.</li>
<li>MySQL o PostgreSQL per dati strutturati.</li>
<li>Docker per ambienti riproducibili.</li>
<li>n8n o Activepieces per automazioni senza codice.</li>
</ul>

<h2>Dove lavoro</h2>
<p>Sviluppo MVP per startup e aziende a city_name. Disponibile da remoto per tutta Italia.</p>
<p>Altre città della provincia: same_province_list</p>
<p>Altre città della regione: same_region_big_cities</p>',
            'seo_title' => 'Sviluppo MVP Veloce in city_name | Daniel Petrica',
            'seo_description' => 'Sviluppo MVP rapido con Laravel a city_name. Dalla idea al prodotto funzionante in settimane. Startup e aziende.',
        ],
        [
            'name' => 'Consulenza Architettura software in city_name',
            'intro_content' => '<p>Consulenza sull\'architettura software per team e aziende a <strong>city_name</strong>. city_description</p>
<p>Una buona architettura è la differenza tra un progetto che scala e uno che diventa impossibile da mantenere. Aiuto team a prendere decisioni architetturali consapevoli, ridurre il debito tecnico e costruire sistemi che crescono con il business.</p>
<p>Disponibile per sessioni di consulenza a city_name e da remoto. <a href="#contact">Parliamo della tua architettura</a>.</p>',
            'main_content' => '<h2>Consulenza architettura software a city_name</h2>
<p>Lavoro con team di sviluppo per analizzare l\'architettura esistente, identificare i punti critici e definire una roadmap di miglioramento. Non solo teoria: ogni raccomandazione è pratica e implementabile.</p>

<h3>Aree di consulenza</h3>
<ul>
<li>Architettura applicazioni Laravel (monoliti modulari, microservizi, API-first).</li>
<li>Design di database relazionali (MySQL, PostgreSQL) e strategie di caching.</li>
<li>Integrazione di code e job queue (Laravel Horizon, Redis).</li>
<li>Strategie di scalabilità orizzontale e verticale.</li>
<li>Code review e definizione di standard di sviluppo.</li>
<li>Migrazione da sistemi legacy a Laravel moderno.</li>
</ul>

<h3>Il mio background</h3>
<p>Sviluppo con Laravel dal 2015 e gestisco server Linux dal 2014. Ho lavorato su piattaforme ad alto traffico, sistemi multi-tenant e integrazioni complesse con API di terze parti (Salesforce, Google Cloud, AWS).</p>

<h2>Dove lavoro</h2>
<p>Consulenza architettura software per team a city_name e provincia. Disponibile da remoto per tutta Italia e internazionalmente.</p>
<p>Altre città della provincia: same_province_list</p>
<p>Altre città della regione: same_region_big_cities</p>',
            'seo_title' => 'Consulenza Architettura Software in city_name | Daniel Petrica',
            'seo_description' => 'Consulenza architettura software Laravel a city_name. Scalabilità, debito tecnico, code review e roadmap tecnica per team di sviluppo.',
        ],
        [
            'name' => 'Consulenza Newrelic in city_name',
            'intro_content' => '<p>Consulenza e configurazione New Relic per aziende a <strong>city_name</strong>. city_description</p>
<p>New Relic è uno degli strumenti di observability più potenti disponibili. Con oltre 3 anni di esperienza su New Relic, aiuto team a configurarlo correttamente, interpretare i dati e usarlo per migliorare le performance delle applicazioni.</p>
<p>Disponibile per consulenza New Relic a city_name e da remoto. <a href="#contact">Contattami</a>.</p>',
            'main_content' => '<h2>Consulenza New Relic a city_name</h2>
<p>Molti team installano New Relic ma non lo usano al massimo del suo potenziale. La configurazione corretta degli agenti, la definizione di alert significativi e la creazione di dashboard utili fanno la differenza tra uno strumento ignorato e uno strumento che salva la produzione.</p>

<h3>Cosa faccio con New Relic</h3>
<ul>
<li>Installazione e configurazione dell\'agente PHP/Laravel su ambienti Docker e Linux.</li>
<li>Setup di distributed tracing per applicazioni multi-servizio.</li>
<li>Creazione di dashboard personalizzate per KPI di business e tecnici.</li>
<li>Configurazione di alert policy e notifiche (Slack, PagerDuty, email).</li>
<li>Analisi delle transaction traces per identificare colli di bottiglia.</li>
<li>Ottimizzazione delle query lente identificate tramite New Relic APM.</li>
<li>Integrazione con CI/CD per deployment markers.</li>
</ul>

<h3>Perché New Relic</h3>
<p>New Relic offre una visibilità completa sullo stack applicativo: dal frontend al database, passando per le code e i servizi esterni. Con i dati giusti, i problemi di produzione si risolvono in minuti invece che in ore.</p>

<h2>Dove lavoro</h2>
<p>Consulenza New Relic per aziende a city_name e provincia. Disponibile da remoto per tutta Italia.</p>
<p>Altre città della provincia: same_province_list</p>
<p>Altre città della regione: same_region_big_cities</p>',
            'seo_title' => 'Consulenza New Relic in city_name | Daniel Petrica',
            'seo_description' => 'Consulenza New Relic a city_name. Configurazione APM, dashboard, alert e ottimizzazione performance per applicazioni Laravel e PHP.',
        ],
        [
            'name' => 'Consulenza Traefik in city_name',
            'intro_content' => '<p>Consulenza e configurazione Traefik per aziende a <strong>city_name</strong>. city_description</p>
<p>Con 5 anni di esperienza con Traefik, aiuto team a configurare questo reverse proxy moderno per ambienti Docker e Kubernetes. Traefik semplifica il routing, la gestione dei certificati SSL e il load balancing in ambienti containerizzati.</p>
<p>Disponibile per consulenza Traefik a city_name e da remoto. <a href="#contact">Contattami</a>.</p>',
            'main_content' => '<h2>Consulenza Traefik a city_name</h2>
<p>Traefik è il reverse proxy di riferimento per ambienti Docker e Kubernetes. La sua configurazione dinamica e il supporto nativo per Let\'s Encrypt lo rendono ideale per infrastrutture moderne. Ma configurarlo correttamente richiede esperienza.</p>

<h3>Cosa faccio con Traefik</h3>
<ul>
<li>Installazione e configurazione Traefik v2/v3 su Docker e Docker Compose.</li>
<li>Setup SSL automatico con Let\'s Encrypt (HTTP challenge, DNS challenge).</li>
<li>Configurazione di middleware: rate limiting, autenticazione, redirect HTTPS.</li>
<li>Routing avanzato per applicazioni multi-dominio e multi-servizio.</li>
<li>Integrazione con Docker Swarm per ambienti ad alta disponibilità.</li>
<li>Dashboard e monitoring con Prometheus e Grafana.</li>
<li>Migrazione da Nginx o Apache a Traefik.</li>
</ul>

<h3>Il mio background DevOps</h3>
<p>Gestisco server Linux dal 2014 e lavoro con Docker e Docker Compose quotidianamente. Ho configurato Traefik per applicazioni Laravel in produzione con zero-downtime deploy e certificati SSL automatici.</p>

<h2>Dove lavoro</h2>
<p>Consulenza Traefik per aziende a city_name e provincia. Disponibile da remoto per tutta Italia.</p>
<p>Altre città della provincia: same_province_list</p>
<p>Altre città della regione: same_region_big_cities</p>',
            'seo_title' => 'Consulenza Traefik in city_name | Daniel Petrica',
            'seo_description' => 'Consulenza Traefik a city_name. Configurazione reverse proxy, SSL automatico, Docker e load balancing per infrastrutture moderne.',
        ],
        [
            'name' => 'Sviluppo server MCP in city_name',
            'intro_content' => '<p>Sviluppo server MCP (Model Context Protocol) per integrare l\'intelligenza artificiale nei tuoi processi aziendali a <strong>city_name</strong>. city_description</p>
<p>I server MCP permettono agli agenti AI come Claude di interagire con i tuoi sistemi in modo sicuro e controllato. Sviluppo server MCP personalizzati che connettono i tuoi dati e le tue applicazioni agli agenti AI più avanzati.</p>
<p>Contattami per discutere come integrare l\'AI nel tuo progetto a city_name.</p>',
            'main_content' => '<h2>Server MCP a city_name: AI integrata nei tuoi sistemi</h2>
<p>Il Model Context Protocol (MCP) è lo standard aperto per connettere gli agenti AI ai sistemi aziendali. Con un server MCP, puoi dare a Claude, GPT e altri agenti AI accesso controllato ai tuoi database, API e strumenti interni.</p>

<h3>Cosa sviluppo</h3>
<ul>
<li>Server MCP personalizzati in PHP/Laravel o Node.js.</li>
<li>Connettori MCP per database MySQL e PostgreSQL.</li>
<li>Integrazione MCP con API REST esistenti.</li>
<li>Server MCP per automazioni con n8n e Activepieces.</li>
<li>Tool MCP per gestione file, email e notifiche.</li>
<li>Deploy sicuro di server MCP su infrastruttura Docker.</li>
</ul>

<h3>Casi d\'uso pratici</h3>
<ul>
<li>Agente AI che risponde a domande sui tuoi dati aziendali.</li>
<li>Automazione di task ripetitivi tramite agenti AI.</li>
<li>Assistente AI per il tuo team di sviluppo con accesso al codebase.</li>
<li>Integrazione AI nel tuo CRM o gestionale personalizzato.</li>
</ul>

<h2>Dove lavoro</h2>
<p>Sviluppo server MCP per aziende a city_name e provincia. Disponibile da remoto per tutta Italia e internazionalmente.</p>
<p>Altre città della provincia: same_province_list</p>
<p>Altre città della regione: same_region_big_cities</p>',
            'seo_title' => 'Sviluppo Server MCP in city_name | Daniel Petrica',
            'seo_description' => 'Sviluppo server MCP a city_name per integrare agenti AI nei tuoi sistemi. Laravel, PHP, Docker. Model Context Protocol personalizzato.',
        ],
        [
            'name' => 'Creazione CRM personalizzato in city_name',
            'intro_content' => '<p>Sviluppo CRM personalizzati con Laravel per aziende a <strong>city_name</strong>. city_description</p>
<p>Un CRM su misura si adatta ai tuoi processi di vendita, non il contrario. Sviluppo CRM con Laravel che integrano le tue fonti di dati, automatizzano i follow-up e danno al tuo team commerciale gli strumenti giusti per chiudere più contratti.</p>
<p>Contattami per discutere il tuo CRM personalizzato a city_name.</p>',
            'main_content' => '<h2>CRM personalizzato a city_name con Laravel</h2>
<p>I CRM generici come Salesforce o HubSpot sono potenti ma costosi e spesso sovradimensionati. Un CRM Laravel su misura costa meno nel lungo periodo, si integra perfettamente con i tuoi sistemi e si evolve con le tue esigenze.</p>

<h3>Funzionalità tipiche di un CRM Laravel</h3>
<ul>
<li>Gestione contatti, aziende e opportunità commerciali.</li>
<li>Pipeline di vendita visuale con drag-and-drop.</li>
<li>Automazione email e follow-up programmati.</li>
<li>Integrazione con email (IMAP/SMTP) e calendario.</li>
<li>Report e dashboard con KPI commerciali.</li>
<li>API per integrazione con altri sistemi aziendali.</li>
<li>Gestione permessi e ruoli per team commerciali.</li>
</ul>

<h3>Integrazioni disponibili</h3>
<ul>
<li>Salesforce API per sincronizzazione dati.</li>
<li>Mailcoach per email marketing integrato.</li>
<li>n8n per automazioni avanzate.</li>
<li>Google Calendar e Microsoft 365.</li>
</ul>

<h2>Dove lavoro</h2>
<p>Sviluppo CRM personalizzati per aziende a city_name e provincia. Disponibile da remoto per tutta Italia.</p>
<p>Altre città della provincia: same_province_list</p>
<p>Altre città della regione: same_region_big_cities</p>',
            'seo_title' => 'Creazione CRM Personalizzato in city_name | Daniel Petrica',
            'seo_description' => 'Sviluppo CRM personalizzato con Laravel a city_name. Gestione contatti, pipeline vendite, automazioni e integrazioni su misura.',
        ],
        [
            'name' => 'Gestionale custom in city_name',
            'intro_content' => '<p>Sviluppo gestionali custom con Laravel per aziende a <strong>city_name</strong>. city_description</p>
<p>Ogni azienda ha processi unici che i software standard non riescono a coprire completamente. Un gestionale custom Laravel si adatta esattamente ai tuoi flussi di lavoro, elimina i workaround e aumenta la produttività del tuo team.</p>
<p>Contattami per discutere il tuo gestionale custom a city_name.</p>',
            'main_content' => '<h2>Gestionale custom a city_name: software su misura per la tua azienda</h2>
<p>Ho realizzato gestionali custom per settori diversi: pubblica amministrazione, immobiliare, utilities, SaaS. In un caso, il gestionale ha aumentato la produttività del 1.100%, permettendo di gestire in un mese il volume annuale di pratiche precedente.</p>

<h3>Tipologie di gestionali che sviluppo</h3>
<ul>
<li>Gestionali per la pubblica amministrazione (pagamenti, rateizzazioni, affissioni).</li>
<li>Portali immobiliari multi-tenant con distribuzione verso portali terzi.</li>
<li>Sistemi di gestione letture contatori e utilities.</li>
<li>Piattaforme SaaS in abbonamento con gestione utenti e fatturazione.</li>
<li>Gestionali per la gestione di ordini, magazzino e logistica.</li>
<li>Portali B2B per la gestione di fornitori e clienti.</li>
</ul>

<h3>Stack tecnologico</h3>
<ul>
<li>Laravel come backend robusto e testato.</li>
<li>FilamentPHP per backoffice e pannelli admin professionali.</li>
<li>Vue.js e TypeScript per interfacce complesse.</li>
<li>MySQL e PostgreSQL per dati strutturati.</li>
<li>Docker e CI/CD per deploy affidabili e zero-downtime.</li>
<li>New Relic per monitoring e observability in produzione.</li>
</ul>

<h2>Dove lavoro</h2>
<p>Sviluppo gestionali custom per aziende a city_name e provincia. Disponibile da remoto per tutta Italia e internazionalmente.</p>
<p>Altre città della provincia: same_province_list</p>
<p>Altre città della regione: same_region_big_cities</p>',
            'seo_title' => 'Gestionale Custom in city_name | Daniel Petrica',
            'seo_description' => 'Sviluppo gestionale custom con Laravel a city_name. Software su misura per automatizzare i processi aziendali. Esperienza dal 2015.',
        ],
    ];

    public function run(): void
    {
        // Remove old services before inserting the new ones
        DB::table('services')->truncate();

        foreach ($this->services as $service) {
            DB::table('services')->insert([
                'name' => $service['name'],
                'slug' => Str::slug($service['name']),
                'intro_content' => $service['intro_content'],
                'main_content' => $service['main_content'],
                'seo_metadata' => json_encode([
                    'title' => $service['seo_title'],
                    'description' => $service['seo_description'],
                ]),
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
