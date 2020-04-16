<?php

namespace App\Controller;

use App\Kernel;
use App\Message\ErtanNotification;
use App\Message\Notification;
use App\Message\Notification2;
use App\Message\Notification3;
use App\Services\ConsumerService;
use App\Services\DispatcherService;
use App\Services\IdeasoftMessenger;
use App\Services\QueueService;
use App\Services\RedisService;
use http\Client\Request;
use Psr\Container\ContainerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Transport\AmqpExt\AmqpStamp;
use Symfony\Component\Messenger\Transport\AmqpExt\Connection;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Yaml\Yaml;

class MessengerController extends AbstractController
{

    private $kernel;

    public function __construct(KernelInterface $kernel)
    {
        $this->kernel = $kernel;
    }

    /**
     * @Route("/messenger", name="messanger")
     */
    public function index(ContainerInterface $container, DispatcherService $dispatcherService, RedisService $redisService, QueueService $queueService)
    {

        dd($queueService->getQueueStats('ertan_3'));
        /*  $path= $container->get('kernel')->getProjectDir();
          $result=Yaml::parseFile($path.'/config/packages/messenger.yaml');
          $ertan=explode('/',$result['imports'][0]['resource']);

          dd(Yaml::parseFile($path.'/config/'.$ertan[1].'/'.$ertan[2]));*/


        for ($i = 0; $i < 100; $i++) {
            $message1 = new Notification(date_format(new \DateTime(), 'H:m:i'), $this->kernel->getCacheDir() . '/ertan1.txt');
            $dispatcherService->dispatchRoundRobin($message1);

            $message2 = new Notification2(date_format(new \DateTime(), 'H:m:i'), $this->kernel->getCacheDir() . '/ertan2.txt');
            $dispatcherService->dispatchRoundRobin($message2);

            $message3 = new Notification3(date_format(new \DateTime(), 'H:m:i'), $this->kernel->getCacheDir() . '/ertan3.txt');
            $dispatcherService->dispatchRoundRobin($message3);


        }


    }
}
