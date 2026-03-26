<?php

declare(strict_types=1);

namespace MauticPlugin\MauticOpenApiBundle\Form\Type;

use Mautic\CoreBundle\Form\Type\YesNoButtonGroupType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;

class ConfigType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add(
            'openapi_public',
            YesNoButtonGroupType::class,
            [
                'label'      => 'plugin.openapi.config.public_label',
                'label_attr' => ['class' => 'control-label'],
                'attr'       => [
                    'tooltip' => 'plugin.openapi.config.public_tooltip',
                ],
                'data'     => $options['data']['openapi_public'] ?? true,
                'required' => false,
            ]
        );
    }

    public function getBlockPrefix(): string
    {
        return 'openapibundleconfig';
    }
}
