<?php


namespace App\Services;


use App\Traits\QueueHelper;
use phpDocumentor\Reflection\Types\Object_;
use Psr\Container\ContainerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Transport\AmqpExt\AmqpStamp;

class DispatcherService
{

    use QueueHelper;

    /** @var MessageBusInterface $bus */
    private $bus;

    /** @var ContainerInterface $container */
    private $container;

    /** @var int $roundRobin */
    private $roundRobin = 0;

    /** @var RedisService $redisService */
    private $redisService;

    /** @var string $dsn */
    private $dsn;


    /**
     * DispatcherService constructor.
     * @param $dsn
     * @param $bus
     * @param RedisService $redisService
     * @param ContainerInterface $container
     */
    public function __construct($dsn, $bus, RedisService $redisService, ContainerInterface $container)
    {
        $this->setDsn($dsn);
        $this->setContainer($container);
        $this->setBus($bus);
        $this->setRedisService($redisService);
        $this->init();


    }

    /**
     * method redis içerisinde kayıtlı bir yapılandırmanın olup olmadığını kontrol eder
     * @param $dsn
     */
    private function init(): void
    {
        if ($this->getRedisService()->getClient()->get(IdeasoftMessenger::REDIS_TRANSPORT) === false) {
            $this->parseDsn($this->getDsn());
            $this->parseTransports();
            $this->parseRoutings();
            $this->getRedisService()->getClient()->set(IdeasoftMessenger::REDIS_TRANSPORT, json_encode($this->getTransports()));
            $this->getRedisService()->getClient()->set(IdeasoftMessenger::REDIS_ROUTINGS, json_encode($this->getRoutings()));
            $this->getRedisService()->getClient()->set(IdeasoftMessenger::REDIS_ROUTING_KEYS, json_encode($this->getRoutingsKeys()));
        } else {
            $this->setTransports(json_decode($this->getRedisService()->getClient()->get(IdeasoftMessenger::REDIS_TRANSPORT), true));
            $this->setRoutings(json_decode($this->getRedisService()->getClient()->get(IdeasoftMessenger::REDIS_ROUTINGS), true));
            $this->setRoutingsKeys(json_decode($this->getRedisService()->getClient()->get(IdeasoftMessenger::REDIS_ROUTING_KEYS), true));
        }
    }

    /**
     * @param $messageObject
     * @param $routingKey
     */
    public function dispatchWithRouting($message, $routingKey): void
    {
        $transport = $this->arraySearch($message);
        $this->counterQueueMessage($transport, $routingKey);
        $this->getBus()->dispatch(new Envelope($message, [
            new AmqpStamp($routingKey)
        ]));
    }

    /**
     * sadece statik olarak tanımlanmış kuyruklar için geçerlidir
     * @param $message
     */
    public function dispatchRandom($message): void
    {
        $transport = $this->arraySearch($message);
        if ($transport === null) {
            throw new \RuntimeException("Not found message interface or class");
        }
        $totalQueueCount = count($this->getRoutingsKeys()[$transport]);
        $routingKey = $this->getRoutingsKeys()[$transport][rand(0, $totalQueueCount - 1)];
        $this->counterQueueMessage($transport, $routingKey);
        $this->dispatchWithRouting($message, $routingKey);
    }

    public function dispatchRoundRobin($message)
    {
        $transport = $this->arraySearch($message);
        if ($transport === null) {
            throw new \RuntimeException("Not found message interface or class");
        }
        $totalQueueCount = count($this->getRoutingsKeys()[$transport]);
        $routingKey = $this->getRoutingsKeys()[$transport][$this->roundRobin];
        if ($totalQueueCount - 1 > $this->roundRobin) {
            $this->roundRobin++;
        } else {
            $this->roundRobin = 0;
        }
        $this->counterQueueMessage($transport, $routingKey);
        $this->dispatchWithRouting($message, $routingKey);
    }

    /**
     * @param Object_|null $message
     * @return bool|null
     */
    private function arraySearch($message = null)
    {
        $messageClass = get_class($message);
        $messageInterface = empty(array_values(class_implements($message))) === false ? array_values(class_implements($message))[0] : null;

        if ($messageClass !== null || $messageInterface !== null) {
            foreach ($this->getRoutings() as $key => $value) {
                if ($value === $messageInterface || $value === $messageClass) {
                    return $key;
                }
            }
        }
        return null;
    }

    /**
     * messenger.yaml da var olan configleri yükler
     */
    public function refreshSettings()
    {
        $this->parseDsn($this->getDsn());
        $this->parseTransports();
        $this->parseRoutings();
        $this->getRedisService()->getClient()->set(IdeasoftMessenger::REDIS_TRANSPORT, json_encode($this->getTransports()));
        $this->getRedisService()->getClient()->set(IdeasoftMessenger::REDIS_ROUTINGS, json_encode($this->getRoutings()));
        $this->getRedisService()->getClient()->set(IdeasoftMessenger::REDIS_ROUTING_KEYS, json_encode($this->getRoutingsKeys()));
    }

    public function counterQueueMessage($transport, $queue)
    {

        $queueCount = $this->getRedisService()->getClient()->get(sprintf(IdeasoftMessenger::REDIS_QUEUE_MESSAGE_COUNT, $transport, $queue));
        if ($queueCount === false) {
            $this->getRedisService()->getClient()->set(sprintf(IdeasoftMessenger::REDIS_QUEUE_MESSAGE_COUNT, $transport, $queue), 1);
            return;
        }
        $this->getRedisService()->getClient()->incr(sprintf(IdeasoftMessenger::REDIS_QUEUE_MESSAGE_COUNT, $transport, $queue));
    }

    /**
     * @return mixed
     */
    public function getBus()
    {
        return $this->bus;
    }

    /**
     * @param mixed $bus
     */
    public function setBus($bus): void
    {
        $this->bus = $bus;
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
     * @return RedisService
     */
    public function getRedisService(): RedisService
    {
        return $this->redisService;
    }

    /**
     * @param RedisService $redisService
     */
    public function setRedisService(RedisService $redisService): void
    {
        $this->redisService = $redisService;
    }

    /**
     * @return string
     */
    public function getDsn(): string
    {
        return $this->dsn;
    }

    /**
     * @param string $dsn
     */
    public function setDsn(string $dsn): void
    {
        $this->dsn = $dsn;
    }


}