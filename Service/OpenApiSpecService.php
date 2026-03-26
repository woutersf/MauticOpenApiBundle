<?php

declare(strict_types=1);

namespace MauticPlugin\MauticOpenApiBundle\Service;

use Mautic\CoreBundle\Helper\CoreParametersHelper;

/**
 * Builds a comprehensive OpenAPI 3.1.0 specification for the Mautic REST API.
 *
 * The spec is assembled from static definitions; the server URL is injected
 * dynamically from the Mautic site_url configuration value.
 */
class OpenApiSpecService
{
    public function __construct(
        private readonly CoreParametersHelper $coreParams,
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
            'version'     => '4.0',
            'description' => <<<'MD'
The **Mautic REST API** lets you read and write Mautic data programmatically.

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
    // Tags (grouping in Swagger UI)
    // -------------------------------------------------------------------------

    private function buildTags(): array
    {
        return [
            ['name' => 'Contacts',         'description' => 'Manage contacts (leads)'],
            ['name' => 'Companies',         'description' => 'Manage companies and company-contact relationships'],
            ['name' => 'Segments',          'description' => 'Manage contact segments (lists)'],
            ['name' => 'Campaigns',         'description' => 'Manage campaigns and campaign membership'],
            ['name' => 'Emails',            'description' => 'Manage email assets and send emails'],
            ['name' => 'Forms',             'description' => 'Manage forms and form submissions'],
            ['name' => 'Landing Pages',     'description' => 'Manage landing pages'],
            ['name' => 'Assets',            'description' => 'Manage file assets (documents, images, etc.)'],
            ['name' => 'Tags',              'description' => 'Manage contact tags'],
            ['name' => 'Categories',        'description' => 'Manage categories'],
            ['name' => 'Custom Fields',     'description' => 'Read contact and company custom fields'],
            ['name' => 'Notes',             'description' => 'Manage contact notes'],
            ['name' => 'Users',             'description' => 'Manage Mautic users'],
            ['name' => 'Roles',             'description' => 'Manage user roles and permissions'],
            ['name' => 'Reports',           'description' => 'Access reports'],
            ['name' => 'Stats',             'description' => 'Access raw table statistics'],
            ['name' => 'Notifications',     'description' => 'Manage web push notifications'],
            ['name' => 'Messages',          'description' => 'Manage marketing messages (multi-channel)'],
            ['name' => 'Points',            'description' => 'Manage point actions and triggers'],
            ['name' => 'Dynamic Content',   'description' => 'Manage dynamic web content blocks'],
        ];
    }

    // -------------------------------------------------------------------------
    // Paths – delegates to per-resource helpers
    // -------------------------------------------------------------------------

    private function buildPaths(): array
    {
        return array_merge(
            $this->contactPaths(),
            $this->companyPaths(),
            $this->segmentPaths(),
            $this->campaignPaths(),
            $this->emailPaths(),
            $this->formPaths(),
            $this->pagePaths(),
            $this->assetPaths(),
            $this->tagPaths(),
            $this->categoryPaths(),
            $this->fieldPaths(),
            $this->notePaths(),
            $this->userPaths(),
            $this->rolePaths(),
            $this->reportPaths(),
            $this->statsPaths(),
            $this->notificationPaths(),
            $this->messagePaths(),
            $this->pointPaths(),
            $this->dynamicContentPaths(),
        );
    }

    // =========================================================================
    // Resource path definitions
    // =========================================================================

    // -------------------------------------------------------------------------
    // Contacts
    // -------------------------------------------------------------------------

    private function contactPaths(): array
    {
        return [
            '/contacts' => [
                'get'  => $this->listOp('Contacts', 'List contacts', 'contacts', '#/components/schemas/Contact'),
                'post' => $this->createOp('Contacts', 'Create a contact', 'contact', '#/components/schemas/Contact', '#/components/schemas/ContactInput'),
            ],
            '/contacts/batch/new' => [
                'post' => [
                    'tags'        => ['Contacts'],
                    'summary'     => 'Batch create contacts',
                    'operationId' => 'batchCreateContacts',
                    'requestBody' => $this->jsonBody('#/components/schemas/ContactInputList'),
                    'responses'   => [
                        '201' => $this->jsonResp('Created', '#/components/schemas/ContactBatchResponse'),
                        '400' => $this->ref400(),
                        '401' => $this->ref401(),
                    ],
                ],
            ],
            '/contacts/batch/edit' => [
                'patch' => [
                    'tags'        => ['Contacts'],
                    'summary'     => 'Batch update contacts',
                    'operationId' => 'batchUpdateContacts',
                    'requestBody' => $this->jsonBody('#/components/schemas/ContactInputList'),
                    'responses'   => [
                        '200' => $this->jsonResp('Updated', '#/components/schemas/ContactBatchResponse'),
                        '400' => $this->ref400(),
                        '401' => $this->ref401(),
                    ],
                ],
            ],
            '/contacts/batch/delete' => [
                'delete' => [
                    'tags'        => ['Contacts'],
                    'summary'     => 'Batch delete contacts',
                    'operationId' => 'batchDeleteContacts',
                    'requestBody' => [
                        'required' => true,
                        'content'  => ['application/json' => ['schema' => ['type' => 'object', 'properties' => ['ids' => ['type' => 'array', 'items' => ['type' => 'integer']]]]]],
                    ],
                    'responses' => [
                        '200' => $this->jsonResp('Deleted'),
                        '401' => $this->ref401(),
                    ],
                ],
            ],
            '/contacts/{id}' => [
                'get'    => $this->getOneOp('Contacts', 'Get a contact', 'contact', '#/components/schemas/Contact'),
                'patch'  => $this->updateOp('Contacts', 'Update a contact', 'contact', '#/components/schemas/Contact', '#/components/schemas/ContactInput'),
                'delete' => $this->deleteOp('Contacts', 'Delete a contact'),
            ],
            '/contacts/{id}/notes' => [
                'get' => [
                    'tags'        => ['Contacts'],
                    'summary'     => 'List notes for a contact',
                    'operationId' => 'getContactNotes',
                    'parameters'  => array_merge([$this->idParam()], $this->paginationParams()),
                    'responses'   => [
                        '200' => $this->jsonResp('Success', '#/components/schemas/NoteListResponse'),
                        '401' => $this->ref401(),
                        '404' => $this->ref404(),
                    ],
                ],
            ],
            '/contacts/{id}/events' => [
                'get' => [
                    'tags'        => ['Contacts'],
                    'summary'     => 'List events for a contact',
                    'operationId' => 'getContactEvents',
                    'parameters'  => array_merge(
                        [$this->idParam()],
                        $this->paginationParams(),
                        [['name' => 'filters[search]', 'in' => 'query', 'schema' => ['type' => 'string']]]
                    ),
                    'responses' => [
                        '200' => $this->jsonResp('Success', '#/components/schemas/ContactEventsResponse'),
                        '401' => $this->ref401(),
                        '404' => $this->ref404(),
                    ],
                ],
            ],
            '/contacts/{id}/companies' => [
                'get' => [
                    'tags'        => ['Contacts'],
                    'summary'     => 'List companies for a contact',
                    'operationId' => 'getContactCompanies',
                    'parameters'  => [$this->idParam()],
                    'responses'   => [
                        '200' => $this->jsonResp('Success', '#/components/schemas/ContactCompaniesResponse'),
                        '401' => $this->ref401(),
                        '404' => $this->ref404(),
                    ],
                ],
            ],
            '/contacts/{id}/segments' => [
                'get' => [
                    'tags'        => ['Contacts'],
                    'summary'     => 'List segments a contact belongs to',
                    'operationId' => 'getContactSegments',
                    'parameters'  => [$this->idParam()],
                    'responses'   => [
                        '200' => $this->jsonResp('Success', '#/components/schemas/ContactSegmentsResponse'),
                        '401' => $this->ref401(),
                        '404' => $this->ref404(),
                    ],
                ],
            ],
            '/contacts/{id}/campaigns' => [
                'get' => [
                    'tags'        => ['Contacts'],
                    'summary'     => 'List campaigns a contact belongs to',
                    'operationId' => 'getContactCampaigns',
                    'parameters'  => [$this->idParam()],
                    'responses'   => [
                        '200' => $this->jsonResp('Success', '#/components/schemas/ContactCampaignsResponse'),
                        '401' => $this->ref401(),
                        '404' => $this->ref404(),
                    ],
                ],
            ],
            '/contacts/{id}/tags' => [
                'post' => [
                    'tags'        => ['Contacts'],
                    'summary'     => 'Add tags to a contact',
                    'operationId' => 'addTagsToContact',
                    'parameters'  => [$this->idParam()],
                    'requestBody' => $this->jsonBody('#/components/schemas/TagListInput'),
                    'responses'   => [
                        '200' => $this->jsonResp('Tags updated', '#/components/schemas/ContactResponse'),
                        '401' => $this->ref401(),
                        '404' => $this->ref404(),
                    ],
                ],
                'delete' => [
                    'tags'        => ['Contacts'],
                    'summary'     => 'Remove tags from a contact',
                    'operationId' => 'removeTagsFromContact',
                    'parameters'  => [$this->idParam()],
                    'requestBody' => $this->jsonBody('#/components/schemas/TagListInput'),
                    'responses'   => [
                        '200' => $this->jsonResp('Tags updated', '#/components/schemas/ContactResponse'),
                        '401' => $this->ref401(),
                        '404' => $this->ref404(),
                    ],
                ],
            ],
            '/contacts/{id}/dnc/{channel}/add' => [
                'post' => [
                    'tags'        => ['Contacts'],
                    'summary'     => 'Add contact to Do Not Contact for a channel',
                    'operationId' => 'addContactToDnc',
                    'parameters'  => [
                        $this->idParam(),
                        ['name' => 'channel', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string', 'enum' => ['email', 'sms', 'push']]],
                    ],
                    'requestBody' => [
                        'content' => ['application/json' => ['schema' => ['type' => 'object', 'properties' => [
                            'reason'   => ['type' => 'integer', 'description' => '1=Manual, 2=Bounce, 3=Unsubscribe'],
                            'comments' => ['type' => 'string'],
                        ]]]],
                    ],
                    'responses' => [
                        '200' => $this->jsonResp('Contact updated', '#/components/schemas/ContactResponse'),
                        '401' => $this->ref401(),
                        '404' => $this->ref404(),
                    ],
                ],
            ],
            '/contacts/{id}/dnc/{channel}/remove' => [
                'post' => [
                    'tags'        => ['Contacts'],
                    'summary'     => 'Remove contact from Do Not Contact for a channel',
                    'operationId' => 'removeContactFromDnc',
                    'parameters'  => [
                        $this->idParam(),
                        ['name' => 'channel', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string', 'enum' => ['email', 'sms', 'push']]],
                    ],
                    'responses' => [
                        '200' => $this->jsonResp('Contact updated', '#/components/schemas/ContactResponse'),
                        '401' => $this->ref401(),
                        '404' => $this->ref404(),
                    ],
                ],
            ],
            '/contacts/{id}/utmtags' => [
                'get' => [
                    'tags'        => ['Contacts'],
                    'summary'     => 'List UTM tags for a contact',
                    'operationId' => 'getContactUtmTags',
                    'parameters'  => [$this->idParam()],
                    'responses'   => [
                        '200' => $this->jsonResp('Success'),
                        '401' => $this->ref401(),
                        '404' => $this->ref404(),
                    ],
                ],
            ],
        ];
    }

    // -------------------------------------------------------------------------
    // Companies
    // -------------------------------------------------------------------------

    private function companyPaths(): array
    {
        return [
            '/companies' => [
                'get'  => $this->listOp('Companies', 'List companies', 'companies', '#/components/schemas/Company'),
                'post' => $this->createOp('Companies', 'Create a company', 'company', '#/components/schemas/Company', '#/components/schemas/CompanyInput'),
            ],
            '/companies/{id}' => [
                'get'    => $this->getOneOp('Companies', 'Get a company', 'company', '#/components/schemas/Company'),
                'patch'  => $this->updateOp('Companies', 'Update a company', 'company', '#/components/schemas/Company', '#/components/schemas/CompanyInput'),
                'delete' => $this->deleteOp('Companies', 'Delete a company'),
            ],
            '/companies/{id}/contact/{contactId}/add' => [
                'post' => [
                    'tags'        => ['Companies'],
                    'summary'     => 'Add a contact to a company',
                    'operationId' => 'addContactToCompany',
                    'parameters'  => [
                        $this->idParam(),
                        ['name' => 'contactId', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']],
                    ],
                    'responses' => [
                        '200' => $this->jsonResp('Contact added to company'),
                        '401' => $this->ref401(),
                        '404' => $this->ref404(),
                    ],
                ],
            ],
            '/companies/{id}/contact/{contactId}/remove' => [
                'post' => [
                    'tags'        => ['Companies'],
                    'summary'     => 'Remove a contact from a company',
                    'operationId' => 'removeContactFromCompany',
                    'parameters'  => [
                        $this->idParam(),
                        ['name' => 'contactId', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']],
                    ],
                    'responses' => [
                        '200' => $this->jsonResp('Contact removed from company'),
                        '401' => $this->ref401(),
                        '404' => $this->ref404(),
                    ],
                ],
            ],
        ];
    }

    // -------------------------------------------------------------------------
    // Segments
    // -------------------------------------------------------------------------

    private function segmentPaths(): array
    {
        return [
            '/segments' => [
                'get'  => $this->listOp('Segments', 'List segments', 'lists', '#/components/schemas/Segment'),
                'post' => $this->createOp('Segments', 'Create a segment', 'list', '#/components/schemas/Segment', '#/components/schemas/SegmentInput'),
            ],
            '/segments/{id}' => [
                'get'    => $this->getOneOp('Segments', 'Get a segment', 'list', '#/components/schemas/Segment'),
                'patch'  => $this->updateOp('Segments', 'Update a segment', 'list', '#/components/schemas/Segment', '#/components/schemas/SegmentInput'),
                'delete' => $this->deleteOp('Segments', 'Delete a segment'),
            ],
            '/segments/{id}/contacts/add' => [
                'post' => [
                    'tags'        => ['Segments'],
                    'summary'     => 'Add contacts to a segment',
                    'description' => 'Pass a `ids` array to add multiple contacts at once.',
                    'operationId' => 'addContactsToSegment',
                    'parameters'  => [$this->idParam()],
                    'requestBody' => $this->jsonBody('#/components/schemas/ContactIdList'),
                    'responses'   => [
                        '200' => $this->jsonResp('Contacts added'),
                        '401' => $this->ref401(),
                        '404' => $this->ref404(),
                    ],
                ],
            ],
            '/segments/{id}/contacts/remove' => [
                'post' => [
                    'tags'        => ['Segments'],
                    'summary'     => 'Remove contacts from a segment',
                    'operationId' => 'removeContactsFromSegment',
                    'parameters'  => [$this->idParam()],
                    'requestBody' => $this->jsonBody('#/components/schemas/ContactIdList'),
                    'responses'   => [
                        '200' => $this->jsonResp('Contacts removed'),
                        '401' => $this->ref401(),
                        '404' => $this->ref404(),
                    ],
                ],
            ],
        ];
    }

    // -------------------------------------------------------------------------
    // Campaigns
    // -------------------------------------------------------------------------

    private function campaignPaths(): array
    {
        return [
            '/campaigns' => [
                'get'  => $this->listOp('Campaigns', 'List campaigns', 'campaigns', '#/components/schemas/Campaign'),
                'post' => $this->createOp('Campaigns', 'Create a campaign', 'campaign', '#/components/schemas/Campaign', '#/components/schemas/CampaignInput'),
            ],
            '/campaigns/{id}' => [
                'get'    => $this->getOneOp('Campaigns', 'Get a campaign', 'campaign', '#/components/schemas/Campaign'),
                'patch'  => $this->updateOp('Campaigns', 'Update a campaign', 'campaign', '#/components/schemas/Campaign', '#/components/schemas/CampaignInput'),
                'delete' => $this->deleteOp('Campaigns', 'Delete a campaign'),
            ],
            '/campaigns/{id}/contact/{contactId}/add' => [
                'post' => [
                    'tags'        => ['Campaigns'],
                    'summary'     => 'Add a contact to a campaign',
                    'operationId' => 'addContactToCampaign',
                    'parameters'  => [
                        $this->idParam(),
                        ['name' => 'contactId', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']],
                    ],
                    'responses' => [
                        '200' => $this->jsonResp('Contact added to campaign'),
                        '401' => $this->ref401(),
                        '404' => $this->ref404(),
                    ],
                ],
            ],
            '/campaigns/{id}/contact/{contactId}/remove' => [
                'post' => [
                    'tags'        => ['Campaigns'],
                    'summary'     => 'Remove a contact from a campaign',
                    'operationId' => 'removeContactFromCampaign',
                    'parameters'  => [
                        $this->idParam(),
                        ['name' => 'contactId', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']],
                    ],
                    'responses' => [
                        '200' => $this->jsonResp('Contact removed from campaign'),
                        '401' => $this->ref401(),
                        '404' => $this->ref404(),
                    ],
                ],
            ],
            '/campaigns/{id}/events' => [
                'get' => [
                    'tags'        => ['Campaigns'],
                    'summary'     => 'List events in a campaign',
                    'operationId' => 'getCampaignEvents',
                    'parameters'  => [$this->idParam()],
                    'responses'   => [
                        '200' => $this->jsonResp('Success'),
                        '401' => $this->ref401(),
                        '404' => $this->ref404(),
                    ],
                ],
            ],
        ];
    }

    // -------------------------------------------------------------------------
    // Emails
    // -------------------------------------------------------------------------

    private function emailPaths(): array
    {
        return [
            '/emails' => [
                'get'  => $this->listOp('Emails', 'List emails', 'emails', '#/components/schemas/Email'),
                'post' => $this->createOp('Emails', 'Create an email', 'email', '#/components/schemas/Email', '#/components/schemas/EmailInput'),
            ],
            '/emails/{id}' => [
                'get'    => $this->getOneOp('Emails', 'Get an email', 'email', '#/components/schemas/Email'),
                'patch'  => $this->updateOp('Emails', 'Update an email', 'email', '#/components/schemas/Email', '#/components/schemas/EmailInput'),
                'delete' => $this->deleteOp('Emails', 'Delete an email'),
            ],
            '/emails/{id}/send' => [
                'post' => [
                    'tags'        => ['Emails'],
                    'summary'     => 'Send a list email to its assigned segments',
                    'operationId' => 'sendEmail',
                    'parameters'  => [$this->idParam()],
                    'responses'   => [
                        '200' => $this->jsonResp('Email queued for sending'),
                        '400' => $this->ref400(),
                        '401' => $this->ref401(),
                        '404' => $this->ref404(),
                    ],
                ],
            ],
            '/emails/{id}/contact/{contactId}/send' => [
                'post' => [
                    'tags'        => ['Emails'],
                    'summary'     => 'Send an email to a specific contact',
                    'operationId' => 'sendEmailToContact',
                    'parameters'  => [
                        $this->idParam(),
                        ['name' => 'contactId', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']],
                    ],
                    'requestBody' => [
                        'content' => ['application/json' => ['schema' => ['type' => 'object', 'properties' => [
                            'tokens' => ['type' => 'object', 'additionalProperties' => ['type' => 'string'], 'description' => 'Token replacements for the email body'],
                        ]]]],
                    ],
                    'responses' => [
                        '200' => $this->jsonResp('Email sent'),
                        '400' => $this->ref400(),
                        '401' => $this->ref401(),
                        '404' => $this->ref404(),
                    ],
                ],
            ],
        ];
    }

    // -------------------------------------------------------------------------
    // Forms
    // -------------------------------------------------------------------------

    private function formPaths(): array
    {
        return [
            '/forms' => [
                'get'  => $this->listOp('Forms', 'List forms', 'forms', '#/components/schemas/Form'),
                'post' => $this->createOp('Forms', 'Create a form', 'form', '#/components/schemas/Form', '#/components/schemas/FormInput'),
            ],
            '/forms/{id}' => [
                'get'    => $this->getOneOp('Forms', 'Get a form', 'form', '#/components/schemas/Form'),
                'patch'  => $this->updateOp('Forms', 'Update a form', 'form', '#/components/schemas/Form', '#/components/schemas/FormInput'),
                'delete' => $this->deleteOp('Forms', 'Delete a form'),
            ],
            '/forms/{id}/submissions' => [
                'get' => [
                    'tags'        => ['Forms'],
                    'summary'     => 'List submissions for a form',
                    'operationId' => 'getFormSubmissions',
                    'parameters'  => array_merge([$this->idParam()], $this->paginationParams()),
                    'responses'   => [
                        '200' => $this->jsonResp('Success', '#/components/schemas/FormSubmissionsResponse'),
                        '401' => $this->ref401(),
                        '404' => $this->ref404(),
                    ],
                ],
            ],
            '/forms/{id}/submissions/{submissionId}' => [
                'get' => [
                    'tags'        => ['Forms'],
                    'summary'     => 'Get a single form submission',
                    'operationId' => 'getFormSubmission',
                    'parameters'  => [
                        $this->idParam(),
                        ['name' => 'submissionId', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']],
                    ],
                    'responses' => [
                        '200' => $this->jsonResp('Success'),
                        '401' => $this->ref401(),
                        '404' => $this->ref404(),
                    ],
                ],
            ],
        ];
    }

    // -------------------------------------------------------------------------
    // Landing Pages
    // -------------------------------------------------------------------------

    private function pagePaths(): array
    {
        return [
            '/pages' => [
                'get'  => $this->listOp('Landing Pages', 'List landing pages', 'pages', '#/components/schemas/Page'),
                'post' => $this->createOp('Landing Pages', 'Create a landing page', 'page', '#/components/schemas/Page', '#/components/schemas/PageInput'),
            ],
            '/pages/{id}' => [
                'get'    => $this->getOneOp('Landing Pages', 'Get a landing page', 'page', '#/components/schemas/Page'),
                'patch'  => $this->updateOp('Landing Pages', 'Update a landing page', 'page', '#/components/schemas/Page', '#/components/schemas/PageInput'),
                'delete' => $this->deleteOp('Landing Pages', 'Delete a landing page'),
            ],
        ];
    }

    // -------------------------------------------------------------------------
    // Assets
    // -------------------------------------------------------------------------

    private function assetPaths(): array
    {
        return [
            '/assets' => [
                'get'  => $this->listOp('Assets', 'List assets', 'assets', '#/components/schemas/Asset'),
                'post' => $this->createOp('Assets', 'Create an asset', 'asset', '#/components/schemas/Asset', '#/components/schemas/AssetInput'),
            ],
            '/assets/{id}' => [
                'get'    => $this->getOneOp('Assets', 'Get an asset', 'asset', '#/components/schemas/Asset'),
                'patch'  => $this->updateOp('Assets', 'Update an asset', 'asset', '#/components/schemas/Asset', '#/components/schemas/AssetInput'),
                'delete' => $this->deleteOp('Assets', 'Delete an asset'),
            ],
        ];
    }

    // -------------------------------------------------------------------------
    // Tags
    // -------------------------------------------------------------------------

    private function tagPaths(): array
    {
        return [
            '/tags' => [
                'get'  => $this->listOp('Tags', 'List tags', 'tags', '#/components/schemas/Tag'),
                'post' => $this->createOp('Tags', 'Create a tag', 'tag', '#/components/schemas/Tag', '#/components/schemas/TagInput'),
            ],
            '/tags/{id}' => [
                'get'    => $this->getOneOp('Tags', 'Get a tag', 'tag', '#/components/schemas/Tag'),
                'patch'  => $this->updateOp('Tags', 'Update a tag', 'tag', '#/components/schemas/Tag', '#/components/schemas/TagInput'),
                'delete' => $this->deleteOp('Tags', 'Delete a tag'),
            ],
        ];
    }

    // -------------------------------------------------------------------------
    // Categories
    // -------------------------------------------------------------------------

    private function categoryPaths(): array
    {
        return [
            '/categories' => [
                'get'  => $this->listOp('Categories', 'List categories', 'categories', '#/components/schemas/Category'),
                'post' => $this->createOp('Categories', 'Create a category', 'category', '#/components/schemas/Category', '#/components/schemas/CategoryInput'),
            ],
            '/categories/{id}' => [
                'get'    => $this->getOneOp('Categories', 'Get a category', 'category', '#/components/schemas/Category'),
                'patch'  => $this->updateOp('Categories', 'Update a category', 'category', '#/components/schemas/Category', '#/components/schemas/CategoryInput'),
                'delete' => $this->deleteOp('Categories', 'Delete a category'),
            ],
        ];
    }

    // -------------------------------------------------------------------------
    // Custom Fields
    // -------------------------------------------------------------------------

    private function fieldPaths(): array
    {
        return [
            '/fields/contact' => [
                'get' => [
                    'tags'        => ['Custom Fields'],
                    'summary'     => 'List contact custom fields',
                    'operationId' => 'getContactFields',
                    'parameters'  => $this->paginationParams(),
                    'responses'   => [
                        '200' => $this->jsonResp('Success', '#/components/schemas/FieldListResponse'),
                        '401' => $this->ref401(),
                    ],
                ],
                'post' => [
                    'tags'        => ['Custom Fields'],
                    'summary'     => 'Create a contact custom field',
                    'operationId' => 'createContactField',
                    'requestBody' => $this->jsonBody('#/components/schemas/FieldInput'),
                    'responses'   => [
                        '201' => $this->jsonResp('Created', '#/components/schemas/FieldResponse'),
                        '400' => $this->ref400(),
                        '401' => $this->ref401(),
                    ],
                ],
            ],
            '/fields/contact/{id}' => [
                'get'    => $this->getOneOp('Custom Fields', 'Get a contact custom field', 'field', '#/components/schemas/Field'),
                'patch'  => $this->updateOp('Custom Fields', 'Update a contact custom field', 'field', '#/components/schemas/Field', '#/components/schemas/FieldInput'),
                'delete' => $this->deleteOp('Custom Fields', 'Delete a contact custom field'),
            ],
            '/fields/company' => [
                'get' => [
                    'tags'        => ['Custom Fields'],
                    'summary'     => 'List company custom fields',
                    'operationId' => 'getCompanyFields',
                    'parameters'  => $this->paginationParams(),
                    'responses'   => [
                        '200' => $this->jsonResp('Success', '#/components/schemas/FieldListResponse'),
                        '401' => $this->ref401(),
                    ],
                ],
                'post' => [
                    'tags'        => ['Custom Fields'],
                    'summary'     => 'Create a company custom field',
                    'operationId' => 'createCompanyField',
                    'requestBody' => $this->jsonBody('#/components/schemas/FieldInput'),
                    'responses'   => [
                        '201' => $this->jsonResp('Created', '#/components/schemas/FieldResponse'),
                        '400' => $this->ref400(),
                        '401' => $this->ref401(),
                    ],
                ],
            ],
            '/fields/company/{id}' => [
                'get'    => $this->getOneOp('Custom Fields', 'Get a company custom field', 'field', '#/components/schemas/Field'),
                'patch'  => $this->updateOp('Custom Fields', 'Update a company custom field', 'field', '#/components/schemas/Field', '#/components/schemas/FieldInput'),
                'delete' => $this->deleteOp('Custom Fields', 'Delete a company custom field'),
            ],
        ];
    }

    // -------------------------------------------------------------------------
    // Notes
    // -------------------------------------------------------------------------

    private function notePaths(): array
    {
        return [
            '/notes' => [
                'get'  => $this->listOp('Notes', 'List notes', 'notes', '#/components/schemas/Note'),
                'post' => $this->createOp('Notes', 'Create a note', 'note', '#/components/schemas/Note', '#/components/schemas/NoteInput'),
            ],
            '/notes/{id}' => [
                'get'    => $this->getOneOp('Notes', 'Get a note', 'note', '#/components/schemas/Note'),
                'patch'  => $this->updateOp('Notes', 'Update a note', 'note', '#/components/schemas/Note', '#/components/schemas/NoteInput'),
                'delete' => $this->deleteOp('Notes', 'Delete a note'),
            ],
        ];
    }

    // -------------------------------------------------------------------------
    // Users
    // -------------------------------------------------------------------------

    private function userPaths(): array
    {
        return [
            '/users' => [
                'get'  => $this->listOp('Users', 'List users', 'users', '#/components/schemas/User'),
                'post' => $this->createOp('Users', 'Create a user', 'user', '#/components/schemas/User', '#/components/schemas/UserInput'),
            ],
            '/users/self' => [
                'get' => [
                    'tags'        => ['Users'],
                    'summary'     => 'Get the currently authenticated user',
                    'operationId' => 'getSelf',
                    'responses'   => [
                        '200' => $this->jsonResp('Success', '#/components/schemas/UserResponse'),
                        '401' => $this->ref401(),
                    ],
                ],
                'patch' => [
                    'tags'        => ['Users'],
                    'summary'     => 'Update the currently authenticated user',
                    'operationId' => 'updateSelf',
                    'requestBody' => $this->jsonBody('#/components/schemas/UserInput'),
                    'responses'   => [
                        '200' => $this->jsonResp('Updated', '#/components/schemas/UserResponse'),
                        '400' => $this->ref400(),
                        '401' => $this->ref401(),
                    ],
                ],
            ],
            '/users/{id}' => [
                'get'    => $this->getOneOp('Users', 'Get a user', 'user', '#/components/schemas/User'),
                'patch'  => $this->updateOp('Users', 'Update a user', 'user', '#/components/schemas/User', '#/components/schemas/UserInput'),
                'delete' => $this->deleteOp('Users', 'Delete a user'),
            ],
        ];
    }

    // -------------------------------------------------------------------------
    // Roles
    // -------------------------------------------------------------------------

    private function rolePaths(): array
    {
        return [
            '/roles' => [
                'get'  => $this->listOp('Roles', 'List roles', 'roles', '#/components/schemas/Role'),
                'post' => $this->createOp('Roles', 'Create a role', 'role', '#/components/schemas/Role', '#/components/schemas/RoleInput'),
            ],
            '/roles/{id}' => [
                'get'    => $this->getOneOp('Roles', 'Get a role', 'role', '#/components/schemas/Role'),
                'patch'  => $this->updateOp('Roles', 'Update a role', 'role', '#/components/schemas/Role', '#/components/schemas/RoleInput'),
                'delete' => $this->deleteOp('Roles', 'Delete a role'),
            ],
        ];
    }

    // -------------------------------------------------------------------------
    // Reports
    // -------------------------------------------------------------------------

    private function reportPaths(): array
    {
        return [
            '/reports' => [
                'get' => $this->listOp('Reports', 'List reports', 'reports', '#/components/schemas/Report'),
            ],
            '/reports/{id}' => [
                'get' => $this->getOneOp('Reports', 'Get a report', 'report', '#/components/schemas/Report'),
            ],
            '/reports/{id}/run' => [
                'get' => [
                    'tags'        => ['Reports'],
                    'summary'     => 'Run a report and get its data',
                    'operationId' => 'runReport',
                    'parameters'  => array_merge(
                        [$this->idParam()],
                        [
                            ['name' => 'dateFrom', 'in' => 'query', 'schema' => ['type' => 'string', 'format' => 'date']],
                            ['name' => 'dateTo', 'in' => 'query', 'schema' => ['type' => 'string', 'format' => 'date']],
                        ],
                        $this->paginationParams()
                    ),
                    'responses' => [
                        '200' => $this->jsonResp('Report data'),
                        '401' => $this->ref401(),
                        '404' => $this->ref404(),
                    ],
                ],
            ],
        ];
    }

    // -------------------------------------------------------------------------
    // Stats
    // -------------------------------------------------------------------------

    private function statsPaths(): array
    {
        return [
            '/stats' => [
                'get' => [
                    'tags'        => ['Stats'],
                    'summary'     => 'List available stat tables',
                    'operationId' => 'getStatTables',
                    'responses'   => [
                        '200' => $this->jsonResp('List of table names'),
                        '401' => $this->ref401(),
                    ],
                ],
            ],
            '/stats/{table}' => [
                'get' => [
                    'tags'        => ['Stats'],
                    'summary'     => 'Get stats from a specific table',
                    'operationId' => 'getStats',
                    'parameters'  => array_merge(
                        [['name' => 'table', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string'], 'description' => 'Table name (e.g. email_stats, lead_event_log)']],
                        $this->paginationParams(),
                        [
                            ['name' => 'dateFrom', 'in' => 'query', 'schema' => ['type' => 'string', 'format' => 'date-time']],
                            ['name' => 'dateTo', 'in' => 'query', 'schema' => ['type' => 'string', 'format' => 'date-time']],
                        ]
                    ),
                    'responses' => [
                        '200' => $this->jsonResp('Stats data'),
                        '401' => $this->ref401(),
                        '404' => $this->ref404(),
                    ],
                ],
            ],
        ];
    }

    // -------------------------------------------------------------------------
    // Notifications
    // -------------------------------------------------------------------------

    private function notificationPaths(): array
    {
        return [
            '/notifications' => [
                'get'  => $this->listOp('Notifications', 'List web push notifications', 'notifications', '#/components/schemas/Notification'),
                'post' => $this->createOp('Notifications', 'Create a web push notification', 'notification', '#/components/schemas/Notification', '#/components/schemas/NotificationInput'),
            ],
            '/notifications/{id}' => [
                'get'    => $this->getOneOp('Notifications', 'Get a notification', 'notification', '#/components/schemas/Notification'),
                'patch'  => $this->updateOp('Notifications', 'Update a notification', 'notification', '#/components/schemas/Notification', '#/components/schemas/NotificationInput'),
                'delete' => $this->deleteOp('Notifications', 'Delete a notification'),
            ],
        ];
    }

    // -------------------------------------------------------------------------
    // Messages
    // -------------------------------------------------------------------------

    private function messagePaths(): array
    {
        return [
            '/messages' => [
                'get'  => $this->listOp('Messages', 'List marketing messages', 'messages', '#/components/schemas/Message'),
                'post' => $this->createOp('Messages', 'Create a marketing message', 'message', '#/components/schemas/Message', '#/components/schemas/MessageInput'),
            ],
            '/messages/{id}' => [
                'get'    => $this->getOneOp('Messages', 'Get a marketing message', 'message', '#/components/schemas/Message'),
                'patch'  => $this->updateOp('Messages', 'Update a marketing message', 'message', '#/components/schemas/Message', '#/components/schemas/MessageInput'),
                'delete' => $this->deleteOp('Messages', 'Delete a marketing message'),
            ],
        ];
    }

    // -------------------------------------------------------------------------
    // Points
    // -------------------------------------------------------------------------

    private function pointPaths(): array
    {
        return [
            '/points' => [
                'get'  => $this->listOp('Points', 'List point actions', 'points', '#/components/schemas/Point'),
                'post' => $this->createOp('Points', 'Create a point action', 'point', '#/components/schemas/Point', '#/components/schemas/PointInput'),
            ],
            '/points/{id}' => [
                'get'    => $this->getOneOp('Points', 'Get a point action', 'point', '#/components/schemas/Point'),
                'patch'  => $this->updateOp('Points', 'Update a point action', 'point', '#/components/schemas/Point', '#/components/schemas/PointInput'),
                'delete' => $this->deleteOp('Points', 'Delete a point action'),
            ],
            '/points/triggers' => [
                'get'  => $this->listOp('Points', 'List point triggers', 'triggers', '#/components/schemas/PointTrigger'),
                'post' => $this->createOp('Points', 'Create a point trigger', 'trigger', '#/components/schemas/PointTrigger', '#/components/schemas/PointTriggerInput'),
            ],
            '/points/triggers/{id}' => [
                'get'    => $this->getOneOp('Points', 'Get a point trigger', 'trigger', '#/components/schemas/PointTrigger'),
                'patch'  => $this->updateOp('Points', 'Update a point trigger', 'trigger', '#/components/schemas/PointTrigger', '#/components/schemas/PointTriggerInput'),
                'delete' => $this->deleteOp('Points', 'Delete a point trigger'),
            ],
        ];
    }

    // -------------------------------------------------------------------------
    // Dynamic Content
    // -------------------------------------------------------------------------

    private function dynamicContentPaths(): array
    {
        return [
            '/dynamiccontents' => [
                'get'  => $this->listOp('Dynamic Content', 'List dynamic content blocks', 'dynamicContents', '#/components/schemas/DynamicContent'),
                'post' => $this->createOp('Dynamic Content', 'Create a dynamic content block', 'dynamicContent', '#/components/schemas/DynamicContent', '#/components/schemas/DynamicContentInput'),
            ],
            '/dynamiccontents/{id}' => [
                'get'    => $this->getOneOp('Dynamic Content', 'Get a dynamic content block', 'dynamicContent', '#/components/schemas/DynamicContent'),
                'patch'  => $this->updateOp('Dynamic Content', 'Update a dynamic content block', 'dynamicContent', '#/components/schemas/DynamicContent', '#/components/schemas/DynamicContentInput'),
                'delete' => $this->deleteOp('Dynamic Content', 'Delete a dynamic content block'),
            ],
        ];
    }

    // =========================================================================
    // CRUD operation helpers
    // =========================================================================

    private function listOp(string $tag, string $summary, string $resourceKey, string $schemaRef): array
    {
        return [
            'tags'        => [$tag],
            'summary'     => $summary,
            'operationId' => 'list' . $this->camel($resourceKey),
            'parameters'  => $this->paginationParams(),
            'responses'   => [
                '200' => [
                    'description' => 'Success',
                    'content'     => ['application/json' => ['schema' => [
                        'type'       => 'object',
                        'properties' => [
                            'total'       => ['type' => 'string', 'description' => 'Total number of records matching the query'],
                            $resourceKey  => [
                                'type'                 => 'object',
                                'description'          => 'Map of string ID → resource object',
                                'additionalProperties' => ['$ref' => $schemaRef],
                            ],
                        ],
                    ]]],
                ],
                '401' => $this->ref401(),
                '403' => $this->ref403(),
            ],
        ];
    }

    private function createOp(string $tag, string $summary, string $resourceKey, string $schemaRef, string $inputRef): array
    {
        return [
            'tags'        => [$tag],
            'summary'     => $summary,
            'operationId' => 'create' . $this->camel($resourceKey),
            'requestBody' => $this->jsonBody($inputRef),
            'responses'   => [
                '201' => [
                    'description' => 'Created',
                    'content'     => ['application/json' => ['schema' => [
                        'type'       => 'object',
                        'properties' => [$resourceKey => ['$ref' => $schemaRef]],
                    ]]],
                ],
                '400' => $this->ref400(),
                '401' => $this->ref401(),
                '403' => $this->ref403(),
                '422' => $this->ref422(),
            ],
        ];
    }

    private function getOneOp(string $tag, string $summary, string $resourceKey, string $schemaRef): array
    {
        return [
            'tags'        => [$tag],
            'summary'     => $summary,
            'operationId' => 'get' . $this->camel($resourceKey),
            'parameters'  => [$this->idParam()],
            'responses'   => [
                '200' => [
                    'description' => 'Success',
                    'content'     => ['application/json' => ['schema' => [
                        'type'       => 'object',
                        'properties' => [$resourceKey => ['$ref' => $schemaRef]],
                    ]]],
                ],
                '401' => $this->ref401(),
                '403' => $this->ref403(),
                '404' => $this->ref404(),
            ],
        ];
    }

    private function updateOp(string $tag, string $summary, string $resourceKey, string $schemaRef, string $inputRef): array
    {
        return [
            'tags'        => [$tag],
            'summary'     => $summary,
            'operationId' => 'update' . $this->camel($resourceKey),
            'parameters'  => [$this->idParam()],
            'requestBody' => $this->jsonBody($inputRef),
            'responses'   => [
                '200' => [
                    'description' => 'Updated',
                    'content'     => ['application/json' => ['schema' => [
                        'type'       => 'object',
                        'properties' => [$resourceKey => ['$ref' => $schemaRef]],
                    ]]],
                ],
                '400' => $this->ref400(),
                '401' => $this->ref401(),
                '403' => $this->ref403(),
                '404' => $this->ref404(),
                '422' => $this->ref422(),
            ],
        ];
    }

    private function deleteOp(string $tag, string $summary): array
    {
        return [
            'tags'        => [$tag],
            'summary'     => $summary,
            'operationId' => 'delete' . $this->camel(substr($summary, strrpos($summary, ' ') + 1)),
            'parameters'  => [$this->idParam()],
            'responses'   => [
                '200' => ['description' => 'Deleted successfully'],
                '401' => $this->ref401(),
                '403' => $this->ref403(),
                '404' => $this->ref404(),
            ],
        ];
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
                        'scopes'           => [],
                    ],
                    'clientCredentials' => [
                        'tokenUrl' => $siteUrl . '/oauth/v2/token',
                        'scopes'   => [],
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

    // =========================================================================
    // Small helpers
    // =========================================================================

    /** Standard pagination query parameters (inline, not $ref) */
    private function paginationParams(): array
    {
        return [
            ['name' => 'start',         'in' => 'query', 'schema' => ['type' => 'integer', 'default' => 0]],
            ['name' => 'limit',         'in' => 'query', 'schema' => ['type' => 'integer', 'default' => 30, 'maximum' => 1000]],
            ['name' => 'search',        'in' => 'query', 'schema' => ['type' => 'string']],
            ['name' => 'orderBy',       'in' => 'query', 'schema' => ['type' => 'string']],
            ['name' => 'orderByDir',    'in' => 'query', 'schema' => ['type' => 'string', 'enum' => ['ASC', 'DESC'], 'default' => 'ASC']],
            ['name' => 'publishedOnly', 'in' => 'query', 'schema' => ['type' => 'boolean', 'default' => false]],
            ['name' => 'minimal',       'in' => 'query', 'schema' => ['type' => 'boolean', 'default' => false]],
        ];
    }

    private function idParam(): array
    {
        return [
            'name'     => 'id',
            'in'       => 'path',
            'required' => true,
            'schema'   => ['type' => 'integer', 'minimum' => 1],
        ];
    }

    private function jsonBody(string $schemaRef): array
    {
        return [
            'required' => true,
            'content'  => ['application/json' => ['schema' => ['$ref' => $schemaRef]]],
        ];
    }

    private function jsonResp(string $description, ?string $schemaRef = null): array
    {
        if ($schemaRef === null) {
            return ['description' => $description];
        }

        return [
            'description' => $description,
            'content'     => ['application/json' => ['schema' => ['$ref' => $schemaRef]]],
        ];
    }

    private function ref400(): array
    {
        return ['$ref' => '#/components/responses/BadRequest'];
    }

    private function ref401(): array
    {
        return ['$ref' => '#/components/responses/Unauthorized'];
    }

    private function ref403(): array
    {
        return ['$ref' => '#/components/responses/Forbidden'];
    }

    private function ref404(): array
    {
        return ['$ref' => '#/components/responses/NotFound'];
    }

    private function ref422(): array
    {
        return ['$ref' => '#/components/responses/UnprocessableEntity'];
    }

    /** CamelCase a resource key for operationId generation */
    private function camel(string $value): string
    {
        return ucfirst(str_replace(['-', '_'], ' ', $value));
    }
}
