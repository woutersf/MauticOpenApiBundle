<?php

declare(strict_types=1);

namespace MauticPlugin\MauticOpenApiBundle\Controller;

use Mautic\CoreBundle\Controller\CommonController;
use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Mautic\PluginBundle\Helper\IntegrationHelper;
use MauticPlugin\MauticOpenApiBundle\Service\OpenApiSpecService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Yaml\Yaml;

class OpenApiController extends CommonController
{
    public static function getSubscribedServices(): array
    {
        return array_merge(parent::getSubscribedServices(), [
            'mautic.helper.core_parameters' => CoreParametersHelper::class,
            'mautic.openapi.service.spec'   => OpenApiSpecService::class,
            'mautic.helper.integration'     => IntegrationHelper::class,
        ]);
    }

    /**
     * Returns true when both the OpenAPI integration is enabled (Settings → Plugins)
     * and the Mautic REST API is enabled (Configuration → API Settings).
     */
    private function isAvailable(): bool
    {
        /** @var IntegrationHelper $integrationHelper */
        $integrationHelper = $this->container->get('mautic.helper.integration');
        $integration       = $integrationHelper->getIntegrationObject('OpenApi');

        if (!$integration) {
            return false;
        }

        $settings = $integration->getIntegrationSettings();
        if (!$settings || !$settings->isPublished()) {
            return false;
        }

        /** @var CoreParametersHelper $coreParams */
        $coreParams = $this->container->get('mautic.helper.core_parameters');

        return (bool) $coreParams->get('api_enabled', false);
    }

    private function unavailableJsonResponse(): JsonResponse
    {
        /** @var IntegrationHelper $integrationHelper */
        $integrationHelper = $this->container->get('mautic.helper.integration');
        $integration       = $integrationHelper->getIntegrationObject('OpenApi');

        $pluginEnabled = $integration && $integration->getIntegrationSettings() && $integration->getIntegrationSettings()->isPublished();

        if (!$pluginEnabled) {
            return new JsonResponse(
                [
                    'error'   => 'service_unavailable',
                    'message' => 'The OpenAPI plugin is disabled. Enable it under Settings → Plugins → OpenAPI Specification.',
                ],
                Response::HTTP_SERVICE_UNAVAILABLE,
                ['Access-Control-Allow-Origin' => '*']
            );
        }

        return new JsonResponse(
            [
                'error'   => 'service_unavailable',
                'message' => 'The Mautic API is currently disabled. Enable it under Configuration → API Settings.',
            ],
            Response::HTTP_SERVICE_UNAVAILABLE,
            ['Access-Control-Allow-Origin' => '*']
        );
    }

    private function unavailableHtmlResponse(): Response
    {
        /** @var IntegrationHelper $integrationHelper */
        $integrationHelper = $this->container->get('mautic.helper.integration');
        $integration       = $integrationHelper->getIntegrationObject('OpenApi');

        $pluginEnabled = $integration && $integration->getIntegrationSettings() && $integration->getIntegrationSettings()->isPublished();

        $reason = $pluginEnabled
            ? 'The Mautic REST API is currently disabled. Enable it under <strong>Configuration → API Settings</strong>.'
            : 'The OpenAPI plugin is currently disabled. Enable it under <strong>Settings → Plugins → OpenAPI Specification</strong>.';

        return new Response(
            $this->renderDisabledPage($reason),
            Response::HTTP_SERVICE_UNAVAILABLE,
            ['Content-Type' => 'text/html; charset=utf-8']
        );
    }

    /**
     * GET /openapi
     * GET /openapi?format=yaml
     *
     * Returns the OpenAPI 3.1.0 specification as JSON (default) or YAML.
     * Returns 503 if the plugin or Mautic API is disabled.
     */
    public function specAction(Request $request): Response
    {
        if (!$this->isAvailable()) {
            return $this->unavailableJsonResponse();
        }

        /** @var OpenApiSpecService $specService */
        $specService = $this->container->get('mautic.openapi.service.spec');
        $spec        = $specService->getSpec();

        $format = strtolower((string) $request->query->get('format', 'json'));
        $accept = $request->headers->get('Accept', '');

        $wantsYaml = $format === 'yaml'
            || str_contains($accept, 'application/yaml')
            || str_contains($accept, 'text/yaml')
            || str_contains($accept, 'text/x-yaml')
            || str_contains($accept, 'application/x-yaml');

        if ($wantsYaml) {
            $yaml = Yaml::dump($spec, 20, 2, Yaml::DUMP_NULL_AS_TILDE | Yaml::DUMP_EMPTY_ARRAY_AS_SEQUENCE);

            return new Response($yaml, Response::HTTP_OK, [
                'Content-Type'                => 'application/yaml; charset=utf-8',
                'Access-Control-Allow-Origin' => '*',
                'Cache-Control'               => 'public, max-age=300',
            ]);
        }

        return new JsonResponse($spec, Response::HTTP_OK, [
            'Access-Control-Allow-Origin' => '*',
            'Cache-Control'               => 'public, max-age=300',
        ]);
    }

