<?php


namespace App\Services;


use App\Traits\QueueHelper;
use Psr\Container\ContainerInterface;

class MessengerBaseService
{

    /** @var QueueService $queueService */
    private $queueService;

    /** @var RedisService $redisService */
    private $redisService;

    /** @var DispatcherService $dispatcherService */
    private $dispatcherService;

    /** @var ConsumerService $consumerService */
    private $consumerService;


    public function __construct($dsn,$dsnApi,$redisDsn,$bus,ContainerInterface $container)
    {
        $this->setRedisService(new RedisService($redisDsn));
        $this->setQueueService(new QueueService($dsn,$dsnApi,$this->getRedisService(),$container));
        $this->setDispatcherService(new DispatcherService($dsn,$bus,$this->getRedisService(),$container));
        $this->setConsumerService(new ConsumerService($dsn,$this->getRedisService(),$this->getQueueService(),$container));
    }

    public function updateLocalConfig(){
        $this->getQueueService()->updateLocalConfig();
        $this->getDispatcherService()->updateLocalConfig();
        $this->getConsumerService()->updateLocalConfig();
    }

    public function refreshSettings(){
        $this->getQueueService()->refreshSettings();
        $this->getDispatcherService()->refreshSettings();
        $this->getConsumerService()->refreshSettings();
    }

    public function updateRedisConfig(){
        $this->getConsumerService()->updateRedisConfig();
        $this->getQueueService()->updateRedisConfig();
        $this->getDispatcherService()->updateRedisConfig();
    }


    /**
     * @return QueueService
     */
    public function getQueueService(): QueueService
    {
        $this->queueService->updateLocalConfig();
        return $this->queueService;
    }

    /**
     * @param QueueService $queueService
     */
    private function setQueueService(QueueService $queueService): void
    {
        $this->queueService = $queueService;
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
    private function setRedisService(RedisService $redisService): void
    {
        $this->redisService = $redisService;
    }

    /**
     * @return DispatcherService
     */
    public function getDispatcherService(): DispatcherService
    {
        $this->dispatcherService->updateLocalConfig();
        return $this->dispatcherService;
    }

    /**
     * @param DispatcherService $dispatcherService
     */
    private function setDispatcherService(DispatcherService $dispatcherService): void
    {
        $this->dispatcherService = $dispatcherService;
    }

    /**
     * @return ConsumerService
     */
    public function getConsumerService(): ConsumerService
    {
        $this->consumerService->updateLocalConfig();
        return $this->consumerService;
    }

    /**
     * @param ConsumerService $consumerService
     */
    private function setConsumerService(ConsumerService $consumerService): void
    {
        $this->consumerService = $consumerService;
    }




}