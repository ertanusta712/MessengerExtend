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

    /** @var QueueService $queueService */
    private $queueService;

    /** @var int $roundRobin */
    private $roundRobin = 0;

    /**
     * DispatcherService constructor.
     * @param $dsn
     * @param $bus
     * @param RedisService $redisService
     * @param ContainerInterface $container
     */
    public function __construct($dsn, $bus, RedisService $redisService,ContainerInterface $container)
    {
        $this->setDsn($dsn);
        $this->setContainer($container);
        $this->setRedisService($redisService);
        $this->init();
        $this->setBus($bus);
    }


    /**
     * @param $message
     * @param $routingKey
     */
    public function dispatchWithRouting($message, $routingKey): void
    {
        $transport = $this->arraySearch($message);
        $this->getBus()->dispatch(new Envelope($message, [
            new AmqpStamp($routingKey)
        ]));
    }

    /**
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