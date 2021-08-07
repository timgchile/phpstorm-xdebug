<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->extension(
        'framework',
        [
            'secret' => '%env(APP_SECRET)%',
            'http_method_override' => false,
            'default_locale' => '%locale%',
            'trusted_proxies' => '%env(TRUSTED_PROXIES)%',
            'php_errors' => [
                'log' => true,
            ],
        ]
    );
};
