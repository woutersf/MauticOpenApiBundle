# Mautic OpenAPI Bundle

A Mautic plugin that generates and exposes an **OpenAPI 3.1.0 specification** for the Mautic REST API, with a built-in **Swagger UI** explorer.

## Features

- Serves the full **OpenAPI 3.1.0** spec at `/openapi` (JSON or YAML)
- Interactive **Swagger UI** at `/openapi/view` (loaded from CDN, zero install)
- Respects the Mautic API enabled/disabled setting
- Optional login gate: toggle between public and authenticated-only access
- Enable/disable via the standard Mautic Plugins screen

## Requirements

- Mautic 4.x or 5.x
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

The OpenAPI spec documents all standard Mautic REST API resources:


## Swagger UI

The `/openapi/view` page loads [Swagger UI v5](https://swagger.io/tools/swagger-ui/) from a CDN and points it at this instance's `/openapi` endpoint. Features enabled by default:

- Try-it-out (live API calls against this Mautic instance)
- Persistent authorization across page reloads
- Filter / search across all endpoints
- Deep linking to specific operations

## About the spec

The spec is generated at runtime so the `servers.url` always reflects the current Mautic instance URL. Format negotiation supports:

- Query param: `?format=yaml`
- Accept header: `application/yaml`, `text/yaml`, `application/x-yaml`

## License

[GNU GPL v3](https://www.gnu.org/licenses/gpl-3.0.html): same as Mautic.
