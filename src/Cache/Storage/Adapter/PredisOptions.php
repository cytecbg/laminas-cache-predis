<?php
namespace Cytec\Cache\Storage\Adapter;

use Zend\Cache\Storage\Adapter\AdapterOptions;
use Zend\Cache\Exception;

class PredisOptions extends AdapterOptions
{
    /**
     * Options that are passed to the Predis client class
     *
     * $client = new Predis\Client($connecton, $options);
     *
     * @var array
     */
    protected $predis_client_options = [];

    /**
     * Connection parameters that are passed to the Predis client class
     *
     * $client = new Predis\Client($connecton, $options);
     *
     * @var mixed
     */
    protected $predis_client_connections = [];

    /**
     * The namespace separator
     * @var string
     */
    protected $namespaceSeparator = ':';

    /**
     * Set predis client options
     *
     * @param array $predisOptions
     * @link https://github.com/nrk/predis/wiki/Client-Options
     */
    public function setPredisClientOptions(array $options)
    {
        $this->triggerOptionEvent('predis_client_option', $options);
        $this->predis_client_options = $options;
    }

    /**
     * Get predis client options
     *
     * @return array
     * @link https://github.com/nrk/predis/wiki/Client-Options
     */
    public function getPredisClientOptions()
    {
        return $this->predis_client_options;
    }

    /**
     * Set predis client connections
     *
     * @param mixed $predisOptions
     * @link https://github.com/nrk/predis/wiki/Connection-Parameters
     */
    public function setPredisClientConnections($connections)
    {
        $this->triggerOptionEvent('predis_client_connection', $connections);
        $this->predis_client_connections = $connections;
    }

    /**
     * Get predis client options
     *
     * @return array
     * @link https://github.com/nrk/predis/wiki/Connection-Parameters
     */
    public function getPredisClientConnections()
    {
        return $this->predis_client_connections;
    }

    /**
     * Set namespace separator
     *
     * @param  string $namespaceSeparator
     * @return RedisOptions Provides a fluent interface
     */
    public function setNamespaceSeparator($namespaceSeparator)
    {
        $namespaceSeparator = (string)$namespaceSeparator;

        if($this->namespaceSeparator !== $namespaceSeparator)
        {
            $this->triggerOptionEvent('namespace_separator', $namespaceSeparator);
            $this->namespaceSeparator = $namespaceSeparator;
        }

        return $this;
    }

    /**
     * Get namespace separator
     *
     * @return string
     */
    public function getNamespaceSeparator()
    {
        return $this->namespaceSeparator;
    }
}