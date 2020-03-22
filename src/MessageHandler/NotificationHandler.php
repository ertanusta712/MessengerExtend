<?php


namespace App\MessageHandler;


use App\Message\MessageInterface;
use App\Message\Notification;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;

class NotificationHandler implements MessageHandlerInterface
{

    /**
     * @param MessageInterface $notification
     * @throws \Exception
     */
    public function __invoke(MessageInterface $notification)
    {
        $content = file_get_contents($notification->getPath());
        $content .= PHP_EOL;
        $newContent = 'Kuyruğa Giriş: ' . $notification->getMessage() . 'Kuyruktan Çıkış: ' . date_format(new \DateTime(), 'H:i:s');
        file_put_contents($notification->getPath(), $content . $newContent);
    }
}