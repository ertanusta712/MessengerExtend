<?php

namespace App\Controller;

use App\Kernel;
use App\Message\ErtanNotification;
use App\Message\Notification;
use Psr\Container\ContainerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Transport\AmqpExt\AmqpStamp;
use Symfony\Component\Messenger\Transport\AmqpExt\Connection;
use Symfony\Component\Routing\Annotation\Route;

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
    public function index()
    {

        for ($i = 0; $i < 100; $i++) {
            $message1 = new Notification(date_format(new \DateTime(), 'H:m:i'), $this->kernel->getCacheDir() . '/ertan1.txt');
            $this->dispatchMessage((new Envelope($message1))->with(new AmqpStamp('ertan1')));

            $message2 = new Notification(date_format(new \DateTime(), 'H:m:i'), $this->kernel->getCacheDir() . '/ertan2.txt');
            $this->dispatchMessage((new Envelope($message2))->with(new AmqpStamp('ertan2')));

            $message3 = new Notification(date_format(new \DateTime(), 'H:m:i'), $this->kernel->getCacheDir() . '/ertan3.txt');
            $this->dispatchMessage((new Envelope($message3))->with(new AmqpStamp('ertan3')));

            $message4 = new Notification(date_format(new \DateTime(), 'H:m:i'), $this->kernel->getCacheDir() . '/ertan4.txt');
            $this->dispatchMessage((new Envelope($message4))->with(new AmqpStamp('ertan4')));

            $message5 = new ErtanNotification(date_format(new \DateTime(), 'H:m:i'), $this->kernel->getCacheDir() . '/ertan_async.txt');
            $this->dispatchMessage($message5);
        }


    }
}
