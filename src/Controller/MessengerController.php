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
use App\Services\MessengerBaseService;
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
    public function index(ContainerInterface $container, MessengerBaseService $messengerBaseService)
    {


        /*  $path= $container->get('kernel')->getProjectDir();
          $result=Yaml::parseFile($path.'/config/packages/messenger.yaml');
          $ertan=explode('/',$result['imports'][0]['resource']);

          dd(Yaml::parseFile($path.'/config/'.$ertan[1].'/'.$ertan[2]));*/



        $messengerBaseService->getQueueService()->createNewQueue('transport1','ertan_7');
        $messengerBaseService->getQueueService()->createNewQueue('transport1','ertan_6');

        $messengerBaseService->getQueueService()->createNewQueue('transport1','ertan_7');



    }
}
