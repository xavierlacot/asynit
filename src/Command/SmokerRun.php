<?php

declare(strict_types=1);

namespace Asynit\Command;

use Asynit\Factory;
use Asynit\Parser\SmokeParser;
use Asynit\Parser\TestPoolBuilder;
use Asynit\Runner\PoolRunner;
use Doctrine\Common\Annotations\AnnotationReader;
use Http\Message\MessageFactory\GuzzleMessageFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use React\EventLoop\Factory as EventLoopFactory;

class SmokerRun extends Command
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('smoke')
            ->addArgument('file', InputArgument::REQUIRED, 'Configuration file for smoker')
            ->addOption('host', null, InputOption::VALUE_OPTIONAL, 'Base host to use', null)
            ->addOption('allow-self-signed-certificate', null, InputOption::VALUE_NONE, 'Allow self signed ssl certificate')
            ->addOption('dns', null, InputOption::VALUE_OPTIONAL, 'DNS Ip to use', '8.8.8.8')
            ->addOption('tty', null, InputOption::VALUE_NONE, 'Force to use tty output')
            ->addOption('no-tty', null, InputOption::VALUE_NONE, 'Force to use no tty output')
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // Build the event loop
        $loop = EventLoopFactory::create();

        // Build the client
        $client = Factory::createClient($loop, $input->getOption('dns'), $input->getOption('allow-self-signed-certificate'), $input->getOption('host'));
        list($chainOutput, $countOutput) = Factory::createOutput($loop, $input->getOption('tty'), $input->getOption('no-tty'));

        $parser = new SmokeParser();
        $builder = new TestPoolBuilder(new AnnotationReader());
        $runner = new PoolRunner(new GuzzleMessageFactory(), $client, $loop, $chainOutput);

        $testMethods = $parser->parse($input->getArgument('file'));
        $pool = $builder->build($testMethods);

        // Run the list of tests
        $runner->run($pool);

        // Return the number of failed tests
        return $countOutput->getFailed();
    }
}
