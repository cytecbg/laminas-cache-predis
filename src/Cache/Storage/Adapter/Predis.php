<?php

namespace Cytec\Cache\Storage\Adapter;

use Zend\Cache\Storage\Adapter\AbstractAdapter;
use Zend\Cache\Storage\ClearByNamespaceInterface;
use Zend\Cache\Storage\ClearByPrefixInterface;
use Zend\Cache\Storage\FlushableInterface;
use Zend\Cache\Storage\TotalSpaceCapableInterface;
use Zend\Cache\Storage\TaggableInterface;
use Zend\Cache\Exception;

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
     * Create new Adapter for predis storage
     *
     * @param null|array|Traversable|PredisOptions $options
     * @see \Zend\Cache\Storage\Adapter\Abstract
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

        $value = $client->get($normalizedKey);

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

        $results = $client->mGet($normalizedKeys);

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

        return (bool)$client->exists($normalizedKey);
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
            $response = $client->setex($normalizedKey, $ttl, $value);
        }
        else
        {
            $response = $client->set($normalizedKey, $value);
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

        if($ttl)
        {
            $pipe = $client->pipeline();

            foreach($normalizedKeyValuePairs as $key => $value)
            {
                $pipe->setex($key, $ttl, $value);
            }

            $pipe->execute();
        }
        else
        {
            $client->mset($normalizedKeyValuePairs);
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

        return (bool)$client->expire($normalizedKey, $ttl);
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

        $pttl = $client->pttl($normalizedKey);

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

        return (bool)$client->del([$normalizedKey]);
    }

    /**
     * {@inheritdoc}
     */
    protected function internalRemoveItems(array & $normalizedKeys)
    {
        $client = $this->getPredisClient();

        foreach($normalizedKeys as $normalizedKey)
        {
            $tags = $this->getTags($normalizedKey);

            if($tags)
            {
                $this->setTags($normalizedKey, []);
            }
        }

        return $client->del($normalizedKeys);
    }

    /**
     * {@inheritdoc}
     */
    protected function internalIncrementItem(& $normalizedKey, & $value)
    {
        $client = $this->getPredisClient();

        return $client->incrby($normalizedKey, $value);
    }

    /**
     * {@inheritdoc}
     */
    protected function internalDecrementItem(& $normalizedKey, & $value)
    {
        $client = $this->getPredisClient();

        return $client->decrby($normalizedKey, $value);
    }

    /* ClearByNamespaceInterface */

    /**
     * {@inheritdoc}
     */
    public function clearByNamespace($namespace)
    {

    }

    /* ClearByPrefixInterface */

    /**
     * {@inheritdoc}
     */
    public function clearByPrefix($prefix)
    {

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
                $client->srem('tags:'.$tag, $key);
            }

            // remove the tags in the key tag set
            $client->del([$key.':tags']);
        }

        if($tags)
        {
            // add the tags to the key tag set
            $client->sadd($key.':tags', $tags);

            // add the key to each tags set
            foreach($tags as $tag)
            {
                $client->sadd('tags:'.$tag, $key);
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

        $tags = $client->smembers($key.':tags');

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
            $tag_keys = $client->smembers('tags:'.$tag);

            foreach($tag_keys as $key)
            {
                if(!isset($key, $keys)) $keys[$key] = [];
                $keys[$key][] = $tag;
            }
        }

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