<?php

namespace App\Command;


use App\Services\ConsumerService;
use Closure;
use phpDocumentor\Reflection\Types\Array_;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\EventListener\StopWorkerOnMemoryLimitListener;
use Symfony\Component\Messenger\EventListener\StopWorkerOnMessageLimitListener;
use Symfony\Component\Messenger\EventListener\StopWorkerOnTimeLimitListener;
use Symfony\Component\Messenger\RoutableMessageBus;
use Symfony\Component\Messenger\Worker;


class ConsumerMessengerCommand extends Command
{
    protected static $defaultName = 'ertan:consume';

    private $routableBus;
    private $logger;
    private $eventDispatcher;
    private $consumerService;


    /**
     * @param ConsumerService $consumerService
     * @param $routableBus
     * @param EventDispatcherInterface $eventDispatcher
     * @param LoggerInterface|null $logger
     * @param array $receiverNames
     */
    public function __construct(ConsumerService $consumerService, $routableBus, EventDispatcherInterface $eventDispatcher, LoggerInterface $logger = null)
    {

        if ($routableBus instanceof ContainerInterface) {
            @trigger_error(sprintf('Passing a "%s" instance as first argument to "%s()" is deprecated since Symfony 4.4, pass a "%s" instance instead.', ContainerInterface::class, __METHOD__, RoutableMessageBus::class), E_USER_DEPRECATED);
            $routableBus = new RoutableMessageBus($routableBus);
        } elseif (!$routableBus instanceof RoutableMessageBus) {
            throw new \TypeError(sprintf('The first argument must be an instance of "%s".', RoutableMessageBus::class));
        }

        $this->routableBus = $routableBus;
        $this->logger = $logger;
        $this->eventDispatcher = $eventDispatcher;
        $this->consumerService=$consumerService;
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this
            ->setDefinition([
                new InputArgument('receiver', InputArgument::REQUIRED, 'Names of the receivers/transports to consume in order of priority'),
                new InputOption('queue-name','s',InputOption::VALUE_REQUIRED,'Queue name'),
                new InputOption('limit', 'l', InputOption::VALUE_REQUIRED, 'Limit the number of received messages'),
                new InputOption('memory-limit', 'm', InputOption::VALUE_REQUIRED, 'The memory limit the worker can consume'),
                new InputOption('time-limit', 't', InputOption::VALUE_REQUIRED, 'The time limit in seconds the worker can run'),
                new InputOption('sleep', null, InputOption::VALUE_REQUIRED, 'Seconds to sleep before asking for new messages after no messages were found', 1),
                new InputOption('bus', 'b', InputOption::VALUE_REQUIRED, 'Name of the bus to which received messages should be dispatched (if not passed, bus is determined automatically)'),
            ])
            ->setDescription('Consumes messages')
            ->setHelp(<<<'EOF'
The <info>%command.name%</info> command consumes messages and dispatches them to the message bus.

    <info>php %command.full_name% <receiver-name></info>

To receive from multiple transports, pass each name:

    <info>php %command.full_name% receiver1 receiver2</info>

Use the --limit option to limit the number of messages received:

    <info>php %command.full_name% <receiver-name> --limit=10</info>

Use the --memory-limit option to stop the worker if it exceeds a given memory usage limit. You can use shorthand byte values [K, M or G]:

    <info>php %command.full_name% <receiver-name> --memory-limit=128M</info>

Use the --time-limit option to stop the worker when the given time limit (in seconds) is reached:

    <info>php %command.full_name% <receiver-name> --time-limit=3600</info>

Use the --bus option to specify the message bus to dispatch received messages
to instead of trying to determine it automatically. This is required if the
messages didn't originate from Messenger:

    <info>php %command.full_name% <receiver-name> --bus=event_bus</info>
EOF
            )
        ;
    }

    /**
     * {@inheritdoc}
     * @throws \ReflectionException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {

        if (false !== strpos($input->getFirstArgument(), ':consume-')) {
            $message = 'The use of the "messenger:consume-messages" command is deprecated since version 4.3 and will be removed in 5.0. Use "messenger:consume" instead.';
            @trigger_error($message, E_USER_DEPRECATED);
            $output->writeln(sprintf('<comment>%s</comment>', $message));
        }

        if ($input->getOption('queue-name') === null){
            throw new \RuntimeException('Queue not selected');
        }

        if ($limit = $input->getOption('limit')) {
            $this->eventDispatcher->addSubscriber(new StopWorkerOnMessageLimitListener($limit, $this->logger));
        }

        if ($memoryLimit = $input->getOption('memory-limit')) {
            $this->eventDispatcher->addSubscriber(new StopWorkerOnMemoryLimitListener($this->convertToBytes($memoryLimit), $this->logger));
        }

        if ($timeLimit = $input->getOption('time-limit')) {
            $this->eventDispatcher->addSubscriber(new StopWorkerOnTimeLimitListener($timeLimit, $this->logger));
        }

        $receiver=$this->consumerService->createAmqpTransport($input->getArgument('receiver'),$input->getOption('queue-name'));


        $bus = $input->getOption('bus') ? $this->routableBus->getMessageBus($input->getOption('bus')) : $this->routableBus;

        $worker = new Worker($receiver, $bus, $this->eventDispatcher, $this->logger);
        $worker->run([
            'sleep' => $input->getOption('sleep') * 1000000,
        ]);

        return 0;
    }

    private function convertToBytes(string $memoryLimit): int
    {
        $memoryLimit = strtolower($memoryLimit);
        $max = strtolower(ltrim($memoryLimit, '+'));
        if (0 === strpos($max, '0x')) {
            $max = \intval($max, 16);
        } elseif (0 === strpos($max, '0')) {
            $max = \intval($max, 8);
        } else {
            $max = (int) $max;
        }

        switch (substr(rtrim($memoryLimit, 'b'), -1)) {
            case 't': $max *= 1024;
            // no break
            case 'g': $max *= 1024;
            // no break
            case 'm': $max *= 1024;
            // no break
            case 'k': $max *= 1024;
        }

        return $max;
    }

}
