<?php

namespace whm\MissingRequest\Cli\Command;

use GuzzleHttp\Psr7\Request;
use phm\HttpWebdriverClient\Http\HttpClient;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class InfoCommand extends Command
{
    protected function configure()
    {
        $this
            ->setDefinition(array(
                new InputArgument('url', InputArgument::REQUIRED, 'url to be scanned'),
                new InputOption('webdriverhost', 'w', InputOption::VALUE_OPTIONAL, 'Webdriver host', 'localhost'),
                new InputOption('webdriverport', 'x', InputOption::VALUE_OPTIONAL, 'Webdriver port', 4444),
            ))
            ->setDescription('Shows all requests')
            ->setName('info');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $client = new HttpClient($input->getOption('webdriverhost'), $input->getOption('webdriverport'));
        try {
            $response = $client->sendRequest(new Request('GET', $input->getArgument('url')));
        } catch (\Exception $e) {
            $output->writeln("<error>" . $e->getMessage() . "</error>");
            exit($e->getExitCode());
        }

        $urls = $response->getRequests();

        $output->writeln("\n<info>Scanning " . $input->getArgument('url') . "</info>\n");

        foreach ($urls as $url) {
            $output->writeln(' - ' . $url['name']);
        }

        $output->writeln("\n<info>   " . count($urls) . " requests found</info>\n");
    }
}
