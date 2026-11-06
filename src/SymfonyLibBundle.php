<?php

declare(strict_types=1);

namespace UniMethod\SymfonyLib;

use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use UniMethod\SymfonyLib\DependencyInjection\Compiler\RegisterApisPass;

class SymfonyLibBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        // Earlier than the FrameworkBundle passes of the same type (RoutingResolverPass
        // collects routing.loader): the route loader is registered here
        //
        // Раньше пассов FrameworkBundle с тем же типом (RoutingResolverPass
        // собирает routing.loader): загрузчик роутов регистрируется здесь
        $container->addCompilerPass(
            new RegisterApisPass(),
            PassConfig::TYPE_BEFORE_OPTIMIZATION,
            RegisterApisPass::PRIORITY,
        );
    }
}
