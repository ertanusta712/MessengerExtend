<?php


namespace App\Services;


use App\Traits\QueueHelper;
use Psr\Container\ContainerInterface;
use Symfony\Component\Messenger\Transport\AmqpExt\AmqpTransport;
use Symfony\Component\Messenger\Transport\AmqpExt\Connection;

class ConsumerService
{
    use QueueHelper;

    /** @var Connection $connection */
    private $connection;

    /** @var ContainerInterface $container */
    private $container;

    /** @var QueueService $queueService */
    private $queueService;

    public function __construct($dsn, RedisService $redisService, QueueService $queueService, ContainerInterface $container)
    {
        $this->setQueueService($queueService);
        $this->setRedisService($redisService);
        $this->setContainer($container);
        $this->parseDsn($dsn);
        $this->parseTransports();
        $this->parseRoutings();
    }


    /**
     * @param $transport
     * @param null $queue
     * @return array
     */
    public function createAmqpTransport($transport,$queue = null): array
    {
        $this->createConnection($transport,$queue);
        return [$transport => new AmqpTransport($this->getConnection(), null)];
    }

    public function createConnection($transport, $queue = null)
    {
        $connectionOptions = [
            'delay' => [
                'exchange_name' => 'delays',
                'queue_name_pattern' => 'delay_%exchange_name%_%routing_key%_%delay%'
            ],
            'host' => $this->getHost(),
            'port' => $this->getPort(),
            'vhost' => '/',
            'login' => $this->getUser(),
            'password' => $this->getPassword()
        ];
        $exchangeOptions = [
            'name' => $this->getTransports()[$transport]['options']['exchange']['name'],
            'type' => $this->getTransports()[$transport]['options']['exchange']['type']
        ];
        $queuesOptions = $queue === null ? $this->getTransports()[$transport]['options']['queues'] : [$queue => $this->getTransports()[$transport]['options']['queues'][$queue]];
        $this->setConnection(new Connection($connectionOptions, $exchangeOptions, $queuesOptions));

        return $this;
    }

    /**
     * @return Connection
     */
    public function getConnection(): Connection
    {
        return $this->connection;
    }

    /**
     * @param Connection $connection
     */
    public function setConnection(Connection $connection): void
    {
        $this->connection = $connection;
    }

    /**
     * @return ContainerInterface
     */
    public function getContainer(): ContainerInterface
    {
        return $this->container;
    }

    /**
     * @param ContainerInterface $container
     */
    public function setContainer(ContainerInterface $container): void
    {
        $this->container = $container;
    }

    /**
     * @return QueueService
     */
    public function getQueueService(): QueueService
    {
        return $this->queueService;
    }

    /**
     * @param QueueService $queueService
     */
    public function setQueueService(QueueService $queueService): void
    {
        $this->queueService = $queueService;
    }



}