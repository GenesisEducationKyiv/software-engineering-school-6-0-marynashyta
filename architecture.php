<?php

/**
 * Platform architecture manifest.
 *
 * The single source of truth for `bin/compile-architecture.php`. It lists every service
 * (each a separate repo/checkout with its own docs/architecture/ARCHITECTURE.md and src/),
 * an optional shared source tree (contracts), and the declared dependency edges between
 * them. Paths are resolved relative to THIS file's directory.
 *
 * Keep this in sync with reality: the compiler fails if a listed service is missing its
 * source or its architecture doc, so drift shows up in CI.
 */

declare(strict_types=1);

return [
    // Where the compiled landscape document is written.
    'output' => __DIR__ . '/docs/LANDSCAPE.md',

    // Human-readable platform name for the document title.
    'platform' => 'GitHub Release Notification Platform',

    // No separate shared contracts package exists yet — services share a queue name
    // ('notifications') and HTTP/OpenAPI contracts declared per-service instead.
    'shared' => null,

    // One entry per service. `path` is the repo/checkout root; `src` and `docs` are relative
    // to it. `namespace` is the service's PSR-4 root. All three currently live in this
    // monorepo as the monolith is strangled into standalone services. Optional `group` puts
    // the node in a labelled subgraph on the compiled landscape diagram — services without
    // one (the monolith) render outside any subgraph.
    'services' => [
        [
            'name'      => 'API',
            'namespace' => 'App',
            'path'      => __DIR__,
            'src'       => 'src',
            'docs'      => 'docs/architecture/domain-map.md',
        ],
        [
            'name'      => 'Notification Service',
            'owner'     => 'Maryna Shyta',
            'namespace' => 'NotificationService',
            'path'      => __DIR__ . '/notification-service',
            'src'       => 'src',
            'docs'      => 'docs/architecture/ARCHITECTURE.md',
            'group'     => 'Extracted microservices',
        ],
        [
            'name'      => 'Scanner Service',
            'owner'     => 'Maryna Shyta',
            'namespace' => 'ScannerService',
            'path'      => __DIR__ . '/scanner-service',
            'src'       => 'src',
            'docs'      => 'docs/architecture/ARCHITECTURE.md',
            'group'     => 'Extracted microservices',
        ],
    ],

    // Third-party systems the platform depends on but doesn't own the code or docs for — no
    // src/docs to verify, they exist purely so edges can point at them and so the landscape
    // distinguishes "our services" from "infrastructure we call". Optional `group` works the
    // same as on a service.
    'externals' => [
        ['name' => 'GitHub REST API'],
        ['name' => 'RabbitMQ'],
        ['name' => 'SMTP Server'],
    ],

    // Declared dependencies. `style` renders the arrow: sync (solid), async (dashed event),
    // lib (uses a shared library/contract). `from`/`to` may name a service or an external.
    // The compiler draws the landscape from these and treats an edge to an unknown node as
    // an error.
    'edges' => [
        // Subscribe saga (SubscribeSagaOrchestrator): create the subscription, then dispatch
        // the confirmation email through whichever ConfirmationMailerInterface adapter
        // NOTIFICATION_DRIVER selects. HTTP and AMQP are both live code paths.
        ['from' => 'API',              'to' => 'Notification Service', 'via' => 'HTTP POST /send-confirmation, /send-notification (NOTIFICATION_DRIVER=http)', 'style' => 'sync'],
        ['from' => 'API',              'to' => 'RabbitMQ',             'via' => 'publish send_confirmation/send_notification (NOTIFICATION_DRIVER=amqp)',       'style' => 'async'],
        // Rollback-only third driver: EmailService talks to SMTP directly, in-process, no
        // network hop to Notification Service at all.
        ['from' => 'API',              'to' => 'SMTP Server',          'via' => 'EmailService (NOTIFICATION_DRIVER=in_process, rollback only)',                 'style' => 'async'],
        // API validates the repo and snapshots the latest release tag directly against GitHub
        // (its own GitHubServiceInterface — not Scanner Service's client).
        ['from' => 'API',              'to' => 'GitHub REST API',      'via' => 'GET /repos/{repo}, GET /repos/{repo}/releases/latest',                         'style' => 'sync'],
        // Scanner Service reads/updates subscriptions through the API's internal endpoints
        // (database-per-service: it never touches the API's database directly).
        ['from' => 'Scanner Service',  'to' => 'API',                  'via' => 'HTTP GET/PATCH /internal/subscriptions',                                        'style' => 'sync'],
        // Release polling checks each subscribed repo's latest tag with its own GitHub client.
        ['from' => 'Scanner Service',  'to' => 'GitHub REST API',      'via' => 'GET /repos/{repo}/releases/latest',                                             'style' => 'sync'],
        // Release polling dispatches notifications by publishing to the same queue the API's
        // AMQP driver uses.
        ['from' => 'Scanner Service',  'to' => 'RabbitMQ',             'via' => 'publish send_notification',                                                     'style' => 'async'],
        // Both services' AMQP messages land on the same queue; Notification Service is the
        // only consumer.
        ['from' => 'RabbitMQ',         'to' => 'Notification Service', 'via' => 'consume notifications (basic_consume)',                                         'style' => 'async'],
        // Notification Service's only outward dependency: delivering the actual email.
        ['from' => 'Notification Service', 'to' => 'SMTP Server',      'via' => 'deliver email (PHPMailer)',                                                     'style' => 'sync'],
    ],
];
