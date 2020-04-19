<?php

namespace App\Command;

use App\Services\MessengerBaseService;
use phpDocumentor\Reflection\Types\This;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;

class MessengerManagementCommand extends Command
{
    protected static $defaultName = 'messenger:management';

    /** @var MessengerBaseService $messengerBaseService */
    private $messengerBaseService;

    /** @var int $consumerPerQueue */
    private $consumerPerQueue;

    /** @var int $maxConsumerCount */
    private $maxConsumerCount;

    /** @var array $currentConsumerList */
    private $currentConsumerList;

    /** @var bool $forceDeleteQueueMaxMin */
    private $forceDeleteQueue;
    /** @var int $consumerLimitPerQueue */
    private $consumerLimitPerQueue;

    /** @var int $callConsumerPerMessageCount */
    private $callConsumerPerMessageCount;


    public function __construct($consumerPerQueue, $maxConsumerCount,$forceDeleteQueue,$consumerLimitPerQueue,$callConsumerPerMessageCount, MessengerBaseService $messengerBaseService)
    {
        parent::__construct();
        $this->consumerPerQueue = $consumerPerQueue;
        $this->maxConsumerCount = $maxConsumerCount;
        $this->forceDeleteQueue = $forceDeleteQueue;
        $this->consumerLimitPerQueue = $consumerLimitPerQueue;
        $this->messengerBaseService = $messengerBaseService;
        $this->callConsumerPerMessageCount = $callConsumerPerMessageCount;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);


        while (true) {
            sleep(4);
            $this->getMessengerBaseService()->updateLocalConfig();
            $this->statusConsumers($output);
            $this->statusQueues($output);
            $this->callConsumer($output);
        }

        $io->success('You have a new command! Now make it your own! Pass --help to see your options.');

        return 0;
    }

    /**
     * @param OutputInterface $output
     */
    private function callConsumer($output){
        sleep(4);
        $output->writeln('---- Consumer açmak için kuyruk durumları inceleniyor ----');
        $queueStatus=$this->getMessengerBaseService()->getQueueService()->getAllQueueStats();
        foreach ($queueStatus as $status){
            sleep(1);
            if ($status['messageCount'] > 0 && $status['messagePerConsume'] < $this->consumerLimitPerQueue && $status['messageCount'] > $this->callConsumerPerMessageCount){
                $transportName=$this->getMessengerBaseService()->getQueueService()->getTransportNameForQueue($status['queueName']);
                $output->writeln(sprintf('Consumer çağrısı gerçekleşti. Açılan Consumer Taşıyıcı Adı: %s  Kuyruk Adı: %s',$transportName,$status['queueName']));
                $consumer = Process::fromShellCommandline('$(which php) ' . __DIR__ . '/../../bin/console messenger:management-consumer  ' . $transportName . ' -s ' . $status['queueName'] . ' 2>/dev/null &');
                $consumer->start();

            }

        }
    }

    /**
     * @param OutputInterface $output
     * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     */
    private function statusConsumers($output): void
    {
        sleep(4);
        $output->writeln('---- Consumer durumları izleniyor ----');
        $consumerList = $this->getCurrentConsumerList();
        foreach ($consumerList as $value) {
            sleep(1);
            $consumerInfo = explode(' ', $value);
            $status = $this->getMessengerBaseService()->getQueueService()->getQueueStats($consumerInfo[2]);
            $output->writeln(sprintf('Çalışan Consumer PID: %s Consumer Taşıyıcı Adı: %s Kuyruk Adı: %s',$consumerInfo[0],$consumerInfo[1],$consumerInfo[2]));
            if ($status['messageCount'] === 0) {
                $this->closeConsumer($consumerInfo, $output);
            }
        }
    }


    private function statusQueues($output)
    {
        sleep(4);
        $output->writeln('---- Kuyruk durumları izleniyor ----');
        $queuesStatus = $this->getMessengerBaseService()->getQueueService()->getAllQueueStats();
        foreach ($queuesStatus as $status) {
            sleep(1);
            $output->writeln(sprintf('Kuyruk Adı: %s Mesaj Sayısı: %s Consumer Sayısı: %s',$status['queueName'],$status['messageCount'],$status['messagePerConsume']));
            if ($status['messageCount'] === 0 && $status['status'] !== 'running') {
                $this->closeQueue($status, $output);
            }
        }
    }


    /**
     * @param array $consumerInfo
     * @param OutputInterface $output
     */
    private function closeConsumer($consumerInfo, $output): void
    {
        exec("kill $consumerInfo[0]");
        $output->writeln(sprintf('Kapatılan Consumer PID: %s  Taşıyıcı: %s  Kuyruk: %s', $consumerInfo[0], $consumerInfo[1], $consumerInfo[2]));
    }

    /**
     * @param array $queueStatus
     * @param OutputInterface $output
     */
    private function closeQueue($queueStatus, $output): void
    {
        if ($queueStatus['messageCount'] === 0 && $queueStatus['status'] !== 'running') {
            $result = $this->getMessengerBaseService()->getQueueService()->deleteQueue($queueStatus['queueName'],$this->forceDeleteQueue);
            if ($result) {
                $output->writeln(sprintf('Kapatılan Kuyruk: %s  Son durumu: %s ', $queueStatus['queueName'], $queueStatus['status'] !== 'running' ? 'Boşta' : 'Çalışıyor'));
            }
        }

    }

    /**
     * Çalışan consumer listesini bir array'a çevirir
     */
    private function parseConsumerList()
    {
        exec("ps aux | grep -i [m]essenger:management-consumer | grep -v grep | awk {'print $2 , $14 , $16'} ", $consumerList);
        $this->setCurrentConsumerList($consumerList);
    }


    /**
     * @return MessengerBaseService
     */
    public function getMessengerBaseService(): MessengerBaseService
    {
        return $this->messengerBaseService;
    }

    /**
     * @param MessengerBaseService $messengerBaseService
     */
    public function setMessengerBaseService(MessengerBaseService $messengerBaseService): void
    {
        $this->messengerBaseService = $messengerBaseService;
    }

    /**
     * @return int
     */
    public function getConsumerPerQueue(): int
    {
        return $this->consumerPerQueue;
    }

    /**
     * @param int $consumerPerQueue
     */
    public function setConsumerPerQueue(int $consumerPerQueue): void
    {
        $this->consumerPerQueue = $consumerPerQueue;
    }

    /**
     * @return int
     */
    public function getMaxConsumerCount(): int
    {
        return $this->maxConsumerCount;
    }

    /**
     * @param int $maxConsumerCount
     */
    public function setMaxConsumerCount(int $maxConsumerCount): void
    {
        $this->maxConsumerCount = $maxConsumerCount;
    }

    /**
     * @return array
     */
    public function getCurrentConsumerList(): array
    {
        $this->parseConsumerList();
        return $this->currentConsumerList;
    }

    /**
     * @param array $currentConsumerList
     */
    public function setCurrentConsumerList(array $currentConsumerList): void
    {
        $this->currentConsumerList = $currentConsumerList;
    }


}
