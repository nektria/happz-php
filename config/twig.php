<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Twig\Environment;

return static function (ContainerConfigurator $container): void {
    $container->extension('twig', [
        'default_path' => '%kernel.project_dir%/templates',
        'strict_variables' => true,
    ]);

    // Also available to the core Controller through ContainerBoxTrait.
    $container->services()->alias(Environment::class, 'twig')->public();
};
