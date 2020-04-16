<?php


namespace App\Services;


use Symfony\Component\Cache\Adapter\RedisAdapter;

class RedisService
{

    /** @var \Redis $client */
    private $client;

    public function __construct($dsn)
    {
        $this->setClient(RedisAdapter::createConnection($dsn));
    }


    /**
     * @return \Redis
     */
    public function getClient(): \Redis
    {
        return $this->client;
    }

    /**
     * @param \Redis $client
     */
    public function setClient(\Redis $client): void
    {
        $this->client = $client;
    }



}