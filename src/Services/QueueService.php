<?php


namespace App\Services;


use App\Traits\QueueHelper;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpClient\HttpClient;
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

    public function __construct($dsn,$apiDsn, ContainerInterface $container)
    {
        $this->setContainer($container);
        $this->parseDsn($dsn);
        $this->parseTransports();
        $this->parseRoutings();
        $this->setApiDsn($apiDsn);
    }

    /**
     * @param $queueName
     * @throws \Exception
     */
    public function getQueueStats($queueName)
    {

        try {
            $client= HttpClient::create()->request('GET',$this->getApiDsn().'/'.$queueName,[
                'auth_basic'=>[$this->getUser(),$this->getPassword()]
            ]);
            $queueStatus = json_decode($client->getContent(),true);
            return [
                'messageCount'=>$queueStatus['messages'],
                'messagePerConsume'=>$queueStatus['messages_unacknowledged'],
                'status'=>$queueStatus['idle_since'] ?? 'running'
            ];
        }catch (\Exception $exception){
            throw new \Exception($exception->getMessage());
        }

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