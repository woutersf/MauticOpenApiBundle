<?php

declare(strict_types=1);

namespace MauticPlugin\MauticOpenApiBundle\Service;

use Psr\Container\ContainerInterface;
use Symfony\Component\Routing\RouterInterface;

/**
 * Builds the OpenAPI "paths" and "tags" objects from Mautic's live routing table.
 *
 * Every path, HTTP method and path parameter is read from the router, so the
 * generated document always matches the instance it is served from, including
 * endpoints contributed by plugins.
 *
 * Resource metadata (the JSON envelope key and the backing entity) is read from
 * the API controller itself: every controller extending CommonApiController sets
 * $entityNameOne, $entityNameMulti and $entityClass in its constructor.
 */
class RoutePathBuilder
{
    private const API_PREFIX = '/api';

    /**
     * Routes under this prefix are served by API Platform, which publishes its
     * own OpenAPI document. They are excluded here to avoid describing them twice.
     */
    private const SKIPPED_PREFIX = '/api/v2';

    private const HTTP_METHODS = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];

    /**
     * Entity short class name => schema component name, for the cases where the
     * hand written schema is not named after the entity.
     */
    private const ENTITY_SCHEMA_MAP = [
        'Lead'      => 'Contact',
        'LeadList'  => 'Segment',
        'LeadNote'  => 'Note',
        'LeadField' => 'Field',
        'Trigger'   => 'PointTrigger',
    ];

    /**
     * Envelope key => human readable tag name. Anything not listed falls back to
     * a title cased version of the envelope key.
     */
    private const TAG_LABELS = [
        'contacts'        => 'Contacts',
        'companies'       => 'Companies',
        'lists'           => 'Segments',
        'segments'        => 'Segments',
        'campaigns'       => 'Campaigns',
        'events'          => 'Campaign Events',
        'emails'          => 'Emails',
        'forms'           => 'Forms',
        'submissions'     => 'Form Submissions',
        'pages'           => 'Landing Pages',
        'assets'          => 'Assets',
        'tags'            => 'Tags',
        'categories'      => 'Categories',
        'fields'          => 'Custom Fields',
        'notes'           => 'Notes',
        'users'           => 'Users',
        'roles'           => 'Roles',
        'reports'         => 'Reports',
        'stats'           => 'Stats',
        'notifications'   => 'Notifications',
        'messages'        => 'Messages',
        'points'          => 'Points',
        'pointGroups'     => 'Point Groups',
        'insights'        => 'Point Insights',
        'triggers'        => 'Point Triggers',
        'dynamicContents' => 'Dynamic Content',
        'devices'         => 'Devices',
        'smses'           => 'Text Messages',
        'tweets'          => 'Tweets',
        'stages'          => 'Stages',
        'hooks'           => 'Webhooks',
        'focus'           => 'Focus Items',
        'files'           => 'Files',
        'themes'          => 'Themes',
        'widgets'         => 'Widgets',
    ];

    private const TAG_DESCRIPTIONS = [
        'Contacts'         => 'Manage contacts (leads)',
        'Companies'        => 'Manage companies and company-contact relationships',
        'Segments'         => 'Manage contact segments (lists)',
        'Campaigns'        => 'Manage campaigns and campaign membership',
        'Campaign Events'  => 'Read and edit campaign events and their contact logs',
        'Emails'           => 'Manage email assets and send emails',
        'Forms'            => 'Manage forms',
        'Form Submissions' => 'Read form submissions',
        'Landing Pages'    => 'Manage landing pages',
        'Assets'           => 'Manage file assets (documents, images, etc.)',
        'Tags'             => 'Manage contact tags',
        'Categories'       => 'Manage categories',
        'Custom Fields'    => 'Manage contact and company custom fields',
        'Notes'            => 'Manage contact notes',
        'Users'            => 'Manage Mautic users',
        'Roles'            => 'Manage user roles and permissions',
        'Reports'          => 'Access reports',
        'Stats'            => 'Access raw table statistics',
        'Notifications'    => 'Manage web push notifications',
        'Messages'         => 'Manage marketing messages (multi-channel)',
        'Points'           => 'Manage point actions',
        'Point Groups'     => 'Manage point groups and per-group contact scores',
        'Point Triggers'   => 'Manage point triggers and trigger events',
        'Dynamic Content'  => 'Manage dynamic web content blocks',
        'Devices'          => 'Manage contact devices',
        'Text Messages'    => 'Manage SMS messages',
        'Tweets'           => 'Manage tweets',
        'Stages'           => 'Manage contact stages',
        'Webhooks'         => 'Manage webhooks',
        'Focus Items'      => 'Manage focus items',
        'Files'            => 'Manage uploaded files',
        'Themes'           => 'Read installed themes',
        'Widgets'          => 'Read dashboard widget types',
    ];

    /** @var array<string, array{one: ?string, multi: ?string, entity: ?string}> */
    private array $metaCache = [];

    /** @var array<string, true> */
    private array $usedOperationIds = [];

    /** @var array<string, true> */
    private array $seenTags = [];

    public function __construct(
        private readonly RouterInterface $router,
        private readonly ContainerInterface $container,
    ) {
    }

    /**
     * @param list<string> $knownSchemas names of the schemas defined under components.schemas
     *
     * @return array{paths: array<string, array<string, mixed>>, tags: list<array{name: string, description: string}>}
     */
    public function build(array $knownSchemas): array
    {
        $this->usedOperationIds = [];
        $this->seenTags         = [];

        $paths = [];

        foreach ($this->collectRoutes() as $specPath => $methods) {
            foreach ($methods as $method => $route) {
                $meta = $this->resolveMeta($route['class'], $route['name']);
                $refs = $this->resolveSchemaRefs($meta, $knownSchemas);

                $paths[$specPath][strtolower($method)] = $this->buildOperation(
                    $method,
                    $specPath,
                    $route,
                    $meta,
                    $refs,
                );
            }
        }

        return ['paths' => $paths, 'tags' => $this->buildTags()];
    }

    // -------------------------------------------------------------------------
    // Route collection
    // -------------------------------------------------------------------------

    /**
     * @return array<string, array<string, array{name: string, class: string, action: string}>>
     */
    private function collectRoutes(): array
    {
        $grouped = [];

        foreach ($this->router->getRouteCollection() as $name => $route) {
            $path = $route->getPath();

            if (self::API_PREFIX !== $path && !str_starts_with($path, self::API_PREFIX.'/')) {
                continue;
            }

            if (str_starts_with($path, self::SKIPPED_PREFIX)) {
                continue;
            }

            $controller = (string) $route->getDefault('_controller');

            if (!str_contains($controller, '::')) {
                continue;
            }

            [$class, $action] = explode('::', $controller, 2);

            $methods = array_values(array_intersect(
                array_map('strtoupper', $route->getMethods()),
                self::HTTP_METHODS,
            ));

            if ([] === $methods) {
                $methods = ['GET'];
            }

            $specPath = substr($path, strlen(self::API_PREFIX));
            $specPath = '' === $specPath ? '/' : $specPath;

            foreach ($methods as $method) {
                // First route wins if two declare the same path and method.
                $grouped[$specPath][$method] ??= [
                    'name'   => $name,
                    'class'  => $class,
                    'action' => $action,
                ];
            }
        }

        ksort($grouped);

        return $grouped;
    }

    // -------------------------------------------------------------------------
    // Controller metadata
    // -------------------------------------------------------------------------

    /**
     * @return array{one: ?string, multi: ?string, entity: ?string}
     */
    private function resolveMeta(string $class, string $routeName): array
    {
        if (!isset($this->metaCache[$class])) {
            $this->metaCache[$class] = $this->readControllerMeta($class);
        }

        $meta = $this->metaCache[$class];

        // Controllers that cannot be built outside a request context still have a
        // usable envelope key in their route name: mautic_api_<multi>_<action>.
        if (null === $meta['multi']) {
            $meta['multi'] = $this->guessMultiFromRouteName($routeName) ?? $this->guessMultiFromClass($class);
        }

        if (null === $meta['one'] && null !== $meta['multi']) {
            $meta['one'] = $this->singularize($meta['multi']);
        }

        return $meta;
    }

    /**
     * @return array{one: ?string, multi: ?string, entity: ?string}
     */
    private function readControllerMeta(string $class): array
    {
        $meta = ['one' => null, 'multi' => null, 'entity' => null];

        try {
            $instance   = $this->container->get($class);
            $reflection = new \ReflectionClass($instance);

            foreach (['entityNameOne' => 'one', 'entityNameMulti' => 'multi', 'entityClass' => 'entity'] as $property => $key) {
                if (!$reflection->hasProperty($property)) {
                    continue;
                }

                $reflected = $reflection->getProperty($property);

                if (!$reflected->isInitialized($instance)) {
                    continue;
                }

                $value = $reflected->getValue($instance);

                if (is_string($value) && '' !== $value) {
                    $meta[$key] = $value;
                }
            }
        } catch (\Throwable) {
            // Some controllers need an active request to construct. The route name
            // fallback covers them.
        }

        return $meta;
    }

    private function guessMultiFromRouteName(string $routeName): ?string
    {
        if (!str_starts_with($routeName, 'mautic_api_')) {
            return null;
        }

        $remainder = substr($routeName, strlen('mautic_api_'));
        $first     = explode('_', $remainder)[0];

        return '' === $first ? null : $first;
    }

    /**
     * Last resort: StatsApiController becomes "stats", ThemeApiController "themes".
     */
    private function guessMultiFromClass(string $class): ?string
    {
        $position = strrpos($class, '\\');
        $short    = false === $position ? $class : substr($class, $position + 1);
        $short    = preg_replace('/ApiController$/', '', $short) ?? '';

        if ('' === $short) {
            return null;
        }

        $name = lcfirst($short);

        return str_ends_with($name, 's') ? $name : $name.'s';
    }

    private function singularize(string $plural): string
    {
        return match (true) {
            str_ends_with($plural, 'ies') => substr($plural, 0, -3).'y',
            str_ends_with($plural, 'ses') => substr($plural, 0, -2),
            str_ends_with($plural, 's')   => substr($plural, 0, -1),
            default                       => $plural,
        };
    }

    /**
     * @param array{one: ?string, multi: ?string, entity: ?string} $meta
     * @param list<string>                                         $knownSchemas
     *
     * @return array{entity: ?string, input: ?string}
     */
    private function resolveSchemaRefs(array $meta, array $knownSchemas): array
    {
        if (null === $meta['entity']) {
            return ['entity' => null, 'input' => null];
        }

        $position = strrpos($meta['entity'], '\\');
        $short    = false === $position ? $meta['entity'] : substr($meta['entity'], $position + 1);
        $name     = self::ENTITY_SCHEMA_MAP[$short] ?? $short;

        if (!in_array($name, $knownSchemas, true)) {
            return ['entity' => null, 'input' => null];
        }

        return [
            'entity' => '#/components/schemas/'.$name,
            'input'  => in_array($name.'Input', $knownSchemas, true)
                ? '#/components/schemas/'.$name.'Input'
                : null,
        ];
    }

    // -------------------------------------------------------------------------
    // Operations
    // -------------------------------------------------------------------------

    /**
     * @param array{name: string, class: string, action: string} $route
     * @param array{one: ?string, multi: ?string, entity: ?string} $meta
     * @param array{entity: ?string, input: ?string}               $refs
     *
     * @return array<string, mixed>
     */
    private function buildOperation(string $method, string $specPath, array $route, array $meta, array $refs): array
    {
        $tag   = $this->tagFor($meta['multi']);
        $one   = $meta['one'] ?? 'item';
        $multi = $meta['multi'] ?? 'items';

        $operation = [
            'tags'        => [$tag],
            'summary'     => $this->summaryFor($route['action'], $method, $one, $multi, $specPath),
            'operationId' => $this->operationIdFor($route['name'], $route['action'], $method, $one, $multi),
            'description' => sprintf('Route `%s`, handled by `%s::%s`.', $route['name'], $route['class'], $route['action']),
        ];

        $parameters = $this->pathParameters($specPath);

        if ('getEntitiesAction' === $route['action']) {
            $parameters = array_merge($parameters, $this->paginationParameters());
        }

        if ('deleteEntitiesAction' === $route['action']) {
            $parameters[] = $this->batchDeleteParameter();
        }

        if ([] !== $parameters) {
            $operation['parameters'] = $parameters;
        }

        $body = $this->requestBodyFor($route['action'], $method, $refs);

        if (null !== $body) {
            $operation['requestBody'] = $body;
        }

        $operation['responses'] = $this->responsesFor($route['action'], $method, $one, $multi, $refs, $specPath);

        return $operation;
    }

    private function tagFor(?string $multi): string
    {
        $tag = null === $multi
            ? 'Other'
            : (self::TAG_LABELS[$multi] ?? ucfirst($multi));

        $this->seenTags[$tag] = true;

        return $tag;
    }

    /**
     * @return list<array{name: string, description: string}>
     */
    private function buildTags(): array
    {
        $names = array_keys($this->seenTags);
        sort($names);

        return array_map(
            static fn (string $name): array => [
                'name'        => $name,
                'description' => self::TAG_DESCRIPTIONS[$name] ?? sprintf('%s endpoints', $name),
            ],
            $names,
        );
    }

    private function summaryFor(string $action, string $method, string $one, string $multi, string $specPath): string
    {
        return match ($action) {
            'getEntitiesAction'    => sprintf('List %s', $multi),
            'getEntityAction'      => sprintf('Get a single %s', $one),
            'newEntityAction'      => sprintf('Create a %s', $one),
            'editEntityAction'     => 'PUT' === $method
                ? sprintf('Replace a %s (creates it when the ID does not exist)', $one)
                : sprintf('Update a %s (only the fields sent)', $one),
            'deleteEntityAction'   => sprintf('Delete a %s', $one),
            'newEntitiesAction'    => sprintf('Create %s in batch', $multi),
            'editEntitiesAction'   => 'PUT' === $method
                ? sprintf('Replace %s in batch', $multi)
                : sprintf('Update %s in batch', $multi),
            'deleteEntitiesAction' => sprintf('Delete %s in batch', $multi),
            default                => $this->humanizeAction($action, $specPath),
        };
    }

    private function humanizeAction(string $action, string $specPath): string
    {
        $name  = preg_replace('/Action$/', '', $action) ?? $action;
        $words = preg_replace('/(?<!^)[A-Z]/', ' $0', $name) ?? $name;

        return ucfirst(strtolower($words)).sprintf(' (%s)', $specPath);
    }

    private function operationIdFor(string $routeName, string $action, string $method, string $one, string $multi): string
    {
        $candidate = match ($action) {
            'getEntitiesAction'    => 'list'.$this->studly($multi),
            'getEntityAction'      => 'get'.$this->studly($one),
            'newEntityAction'      => 'create'.$this->studly($one),
            'editEntityAction'     => ('PUT' === $method ? 'replace' : 'update').$this->studly($one),
            'deleteEntityAction'   => 'delete'.$this->studly($one),
            'newEntitiesAction'    => 'batchCreate'.$this->studly($multi),
            'editEntitiesAction'   => ('PUT' === $method ? 'batchReplace' : 'batchUpdate').$this->studly($multi),
            'deleteEntitiesAction' => 'batchDelete'.$this->studly($multi),
            default                => lcfirst($this->studly(str_replace('mautic_api_', '', $routeName))),
        };

        if (isset($this->usedOperationIds[$candidate])) {
            $candidate .= strtolower($method).$this->studly(str_replace('mautic_api_', '', $routeName));
        }

        $this->usedOperationIds[$candidate] = true;

        return $candidate;
    }

    private function studly(string $value): string
    {
        $value = str_replace(['-', '_'], ' ', $value);

        return str_replace(' ', '', ucwords($value));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function pathParameters(string $specPath): array
    {
        if (!preg_match_all('/\{([^}]+)\}/', $specPath, $matches)) {
            return [];
        }

        $parameters = [];

        foreach ($matches[1] as $name) {
            $parameters[] = [
                'name'        => $name,
                'in'          => 'path',
                'required'    => true,
                'description' => $this->describeParameter($name),
                'schema'      => $this->parameterSchema($name),
            ];
        }

        return $parameters;
    }

    /**
     * @return array<string, mixed>
     */
    private function parameterSchema(string $name): array
    {
        if ('object' === $name) {
            return ['type' => 'string', 'enum' => ['contact', 'company']];
        }

        if ('table' === $name) {
            return ['type' => 'string'];
        }

        $isNumeric = 'id' === strtolower($name) || str_ends_with($name, 'Id') || str_ends_with($name, 'ID');

        return $isNumeric
            ? ['type' => 'integer', 'minimum' => 1]
            : ['type' => 'string'];
    }

    private function describeParameter(string $name): string
    {
        return match ($name) {
            'id'        => 'The resource ID',
            'object'    => 'Field object type',
            'table'     => 'Statistics table name',
            'contactId' => 'Contact ID',
            'leadId'    => 'Contact ID',
            default     => sprintf('%s path parameter', $name),
        };
    }

    /**
     * Batch delete takes its IDs in the query string, not in a request body.
     * CommonApiController::deleteEntitiesAction() reads $request->query, so a
     * JSON body is silently ignored: the call answers 200 and deletes nothing.
     *
     * @return array<string, mixed>
     */
    private function batchDeleteParameter(): array
    {
        return [
            'name'        => 'ids',
            'in'          => 'query',
            'required'    => true,
            'description' => 'IDs of the records to delete, as a comma separated list (?ids=1,2,3) or as repeated values (?ids[]=1&ids[]=2). A request body is ignored.',
            'schema'      => ['type' => 'string'],
            'example'     => '1,2,3',
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function paginationParameters(): array
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

    /**
     * @param array{entity: ?string, input: ?string} $refs
     *
     * @return array<string, mixed>|null
     */
    private function requestBodyFor(string $action, string $method, array $refs): ?array
    {
        $single = ['newEntityAction', 'editEntityAction'];
        $batch  = ['newEntitiesAction', 'editEntitiesAction'];

        if (in_array($action, $single, true)) {
            return $this->jsonBody($refs['input'] ?? $refs['entity']);
        }

        if (in_array($action, $batch, true)) {
            return $this->jsonBody($refs['input'] ?? $refs['entity'], true);
        }

        // deleteEntitiesAction reads $request->query, never the body. It is given
        // an "ids" query parameter instead, see batchDeleteParameter().
        if ('deleteEntitiesAction' === $action) {
            return null;
        }

        if (in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
            return [
                'required' => false,
                'content'  => ['application/json' => ['schema' => ['type' => 'object', 'additionalProperties' => true]]],
            ];
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function jsonBody(?string $ref, bool $asList = false): array
    {
        $schema = null === $ref
            ? ['type' => 'object', 'additionalProperties' => true]
            : ['$ref' => $ref];

        if ($asList) {
            $schema = ['type' => 'array', 'items' => $schema];
        }

        return [
            'required' => true,
            'content'  => ['application/json' => ['schema' => $schema]],
        ];
    }

    /**
     * @param array{entity: ?string, input: ?string} $refs
     *
     * @return array<string, mixed>
     */
    private function responsesFor(string $action, string $method, string $one, string $multi, array $refs, string $specPath): array
    {
        $hasPathParams = str_contains($specPath, '{');

        $responses = match ($action) {
            'getEntitiesAction' => ['200' => $this->collectionResponse($multi, $refs['entity'])],
            'getEntityAction'   => ['200' => $this->itemResponse('Success', $one, $refs['entity'])],
            'newEntityAction'   => ['201' => $this->itemResponse('Created', $one, $refs['entity'])],
            // Only PUT creates the record when the ID does not exist yet.
            'editEntityAction'  => 'PUT' === $method
                ? [
                    '200' => $this->itemResponse('Updated', $one, $refs['entity']),
                    '201' => $this->itemResponse('Created, because no record with that ID existed', $one, $refs['entity']),
                ]
                : ['200' => $this->itemResponse('Updated', $one, $refs['entity'])],
            'deleteEntityAction'   => ['200' => $this->itemResponse('Deleted, returns the record as it was', $one, $refs['entity'])],
            'newEntitiesAction'    => ['201' => $this->batchResponse('Created', $multi, $refs['entity'])],
            'editEntitiesAction'   => ['200' => $this->batchResponse('Updated', $multi, $refs['entity'])],
            'deleteEntitiesAction' => ['200' => $this->batchResponse('Deleted', $multi, $refs['entity'])],
            default                => ['200' => ['description' => 'Success']],
        };

        $responses['401'] = ['$ref' => '#/components/responses/Unauthorized'];
        $responses['403'] = ['$ref' => '#/components/responses/Forbidden'];

        if (in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
            $responses['400'] = ['$ref' => '#/components/responses/BadRequest'];
            $responses['422'] = ['$ref' => '#/components/responses/UnprocessableEntity'];
        }

        if ($hasPathParams) {
            $responses['404'] = ['$ref' => '#/components/responses/NotFound'];
        }

        ksort($responses);

        return $responses;
    }

    /**
     * @return array<string, mixed>
     */
    private function collectionResponse(string $multi, ?string $ref): array
    {
        return [
            'description' => 'Success',
            'content'     => ['application/json' => ['schema' => [
                'type'       => 'object',
                'properties' => [
                    'total' => ['type' => 'string', 'description' => 'Total number of records matching the query'],
                    $multi  => [
                        'type'                 => 'object',
                        'description'          => 'Map of string ID to resource object',
                        'additionalProperties' => $this->itemSchema($ref),
                    ],
                ],
            ]]],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function itemResponse(string $description, string $one, ?string $ref): array
    {
        return [
            'description' => $description,
            'content'     => ['application/json' => ['schema' => [
                'type'       => 'object',
                'properties' => [$one => $this->itemSchema($ref)],
            ]]],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function batchResponse(string $description, string $multi, ?string $ref): array
    {
        return [
            'description' => $description,
            'content'     => ['application/json' => ['schema' => [
                'type'       => 'object',
                'properties' => [
                    $multi        => ['type' => 'array', 'items' => $this->itemSchema($ref)],
                    'statusCodes' => [
                        'type'        => 'array',
                        'description' => 'Per-record HTTP status code, in the order the records were sent',
                        'items'       => ['type' => 'integer'],
                    ],
                ],
            ]]],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function itemSchema(?string $ref): array
    {
        return null === $ref
            ? ['type' => 'object', 'additionalProperties' => true]
            : ['$ref' => $ref];
    }
}
