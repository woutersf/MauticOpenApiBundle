<?php

declare(strict_types=1);

return [
    'name'        => 'OpenAPI Specification',
    'description' => 'Exposes the Mautic REST API as an OpenAPI 3.1.0 specification at <code>/openapi</code>, with a Swagger UI viewer at <code>/openapi/view</code>.',
    'version'     => '1.0.0',
    'author'      => 'Dropsolid',

    'routes' => [
        'public' => [
            // /openapi/view must be defined before /openapi to avoid matching issues
            'mautic_openapi_view' => [
                'path'       => '/openapi/view',
                'controller' => 'MauticPlugin\MauticOpenApiBundle\Controller\OpenApiController::viewAction',
                'method'     => ['GET'],
            ],
            'mautic_openapi_spec' => [
                'path'       => '/openapi',
                'controller' => 'MauticPlugin\MauticOpenApiBundle\Controller\OpenApiController::specAction',
                'method'     => ['GET'],
            ],
        ],
    ],

    'services' => [
        'other' => [
            'mautic.openapi.service.spec' => [
                'class'     => \MauticPlugin\MauticOpenApiBundle\Service\OpenApiSpecService::class,
                'arguments' => [
                    'mautic.helper.core_parameters',
                ],
            ],
        ],
        'integrations' => [
            'mautic.integration.openapi' => [
                'class'     => \MauticPlugin\MauticOpenApiBundle\Integration\OpenApiIntegration::class,
                'arguments' => [
                    'event_dispatcher',
                    'mautic.helper.cache_storage',
                    'doctrine.orm.entity_manager',
                    'session',
                    'request_stack',
                    'router',
                    'translator',
                    'monolog.logger.mautic',
                    'mautic.helper.encryption',
                    'mautic.lead.model.lead',
                    'mautic.lead.model.company',
                    'mautic.helper.paths',
                    'mautic.core.model.notification',
                    'mautic.lead.model.field',
                    'mautic.plugin.model.integration_entity',
                    'mautic.lead.model.dnc',
                ],
            ],
        ],
    ],
];
