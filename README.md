# Mautic OpenAPI Bundle

A Mautic plugin that generates and exposes an **OpenAPI 3.1.0 specification** for the Mautic REST API, with a built-in **Swagger UI** explorer.

> **This is the `7.x` branch, for Mautic 7.** Paths and tags are generated from
> the instance's own routing table. For Mautic 4 and 5, use the `main` branch.

## Features

- Serves the full **OpenAPI 3.1.0** spec at `/openapi` (JSON or YAML)
- Interactive **Swagger UI** at `/openapi/view` (loaded from CDN, zero install)
- Respects the Mautic API enabled/disabled setting
- Optional login gate: toggle between public and authenticated-only access
- Enable/disable via the standard Mautic Plugins screen

## Requirements

- Mautic 7.x (verified against 7.2.2). For Mautic 4.x and 5.x, use the `main` branch
- PHP 8.1+
- Mautic REST API enabled (**Configuration → API Settings**)

## Installation

Copy `MauticOpenApiBundle/` into your Mautic `plugins/` directory, then clear the cache:

```bash
php bin/console cache:clear
```

Go to **Settings → Plugins**, find **OpenAPI Specification** and enable it.

## Endpoints

| URL | Response | Description |
|---|---|---|
| `/openapi` | `application/json` | OpenAPI 3.1.0 spec |
| `/openapi?format=yaml` | `application/yaml` | Same spec in YAML |
| `/openapi/view` | `text/html` | Swagger UI explorer |

The spec and viewer honour the same availability gate: both return `503` if the plugin or the Mautic API is disabled.

## Configuration

Under **Configuration → OpenAPI Settings**:

| Setting | Default | Description |
|---|---|---|
| Publicly available | Yes | When **No**, `/openapi` returns `401 Unauthorized` and `/openapi/view` redirects to the Mautic login page |

## API coverage

Paths, HTTP methods, path parameters and tags are generated from this
instance's Symfony routing table every time the spec is requested. The document
therefore describes exactly the endpoints that are reachable here, including
endpoints contributed by plugins, and it cannot drift when Mautic is upgraded or
a plugin is enabled.

Response and request schemas are curated in `Service/OpenApiSpecService.php` and
linked to each route through the API controller's `$entityClass`. Routes whose
entity has no curated schema are documented with a generic object body, so they
appear in the spec instead of being left out.

### What this fixes

Mautic's legacy API does not use REST verbs on the collection URL. Write
operations have their own path suffix:

| Operation | Path | Method |
|---|---|---|
| Create | `/api/contacts/new` | POST |
| Replace | `/api/contacts/{id}/edit` | PUT |
| Update | `/api/contacts/{id}/edit` | PATCH |
| Delete | `/api/contacts/{id}/delete` | DELETE |
| Batch create | `/api/contacts/batch/new` | POST |
| Batch update | `/api/contacts/batch/edit` | PUT or PATCH |
| Batch delete | `/api/contacts/batch/delete` | DELETE |

`POST /api/contacts` does not exist. Earlier versions of this plugin described
the API as if it did.

### Excluded

Routes under `/api/v2` are served by API Platform, which publishes its own
OpenAPI document at `/api/v2/docs.json`. They are skipped here to avoid
describing the same endpoints twice, and the spec description points readers to
that document.

## Verifying the spec

Compare the generated document against the router:

```bash
ddev exec php bin/console debug:router --format=json > /tmp/routes.json
curl -s https://your-mautic/openapi > /tmp/spec.json
```

Every `(path, method)` pair under `/api` that is not under `/api/v2` should
appear exactly once in `paths`, and no operation should describe a route that
the router does not have.


## Swagger UI

The `/openapi/view` page loads [Swagger UI v5](https://swagger.io/tools/swagger-ui/) from a CDN and points it at this instance's `/openapi` endpoint. Features enabled by default:

- Try-it-out (live API calls against this Mautic instance)
- Persistent authorization across page reloads
- Filter / search across all endpoints
- Deep linking to specific operations

## About the spec

The spec is generated at runtime, so `servers.url` reflects the current Mautic
instance URL, `info.version` reports the running Mautic version, and the paths
match the routes this instance serves. Format negotiation supports:

- Query param: `?format=yaml`
- Accept header: `application/yaml`, `text/yaml`, `application/x-yaml`

## License

[GNU GPL v3](https://www.gnu.org/licenses/gpl-3.0.html): same as Mautic.

---

Built by [Dropsolid](https://dropsolid.ai)
