<?php

declare(strict_types=1);

namespace Cytec\Cache\Storage\Adapter\Predis;

use function assert;
use function is_array;

final class Module
{
    public function getConfig(): array
    {
        $config       = (new ConfigProvider())();
        $dependencies = $config['dependencies'] ?? [];
        assert(is_array($dependencies));
        $config['service_manager'] = $dependencies;
        unset($config['dependencies']);

        return $config;
    }
}