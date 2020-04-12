<?php


namespace App\Services;


use App\Traits\QueueHelper;
use phpDocumentor\Reflection\Types\This;
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


    public function __construct($dsn, $bus, ContainerInterface $container)
    {
        $this->setContainer($container);
        $this->setBus($bus);
        $this->parseDsn($dsn);
        $this->parseTransports();
        $this->parseRoutings();
    }

    /**
     * @param $messageObject
     * @param $routingKey
     */
    public function dispatchWithRouting($messageObject, $routingKey): void
    {
        $this->getBus()->dispatch(new Envelope($messageObject, [
            new AmqpStamp($routingKey)
        ]));
    }

    /**
     * @param $message
     */
    public function dispatchRandom($message): void
    {
        $transport = $this->arraySearch(array_values(class_implements($message))[0], get_class($message));
        if ($transport === null){
            throw new \RuntimeException("Not found message interface or class");
        }
        $totalQueueCount=count($this->getRoutingsKeys()[$transport]);
        $routingKey=$this->getRoutingsKeys()[$transport][rand(0,$totalQueueCount-1)];
        $this->dispatchWithRouting($message,$routingKey);
    }

    /**
     * @param null $messageInterface
     * @param null $messageClass
     * @return bool|null
     */
    private function arraySearch($messageInterface = null, $messageClass = null)
    {
        if ($messageInterface !== null && in_array($messageInterface, $this->getRoutings(), true)) {
            return array_search($messageInterface, $this->getRoutings(), true);
        }
        if ($messageClass !== null && in_array($messageClass, $this->getRoutings(), true)) {
            return array_search($messageClass, $this->getRoutings(), true);
        }

        return null;
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


}