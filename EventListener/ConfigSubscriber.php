<?php

declare(strict_types=1);

namespace MauticPlugin\MauticOpenApiBundle\EventListener;

use Mautic\ConfigBundle\ConfigEvents;
use Mautic\ConfigBundle\Event\ConfigBuilderEvent;
use MauticPlugin\MauticOpenApiBundle\Form\Type\ConfigType;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ConfigSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            ConfigEvents::CONFIG_ON_GENERATE => ['onConfigGenerate', 0],
        ];
    }

    public function onConfigGenerate(ConfigBuilderEvent $event): void
    {
        $event->addForm([
            'bundle'     => 'MauticOpenApiBundle',
            'formType'   => ConfigType::class,
            'formAlias'  => 'openapibundleconfig',
            'formTheme'  => '@MauticOpenApi/FormTheme/Config/_config_openapibundle_widget.html.twig',
            'parameters' => $event->getParametersFromConfig('MauticOpenApiBundle'),
        ]);
    }
}
