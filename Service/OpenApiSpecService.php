<?php

declare(strict_types=1);

namespace MauticPlugin\MauticOpenApiBundle\Service;

use Mautic\CoreBundle\Helper\CoreParametersHelper;

/**
 * Builds a comprehensive OpenAPI 3.1.0 specification for the Mautic REST API.
 *
 * Paths and tags are generated from the live Symfony routing table, so the
 * document always matches the endpoints this instance actually serves,
 * including endpoints contributed by plugins. Schemas and prose are curated
 * here. The server URL comes from the Mautic site_url configuration value.
 */
class OpenApiSpecService
{
    /**
     * Cached result of the single path/tag generation pass.
     *
     * @var array{paths: array<string, array<string, mixed>>, tags: list<array{name: string, description: string}>}|null
     */
    private ?array $generated = null;

    public function __construct(
        private readonly CoreParametersHelper $coreParams,
        private readonly RoutePathBuilder $pathBuilder,
    ) {
    }

    public function getSpec(): array
    {
        $siteUrl = rtrim((string) $this->coreParams->get('site_url', ''), '/');

        return [
            'openapi'    => '3.1.0',
            'info'       => $this->buildInfo(),
            'servers'    => [
                [
                    'url'         => $siteUrl . '/api',
                    'description' => 'This Mautic instance',
                ],
            ],
            'security'   => [
                ['BasicAuth' => []],
                ['OAuth2'    => []],
            ],
            'tags'       => $this->buildTags(),
            'paths'      => $this->buildPaths(),
            'components' => $this->buildComponents($siteUrl),
        ];
    }

    // -------------------------------------------------------------------------
    // Info
    // -------------------------------------------------------------------------

    private function buildInfo(): array
    {
        return [
            'title'       => 'Mautic REST API',
            'version'     => defined('MAUTIC_VERSION') ? MAUTIC_VERSION : '0.0.0',
            'description' => <<<'MD'
The **Mautic REST API** lets you read and write Mautic data programmatically.

Every path and method in this document is generated from the routing table of
this instance, so it describes the endpoints that are actually available here.

## Write operations use explicit route suffixes

Mautic does not use REST verbs on the collection URL. Creating, updating and
deleting each have their own path:

| Operation | Path | Method |
|---|---|---|
| Create | `/contacts/new` | POST |
| Replace | `/contacts/{id}/edit` | PUT |
| Update | `/contacts/{id}/edit` | PATCH |
| Delete | `/contacts/{id}/delete` | DELETE |
| Batch create | `/contacts/batch/new` | POST |
| Batch update | `/contacts/batch/edit` | PUT or PATCH |
| Batch delete | `/contacts/batch/delete` | DELETE |

`POST /contacts` does not exist and returns 405. The same pattern applies to
every resource in this document.

PUT replaces the record and creates it when no record with that ID exists.
PATCH updates only the fields you send.

## Authentication

All endpoints require authentication via one of:

- **HTTP Basic Auth** – pass `username:password` base-64 encoded in the `Authorization` header.
  Must be enabled under *Configuration → API Settings → Enable HTTP Basic Auth*.
- **OAuth 2.0** – obtain an access token via the Authorization Code or Client Credentials flow.

## Pagination

List endpoints accept the following query parameters:

| Parameter | Type | Default | Description |
|---|---|---|---|
| `start` | integer | 0 | Offset (0-based) |
| `limit` | integer | 30 | Items per page (max 1000) |
| `search` | string | | Search/filter query |
| `orderBy` | string | | Field to order by |
| `orderByDir` | string | `ASC` | `ASC` or `DESC` |
| `publishedOnly` | boolean | false | Return only published records |
| `minimal` | boolean | false | Omit relational data |

## List response format

Resources are returned as an object keyed by string ID (not an array):

```json
{
  "total": "42",
  "contacts": {
    "1": { ... },
    "2": { ... }
  }
}
```

## Error format

```json
{
  "errors": [
    { "code": 404, "message": "Resource not found", "details": [] }
  ]
}
```

## API Platform endpoints

This instance also serves a second API under `/api/v2`, built with API Platform.
Those endpoints are not described here. API Platform publishes its own OpenAPI
document for them at `/api/v2/docs.json`, which requires the same authentication
as the rest of the API.
MD,
            'contact'     => [
                'name' => 'Mautic Community',
                'url'  => 'https://mautic.org',
            ],
            'license'     => [
                'name' => 'GNU GPL v3',
                'url'  => 'https://www.gnu.org/licenses/gpl-3.0.html',
            ],
            'x-logo'      => [
                'url'             => 'https://www.mautic.org/media/images/mautic-logo-db.png',
                'backgroundColor' => '#4e5e9e',
                'altText'         => 'Mautic',
            ],
        ];
    }

    // -------------------------------------------------------------------------
    // Tags and paths, generated from the live routing table
    // -------------------------------------------------------------------------

