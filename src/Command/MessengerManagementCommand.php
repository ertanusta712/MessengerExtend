<?php

namespace App\Command;

use App\Services\MessengerBaseService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class MessengerManagementCommand extends Command
{
    protected static $defaultName = 'messenger:management';
    /**
     * @var MessengerBaseService
     */
    private $messengerBaseService;



    public function __construct(MessengerBaseService $messengerBaseService)
    {
        $this->setMessengerBaseService($messengerBaseService);
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $arg1 = $input->getArgument('arg1');

        while (true){

        }

        $io->success('You have a new command! Now make it your own! Pass --help to see your options.');

        return 0;
    }
    /**
     * @return MessengerBaseService
     */
    public function getMessengerBaseService(): MessengerBaseService
    {
        return $this->messengerBaseService;
    }

    /**
     * @param MessengerBaseService $messengerBaseService
     */
    public function setMessengerBaseService(MessengerBaseService $messengerBaseService): void
    {
        $this->messengerBaseService = $messengerBaseService;
    }
}
