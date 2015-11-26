<?php

namespace whm\MissingRequest\Cli\Command;

use GuzzleHttp\Psr7\Uri;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use whm\MissingRequest\PhantomJS\HarRetriever;

class InfoCommand extends Command
{
    protected function configure()
    {
        $this
            ->setDefinition(array(
                new InputArgument('url', InputArgument::REQUIRED, 'url to be scanned'),

            ))
            ->setDescription('Shows all requests')
            ->setName('info');
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $harRetriever = new HarRetriever();
        $harFile = $harRetriever->getHarFile(new Uri($input->getArgument('url')));

        $urls = array_keys($harFile['harFile']->getEntries());

        $output->writeln("\n<info>Scanning ".$input->getArgument('url')."</info>\n");

        foreach ($urls as $url) {
            $output->writeln(' - '.$url);
        }

        $output->writeln("\n<info>   ".count($urls)." requests found</info>\n");
    }
}
