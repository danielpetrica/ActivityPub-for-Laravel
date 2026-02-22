    const { createApp, ref, computed, reactive, watch } = Vue;

    // ── helpers ──────────────────────────────────────────────────────────────
    function slugify(str) {
        return str.toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_|_$/g, '') || 'project';
    }

    function downloadFile(filename, content) {
        const blob = new Blob([content], { type: 'text/plain' });
        const a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = filename;
        a.click();
        URL.revokeObjectURL(a.href);
    }

    // ── generators ───────────────────────────────────────────────────────────
    function genTraefikCompose(cfg) {
        const dir = cfg.traefikDir || '/home/ubuntu/traefik/traefik';
        const isCloudflare = cfg.traefikCertResolver === 'cloudflare';
        const envBlock = isCloudflare
            ? `    environment:\n      - CLOUDFLARE_EMAIL=${cfg.acmeEmail}\n      - CLOUDFLARE_DNS_API_TOKEN=\${CLOUDFLARE_DNS_API_TOKEN}\n`
            : '';
        return `services:
  traefik:
    image: traefik:v3.5
    hostname: traefik_host
    restart: unless-stopped
${envBlock}    networks:
      - traefik
    volumes:
      - ${dir}/letsencrypt:/letsencrypt
      - ${dir}/traefik.yml:/etc/traefik/traefik.yml:ro
      - /var/run/docker.sock:/var/run/docker.sock:ro
    ports:
      - target: 80
        published: 80
        mode: host
        protocol: tcp
      - target: 443
        published: 443
        mode: host
        protocol: tcp
    labels:
      - traefik.enable=true
      - traefik.http.routers.traefik.rule=Host(\`${cfg.traefikDomain}\`)
      - traefik.http.routers.traefik.tls=true
      - traefik.http.routers.traefik.service=api@internal
      - traefik.http.services.traefik.loadbalancer.server.port=8000
      - traefik.http.routers.traefik.tls.certresolver=${cfg.traefikCertResolver || 'cloudflare'}
      - traefik.http.routers.traefik.entrypoints=websecure
      - traefik.http.middlewares.traefik-auth.basicauth.users=\${TRAEFIK_BASIC_AUTH}
      - traefik.http.routers.traefik.middlewares=traefik-auth

networks:
  traefik:
    external: true
    name: traefik
`;
    }

    function genTraefikYml(cfg) {
        const isCloudflare = cfg.traefikCertResolver === 'cloudflare';
        const resolversBlock = isCloudflare
            ? `certificatesResolvers:
  cloudflare:
    acme:
      dnschallenge:
        provider: cloudflare
      email: ${cfg.acmeEmail}
      storage: /letsencrypt/acmeCloudflare.json
`
            : `certificatesResolvers:
  letsencrypt:
    acme:
      httpChallenge:
        entryPoint: web
      email: ${cfg.acmeEmail}
      storage: /letsencrypt/acme.json
`;
        return `global:
  checkNewVersion: true
  sendAnonymousUsage: false

entryPoints:
  web:
    address: ":80"
    http:
      redirections:
        entryPoint:
          to: websecure
          scheme: https
          permanent: true
    forwardedHeaders:
      insecure: true

  websecure:
    address: ":443"
    forwardedHeaders:
      insecure: true

  webNoRedirect:
    address: ":8080"
    forwardedHeaders:
      insecure: true

log:
  level: warn

accessLog:
  format: common

api:
  dashboard: true

ping:
  entryPoint: webNoRedirect

providers:
  docker:
    allowEmptyServices: true
    endpoint: "unix:///var/run/docker.sock"
    network: traefik
    watch: true
    exposedByDefault: false
  file:
    filename: /etc/traefik/file_provider.yml
    watch: true

${resolversBlock}`;
    }

    function genAppCompose(cfg) {
        const name = slugify(cfg.projectName);
        const isLaravel = cfg.appType === 'laravel';
        const isStatic = cfg.appType === 'static';
        const hasDb = cfg.services.includes('db');
        const hasRedis = cfg.services.includes('redis');
        const hasWorker = cfg.services.includes('worker') && isLaravel;
        const hasScheduler = cfg.services.includes('scheduler') && isLaravel;
        const hasPulse = cfg.services.includes('pulse') && isLaravel;
        const hasNightwatch = cfg.services.includes('nightwatch') && isLaravel;
        const hasBackup = cfg.services.includes('backup') && hasDb;
        const dbType = cfg.dbType || 'mysql';
        const certResolver = cfg.certResolver || 'cloudflare';
        const workerCmd = cfg.useHorizon ? 'horizon' : 'queue:work --tries=3 --sleep=1';

        let out = '';

        if (isLaravel) {
            out += `x-env: &default-env
  env_file:
    - .env

x-volumes: &laravel-volumes
  volumes:
    - ./.storage/logs/:/app/storage/logs
    - ./.storage/app/:/app/storage/app
    - ./.storage/framework/:/app/storage/framework

x-common: &common
  <<: [*default-env, *laravel-volumes]

`;
        }

        out += `name: ${name}_\${APP_ENV}

services:
`;

        if (isStatic) {
            out += `  ${name}-server:
    image: halverneus/static-file-server:latest
    container_name: ${name}-web
    volumes:
      - ./html:/content
    environment:
      - FOLDER=/content
    labels:
      - traefik.enable=true
      - traefik.http.routers.${name}-https.rule=Host(\`\${APP_DOMAIN}\`)
      - traefik.http.routers.${name}-https.tls=true
      - traefik.http.services.${name}-https.loadbalancer.server.port=8080
      - traefik.http.routers.${name}-https.tls.certresolver=${certResolver}
      - traefik.http.routers.${name}-https.entrypoints=websecure
    networks:
      - traefik
    restart: unless-stopped
`;
        } else if (isLaravel) {
            const octaneEngine = cfg.octaneEngine || 'frankenphp';
            const octanePort = 80;
            const octaneImage = octaneEngine === 'frankenphp' ? `dunglas/frankenphp:php8.4-alpine` : `php:8.4-cli-alpine`;
            const appTarget = octaneEngine === 'frankenphp' ? 'frankenphp' : 'octane';

            out += `  app:
    container_name: ${name}_web_\${APP_ENV}
    image: ${name}:${appTarget}
    pull_policy: never
    build:
      context: \${GITFOLDER}
      dockerfile: docker/Dockerfile
      target: ${appTarget}
      args:
        APP_ENV: \${APP_ENV:-production}
    <<: *laravel-volumes
    labels:
      - traefik.enable=true
      - traefik.http.routers.${name}_\${APP_ENV}-https.rule=Host(\`\${APP_DOMAIN}\`)
      - traefik.http.routers.${name}_\${APP_ENV}-https.tls=true
      - traefik.http.services.${name}_\${APP_ENV}-https.loadbalancer.server.port=${octanePort}
      - traefik.http.routers.${name}_\${APP_ENV}-https.tls.certresolver=${certResolver}
      - traefik.http.routers.${name}_\${APP_ENV}-https.entrypoints=websecure
    environment:
      SERVER_NAME: \${APP_DOMAIN:-:80}
      SERVER_ROOT: /app/public
    depends_on:
`;
            if (hasDb) out += `      - db\n`;
            if (hasRedis) out += `      - redis\n`;
            out += `    networks:
      - internal
      - traefik
    restart: unless-stopped
    command: ["php", "artisan", "octane:${octaneEngine}", "--host=0.0.0.0", "--port=${octanePort}", "--workers=${cfg.workers || 4}"]
    healthcheck:
      test: ["CMD-SHELL", "curl -fsS http://127.0.0.1:${octanePort}/up || exit 1"]
      interval: 15s
      timeout: 5s
      retries: 20
      start_period: 40s
`;

            if (hasWorker) {
                out += `
  worker:
    container_name: ${name}_worker_\${APP_ENV}
    image: ${name}:worker
    pull_policy: never
    build:
      context: \${GITFOLDER}
      dockerfile: docker/Dockerfile
      target: worker
      args:
        APP_ENV: \${APP_ENV:-production}
    <<: *common
    command: ["php", "artisan", "${workerCmd}"]
    depends_on:
`;
                if (hasDb) out += `      - db\n`;
                if (hasRedis) out += `      - redis\n`;
                out += `    networks:
      - internal
    restart: unless-stopped
    healthcheck:
      test: ["CMD-SHELL", "php artisan inspire >/dev/null 2>&1 || exit 1"]
      interval: 15s
      timeout: 2s
      retries: 10
`;
            }

            if (hasScheduler) {
                out += `
  scheduler:
    container_name: ${name}_scheduler_\${APP_ENV}
    image: ${name}:worker
    <<: *laravel-volumes
    command: ["php", "artisan", "schedule:work"]
    depends_on:
`;
                if (hasDb) out += `      - db\n`;
                if (hasRedis) out += `      - redis\n`;
                out += `    networks:
      - internal
    restart: unless-stopped
`;
            }

            if (hasPulse) {
                out += `
  pulse_check:
    container_name: ${name}_pulse_check_\${APP_ENV}
    image: ${name}:worker
    <<: *laravel-volumes
    command: ["php", "artisan", "pulse:check"]
    depends_on:
`;
                if (hasDb) out += `      - db\n`;
                if (hasRedis) out += `      - redis\n`;
                out += `    networks:
      - internal
    restart: unless-stopped

  pulse_work:
    container_name: ${name}_pulse_work_\${APP_ENV}
    image: ${name}:worker
    <<: *laravel-volumes
    command: ["php", "artisan", "pulse:work"]
    depends_on:
`;
                if (hasDb) out += `      - db\n`;
                if (hasRedis) out += `      - redis\n`;
                out += `    networks:
      - internal
    restart: unless-stopped
`;
            }

            if (hasNightwatch) {
                out += `
  nightwatch:
    container_name: ${name}_nightwatch_\${APP_ENV}
    image: laravelphp/nightwatch-agent:latest
    environment:
      NIGHTWATCH_TOKEN: \${NIGHTWATCH_TOKEN}
    networks:
      - internal
    restart: unless-stopped
    healthcheck:
      test: php nightwatch-status
      interval: 30s
      timeout: 5s
      retries: 3
      start_period: 5s
`;
            }
        } else {
            // Generic external service
            const port = cfg.appPort || 80;
            out += `  app:
    image: ${cfg.appImage || 'your-image:latest'}
    container_name: ${name}
    restart: unless-stopped
    env_file: .env
    networks:
`;
            if (hasDb || hasRedis) out += `      - internal\n`;
            out += `      - traefik
    labels:
      - traefik.enable=true
      - traefik.http.routers.${name}-https.rule=Host(\`\${APP_DOMAIN}\`)
      - traefik.http.routers.${name}-https.entrypoints=websecure
      - traefik.http.routers.${name}-https.tls.certresolver=${certResolver}
      - traefik.http.services.${name}-https.loadbalancer.server.port=${port}
`;
        }

        // DB service
        if (hasDb) {
            if (dbType === 'mysql') {
                out += `
  db:
    image: mysql:${cfg.dbVersion || '9.3'}
    container_name: ${name}_db_\${APP_ENV}
    <<: *default-env
    environment:
      MYSQL_DATABASE: \${DB_DATABASE:-laravel}
      MYSQL_USER: \${DB_USERNAME:-laravel}
      MYSQL_PASSWORD: \${DB_PASSWORD:-secret}
      MYSQL_ROOT_PASSWORD: \${DB_PASSWORD:-secret}
    ports:
      - "\${DB_EXPOSE_PORT:-13306}:3306"
    volumes:
      - ./.mysql-db/:/var/lib/mysql
    networks:
      - internal
    restart: always
    healthcheck:
      test: ["CMD", "mysqladmin", "ping", "-h", "localhost"]
      timeout: 20s
      retries: 10
      start_period: 30s
`;
            } else if (dbType === 'postgres') {
                out += `
  db:
    image: postgres:${cfg.dbVersion || '18'}
    container_name: ${name}_db_\${APP_ENV}
    env_file: .env
    environment:
      POSTGRES_DB: \${DB_DATABASE:-app}
      POSTGRES_USER: \${DB_USERNAME:-app}
      POSTGRES_PASSWORD: \${DB_PASSWORD:-secret}
    ports:
      - "\${DB_EXPOSE_PORT:-15432}:5432"
    volumes:
      - ./.postgres-db/:/var/lib/postgresql/data
    networks:
      - internal
    restart: always
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U \${DB_USERNAME:-app}"]
      timeout: 20s
      retries: 10
      start_period: 30s
`;
            } else if (dbType === 'mariadb') {
                out += `
  db:
    image: mariadb:11
    container_name: ${name}_db_\${APP_ENV}
    env_file: .env
    environment:
      MARIADB_DATABASE: \${DB_DATABASE:-laravel}
      MARIADB_USER: \${DB_USERNAME:-laravel}
      MARIADB_PASSWORD: \${DB_PASSWORD:-secret}
      MARIADB_ROOT_PASSWORD: \${DB_PASSWORD:-secret}
    ports:
      - "\${DB_EXPOSE_PORT:-13306}:3306"
    volumes:
      - ./.mariadb/:/var/lib/mysql
    networks:
      - internal
    restart: always
    healthcheck:
      test: ["CMD", "healthcheck.sh", "--connect", "--innodb_initialized"]
      timeout: 20s
      retries: 10
      start_period: 30s
`;
            }
        }

        // Redis service
        if (hasRedis) {
            out += `
  redis:
    image: redis:alpine
    container_name: ${name}_redis_\${APP_ENV}
    volumes:
      - ./.redis/redis:/data
    networks:
      - internal
    healthcheck:
      test: ["CMD", "redis-cli", "ping"]
      start_period: 30s
      retries: 10
    restart: unless-stopped
`;
        }

        // Backup service
        if (hasBackup) {
            const dbHost = dbType === 'postgres' ? 'postgres' : 'mysql';
            out += `
  db_backup:
    image: tiredofit/db-backup
    container_name: db_backup_${name}_\${APP_ENV}
    depends_on:
      - db
    volumes:
      - ../../backups/\${APP_DOMAIN}/\${APP_ENV}:/backup
    environment:
      - MODE=AUTO
      - DB01_TYPE=${dbHost}
      - DB01_HOST=\${DB_HOST:-db}
      - DB01_NAME=\${DB_DATABASE}
      - DB01_USER=root
      - DB01_PASS=\${DB_PASSWORD}
      - DB01_BACKUP_BEGIN=+0
      - DB01_BACKUP_INTERVAL=1440
      - DB01_CLEANUP_TIME=10080
      - DB01_COMPRESSION=ZSTD
      - DB01_COMPRESSION_LEVEL=3
      - DB01_ENABLE_PARALLEL_COMPRESSION=TRUE
      - DB01_CHECKSUM=MD5
    networks:
      - internal
    restart: always
`;
        }

        // Networks
        out += `\nnetworks:\n`;
        const needsInternal = cfg.appType !== 'static' && (hasDb || hasRedis || cfg.appType === 'laravel' || cfg.appType === 'generic');
        if (needsInternal) {
            out += `  internal:\n`;
        }
        out += `  traefik:\n    external: true\n    name: traefik\n`;

        return out;
    }

    function genDockerfile(cfg) {
        const octaneEngine = cfg.octaneEngine || 'frankenphp';
        const phpVersion = cfg.phpVersion || '8.4';
        const baseImage = octaneEngine === 'frankenphp'
            ? `dunglas/frankenphp:php${phpVersion}-alpine`
            : `php:${phpVersion}-cli-alpine`;

        return `# Stage 1: Vendor
FROM composer:2 AS vendor
WORKDIR /app

ADD --chmod=0755 https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/
RUN install-php-extensions gd bcmath intl pcntl redis pdo_mysql

COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-scripts --no-progress

# Stage 2: Assets
FROM node:22-alpine AS assets
WORKDIR /app

COPY package.json yarn.lock ./
RUN corepack enable && corepack prepare yarn@1.22.22 --activate && yarn install --frozen-lockfile

COPY vite.config.js ./
COPY resources ./resources
COPY public ./public

ENV NODE_ENV=production
RUN yarn build

# Stage 3: Worker (CLI)
FROM php:${phpVersion}-cli-alpine AS worker
COPY --from=vendor /usr/local/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions bcmath intl pcntl gd curl pdo_mysql mbstring redis

ARG APP_ENV=production
WORKDIR /app
COPY . /app
COPY ".env.\${APP_ENV:-production}" .env
COPY --from=vendor /app/vendor /app/vendor

RUN mkdir -p storage bootstrap/cache && \\
    chown -R www-data:www-data storage bootstrap/cache && \\
    chmod -R 775 storage bootstrap/cache

USER www-data
CMD ["php", "artisan", "queue:work", "--tries=3", "--sleep=1"]

# Stage 4: ${octaneEngine === 'frankenphp' ? 'FrankenPHP' : 'Octane'} (Web)
FROM ${baseImage} AS ${octaneEngine === 'frankenphp' ? 'frankenphp' : 'octane'}
WORKDIR /app

ARG APP_ENV=production
ADD --chmod=0755 https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/
RUN install-php-extensions bcmath intl pcntl gd curl pdo_mysql mbstring redis

COPY . /app
COPY ".env.\${APP_ENV:-production}" .env
COPY --from=vendor /app/vendor /app/vendor
COPY --from=assets /app/public/build /app/public/build

RUN mkdir -p storage bootstrap/cache && \\
    chown -R www-data:www-data storage bootstrap/cache && \\
    chmod -R 775 storage bootstrap/cache

CMD ["php", "artisan", "octane:${octaneEngine}", "--host=0.0.0.0", "--port=80", "--workers=${cfg.workers || 4}"]
`;
    }

    function genRunSh(cfg) {
        const name = slugify(cfg.projectName);
        const gitFolder = cfg.gitFolder || `../${name}.git`;
        return `#!/bin/bash

GITFOLDER="${gitFolder}"
LOCALFOLDER=$(pwd)

source "$LOCALFOLDER/.env"

echo "*** Pulling repo."
cd "$GITFOLDER" || exit 1
git pull
cd "$LOCALFOLDER" || exit 1

echo "*** Copying files."
cp -f "\${GITFOLDER}/compose.yml" "$LOCALFOLDER/compose.yml"
cp -f "$LOCALFOLDER/.env" "\${GITFOLDER}/.env.\${APP_ENV}"

# Ensure correct ownership
sudo chown -R 82:82 .storage
sudo chown -R 999:999 .redis .mysql-db

echo "*** Building docker."
docker compose build && \\
    docker compose exec -u root ${name}_web_\${APP_ENV} php artisan down --retry 30 --refresh 30 && \\
    docker compose up -d --force-recreate && \\
    docker compose exec -u root ${name}_web_\${APP_ENV} chown -R www-data:www-data /app && \\
    docker compose exec -u root ${name}_web_\${APP_ENV} php artisan up && \\
    docker compose exec ${name}_web_\${APP_ENV} php artisan optimize && \\
    docker compose exec ${name}_web_\${APP_ENV} php artisan migrate --force

echo -en "\\007"
`;
    }

    function genEnv(cfg) {
        const name = slugify(cfg.projectName);
        const isLaravel = cfg.appType === 'laravel';
        const hasDb = cfg.services.includes('db');
        const hasRedis = cfg.services.includes('redis');
        const dbType = cfg.dbType || 'mysql';

        const gitFolder = cfg.gitFolder || `../${name}.git`;

        let out = `APP_ENV=production
APP_DOMAIN=${cfg.appDomain || 'example.com'}
GITFOLDER=${gitFolder}
`;
        if (isLaravel) {
            out += `
APP_KEY=
APP_DEBUG=false
APP_URL=https://\${APP_DOMAIN}
`;
        }
        if (hasDb) {
            out += `
DB_HOST=db
DB_DATABASE=${name}
DB_USERNAME=${name}
DB_PASSWORD=change_me_secret
DB_EXPOSE_PORT=13306
`;
            if (dbType === 'mysql' || dbType === 'mariadb') {
                out += `MYSQL_DATABASE=${name}
MYSQL_ROOT_PASSWORD=change_me_secret
`;
            }
        }
        if (hasRedis) {
            out += `
REDIS_HOST=redis
REDIS_PORT=6379
`;
        }
        if (cfg.certResolver === 'cloudflare' || cfg.traefikCertResolver === 'cloudflare') {
            out += `
CLOUDFLARE_DNS_API_TOKEN=your_cloudflare_token_here
`;
        }
        if (cfg.services.includes('nightwatch')) {
            out += `NIGHTWATCH_TOKEN=your_nightwatch_token_here\n`;
        }
        return out;
    }

    // ── Vue App ───────────────────────────────────────────────────────────────
    createApp({
        setup() {
            const step = ref(1);
            const totalSteps = 4;

            const cfg = reactive({
                // Step 1 – Traefik
                traefikSetup: true,
                traefikDomain: 'traefik.example.com',
                acmeEmail: 'admin@example.com',
                traefikDir: '/home/ubuntu/traefik/traefik',
                traefikCertResolver: 'cloudflare', // cloudflare | letsencrypt

                // Step 2 – App
                projectName: 'my_project',
                appType: 'laravel',   // laravel | static | generic
                appDomain: 'app.example.com',
                appImage: '',
                appPort: 80,
                certResolver: 'cloudflare',

                // Step 3 – Services
                services: ['db', 'redis', 'worker', 'scheduler'],
                dbType: 'mysql',
                dbVersion: '9.3',
                useHorizon: true,
                octaneEngine: 'frankenphp',
                phpVersion: '8.5',
                workers: 4,
                gitFolder: '',

                // Step 4 – Output (read only)
            });

            const serviceOptions = computed(() => {
                const base = [
                    { value: 'db', label: 'Database' },
                    { value: 'redis', label: 'Redis' },
                ];
                if (cfg.appType === 'laravel') {
                    base.push(
                        { value: 'worker', label: 'Queue Worker' },
                        { value: 'scheduler', label: 'Scheduler' },
                        { value: 'pulse', label: 'Laravel Pulse' },
                        { value: 'nightwatch', label: 'Nightwatch Agent' },
                    );
                }
                base.push({ value: 'backup', label: 'DB Backup (tiredofit)' });
                return base;
            });

            function toggleService(val) {
                const idx = cfg.services.indexOf(val);
                if (idx === -1) cfg.services.push(val);
                else cfg.services.splice(idx, 1);
            }

            function hasService(val) {
                return cfg.services.includes(val);
            }

            // Reset dbVersion to a sensible default when the engine changes.
            watch(() => cfg.dbType, (type) => {
                if (type === 'mysql') cfg.dbVersion = '9.3';
                else if (type === 'postgres') cfg.dbVersion = '18';
                else cfg.dbVersion = '';
            });

            // Generated outputs
            const outputs = computed(() => {
                const files = [];
                if (cfg.traefikSetup) {
                    files.push({ name: 'traefik/compose.yml', content: genTraefikCompose(cfg) });
                    files.push({ name: 'traefik/traefik.yml', content: genTraefikYml(cfg) });
                }
                files.push({ name: 'app/compose.yml', content: genAppCompose(cfg) });
                files.push({ name: 'app/.env', content: genEnv(cfg) });
                if (cfg.appType === 'laravel') {
                    files.push({ name: 'app/docker/Dockerfile', content: genDockerfile(cfg) });
                    files.push({ name: 'app/run.sh', content: genRunSh(cfg) });
                }
                return files;
            });

            const activeFile = ref(0);

            function downloadAll() {
                outputs.value.forEach(f => downloadFile(f.name.replace('/', '_'), f.content));
            }

            return {
                step, totalSteps, cfg, serviceOptions,
                toggleService, hasService, outputs, activeFile, downloadAll,
            };
        },

        template: `
<div class="space-y-8">
    <!-- Progress -->
    <div class="flex items-center gap-2">
        <template v-for="n in totalSteps" :key="n">
            <button
                @click="step = n"
                :class="[
                    'flex items-center justify-center w-9 h-9 rounded-full text-sm font-bold transition-all',
                    step === n ? 'bg-primary-600 text-white shadow-md' :
                    step > n  ? 'bg-primary-100 text-primary-700' :
                                'bg-neutral-100 text-neutral-400'
                ]"
            >{{ n }}</button>
            <div v-if="n < totalSteps" :class="['flex-1 h-1 rounded', step > n ? 'bg-primary-300' : 'bg-neutral-200']"></div>
        </template>
        <span class="ml-3 text-sm text-neutral-500 font-medium">
            {{ ['Traefik Setup', 'App Config', 'Services', 'Download'][step - 1] }}
        </span>
    </div>

    <!-- Step 1: Traefik -->
    <div v-if="step === 1" class="space-y-6">
        <div class="bg-neutral-50 rounded-2xl p-6 border border-neutral-100">
            <h2 class="text-xl font-bold text-neutral-900 mb-1">Traefik Configuration</h2>
            <p class="text-sm text-neutral-500 mb-6">Configure the Traefik reverse proxy that will manage all your services.</p>

            <label class="flex items-center gap-3 mb-6 cursor-pointer">
                <input type="checkbox" v-model="cfg.traefikSetup" class="w-4 h-4 accent-primary-600">
                <span class="font-medium text-neutral-800">Generate Traefik stack files</span>
            </label>

            <template v-if="cfg.traefikSetup">
                <div class="grid sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-neutral-700 mb-1">Traefik Dashboard Domain</label>
                        <input v-model="cfg.traefikDomain" type="text" placeholder="traefik.example.com"
                            class="w-full border border-neutral-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-400">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-neutral-700 mb-1">SSL Certificate Resolver</label>
                        <select v-model="cfg.traefikCertResolver" class="w-full border border-neutral-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-400">
                            <option value="cloudflare">Cloudflare DNS Challenge</option>
                            <option value="letsencrypt">Let's Encrypt HTTP Challenge</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-neutral-700 mb-1">ACME Email</label>
                        <input v-model="cfg.acmeEmail" type="email" placeholder="admin@example.com"
                            class="w-full border border-neutral-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-400">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-semibold text-neutral-700 mb-1">Traefik Config Directory (on host)</label>
                        <input v-model="cfg.traefikDir" type="text" placeholder="/home/ubuntu/traefik/traefik"
                            class="w-full border border-neutral-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-400">
                    </div>
                </div>
                <div v-if="cfg.traefikCertResolver === 'cloudflare'" class="mt-4 p-4 bg-amber-50 border border-amber-200 rounded-xl text-sm text-amber-800">
                    <strong>Cloudflare DNS Challenge:</strong> Set <code class="bg-amber-100 px-1 rounded">CLOUDFLARE_DNS_API_TOKEN</code> and <code class="bg-amber-100 px-1 rounded">TRAEFIK_BASIC_AUTH</code> in your environment before starting Traefik.
                    Generate the basic auth hash with: <code class="bg-amber-100 px-1 rounded">echo $(htpasswd -nB user) | sed -e s/\\$/\\$\\$/g</code>
                </div>
                <div v-else class="mt-4 p-4 bg-blue-50 border border-blue-200 rounded-xl text-sm text-blue-800">
                    <strong>Let's Encrypt HTTP Challenge:</strong> Port 80 must be publicly accessible for domain validation. Set <code class="bg-blue-100 px-1 rounded">TRAEFIK_BASIC_AUTH</code> before starting Traefik.
                    Generate the basic auth hash with: <code class="bg-blue-100 px-1 rounded">echo $(htpasswd -nB user) | sed -e s/\\$/\\$\\$/g</code>
                </div>
            </template>
        </div>
        <div class="flex justify-end">
            <button @click="step = 2" class="bg-primary-600 hover:bg-primary-700 text-white font-semibold px-6 py-2.5 rounded-xl transition-colors">
                Next: App Config →
            </button>
        </div>
    </div>

    <!-- Step 2: App Config -->
    <div v-if="step === 2" class="space-y-6">
        <div class="bg-neutral-50 rounded-2xl p-6 border border-neutral-100">
            <h2 class="text-xl font-bold text-neutral-900 mb-1">Application Configuration</h2>
            <p class="text-sm text-neutral-500 mb-6">Configure your application's basic settings.</p>

            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-neutral-700 mb-1">Project Name</label>
                    <input v-model="cfg.projectName" type="text" placeholder="my_project"
                        class="w-full border border-neutral-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-400">
                    <p class="text-xs text-neutral-400 mt-1">Used as container/network prefix. No spaces.</p>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-neutral-700 mb-1">App Domain</label>
                    <input v-model="cfg.appDomain" type="text" placeholder="app.example.com"
                        class="w-full border border-neutral-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-400">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-neutral-700 mb-1">Application Type</label>
                    <select v-model="cfg.appType" class="w-full border border-neutral-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-400">
                        <option value="laravel">Laravel (Octane)</option>
                        <option value="static">Static Website</option>
                        <option value="generic">Generic / External Service</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-neutral-700 mb-1">SSL Certificate Resolver</label>
                    <select v-model="cfg.certResolver" class="w-full border border-neutral-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-400">
                        <option value="cloudflare">Cloudflare DNS Challenge</option>
                        <option value="letsencrypt">Let's Encrypt HTTP Challenge</option>
                    </select>
                </div>

                <!-- Laravel specific -->
                <template v-if="cfg.appType === 'laravel'">
                    <div>
                        <label class="block text-sm font-semibold text-neutral-700 mb-1">Octane Engine</label>
                        <select v-model="cfg.octaneEngine" class="w-full border border-neutral-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-400">
                            <option value="frankenphp">FrankenPHP</option>
                            <option value="swoole">Swoole</option>
                            <option value="roadrunner">RoadRunner</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-neutral-700 mb-1">PHP Version</label>
                        <select v-model="cfg.phpVersion" class="w-full border border-neutral-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-400">
                            <option value="8.5">8.5</option>
                            <option value="8.4">8.4</option>
                            <option value="8.3">8.3</option>
                            <option value="8.2">8.2</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-neutral-700 mb-1">Octane Workers</label>
                        <input v-model.number="cfg.workers" type="number" min="1" max="32"
                            class="w-full border border-neutral-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-400">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-neutral-700 mb-1">Git Repo Folder</label>
                        <input v-model="cfg.gitFolder" type="text" placeholder="../project.git"
                            class="w-full border border-neutral-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-400">
                        <p class="text-xs text-neutral-400 mt-1">Used as Docker build context and in run.sh. The Dockerfile is read from this folder.</p>
                    </div>
                </template>

                <!-- Generic specific -->
                <template v-if="cfg.appType === 'generic'">
                    <div>
                        <label class="block text-sm font-semibold text-neutral-700 mb-1">Docker Image</label>
                        <input v-model="cfg.appImage" type="text" placeholder="your-image:latest"
                            class="w-full border border-neutral-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-400">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-neutral-700 mb-1">Internal App Port</label>
                        <input v-model.number="cfg.appPort" type="number"
                            class="w-full border border-neutral-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-400">
                    </div>
                </template>
            </div>
        </div>
        <div class="flex justify-between">
            <button @click="step = 1" class="border border-neutral-200 text-neutral-700 font-semibold px-6 py-2.5 rounded-xl hover:bg-neutral-50 transition-colors">
                ← Back
            </button>
            <button @click="step = 3" class="bg-primary-600 hover:bg-primary-700 text-white font-semibold px-6 py-2.5 rounded-xl transition-colors">
                Next: Services →
            </button>
        </div>
    </div>

    <!-- Step 3: Services -->
    <div v-if="step === 3" class="space-y-6">
        <div class="bg-neutral-50 rounded-2xl p-6 border border-neutral-100">
            <h2 class="text-xl font-bold text-neutral-900 mb-1">Services</h2>
            <p class="text-sm text-neutral-500 mb-6">Select the services to include in your stack.</p>

            <div class="grid sm:grid-cols-2 gap-3 mb-6">
                <label v-for="opt in serviceOptions" :key="opt.value"
                    class="flex items-center gap-3 p-3 rounded-xl border cursor-pointer transition-all"
                    :class="hasService(opt.value) ? 'border-primary-400 bg-primary-50' : 'border-neutral-200 bg-white hover:border-neutral-300'"
                >
                    <input type="checkbox" :checked="hasService(opt.value)" @change="toggleService(opt.value)" class="w-4 h-4 accent-primary-600">
                    <span class="font-medium text-neutral-800 text-sm">{{ opt.label }}</span>
                </label>
            </div>

            <!-- DB type -->
            <div v-if="hasService('db')" class="grid sm:grid-cols-2 gap-4 pt-4 border-t border-neutral-200">
                <div>
                    <label class="block text-sm font-semibold text-neutral-700 mb-1">Database Engine</label>
                    <select v-model="cfg.dbType" class="w-full border border-neutral-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-400">
                        <option value="mysql">MySQL</option>
                        <option value="mariadb">MariaDB 11</option>
                        <option value="postgres">PostgreSQL</option>
                    </select>
                </div>
                <div v-if="cfg.dbType === 'mysql'">
                    <label class="block text-sm font-semibold text-neutral-700 mb-1">MySQL Version</label>
                    <select v-model="cfg.dbVersion" class="w-full border border-neutral-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-400">
                        <option value="9.3">9.3 (latest)</option>
                        <option value="9.2">9.2</option>
                        <option value="9.1">9.1</option>
                        <option value="9.0">9.0</option>
                        <option value="8.4">8.4 (LTS)</option>
                        <option value="8.0">8.0</option>
                    </select>
                </div>
                <div v-if="cfg.dbType === 'postgres'">
                    <label class="block text-sm font-semibold text-neutral-700 mb-1">PostgreSQL Version</label>
                    <select v-model="cfg.dbVersion" class="w-full border border-neutral-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-400">
                        <option value="18">18 (latest)</option>
                        <option value="17">17</option>
                        <option value="16">16</option>
                    </select>
                </div>
                <div v-if="cfg.appType === 'laravel' && hasService('worker')">
                    <label class="block text-sm font-semibold text-neutral-700 mb-1">Queue Driver</label>
                    <select v-model="cfg.useHorizon" class="w-full border border-neutral-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-400">
                        <option :value="true">Laravel Horizon</option>
                        <option :value="false">queue:work (basic)</option>
                    </select>
                </div>
            </div>
        </div>
        <div class="flex justify-between">
            <button @click="step = 2" class="border border-neutral-200 text-neutral-700 font-semibold px-6 py-2.5 rounded-xl hover:bg-neutral-50 transition-colors">
                ← Back
            </button>
            <button @click="step = 4; activeFile = 0" class="bg-primary-600 hover:bg-primary-700 text-white font-semibold px-6 py-2.5 rounded-xl transition-colors">
                Generate Files →
            </button>
        </div>
    </div>

    <!-- Step 4: Output -->
    <div v-if="step === 4" class="space-y-6">
        <div class="bg-neutral-50 rounded-2xl border border-neutral-100 overflow-hidden">
            <div class="flex items-center justify-between px-6 py-4 border-b border-neutral-200 bg-white">
                <h2 class="text-xl font-bold text-neutral-900">Generated Files</h2>
                <button @click="downloadAll" class="bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold px-4 py-2 rounded-lg transition-colors flex items-center gap-2">
                    ⬇ Download All
                </button>
            </div>

            <!-- File tabs -->
            <div class="flex overflow-x-auto border-b border-neutral-200 bg-white">
                <button
                    v-for="(file, idx) in outputs"
                    :key="idx"
                    @click="activeFile = idx"
                    :class="[
                        'px-4 py-2.5 text-xs font-mono font-semibold whitespace-nowrap border-b-2 transition-colors',
                        activeFile === idx
                            ? 'border-primary-500 text-primary-700 bg-primary-50'
                            : 'border-transparent text-neutral-500 hover:text-neutral-700'
                    ]"
                >{{ file.name }}</button>
            </div>

            <!-- File content -->
            <div class="relative">
                <button
                    @click="downloadFile(outputs[activeFile].name.replace('/', '_'), outputs[activeFile].content)"
                    class="absolute top-3 right-3 z-10 bg-white border border-neutral-200 text-neutral-600 hover:text-primary-600 text-xs font-semibold px-3 py-1.5 rounded-lg transition-colors shadow-sm"
                >⬇ Download</button>
                <pre class="overflow-auto text-xs leading-relaxed p-6 bg-neutral-900 text-green-300 max-h-[500px]"><code>{{ outputs[activeFile]?.content }}</code></pre>
            </div>
        </div>

        <div class="p-5 bg-blue-50 border border-blue-200 rounded-xl text-sm text-blue-800">
            <strong>Next steps:</strong>
            <ol class="list-decimal list-inside mt-2 space-y-1">
                <li>Review and adjust the generated files for your project specifics.</li>
                <li>Create the external Docker network: <code class="bg-blue-100 px-1 rounded">docker network create traefik</code></li>
                <li v-if="cfg.traefikSetup">Start Traefik first: <code class="bg-blue-100 px-1 rounded">cd traefik && docker compose up -d</code></li>
                <li>Deploy your app: <code class="bg-blue-100 px-1 rounded">cd app && docker compose up -d</code></li>
                <li v-if="cfg.appType === 'laravel'">Make <code class="bg-blue-100 px-1 rounded">run.sh</code> executable: <code class="bg-blue-100 px-1 rounded">chmod +x run.sh</code></li>
            </ol>
        </div>

        <div class="flex justify-between">
            <button @click="step = 3" class="border border-neutral-200 text-neutral-700 font-semibold px-6 py-2.5 rounded-xl hover:bg-neutral-50 transition-colors">
                ← Back
            </button>
            <button @click="step = 1" class="border border-neutral-200 text-neutral-700 font-semibold px-6 py-2.5 rounded-xl hover:bg-neutral-50 transition-colors">
                Start Over
            </button>
        </div>
    </div>
</div>
        `,
    }).mount('#app');
