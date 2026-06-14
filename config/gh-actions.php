<?php

return [
    'github' => [
        'api_base_url' => 'https://api.github.com',
        'token' => env('GITHUB_TOKEN'),
    ],

    'cache' => [
        'store' => 'redis',
        'action_ttl' => 21600,       // 6 hours
        'negative_ttl' => 86400,     // 24 hours
        'popular_ttl' => 86400,      // 24 hours
    ],

    'popular_actions' => [
        'ci' => [
            'actions/checkout',
            'actions/setup-node',
            'actions/setup-python',
            'actions/setup-go',
            'actions/setup-java',
            'actions/setup-dotnet',
            'actions/cache',
            'actions/upload-artifact',
            'actions/download-artifact',
        ],
        'deployment' => [
            'actions/deploy-pages',
            'peaceiris/actions-gh-pages',
            'JamesIves/github-pages-deploy-action',
            'appleboy/ssh-action',
            'easingthemes/ssh-deploy',
            'SamKirkland/FTP-Deploy-Action',
            'burnett01/rsync-deployments',
        ],
        'security' => [
            'github/codeql-action',
            'actions/dependency-review-action',
            'aquasecurity/trivy-action',
            'snyk/actions',
            'anchore/scan-action',
            'step-security/harden-runner',
        ],
        'utility' => [
            'actions/create-release',
            'stefanzweifel/git-auto-commit-action',
            'peter-evans/create-pull-request',
            'actions/labeler',
            'rtCamp/action-slack-notify',
            'marocchino/sticky-pull-request-comment',
            'peter-evans/repository-dispatch',
        ],
        'docker' => [
            'docker/login-action',
            'docker/build-push-action',
            'docker/setup-buildx-action',
            'docker/setup-qemu-action',
            'docker/metadata-action',
            'docker/ bake-action',
        ],
        'node' => [
            'pnpm/action-setup',
            'actions/setup-node',
            'bahmutov/npm-install',
            'cycjimmy/semantic-release-action',
        ],
    ],
];
