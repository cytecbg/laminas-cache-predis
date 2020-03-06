<?php

namespace Cytec\Cache\Storage\Adapter;

use Laminas\Cache\Storage\Adapter\AbstractAdapter;
use Laminas\Cache\Storage\ClearByNamespaceInterface;
use Laminas\Cache\Storage\ClearByPrefixInterface;
use Laminas\Cache\Storage\FlushableInterface;
use Laminas\Cache\Storage\TotalSpaceCapableInterface;
use Laminas\Cache\Storage\TaggableInterface;
use Laminas\Cache\Exception;

use Predis\Client as PredisClient;

class Predis extends AbstractAdapter implements
    ClearByNamespaceInterface,
    ClearByPrefixInterface,
    FlushableInterface,
    TotalSpaceCapableInterface,
    TaggableInterface
{

    /**
     * Has this instance be initialized
     *
     * @var bool
     */
    protected $initialized = false;

    /**
     * @var PredisClient
     */
    protected $predis_client = null;

    /**
     * The namespace prefix
     *
     * @var string
     */
    protected $namespacePrefix = '';

    /**
     * Create new Adapter for predis storage
     *
     * @param null|array|Traversable|PredisOptions $options
     * @see \Laminas\Cache\Storage\Adapter\Abstract
     */
    public function __construct($options = null)
    {
        parent::__construct($options);
    }

    protected function getPredisClient()
    {
        if(!$this->initialized)
        {
            $options = $this->getOptions();

            // get resource manager and resource id
            $this->predis_client = new PredisClient($options->getPredisClientConnections(), $options->getPredisClientOptions());

            // update initialized flag
            $this->initialized = true;

            // init namespace prefix
            $namespace = $options->getNamespace();

            if($namespace !== '')
            {
                $this->namespacePrefix = $namespace . $options->getNamespaceSeparator();
            }
            else
            {
                $this->namespacePrefix = '';
            }
        }

        return $this->predis_client;
    }

    /**
     * Set options.
     *
     * @param  array|Traversable|PredisOptions $options
     * @return Predis
     * @see    getOptions()
     */
    public function setOptions($options)
    {
        if(!$options instanceof PredisOptions)
        {
            $options = new PredisOptions($options);
        }

        return parent::setOptions($options);
    }

    /**
     * Get options.
     *
     * @return PredisOptions
     * @see setOptions()
     */
    public function getOptions()
    {
        if(!$this->options)
        {
            $this->setOptions(new PredisOptions());
        }

        return $this->options;
    }

    /**
     * {@inheritdoc}
     */
    protected function internalGetItem(& $normalizedKey, & $success = null, & $casToken = null)
    {
        $client = $this->getPredisClient();

        $value = $client->get($this->namespacePrefix . $normalizedKey);

        if($value === NULL)
        {
            $success = false;
            return;
        }

        $success = true;
        $casToken = $value;

        return $value;
    }

    /**
     * {@inheritdoc}
     */
    protected function internalGetItems(array & $normalizedKeys)
    {
        $client = $this->getPredisClient();

        $namespacedKeys = [];

        foreach($normalizedKeys as $normalizedKey)
        {
            $namespacedKeys[] = $this->namespacePrefix . $normalizedKey;
        }

        $results = $client->mGet($namespacedKeys);

        //combine the key => value pairs and remove all missing values
        return array_filter(
            array_combine($normalizedKeys, $results),
            function ($value) {
                return $value !== false;
            }
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function internalHasItem(& $normalizedKey)
    {
        $client = $this->getPredisClient();

        return (bool)$client->exists($this->namespacePrefix . $normalizedKey);
    }

    /**
     * {@inheritdoc}
     */
    protected function internalSetItem(& $normalizedKey, & $value)
    {
        $client  = $this->getPredisClient();
        $options = $this->getOptions();
        $ttl     = $options->getTtl();

        if($ttl)
        {
            $response = $client->setex($this->namespacePrefix . $normalizedKey, $ttl, $value);
        }
        else
        {
            $response = $client->set($this->namespacePrefix . $normalizedKey, $value);
        }


        return $response == 'OK';
    }

    /**
     * {@inheritdoc}
     */
    protected function internalSetItems(array & $normalizedKeyValuePairs)
    {
        $client  = $this->getPredisClient();
        $options = $this->getOptions();
        $ttl     = $options->getTtl();

        $namespacedKeyValuePairs = [];

        foreach($normalizedKeyValuePairs as $normalizedKey => $value)
        {
            $namespacedKeyValuePairs[$this->namespacePrefix . $normalizedKey] = $value;
        }

        if($ttl)
        {
            $pipe = $client->pipeline();

            foreach($namespacedKeyValuePairs as $key => $value)
            {
                $pipe->setex($key, $ttl, $value);
            }

            $pipe->execute();
        }
        else
        {
            $client->mset($namespacedKeyValuePairs);
        }

        return [];
    }

    /**
     * {@inheritdoc}
     */
    protected function internalTouchItem(& $normalizedKey)
    {
        $client = $this->getPredisClient();
        $ttl    = $this->getOptions()->getTtl();

        return (bool)$client->expire($this->namespacePrefix . $normalizedKey, $ttl);
    }

    /**
     * {@inheritDoc}
     *
     * @throws Exception\ExceptionInterface
     */
    protected function internalGetMetadata(& $normalizedKey)
    {
        $client = $this->getPredisClient();
        $metadata = [];

        $pttl = $client->pttl($this->namespacePrefix . $normalizedKey);

        if($pttl <= -2)
        {
            return false;
        }

        $metadata['ttl'] = ($pttl == -1) ? null : $pttl / 1000;

        return $metadata;
    }

    /**
     * {@inheritdoc}
     */
    protected function internalRemoveItem(& $normalizedKey)
    {
        $client = $this->getPredisClient();

        $tags = $this->getTags($normalizedKey);

        if($tags)
        {
            $this->setTags($normalizedKey, []);
        }

        return (bool)$client->del([$this->namespacePrefix . $normalizedKey]);
    }

    /**
     * {@inheritdoc}
     */
    protected function internalRemoveItems(array & $normalizedKeys)
    {
        $client = $this->getPredisClient();

        $namespacedKeys = [];

        foreach($normalizedKeys as $normalizedKey)
        {
            $namespacedKeys[] = $this->namespacePrefix . $normalizedKey;

            $tags = $this->getTags($normalizedKey);

            if($tags)
            {
                $this->setTags($normalizedKey, []);
            }
        }

        return $client->del($namespacedKeys);
    }

    /**
     * {@inheritdoc}
     */
    protected function internalIncrementItem(& $normalizedKey, & $value)
    {
        $client = $this->getPredisClient();

        return $client->incrby($this->namespacePrefix . $normalizedKey, $value);
    }

    /**
     * {@inheritdoc}
     */
    protected function internalDecrementItem(& $normalizedKey, & $value)
    {
        $client = $this->getPredisClient();

        return $client->decrby($this->namespacePrefix . $normalizedKey, $value);
    }

    /* ClearByNamespaceInterface */

    /**
     * {@inheritdoc}
     */
    public function clearByNamespace($namespace)
    {
        $client = $this->getPredisClient();

        $namespace = (string)$namespace;

        if($namespace === '')
        {
            throw new Exception\InvalidArgumentException('No namespace given');
        }

        $options = $this->getOptions();
        $prefix  = $namespace . $options->getNamespaceSeparator();

        $client_options = $options->getPredisClientOptions();
        $client_prefix = $client_options['prefix'];

        $keys = $client->keys($prefix . '*');

        foreach($keys as $index=>$key)
        {
            $keys[$index] = str_replace($client_prefix, "", $key);
        }

        if(count($keys) > 0)
        {
            $client->del($keys);
        }

        return true;
     }

    /* ClearByPrefixInterface */

    /**
     * {@inheritdoc}
     */
    public function clearByPrefix($prefix)
    {
        $client = $this->getPredisClient();

        $prefix = (string)$prefix;

        if($prefix === '')
        {
            throw new Exception\InvalidArgumentException('No prefix given');
        }

        $options   = $this->getOptions();
        $namespace = $options->getNamespace();
        $prefix    = ($namespace === '') ? '' : $namespace . $options->getNamespaceSeparator() . $prefix;

        $client->del($client->keys($prefix.'*'));

        return true;
    }

    /* FlushableInterface */

    /**
     * {@inheritdoc}
     */
    public function flush()
    {
        $client = $this->getPredisClient();

        $client->flushdb();
    }

    /* TotalSpaceCapableInterface */

    /**
     * {@inheritdoc}
     */
    public function getTotalSpace()
    {
        $client = $this->getPredisClient();

        $info = $client->info();

        return (int)$info['Memory']['total_system_memory'];
    }

    /* TaggableInterface */

    /**
     * {@inheritdoc}
     */
    public function setTags($key, array $tags)
    {
        $client = $this->getPredisClient();

        if(!$this->hasItem($key))
        {
            return false;
        }

        // get the tags the key has
        $key_tags = $this->getTags($key);

        if($key_tags)
        {
            // remove the key from each tags set
            foreach($key_tags as $tag)
            {
                $client->srem($this->namespacePrefix . 'tags:' . $tag, $key);
            }

            // remove the tags in the key tag set
            $client->del([$this->namespacePrefix . $key . ':tags']);
        }

        if($tags)
        {
            // add the tags to the key tag set
            $client->sadd($this->namespacePrefix . $key . ':tags', $tags);

            // add the key to each tags set
            foreach($tags as $tag)
            {
                $client->sadd($this->namespacePrefix . 'tags:' . $tag, $key);
            }
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function getTags($key)
    {
        $client = $this->getPredisClient();

        $tags = $client->smembers($this->namespacePrefix . $key . ':tags');

        return $tags ? $tags : false;
    }

    /**
     * {@inheritdoc}
     */
    public function clearByTags(array $tags, $disjunction = false)
    {
        $client = $this->getPredisClient();

        $keys = [];

        foreach($tags as $tag)
        {
            $tag_keys = $client->smembers($this->namespacePrefix . 'tags:' . $tag);

            foreach($tag_keys as $key)
            {
                if(!isset($key, $keys)) $keys[$key] = [];
                $keys[$key][] = $tag;
            }
        }

        if(!$keys) return;

        if($disjunction) // delete all keys regardless of tag composition
        {
            $this->removeItems(array_keys($keys));
        }
        else // delete only keys that contain all of the provided tags
        {
            $keys_to_remove = [];

            foreach($keys as $key=>$key_tags)
            {
                $contains_all = true;

                foreach($tags as $tag)
                {
                    if(!in_array($tag, $key_tags))
                    {
                        $contains_all = false;
                        break;
                    }
                }

                if($contains_all)
                {
                    $keys_to_remove[] = $key;

                }
            }

            $this->removeItems($keys_to_remove);
        }
    }
}