    /**
     * GET /openapi/view
     *
     * Renders a Swagger UI page pre-loaded with this site's OpenAPI spec.
     * Returns 503 if the plugin or Mautic API is disabled.
     */
    public function viewAction(Request $request): Response
    {
        if (!$this->isAvailable()) {
            return $this->unavailableHtmlResponse();
        }

        $specUrl = $request->getSchemeAndHttpHost()
            . $request->getBasePath()
            . '/openapi';

        return new Response(
            $this->renderSwaggerUi($specUrl),
            Response::HTTP_OK,
            ['Content-Type' => 'text/html; charset=utf-8']
        );
    }

    private function renderSwaggerUi(string $specUrl): string
    {
        $escapedUrl = htmlspecialchars($specUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Mautic REST API — OpenAPI Docs</title>
  <meta name="robots" content="noindex, nofollow" />
  <link rel="stylesheet" type="text/css" href="https://unpkg.com/swagger-ui-dist@5/swagger-ui.css" />
  <style>
    * { box-sizing: border-box; }
    body { margin: 0; padding: 0; background: #fafafa; }
    .swagger-ui .topbar { background-color: #4e5e9e; }
    .swagger-ui .topbar .download-url-wrapper { display: none; }
    .swagger-ui .topbar-wrapper .link { pointer-events: none; }
    .swagger-ui .topbar-wrapper img { content: none; }
    #api-header {
      background: #4e5e9e;
      color: #fff;
      padding: 12px 20px;
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
      font-size: 18px;
      font-weight: 600;
      display: flex;
      align-items: center;
      gap: 10px;
    }
    #api-header small {
      font-size: 12px;
      opacity: .7;
      font-weight: 400;
      margin-left: auto;
    }
    #api-header a {
      color: rgba(255,255,255,.8);
      text-decoration: none;
      font-size: 12px;
    }
    #api-header a:hover { color: #fff; text-decoration: underline; }
  </style>
</head>
<body>
  <div id="api-header">
    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/>
    </svg>
    Mautic REST API
    <small>
      <a href="{$escapedUrl}" target="_blank" rel="noopener">JSON</a>
      &nbsp;·&nbsp;
      <a href="{$escapedUrl}?format=yaml" target="_blank" rel="noopener">YAML</a>
    </small>
  </div>
  <div id="swagger-ui"></div>
  <script src="https://unpkg.com/swagger-ui-dist@5/swagger-ui-bundle.js"></script>
  <script src="https://unpkg.com/swagger-ui-dist@5/swagger-ui-standalone-preset.js"></script>
  <script>
    window.onload = function () {
      SwaggerUIBundle({
        url: "{$escapedUrl}",
        dom_id: "#swagger-ui",
        presets: [
          SwaggerUIBundle.presets.apis,
          SwaggerUIStandalonePreset
        ],
        plugins: [
          SwaggerUIBundle.plugins.DownloadUrl
        ],
        layout: "StandaloneLayout",
        deepLinking: true,
        persistAuthorization: true,
        displayOperationId: false,
        defaultModelsExpandDepth: 1,
        defaultModelExpandDepth: 2,
        docExpansion: "list",
        filter: true,
        showExtensions: false,
        showCommonExtensions: true,
        syntaxHighlight: { activated: true, theme: "agate" },
        tryItOutEnabled: true
      });
    };
  </script>
</body>
</html>
HTML;
    }

    private function renderDisabledPage(string $reason): string
    {
        $safeReason = $reason; // Already trusted HTML from internal code

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>OpenAPI Unavailable — Mautic</title>
  <style>
    * { box-sizing: border-box; }
    body {
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
      display: flex; align-items: center; justify-content: center;
      min-height: 100vh; margin: 0; background: #f0f2f5;
    }
    .card {
      background: #fff; border-radius: 10px; padding: 2.5rem 3rem;
      box-shadow: 0 4px 20px rgba(0,0,0,.1); text-align: center; max-width: 460px;
    }
    .icon { font-size: 3rem; margin-bottom: 1rem; }
    h1 { color: #c0392b; margin: 0 0 .5rem; font-size: 1.5rem; }
    p { color: #555; margin: .5rem 0; line-height: 1.6; }
  </style>
</head>
<body>
  <div class="card">
    <div class="icon">🔒</div>
    <h1>OpenAPI Unavailable</h1>
    <p>{$safeReason}</p>
  </div>
</body>
</html>
HTML;
    }
}
