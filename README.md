# cytec/laminas-cache-predis
Redis adapter for Laminas framework with tagging support

Installation
---
```
composer require cytec/laminas-cache-predis
```

Configuration
---

Somewhere in your configuration (eg. config/autoload/global.php) add

```php
...
'caches' => [
    'AppCache' => [
        'adapter' => 'Cytec\Cache\Storage\Adapter\Predis',
        'options' => [
            'ttl' => 600,
            'predis_client_connections' => [
                'host' => '127.0.0.1',
                'port' => 6379,
            ],
            'predis_client_options' => [
                'profile' => '2.4',
                'prefix'  => 'ns:'
            ]
        ],
        'plugins' => [
            ['name' => 'serializer']
        ],
    ]
],
...
```

Since laminas-cache v3 storage adapters need to be registered as modules:

`modules.config.php`:

```php
<?php

return [
    ...{your other modules}...
    'Cytec\Cache\Storage\Adapter\Predis',
];
```

The `predis_client_connections` option is passed directly as the first argument when creating the Predis client and
`predis_client_options` as the second parameter:

```php
$client = new Predis\Client($predis_client_connections, $predis_client_options);
```

For more information check out Predis documentation on [Connection Parameters](https://github.com/nrk/predis/wiki/Connection-Parameters) and [Client Options](https://github.com/nrk/predis/wiki/Client-Options)

And then you can get the cache via the service manager:

```php
$cache = $this->getServiceManager()->get('AppCache');
$cache->setItem($key, $value);
```
