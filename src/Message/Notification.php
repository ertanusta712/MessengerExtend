<?php


namespace App\Message;


use Doctrine\ORM\Mapping as ORM;

class Notification implements MessageInterface
{

    /**
     * @var string
     */
    private $message;
    /**
     * @var string
     */
    private $path;

    /**
     * Notification constructor.
     * @param string $message
     * @param string $path
     */
    public function __construct(string $message,string $path)
    {
        $this->message=$message;
        $this->path=$path;
    }

    /**
     * @return mixed
     */
    public function getMessage()
    {
        return $this->message;
    }

    /**
     * @param mixed $message
     */
    public function setMessage($message): void
    {
        $this->message = $message;
    }

    /**
     * @return string
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * @param string $path
     */
    public function setPath(string $path): void
    {
        $this->path = $path;
    }


}