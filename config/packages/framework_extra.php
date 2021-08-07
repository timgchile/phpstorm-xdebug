<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->extension(
        'sensio_framework_extra',
        [
            'router' => [
                'annotations' => false,
            ],
            'request' => [
                'converters' => true,
                'auto_convert' => true,
            ],
            'view' => [
                'annotations' => false,
            ],
            'cache' => [
                'annotations' => false,
            ],
            'security' => [
                'annotations' => true,
            ],
        ]
    );
};
