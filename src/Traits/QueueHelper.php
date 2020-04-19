<?php


namespace App\Traits;


use App\Services\IdeasoftMessenger;
use App\Services\RedisService;
use Psr\Container\ContainerInterface;
use Symfony\Component\Yaml\Yaml;

trait QueueHelper
{

    /** @var string $dsn */
    private $dsn;

    /** @var string $host */
    private $host;

    /** @var string $user */
    private $user;

    /** @var string $password */
    private $password;

    /** @var string $port */
    private $port;

    /** @var array $transports */
    private $transports = [];

    /** @var array $routings */
    private $routings = [];

    /** @var array $routingsKeys */
    private $routingsKeys = [];

    /** @var RedisService $redisService */
    private $redisService;

    /** @var array $staticRoutins */
    private $staticRoutings = [];

    /** @var array $staticRoutingsKeys */
    private $staticRoutingsKeys = [];

    /** @var array $staticTransports */
    private $staticTransports = [];

    private function parseTransports()
    {
        $transports = array();
        $path = $this->getContainer()->get('kernel')->getProjectDir();
        $messengerPath = '/config/packages/messenger.yaml';
        $imports = Yaml::parseFile($path . $messengerPath)['imports'];
        foreach ($imports as $value) {
            $configPath = str_replace('../', '', $value['resource']);
            $configs = Yaml::parseFile($path . '/config/' . $configPath)['framework']['messenger'];
            foreach ($configs['transports'] as $key => $value) {
                unset($value['dsn']);
                $transports[$key] = $value;
            }
        }
        $routingKeys = array();
        foreach ($transports as $key => $value) {
            foreach ($value['options']['queues'] as $item) {
                $routingKeys[$key][] = $item['binding_keys'][0];
            }
        }
        $this->setRoutingsKeys($routingKeys);
        $this->setTransports($transports);
        $this->setStaticRoutingsKeys($routingKeys);
        $this->setStaticTransports($transports);
    }

    private function parseRoutings()
    {
        $routings = array();
        $path = $this->getContainer()->get('kernel')->getProjectDir();
        $messengerPath = '/config/packages/messenger.yaml';
        $imports = Yaml::parseFile($path . $messengerPath)['imports'];
        foreach ($imports as $value) {
            $configPath = str_replace('../', '', $value['resource']);
            $configs = Yaml::parseFile($path . '/config/' . $configPath)['framework']['messenger'];
            foreach ($configs['routing'] as $key => $value) {
                $routings[$value] = $key;
            }
        }
        $this->setRoutings($routings);
        $this->setStaticTransports($routings);
    }


    private function parseDsn($dsn)
    {
        $dsn = str_replace('amqp://', '', $dsn);
        $dsn = explode(':', $dsn);
        $this->setUser($dsn[0]);
        $this->setPassword(explode('@', $dsn[1])[0]);
        $this->setHost(explode('@', $dsn[1])[1]);
        $this->setPort(explode('/', $dsn[2])[0]);
    }

    /**
     * method redis içerisinde kayıtlı bir yapılandırmanın olup olmadığını kontrol eder
     * @param $dsn
     */
    private function init(): void
    {
        $this->parseDsn($this->getDsn());
        $this->parseTransports();
        $this->parseRoutings();

        if ($this->getRedisService()->getClient()->get(IdeasoftMessenger::REDIS_TRANSPORT) === false) {
            $this->getRedisService()->getClient()->set(IdeasoftMessenger::REDIS_TRANSPORT, json_encode($this->getTransports()));
            $this->getRedisService()->getClient()->set(IdeasoftMessenger::REDIS_ROUTINGS, json_encode($this->getRoutings()));
            $this->getRedisService()->getClient()->set(IdeasoftMessenger::REDIS_ROUTING_KEYS, json_encode($this->getRoutingsKeys()));
        } else {
            $this->setTransports(json_decode($this->getRedisService()->getClient()->get(IdeasoftMessenger::REDIS_TRANSPORT), true));
            $this->setRoutings(json_decode($this->getRedisService()->getClient()->get(IdeasoftMessenger::REDIS_ROUTINGS), true));
            $this->setRoutingsKeys(json_decode($this->getRedisService()->getClient()->get(IdeasoftMessenger::REDIS_ROUTING_KEYS), true));
        }
    }

