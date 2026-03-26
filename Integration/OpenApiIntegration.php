<?php

declare(strict_types=1);

namespace MauticPlugin\MauticOpenApiBundle\Integration;

use Mautic\PluginBundle\Integration\AbstractIntegration;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class OpenApiIntegration extends AbstractIntegration
{
    public function getName(): string
    {
        return 'OpenApi';
    }

    public function getDisplayName(): string
    {
        return 'OpenAPI Specification';
    }

    public function getDescription(): string
    {
        $specUrl = $this->router->generate('mautic_openapi_spec', [], UrlGeneratorInterface::ABSOLUTE_URL);
        $viewUrl = $this->router->generate('mautic_openapi_view', [], UrlGeneratorInterface::ABSOLUTE_URL);

        return '<div>Exposes the Mautic REST API as an OpenAPI 3.1.0 specification.</div>'
            . '<div>Spec (JSON/YAML): <a href="' . $specUrl . '" target="_blank">' . $specUrl . '</a></div>'
            . '<div>Swagger UI viewer: <a href="' . $viewUrl . '" target="_blank">' . $viewUrl . '</a></div>'
            . '<div>Only active when the Mautic API is enabled in Configuration → API Settings.</div>';
    }

    /**
     * Returns the web-accessible path to the plugin icon shown in /s/plugins.
     * The file lives at plugins/MauticOpenApiBundle/openapi.png which is
     * directly accessible from the Mautic web root.
     */
    public function getIcon(): string
    {
        return 'plugins/MauticOpenApiBundle/openapi.png';
    }

    public function getAuthenticationType(): string
    {
        return 'none';
    }

    public function getRequiredKeyFields(): array
    {
        return [];
    }
}
