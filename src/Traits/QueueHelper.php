<?php


namespace App\Traits;


use Psr\Container\ContainerInterface;
use Symfony\Component\Yaml\Yaml;

trait QueueHelper
{
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
        $routingKeys=array();
        foreach ($transports as $key => $value){
            foreach ($value['options']['queues'] as $item){
             $routingKeys[$key][]=$item['binding_keys'][0];
            }
        }
        $this->setRoutingsKeys($routingKeys);
        $this->setTransports($transports);
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


}