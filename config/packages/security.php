<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->extension(
        'security',
        [
            'enable_authenticator_manager' => true,
            'erase_credentials' => false,
            'firewalls' => [
                'hearthcheck' => [
                    'pattern' => '^/healthcheck$',
                    'security' => false,
                ],
            ],
            'access_control' => [
            ],
        ]
    );
};
