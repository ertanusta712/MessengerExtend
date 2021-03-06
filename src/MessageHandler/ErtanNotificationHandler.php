<?php


namespace App\MessageHandler;


use App\Message\ErtanNotification;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;

class ErtanNotificationHandler implements MessageHandlerInterface
{

    public function __invoke(ErtanNotification $notification)
    {
        $content=file_get_contents($notification->getPath());
        $content.=PHP_EOL;
        $newContent = 'Kuyruğa Giriş: ' . $notification->getMessage() . 'Kuyruktan Çıkış: ' . date_format(new \DateTime(), 'H:i:s');
        file_put_contents($notification->getPath(), $content . $newContent);
    }
}