    /**
     * @return list<array{name: string, description: string}>
     */
    private function buildTags(): array
    {
        return $this->generated()['tags'];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function buildPaths(): array
    {
        return $this->generated()['paths'];
    }

    /**
     * Paths and tags are produced in a single pass and reused within the request.
     *
     * @return array{paths: array<string, array<string, mixed>>, tags: list<array{name: string, description: string}>}
     */
    private function generated(): array
    {
        return $this->generated ??= $this->pathBuilder->build(array_keys($this->buildSchemas()));
    }

    // =========================================================================
    // Components
    // =========================================================================

    private function buildComponents(string $siteUrl): array
    {
        return [
            'securitySchemes' => $this->buildSecuritySchemes($siteUrl),
            'parameters'      => $this->buildComponentParameters(),
            'responses'       => $this->buildComponentResponses(),
            'schemas'         => $this->buildSchemas(),
        ];
    }

    private function buildSecuritySchemes(string $siteUrl): array
    {
        return [
            'BasicAuth' => [
                'type'        => 'http',
                'scheme'      => 'basic',
                'description' => 'HTTP Basic Authentication. Must be enabled in Mautic API Settings.',
            ],
            'OAuth2' => [
                'type'        => 'oauth2',
                'description' => 'OAuth 2.0 authentication',
                'flows'       => [
                    'authorizationCode' => [
                        'authorizationUrl' => $siteUrl . '/oauth/v2/authorize',
                        'tokenUrl'         => $siteUrl . '/oauth/v2/token',
                        'scopes'           => new \stdClass(), // must serialise as a JSON object, not []
                    ],
                    'clientCredentials' => [
                        'tokenUrl' => $siteUrl . '/oauth/v2/token',
                        'scopes'   => new \stdClass(), // must serialise as a JSON object, not []
                    ],
                ],
            ],
        ];
    }

    private function buildComponentParameters(): array
    {
        return [
            'id' => [
                'name'        => 'id',
                'in'          => 'path',
                'required'    => true,
                'description' => 'The resource ID',
                'schema'      => ['type' => 'integer', 'minimum' => 1],
            ],
            'start' => [
                'name'   => 'start',
                'in'     => 'query',
                'schema' => ['type' => 'integer', 'minimum' => 0, 'default' => 0],
            ],
            'limit' => [
                'name'   => 'limit',
                'in'     => 'query',
                'schema' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 1000, 'default' => 30],
            ],
            'search' => [
                'name'   => 'search',
                'in'     => 'query',
                'schema' => ['type' => 'string'],
            ],
            'orderBy' => [
                'name'   => 'orderBy',
                'in'     => 'query',
                'schema' => ['type' => 'string'],
            ],
            'orderByDir' => [
                'name'   => 'orderByDir',
                'in'     => 'query',
                'schema' => ['type' => 'string', 'enum' => ['ASC', 'DESC'], 'default' => 'ASC'],
            ],
            'publishedOnly' => [
                'name'   => 'publishedOnly',
                'in'     => 'query',
                'schema' => ['type' => 'boolean', 'default' => false],
            ],
            'minimal' => [
                'name'        => 'minimal',
                'in'          => 'query',
                'description' => 'Return minimal data (no relationships)',
                'schema'      => ['type' => 'boolean', 'default' => false],
            ],
        ];
    }

    private function buildComponentResponses(): array
    {
        return [
            'Unauthorized' => [
                'description' => 'Authentication required',
                'content'     => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/ApiError']]],
            ],
            'Forbidden' => [
                'description' => 'Access denied',
                'content'     => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/ApiError']]],
            ],
            'NotFound' => [
                'description' => 'Resource not found',
                'content'     => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/ApiError']]],
            ],
            'BadRequest' => [
                'description' => 'Bad request / validation error',
                'content'     => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/ApiError']]],
            ],
            'UnprocessableEntity' => [
                'description' => 'Validation failed',
                'content'     => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/ApiError']]],
            ],
        ];
    }

    // =========================================================================
    // Schemas
    // =========================================================================

    private function buildSchemas(): array
    {
        return array_merge(
            $this->coreSchemas(),
            $this->contactSchemas(),
            $this->companySchemas(),
            $this->segmentSchemas(),
            $this->campaignSchemas(),
            $this->emailSchemas(),
            $this->formSchemas(),
            $this->pageSchemas(),
            $this->assetSchemas(),
            $this->tagSchemas(),
            $this->categorySchemas(),
            $this->fieldSchemas(),
            $this->noteSchemas(),
            $this->userSchemas(),
            $this->roleSchemas(),
            $this->reportSchemas(),
            $this->notificationSchemas(),
            $this->messageSchemas(),
            $this->pointSchemas(),
            $this->dynamicContentSchemas(),
            $this->helperSchemas(),
        );
    }

    private function coreSchemas(): array
    {
        return [
            'ApiError' => [
                'type'       => 'object',
                'properties' => [
                    'errors' => [
                        'type'  => 'array',
                        'items' => [
                            'type'       => 'object',
                            'properties' => [
                                'code'    => ['type' => 'integer'],
                                'message' => ['type' => 'string'],
                                'details' => ['type' => 'array', 'items' => ['type' => 'string']],
                            ],
                        ],
                    ],
                ],
            ],
            'BaseResource' => [
                'type'       => 'object',
                'properties' => [
                    'id'              => ['type' => 'integer', 'readOnly' => true],
                    'isPublished'     => ['type' => 'boolean'],
                    'dateAdded'       => ['type' => 'string', 'format' => 'date-time', 'readOnly' => true],
                    'dateModified'    => ['type' => ['string', 'null'], 'format' => 'date-time', 'readOnly' => true],
                    'createdBy'       => ['type' => ['integer', 'null'], 'readOnly' => true],
                    'createdByUser'   => ['type' => ['string', 'null'], 'readOnly' => true],
                    'modifiedBy'      => ['type' => ['integer', 'null'], 'readOnly' => true],
                    'modifiedByUser'  => ['type' => ['string', 'null'], 'readOnly' => true],
                ],
            ],
            'OwnerRef' => [
                'type'       => 'object',
                'properties' => [
                    'id'        => ['type' => 'integer'],
                    'username'  => ['type' => 'string'],
                    'firstName' => ['type' => 'string'],
                    'lastName'  => ['type' => 'string'],
                ],
            ],
        ];
    }