    /**
     * messenger.yaml da var olan configleri yükler
     */
    public function refreshSettings()
    {
        $this->parseDsn($this->getDsn());
        $this->parseTransports();
        $this->parseRoutings();
        $this->getRedisService()->getClient()->set(IdeasoftMessenger::REDIS_TRANSPORT, json_encode($this->getTransports()));
        $this->getRedisService()->getClient()->set(IdeasoftMessenger::REDIS_ROUTINGS, json_encode($this->getRoutings()));
        $this->getRedisService()->getClient()->set(IdeasoftMessenger::REDIS_ROUTING_KEYS, json_encode($this->getRoutingsKeys()));
    }

    /**
     *  Redis üzerinde ki config'i günceller
     */
    public function updateRedisConfig()
    {
        $this->getRedisService()->getClient()->set(IdeasoftMessenger::REDIS_TRANSPORT, json_encode($this->getTransports()));
        $this->getRedisService()->getClient()->set(IdeasoftMessenger::REDIS_ROUTINGS, json_encode($this->getRoutings()));
        $this->getRedisService()->getClient()->set(IdeasoftMessenger::REDIS_ROUTING_KEYS, json_encode($this->getRoutingsKeys()));
    }

    /**
     * Local configleri günceller
     */
    public function updateLocalConfig()
    {
        $this->setTransports(json_decode($this->getRedisService()->getClient()->get(IdeasoftMessenger::REDIS_TRANSPORT), true));
        $this->setRoutings(json_decode($this->getRedisService()->getClient()->get(IdeasoftMessenger::REDIS_ROUTINGS), true));
        $this->setRoutingsKeys(json_decode($this->getRedisService()->getClient()->get(IdeasoftMessenger::REDIS_ROUTING_KEYS), true));
    }


    /**
     * @return string
     */
    public function getHost(): string
    {
        return $this->host;
    }

    /**
     * @param string $host
     */
    public function setHost(string $host): void
    {
        $this->host = $host;
    }

    /**
     * @return string
     */
    public function getUser(): string
    {
        return $this->user;
    }

    /**
     * @param string $user
     */
    public function setUser(string $user): void
    {
        $this->user = $user;
    }

    /**
     * @return string
     */
    public function getPassword(): string
    {
        return $this->password;
    }

    /**
     * @param string $password
     */
    public function setPassword(string $password): void
    {
        $this->password = $password;
    }

    /**
     * @return string
     */
    public function getPort(): string
    {
        return $this->port;
    }

    /**
     * @param string $port
     */
    public function setPort(string $port): void
    {
        $this->port = $port;
    }

    /**
     * @return array
     */
    public function getTransports(): array
    {
        return $this->transports;
    }

    /**
     * @param array $transports
     */
    public function setTransports(array $transports): void
    {
        $this->transports = $transports;
    }

    /**
     * @return array
     */
    public function getRoutings(): array
    {
        return $this->routings;
    }

    /**
     * @param array $routings
     */
    public function setRoutings(array $routings): void
    {
        $this->routings = $routings;
    }

    /**
     * @return array
     */
    public function getRoutingsKeys(): array
    {
        return $this->routingsKeys;
    }

    /**
     * @param array $routingsKeys
     */
    public function setRoutingsKeys(array $routingsKeys): void
    {
        $this->routingsKeys = $routingsKeys;
    }

    /**
     * @return string
     */
    public function getDsn(): string
    {
        return $this->dsn;
    }

    /**
     * @param string $dsn
     */
    public function setDsn(string $dsn): void
    {
        $this->dsn = $dsn;
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
    public function setRedisService(RedisService $redisService): void
    {
        $this->redisService = $redisService;
    }

    /**
     * @return array
     */
    public function getStaticRoutings(): array
    {
        return $this->staticRoutings;
    }

    /**
     * @param array $staticRoutings
     */
    public function setStaticRoutings(array $staticRoutings): void
    {
        $this->staticRoutings = $staticRoutings;
    }

    /**
     * @return array
     */
    public function getStaticRoutingsKeys(): array
    {
        return $this->staticRoutingsKeys;
    }

    /**
     * @param array $staticRoutingsKeys
     */
    public function setStaticRoutingsKeys(array $staticRoutingsKeys): void
    {
        $this->staticRoutingsKeys = $staticRoutingsKeys;
    }

    /**
     * @return array
     */
    public function getStaticTransports(): array
    {
        return $this->staticTransports;
    }

    /**
     * @param array $staticTransports
     */
    public function setStaticTransports(array $staticTransports): void
    {
        $this->staticTransports = $staticTransports;
    }



}