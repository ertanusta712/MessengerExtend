<?php


namespace App\Services;


use App\Traits\QueueHelper;
use http\Exception\RuntimeException;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Messenger\Transport\AmqpExt\Connection;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

class QueueService
{
    use QueueHelper;


    /** @var ContainerInterface $container */
    private $container;

    /** @var string $apiDsn */
    private $apiDsn;


    public function __construct($dsn, $apiDsn, RedisService $redisService, ContainerInterface $container)
    {
        $this->setRedisService($redisService);
        $this->setContainer($container);
        $this->setDsn($dsn);
        $this->setApiDsn($apiDsn);
        $this->parseDsn($dsn);
        $this->init();

    }

    /**
     * @param $queueName
     * @return array|null
     * @throws ClientExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    public function getQueueStats($queueName): ?array
    {
        try {
            $client = HttpClient::create()->request('GET', $this->getApiDsn() . '/' . $queueName, [
                'auth_basic' => [$this->getUser(), $this->getPassword()]
            ]);
            $queueStatus = json_decode($client->getContent(), true);
            return [
                'messageCount' => $queueStatus['messages'],
                'messagePerConsume' => $queueStatus['messages_unacknowledged'],
                'status' => $queueStatus['idle_since'] ?? 'running',
                'queueName' => $queueStatus['name']
            ];
        } catch (\Exception $exception) {
            throw new \RuntimeException($exception->getMessage());
        }
    }

    public function getAllQueueStats(): ?array
    {
        try {
            $client = HttpClient::create()->request('GET', $this->getApiDsn(), [
                'auth_basic' => [$this->getUser(), $this->getPassword()]
            ]);
            $queuesStatus = json_decode($client->getContent(), true);
            $status = [];
            foreach ($queuesStatus as $value) {
                $status[] = [
                    'messageCount' => $value['messages'],
                    'messagePerConsume' => $value['messages_unacknowledged'],
                    'status' => $value['idle_since'] ?? 'running',
                    'queueName' => $value['name']
                ];
            }
            return $status;
        } catch (\Exception $exception) {
            throw new \RuntimeException($exception->getMessage());
        }
    }

    /**
     * @param $transportName
     * @param $queueName
     * @return bool
     * @throws \Exception
     */
    public function createNewQueue($transportName, $queueName): bool
    {
        $transports = $this->getTransports();
        if (!array_key_exists($queueName, $transports[$transportName]['options']['queues'])) {
            $transports[$transportName]['options']['queues'][$queueName] = ['binding_keys' => [$queueName]];
            $this->setTransports($transports);
            $routings = $this->getRoutingsKeys();
            $routings[$transportName][] = $queueName;
            $this->setRoutingsKeys($routings);
            $this->updateRedisConfig();
            $connection = Connection::fromDsn($this->getDsn(), $this->getTransports()[$transportName]['options']);
            $connection->queue($queueName)->declareQueue();
            $connection->queue($queueName)->bind($this->getTransports()[$transportName]['options']['exchange']['name'], $queueName);
            return true;
        }

        return false;
    }

    /**
     * Kuyruk dolu olsun olmasın siler
     * $force parametresinin tru olması durumunda config içerisinde de kuyruğu siler
     * @param $queueName
     * @param bool $force
     * @return bool
     */
    public function deleteQueue($queueName, $force = false): bool
    {
        dd($this->checkStaticQueue($queueName));
        if ($this->checkStaticQueue($queueName)){
            return false;
        }
        $transports = $this->getTransports();
        $transportName = $this->getTransportNameForQueue($queueName);
        if ($transportName === null) return false;
        if (array_key_exists($queueName, $transports[$transportName]['options']['queues'])) {
            try {
                $connection = Connection::fromDsn($this->getDsn(), $this->getTransports()[$transportName]['options']);
                $connection->queue($queueName)->purge();
                $connection->queue($queueName)->delete();
                if ($force) {
                    unset($transports[$transportName]['options']['queues'][$queueName]);
                    $this->setTransports($transports);
                    $routings = $this->getRoutingsKeys();
                    unset($routings[$transportName][array_search($queueName, $routings[$transportName])]);
                    $routings[$transportName] = array_values($routings[$transportName]);
                    $this->setRoutingsKeys($routings);
                    $this->updateRedisConfig();
                }
            } catch (\Exception $exception) {
                throw  new \Exception($exception->getMessage());
            }
        }
        return true;
    }

    /**
     * Tüm kuyrukları siler $force parametresine göre config içerisinden de siler
     * @param bool $force
     * @return bool
     */
    public function deleteAllQueues($force = false): bool
    {
        $transports = $this->getTransports();
        $routings = $this->getRoutingsKeys();
        foreach ($routings as $transportName => $queues) {
            $connection = Connection::fromDsn($this->getDsn(), $this->getTransports()[$transportName]['options']);
            foreach ($queues as $queue) {
                if ($this->checkStaticQueue($queue)){
                    continue;
                }
                try {
                    $connection->queue($queue)->purge();
                    $connection->queue($queue)->delete();
                    if ($force) {
                        unset($transports[$transportName]['options']['queues'][$queue]);
                        $this->setTransports($transports);
                        $routings = $this->getRoutingsKeys();
                        unset($routings[$transportName][array_search($queue, $routings[$transportName])]);
                        $routings[$transportName] = array_values($routings[$transportName]);
                        $this->setRoutingsKeys($routings);
                        $this->updateRedisConfig();
                    }
                } catch (\Exception $exception) {
                    throw  new RuntimeException($exception->getMessage());
                }

            }
        }
        return true;
    }

    /**
     * seçili kuyruğu boşaltır
     * @param $queueName
     * @return bool
     * @throws \AMQPChannelException
     * @throws \AMQPConnectionException
     */
    public function purgeQueue($queueName): bool
    {
        $transportName = $this->getTransportNameForQueue($queueName);
        if ($transportName === null) return false;
        $transports = $this->getTransports();
        if (array_key_exists($queueName, $transports[$transportName]['options']['queues'])) {
            $connection = Connection::fromDsn($this->getDsn(), $this->getTransports()[$transportName]['options']);
            $connection->queue($queueName)->purge();
            return true;
        }
        return false;
    }

    /**
     * Taşıyıcı altında ki tüm kuyrukları boşaltır kuyruları boşaltır
     * @param $transportName
     */
    public function purgeAllQueues($transportName)
    {
        $connection = Connection::fromDsn($this->getDsn(), $this->getTransports()[$transportName]['options']);
        $connection->purgeQueues();
    }

    /**
     * Kuyruk adına göre taşıyıcıyı döner
     * @param string $queueName
     * @return int|string|null
     */
    public function getTransportNameForQueue($queueName)
    {
        foreach ($this->getRoutingsKeys() as $key => $value) {
            if (in_array($queueName, $value)) {
                return $key;
            }
        }
        return null;
    }

    public function checkStaticQueue($queueName){
        foreach ($this->getStaticRoutingsKeys() as $key => $value) {
            if (in_array($queueName, $value)) {
                return true;
            }
        }
        return false;
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
     * @return string
     */
    public function getApiDsn(): string
    {
        return $this->apiDsn;
    }

    /**
     * @param string $apiDsn
     */
    public function setApiDsn(string $apiDsn): void
    {
        $this->apiDsn = $apiDsn;
    }


}