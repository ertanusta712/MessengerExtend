<?php


namespace App\Services;


use App\Traits\QueueHelper;
use Psr\Container\ContainerInterface;

class IdeasoftMessenger
{

    public const REDIS_TRANSPORT = 'IDEASOFT_TRANSPORTS';
    public const REDIS_ROUTINGS = 'IDEASOFT_TRANSPORT_ROUTING';
    public const REDIS_ROUTING_KEYS = 'IDEASOFT_TRANSPORT_ROUTING_KEYS';
    public const REDIS_QUEUE_MESSAGE_COUNT = 'Transport %s Queue %s';

}