    private function contactSchemas(): array
    {
        return [
            'Contact' => [
                'allOf' => [
                    ['$ref' => '#/components/schemas/BaseResource'],
                    [
                        'type'       => 'object',
                        'properties' => [
                            'points'         => ['type' => 'integer'],
                            'lastActive'     => ['type' => ['string', 'null'], 'format' => 'date-time'],
                            'dateIdentified' => ['type' => ['string', 'null'], 'format' => 'date-time'],
                            'color'          => ['type' => ['string', 'null']],
                            'owner'          => ['oneOf' => [['$ref' => '#/components/schemas/OwnerRef'], ['type' => 'null']]],
                            'ipAddresses'    => ['type' => 'object', 'additionalProperties' => ['type' => 'object']],
                            'tags'           => [
                                'type'  => 'array',
                                'items' => ['type' => 'object', 'properties' => ['tag' => ['type' => 'string']]],
                            ],
                            'stage'          => ['type' => ['object', 'null']],
                            'fields'         => [
                                'type'        => 'object',
                                'description' => 'Custom fields grouped by group name (core, social, personal, professional). Each group is an object of alias → field value info.',
                                'properties'  => [
                                    'core'         => ['type' => 'object', 'additionalProperties' => ['$ref' => '#/components/schemas/ContactFieldValue']],
                                    'social'       => ['type' => 'object', 'additionalProperties' => ['$ref' => '#/components/schemas/ContactFieldValue']],
                                    'personal'     => ['type' => 'object', 'additionalProperties' => ['$ref' => '#/components/schemas/ContactFieldValue']],
                                    'professional' => ['type' => 'object', 'additionalProperties' => ['$ref' => '#/components/schemas/ContactFieldValue']],
                                    'all'          => ['type' => 'object', 'additionalProperties' => true, 'description' => 'Flat map of alias → value for all fields'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'ContactFieldValue' => [
                'type'       => 'object',
                'properties' => [
                    'id'              => ['type' => 'integer'],
                    'label'           => ['type' => 'string'],
                    'alias'           => ['type' => 'string'],
                    'type'            => ['type' => 'string'],
                    'group'           => ['type' => 'string'],
                    'value'           => ['description' => 'Current value (type depends on field type)'],
                    'normalizedValue' => ['description' => 'Normalized/display value'],
                ],
            ],
            'ContactInput' => [
                'type'       => 'object',
                'description' => 'Fields are the contact field aliases. Common fields listed; pass any custom field alias.',
                'properties' => [
                    'firstname'   => ['type' => 'string'],
                    'lastname'    => ['type' => 'string'],
                    'email'       => ['type' => 'string', 'format' => 'email'],
                    'company'     => ['type' => 'string'],
                    'phone'       => ['type' => 'string'],
                    'mobile'      => ['type' => 'string'],
                    'address1'    => ['type' => 'string'],
                    'address2'    => ['type' => 'string'],
                    'city'        => ['type' => 'string'],
                    'state'       => ['type' => 'string'],
                    'zipcode'     => ['type' => 'string'],
                    'country'     => ['type' => 'string'],
                    'website'     => ['type' => 'string', 'format' => 'uri'],
                    'title'       => ['type' => 'string'],
                    'position'    => ['type' => 'string'],
                    'twitter'     => ['type' => 'string'],
                    'facebook'    => ['type' => 'string'],
                    'linkedin'    => ['type' => 'string'],
                    'tags'        => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Tag names to assign'],
                    'owner'       => ['type' => 'integer', 'description' => 'User ID of the owner'],
                    'ipAddress'   => ['type' => 'string', 'description' => 'IP address to associate'],
                    'isPublished' => ['type' => 'boolean'],
                ],
                'additionalProperties' => ['type' => 'string'],
            ],
            'ContactInputList' => [
                'type'  => 'array',
                'items' => ['$ref' => '#/components/schemas/ContactInput'],
            ],
            'ContactBatchResponse' => [
                'type'       => 'object',
                'properties' => [
                    'statusCodes' => ['type' => 'object', 'additionalProperties' => ['type' => 'integer']],
                    'contacts'    => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/Contact']],
                ],
            ],
            'ContactResponse' => [
                'type'       => 'object',
                'properties' => ['contact' => ['$ref' => '#/components/schemas/Contact']],
            ],
            'ContactIdList' => [
                'type'       => 'object',
                'properties' => ['ids' => ['type' => 'array', 'items' => ['type' => 'integer']]],
                'required'   => ['ids'],
            ],
            'TagListInput' => [
                'type'       => 'object',
                'properties' => ['tags' => ['type' => 'array', 'items' => ['type' => 'string']]],
                'required'   => ['tags'],
            ],
            'ContactEventsResponse' => [
                'type'       => 'object',
                'properties' => [
                    'total'  => ['type' => 'string'],
                    'events' => ['type' => 'array', 'items' => ['type' => 'object']],
                    'types'  => ['type' => 'object'],
                    'order'  => ['type' => 'array', 'items' => ['type' => 'string']],
                ],
            ],
            'ContactCompaniesResponse' => [
                'type'       => 'object',
                'properties' => ['total' => ['type' => 'string'], 'companies' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/Company']]],
            ],
            'ContactSegmentsResponse' => [
                'type'       => 'object',
                'properties' => ['total' => ['type' => 'string'], 'lists' => ['type' => 'object', 'additionalProperties' => ['$ref' => '#/components/schemas/Segment']]],
            ],
            'ContactCampaignsResponse' => [
                'type'       => 'object',
                'properties' => ['total' => ['type' => 'string'], 'campaigns' => ['type' => 'object', 'additionalProperties' => ['$ref' => '#/components/schemas/Campaign']]],
            ],
        ];
    }

    private function companySchemas(): array
    {
        return [
            'Company' => [
                'allOf' => [
                    ['$ref' => '#/components/schemas/BaseResource'],
                    [
                        'type'       => 'object',
                        'properties' => [
                            'name'    => ['type' => 'string'],
                            'fields'  => ['type' => 'object', 'additionalProperties' => true],
                            'score'   => ['type' => 'integer'],
                            'leads'   => ['type' => 'object', 'additionalProperties' => true],
                        ],
                    ],
                ],
            ],
            'CompanyInput' => [
                'type'       => 'object',
                'required'   => ['companyname'],
                'properties' => [
                    'companyname'    => ['type' => 'string'],
                    'companyemail'   => ['type' => 'string', 'format' => 'email'],
                    'companyaddress1'=> ['type' => 'string'],
                    'companyaddress2'=> ['type' => 'string'],
                    'companycity'    => ['type' => 'string'],
                    'companystate'   => ['type' => 'string'],
                    'companyzipcode' => ['type' => 'string'],
                    'companycountry' => ['type' => 'string'],
                    'companywebsite' => ['type' => 'string', 'format' => 'uri'],
                    'companyphone'   => ['type' => 'string'],
                    'companyindustry'=> ['type' => 'string'],
                    'isPublished'    => ['type' => 'boolean'],
                ],
                'additionalProperties' => ['type' => 'string'],
            ],
        ];
    }

    private function segmentSchemas(): array
    {
        return [
            'Segment' => [
                'allOf' => [
                    ['$ref' => '#/components/schemas/BaseResource'],
                    [
                        'type'       => 'object',
                        'properties' => [
                            'name'               => ['type' => 'string'],
                            'alias'              => ['type' => 'string'],
                            'description'        => ['type' => ['string', 'null']],
                            'filters'            => ['type' => 'array', 'items' => ['type' => 'object']],
                            'isGlobal'           => ['type' => 'boolean'],
                            'isPreferenceCenter' => ['type' => 'boolean'],
                        ],
                    ],
                ],
            ],
            'SegmentInput' => [
                'type'       => 'object',
                'required'   => ['name'],
                'properties' => [
                    'name'               => ['type' => 'string'],
                    'alias'              => ['type' => 'string'],
                    'description'        => ['type' => 'string'],
                    'filters'            => ['type' => 'array', 'items' => ['type' => 'object']],
                    'isGlobal'           => ['type' => 'boolean'],
                    'isPreferenceCenter' => ['type' => 'boolean'],
                    'isPublished'        => ['type' => 'boolean'],
                ],
            ],
        ];
    }

    private function campaignSchemas(): array
    {
        return [
            'Campaign' => [
                'allOf' => [
                    ['$ref' => '#/components/schemas/BaseResource'],
                    [
                        'type'       => 'object',
                        'properties' => [
                            'name'           => ['type' => 'string'],
                            'description'    => ['type' => ['string', 'null']],
                            'isSystem'       => ['type' => 'boolean'],
                            'canvasSettings' => ['type' => 'object'],
                            'events'         => ['type' => 'array', 'items' => ['type' => 'object']],
                            'forms'          => ['type' => 'array', 'items' => ['type' => 'object']],
                        ],
                    ],
                ],
            ],
            'CampaignInput' => [
                'type'       => 'object',
                'required'   => ['name'],
                'properties' => [
                    'name'        => ['type' => 'string'],
                    'description' => ['type' => 'string'],
                    'isPublished' => ['type' => 'boolean'],
                ],
            ],
        ];
    }

    private function emailSchemas(): array
    {
        return [
            'Email' => [
                'allOf' => [
                    ['$ref' => '#/components/schemas/BaseResource'],
                    [
                        'type'       => 'object',
                        'properties' => [
                            'name'           => ['type' => 'string'],
                            'subject'        => ['type' => 'string'],
                            'language'       => ['type' => 'string', 'example' => 'en'],
                            'fromAddress'    => ['type' => ['string', 'null'], 'format' => 'email'],
                            'fromName'       => ['type' => ['string', 'null']],
                            'replyToAddress' => ['type' => ['string', 'null'], 'format' => 'email'],
                            'bccAddress'     => ['type' => ['string', 'null'], 'format' => 'email'],
                            'emailType'      => ['type' => 'string', 'enum' => ['template', 'list']],
                            'customHtml'     => ['type' => ['string', 'null']],
                            'plainText'      => ['type' => ['string', 'null']],
                            'template'       => ['type' => ['string', 'null']],
                            'sentCount'      => ['type' => 'integer', 'readOnly' => true],
                            'readCount'      => ['type' => 'integer', 'readOnly' => true],
                            'revision'       => ['type' => 'integer', 'readOnly' => true],
                            'publishUp'      => ['type' => ['string', 'null'], 'format' => 'date-time'],
                            'publishDown'    => ['type' => ['string', 'null'], 'format' => 'date-time'],
                            'lists'          => ['type' => 'array', 'items' => ['type' => 'object']],
                        ],
                    ],
                ],
            ],
            'EmailInput' => [
                'type'       => 'object',
                'required'   => ['name', 'subject', 'emailType'],
                'properties' => [
                    'name'           => ['type' => 'string'],
                    'subject'        => ['type' => 'string'],
                    'emailType'      => ['type' => 'string', 'enum' => ['template', 'list']],
                    'fromAddress'    => ['type' => 'string', 'format' => 'email'],
                    'fromName'       => ['type' => 'string'],
                    'replyToAddress' => ['type' => 'string', 'format' => 'email'],
                    'customHtml'     => ['type' => 'string'],
                    'plainText'      => ['type' => 'string'],
                    'template'       => ['type' => 'string'],
                    'lists'          => ['type' => 'array', 'items' => ['type' => 'integer'], 'description' => 'Segment IDs for list emails'],
                    'publishUp'      => ['type' => 'string', 'format' => 'date-time'],
                    'publishDown'    => ['type' => 'string', 'format' => 'date-time'],
                    'isPublished'    => ['type' => 'boolean'],
                ],
            ],
        ];
    }

    private function formSchemas(): array
    {
        return [
            'Form' => [
                'allOf' => [
                    ['$ref' => '#/components/schemas/BaseResource'],
                    [
                        'type'       => 'object',
                        'properties' => [
                            'name'               => ['type' => 'string'],
                            'alias'              => ['type' => 'string'],
                            'formType'           => ['type' => 'string', 'enum' => ['standalone', 'campaign', 'standalone-wizard']],
                            'description'        => ['type' => ['string', 'null']],
                            'postAction'         => ['type' => 'string'],
                            'postActionProperty' => ['type' => ['string', 'null']],
                            'isSystem'           => ['type' => 'boolean'],
                            'fields'             => ['type' => 'array', 'items' => ['type' => 'object']],
                            'actions'            => ['type' => 'array', 'items' => ['type' => 'object']],
                        ],
                    ],
                ],
            ],
            'FormInput' => [
                'type'       => 'object',
                'required'   => ['name'],
                'properties' => [
                    'name'               => ['type' => 'string'],
                    'alias'              => ['type' => 'string'],
                    'formType'           => ['type' => 'string', 'enum' => ['standalone', 'campaign']],
                    'description'        => ['type' => 'string'],
                    'postAction'         => ['type' => 'string', 'enum' => ['return', 'redirect', 'message']],
                    'postActionProperty' => ['type' => 'string'],
                    'isPublished'        => ['type' => 'boolean'],
                    'fields'             => ['type' => 'array', 'items' => ['type' => 'object']],
                    'actions'            => ['type' => 'array', 'items' => ['type' => 'object']],
                ],
            ],
            'FormSubmissionsResponse' => [
                'type'       => 'object',
                'properties' => [
                    'total'       => ['type' => 'string'],
                    'submissions' => ['type' => 'object', 'additionalProperties' => ['type' => 'object']],
                ],
            ],
        ];
    }

    private function pageSchemas(): array
    {
        return [
            'Page' => [
                'allOf' => [
                    ['$ref' => '#/components/schemas/BaseResource'],
                    [
                        'type'       => 'object',
                        'properties' => [
                            'title'              => ['type' => 'string'],
                            'alias'              => ['type' => 'string'],
                            'template'           => ['type' => ['string', 'null']],
                            'customHtml'         => ['type' => ['string', 'null']],
                            'language'           => ['type' => 'string'],
                            'isPreferenceCenter' => ['type' => 'boolean'],
                            'variantParent'      => ['type' => ['integer', 'null']],
                            'translationParent'  => ['type' => ['integer', 'null']],
                            'hits'               => ['type' => 'integer', 'readOnly' => true],
                            'uniqueHits'         => ['type' => 'integer', 'readOnly' => true],
                            'publishUp'          => ['type' => ['string', 'null'], 'format' => 'date-time'],
                            'publishDown'        => ['type' => ['string', 'null'], 'format' => 'date-time'],
                        ],
                    ],
                ],
            ],
            'PageInput' => [
                'type'       => 'object',
                'required'   => ['title'],
                'properties' => [
                    'title'              => ['type' => 'string'],
                    'alias'              => ['type' => 'string'],
                    'template'           => ['type' => 'string'],
                    'customHtml'         => ['type' => 'string'],
                    'language'           => ['type' => 'string', 'default' => 'en'],
                    'isPreferenceCenter' => ['type' => 'boolean'],
                    'publishUp'          => ['type' => 'string', 'format' => 'date-time'],
                    'publishDown'        => ['type' => 'string', 'format' => 'date-time'],
                    'isPublished'        => ['type' => 'boolean'],
                ],
            ],
        ];
    }

    private function assetSchemas(): array
    {
        return [
            'Asset' => [
                'allOf' => [
                    ['$ref' => '#/components/schemas/BaseResource'],
                    [
                        'type'       => 'object',
                        'properties' => [
                            'title'            => ['type' => 'string'],
                            'alias'            => ['type' => 'string'],
                            'description'      => ['type' => ['string', 'null']],
                            'storageLocation'  => ['type' => 'string', 'enum' => ['local', 'remote']],
                            'path'             => ['type' => ['string', 'null']],
                            'remotePath'       => ['type' => ['string', 'null']],
                            'originalFileName' => ['type' => ['string', 'null']],
                            'size'             => ['type' => ['integer', 'null']],
                            'mime'             => ['type' => ['string', 'null']],
                            'extension'        => ['type' => ['string', 'null']],
                            'downloadUrl'      => ['type' => 'string', 'format' => 'uri', 'readOnly' => true],
                            'downloadCount'    => ['type' => 'integer', 'readOnly' => true],
                        ],
                    ],
                ],
            ],
            'AssetInput' => [
                'type'       => 'object',
                'required'   => ['title', 'storageLocation'],
                'properties' => [
                    'title'           => ['type' => 'string'],
                    'alias'           => ['type' => 'string'],
                    'description'     => ['type' => 'string'],
                    'storageLocation' => ['type' => 'string', 'enum' => ['local', 'remote']],
                    'remotePath'      => ['type' => 'string', 'description' => 'URL for remote assets'],
                    'isPublished'     => ['type' => 'boolean'],
                ],
            ],
        ];
    }

    private function tagSchemas(): array
    {
        return [
            'Tag' => [
                'type'       => 'object',
                'properties' => [
                    'id'  => ['type' => 'integer', 'readOnly' => true],
                    'tag' => ['type' => 'string'],
                ],
            ],
            'TagInput' => [
                'type'       => 'object',
                'required'   => ['tag'],
                'properties' => ['tag' => ['type' => 'string']],
            ],
        ];
    }

    private function categorySchemas(): array
    {
        return [
            'Category' => [
                'allOf' => [
                    ['$ref' => '#/components/schemas/BaseResource'],
                    [
                        'type'       => 'object',
                        'properties' => [
                            'title'       => ['type' => 'string'],
                            'alias'       => ['type' => 'string'],
                            'description' => ['type' => ['string', 'null']],
                            'color'       => ['type' => ['string', 'null']],
                            'bundle'      => ['type' => 'string', 'description' => 'e.g. email, form, asset, page'],
                        ],
                    ],
                ],
            ],
            'CategoryInput' => [
                'type'       => 'object',
                'required'   => ['title', 'bundle'],
                'properties' => [
                    'title'       => ['type' => 'string'],
                    'alias'       => ['type' => 'string'],
                    'description' => ['type' => 'string'],
                    'color'       => ['type' => 'string', 'example' => '#4e5e9e'],
                    'bundle'      => ['type' => 'string', 'enum' => ['email', 'form', 'asset', 'page', 'campaign', 'global']],
                    'isPublished' => ['type' => 'boolean'],
                ],
            ],
        ];
    }

    private function fieldSchemas(): array
    {
        return [
            'Field' => [
                'type'       => 'object',
                'properties' => [
                    'id'                  => ['type' => 'integer', 'readOnly' => true],
                    'isPublished'         => ['type' => 'boolean'],
                    'alias'               => ['type' => 'string'],
                    'label'               => ['type' => 'string'],
                    'description'         => ['type' => ['string', 'null']],
                    'type'                => ['type' => 'string', 'enum' => ['text', 'number', 'boolean', 'date', 'datetime', 'time', 'select', 'multiselect', 'lookup', 'email', 'url', 'tel', 'region', 'country', 'timezone', 'locale']],
                    'group'               => ['type' => 'string', 'enum' => ['core', 'social', 'personal', 'professional']],
                    'order'               => ['type' => 'integer'],
                    'defaultValue'        => ['description' => 'Default value (type varies)'],
                    'isRequired'          => ['type' => 'boolean'],
                    'isUnique'            => ['type' => 'boolean'],
                    'isShortVisible'      => ['type' => 'boolean'],
                    'isListable'          => ['type' => 'boolean'],
                    'isPubliclyUpdatable' => ['type' => 'boolean'],
                    'properties'          => ['type' => 'object', 'description' => 'Type-specific properties (e.g. list choices for select)'],
                ],
            ],
            'FieldInput' => [
                'type'       => 'object',
                'required'   => ['label', 'type', 'group'],
                'properties' => [
                    'label'               => ['type' => 'string'],
                    'alias'               => ['type' => 'string'],
                    'description'         => ['type' => 'string'],
                    'type'                => ['type' => 'string'],
                    'group'               => ['type' => 'string'],
                    'defaultValue'        => ['type' => 'string'],
                    'isRequired'          => ['type' => 'boolean'],
                    'isUnique'            => ['type' => 'boolean'],
                    'isShortVisible'      => ['type' => 'boolean'],
                    'isListable'          => ['type' => 'boolean'],
                    'isPubliclyUpdatable' => ['type' => 'boolean'],
                    'properties'          => ['type' => 'object'],
                    'isPublished'         => ['type' => 'boolean'],
                ],
            ],
            'FieldListResponse' => [
                'type'       => 'object',
                'properties' => [
                    'total'  => ['type' => 'string'],
                    'fields' => ['type' => 'object', 'additionalProperties' => ['$ref' => '#/components/schemas/Field']],
                ],
            ],
            'FieldResponse' => [
                'type'       => 'object',
                'properties' => ['field' => ['$ref' => '#/components/schemas/Field']],
            ],
        ];
    }

    private function noteSchemas(): array
    {
        return [
            'Note' => [
                'type'       => 'object',
                'properties' => [
                    'id'       => ['type' => 'integer', 'readOnly' => true],
                    'lead'     => ['type' => 'object', 'properties' => ['id' => ['type' => 'integer']]],
                    'user'     => ['type' => ['object', 'null']],
                    'dateTime' => ['type' => 'string', 'format' => 'date-time'],
                    'text'     => ['type' => 'string'],
                    'type'     => ['type' => 'string', 'enum' => ['general', 'email', 'call', 'meeting', 'event']],
                ],
            ],
            'NoteInput' => [
                'type'       => 'object',
                'required'   => ['lead', 'text', 'type'],
                'properties' => [
                    'lead'     => ['type' => 'integer', 'description' => 'Contact ID'],
                    'text'     => ['type' => 'string'],
                    'type'     => ['type' => 'string', 'enum' => ['general', 'email', 'call', 'meeting', 'event']],
                    'dateTime' => ['type' => 'string', 'format' => 'date-time'],
                ],
            ],
            'NoteListResponse' => [
                'type'       => 'object',
                'properties' => [
                    'total' => ['type' => 'string'],
                    'notes' => ['type' => 'object', 'additionalProperties' => ['$ref' => '#/components/schemas/Note']],
                ],
            ],
        ];
    }

    private function userSchemas(): array
    {
        return [
            'User' => [
                'type'       => 'object',
                'properties' => [
                    'id'         => ['type' => 'integer', 'readOnly' => true],
                    'isPublished'=> ['type' => 'boolean'],
                    'isAdmin'    => ['type' => 'boolean'],
                    'username'   => ['type' => 'string'],
                    'firstName'  => ['type' => 'string'],
                    'lastName'   => ['type' => 'string'],
                    'email'      => ['type' => 'string', 'format' => 'email'],
                    'lastLogin'  => ['type' => ['string', 'null'], 'format' => 'date-time'],
                    'lastActive' => ['type' => ['string', 'null'], 'format' => 'date-time'],
                    'role'       => ['oneOf' => [['$ref' => '#/components/schemas/Role'], ['type' => 'null']]],
                ],
            ],
            'UserInput' => [
                'type'       => 'object',
                'required'   => ['username', 'email', 'firstName', 'lastName'],
                'properties' => [
                    'username'    => ['type' => 'string'],
                    'email'       => ['type' => 'string', 'format' => 'email'],
                    'firstName'   => ['type' => 'string'],
                    'lastName'    => ['type' => 'string'],
                    'plainPassword'=> ['type' => 'string', 'format' => 'password'],
                    'isAdmin'     => ['type' => 'boolean'],
                    'role'        => ['type' => 'integer', 'description' => 'Role ID'],
                    'isPublished' => ['type' => 'boolean'],
                ],
            ],
            'UserResponse' => [
                'type'       => 'object',
                'properties' => ['user' => ['$ref' => '#/components/schemas/User']],
            ],
        ];
    }

    private function roleSchemas(): array
    {
        return [
            'Role' => [
                'type'       => 'object',
                'properties' => [
                    'id'          => ['type' => 'integer', 'readOnly' => true],
                    'isPublished' => ['type' => 'boolean'],
                    'name'        => ['type' => 'string'],
                    'description' => ['type' => ['string', 'null']],
                    'isAdmin'     => ['type' => 'boolean'],
                    'permissions' => ['type' => 'object'],
                ],
            ],
            'RoleInput' => [
                'type'       => 'object',
                'required'   => ['name'],
                'properties' => [
                    'name'        => ['type' => 'string'],
                    'description' => ['type' => 'string'],
                    'isAdmin'     => ['type' => 'boolean'],
                    'rawPermissions' => ['type' => 'object', 'description' => 'Permission map'],
                    'isPublished' => ['type' => 'boolean'],
                ],
            ],
        ];
    }

    private function reportSchemas(): array
    {
        return [
            'Report' => [
                'type'       => 'object',
                'properties' => [
                    'id'          => ['type' => 'integer', 'readOnly' => true],
                    'name'        => ['type' => 'string'],
                    'description' => ['type' => ['string', 'null']],
                    'isSystem'    => ['type' => 'boolean'],
                    'isScheduled' => ['type' => 'boolean'],
                    'source'      => ['type' => 'string'],
                    'columns'     => ['type' => 'array', 'items' => ['type' => 'string']],
                    'filters'     => ['type' => 'array', 'items' => ['type' => 'object']],
                    'tableOrder'  => ['type' => 'array', 'items' => ['type' => 'object']],
                    'graphs'      => ['type' => 'array', 'items' => ['type' => 'string']],
                    'groupBy'     => ['type' => 'array', 'items' => ['type' => 'string']],
                    'settings'    => ['type' => 'object'],
                ],
            ],
        ];
    }

    private function notificationSchemas(): array
    {
        return [
            'Notification' => [
                'allOf' => [
                    ['$ref' => '#/components/schemas/BaseResource'],
                    [
                        'type'       => 'object',
                        'properties' => [
                            'name'         => ['type' => 'string'],
                            'heading'      => ['type' => ['string', 'null']],
                            'message'      => ['type' => 'string'],
                            'url'          => ['type' => ['string', 'null'], 'format' => 'uri'],
                            'language'     => ['type' => 'string'],
                            'sentCount'    => ['type' => 'integer', 'readOnly' => true],
                            'readCount'    => ['type' => 'integer', 'readOnly' => true],
                            'publishUp'    => ['type' => ['string', 'null'], 'format' => 'date-time'],
                            'publishDown'  => ['type' => ['string', 'null'], 'format' => 'date-time'],
                        ],
                    ],
                ],
            ],
            'NotificationInput' => [
                'type'       => 'object',
                'required'   => ['name', 'message'],
                'properties' => [
                    'name'        => ['type' => 'string'],
                    'heading'     => ['type' => 'string'],
                    'message'     => ['type' => 'string'],
                    'url'         => ['type' => 'string', 'format' => 'uri'],
                    'language'    => ['type' => 'string', 'default' => 'en'],
                    'publishUp'   => ['type' => 'string', 'format' => 'date-time'],
                    'publishDown' => ['type' => 'string', 'format' => 'date-time'],
                    'isPublished' => ['type' => 'boolean'],
                ],
            ],
        ];
    }

    private function messageSchemas(): array
    {
        return [
            'Message' => [
                'allOf' => [
                    ['$ref' => '#/components/schemas/BaseResource'],
                    [
                        'type'       => 'object',
                        'properties' => [
                            'name'        => ['type' => 'string'],
                            'description' => ['type' => ['string', 'null']],
                            'channels'    => ['type' => 'object', 'description' => 'Channel configuration keyed by channel type'],
                        ],
                    ],
                ],
            ],
            'MessageInput' => [
                'type'       => 'object',
                'required'   => ['name'],
                'properties' => [
                    'name'        => ['type' => 'string'],
                    'description' => ['type' => 'string'],
                    'channels'    => ['type' => 'object'],
                    'isPublished' => ['type' => 'boolean'],
                ],
            ],
        ];
    }

    private function pointSchemas(): array
    {
        return [
            'Point' => [
                'allOf' => [
                    ['$ref' => '#/components/schemas/BaseResource'],
                    [
                        'type'       => 'object',
                        'properties' => [
                            'name'        => ['type' => 'string'],
                            'description' => ['type' => ['string', 'null']],
                            'type'        => ['type' => 'string'],
                            'delta'       => ['type' => 'integer'],
                            'repeatable'  => ['type' => 'boolean'],
                            'category'    => ['type' => ['object', 'null']],
                        ],
                    ],
                ],
            ],
            'PointInput' => [
                'type'       => 'object',
                'required'   => ['name', 'delta', 'type'],
                'properties' => [
                    'name'        => ['type' => 'string'],
                    'description' => ['type' => 'string'],
                    'type'        => ['type' => 'string'],
                    'delta'       => ['type' => 'integer'],
                    'repeatable'  => ['type' => 'boolean'],
                    'isPublished' => ['type' => 'boolean'],
                ],
            ],
            'PointTrigger' => [
                'allOf' => [
                    ['$ref' => '#/components/schemas/BaseResource'],
                    [
                        'type'       => 'object',
                        'properties' => [
                            'name'        => ['type' => 'string'],
                            'description' => ['type' => ['string', 'null']],
                            'points'      => ['type' => 'integer'],
                            'color'       => ['type' => 'string'],
                            'events'      => ['type' => 'array', 'items' => ['type' => 'object']],
                        ],
                    ],
                ],
            ],
            'PointTriggerInput' => [
                'type'       => 'object',
                'required'   => ['name', 'points'],
                'properties' => [
                    'name'        => ['type' => 'string'],
                    'description' => ['type' => 'string'],
                    'points'      => ['type' => 'integer'],
                    'color'       => ['type' => 'string'],
                    'isPublished' => ['type' => 'boolean'],
                ],
            ],
        ];
    }

    private function dynamicContentSchemas(): array
    {
        return [
            'DynamicContent' => [
                'allOf' => [
                    ['$ref' => '#/components/schemas/BaseResource'],
                    [
                        'type'       => 'object',
                        'properties' => [
                            'name'              => ['type' => 'string'],
                            'slot'              => ['type' => 'string'],
                            'content'           => ['type' => 'string'],
                            'isCampaignBased'   => ['type' => 'boolean'],
                            'filters'           => ['type' => 'array', 'items' => ['type' => 'object']],
                            'variantParent'     => ['type' => ['object', 'null']],
                            'translationParent' => ['type' => ['object', 'null']],
                        ],
                    ],
                ],
            ],
            'DynamicContentInput' => [
                'type'       => 'object',
                'required'   => ['name'],
                'properties' => [
                    'name'            => ['type' => 'string'],
                    'slot'            => ['type' => 'string'],
                    'content'         => ['type' => 'string'],
                    'isCampaignBased' => ['type' => 'boolean'],
                    'filters'         => ['type' => 'array', 'items' => ['type' => 'object']],
                    'isPublished'     => ['type' => 'boolean'],
                ],
            ],
        ];
    }

    private function helperSchemas(): array
    {
        return [];
    }
}
