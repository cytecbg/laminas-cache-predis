<?php

declare(strict_types=1);

namespace Cytec\Cache\Storage\Adapter\Predis;

use Cytec\Cache\Storage\Adapter\Predis;

use Interop\Container\ContainerInterface;
use Laminas\Cache\Storage\AdapterPluginManager;
use Laminas\ServiceManager\Factory\InvokableFactory;

use function assert;

final class AdapterPluginManagerDelegatorFactory
{
    public function __invoke(ContainerInterface $container, string $name, callable $callback): AdapterPluginManager
    {
        $pluginManager = $callback();
        assert($pluginManager instanceof AdapterPluginManager);

        $pluginManager->configure([
            'factories' => [
                Predis::class => InvokableFactory::class,
            ],
            'aliases'   => [
                'predis' => Predis::class,
                'Predis' => Predis::class,
            ],
        ]);

        return $pluginManager;
    }
}