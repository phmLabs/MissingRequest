<?php

namespace whm\MissingRequest\Cli\Command;

use GuzzleHttp\Psr7\Request;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use whm\Html\Uri;

class InfoCommand extends MissingRequestCommand
{
    protected function configure()
    {
        $this
            ->setDefinition(array(
                new InputArgument('url', InputArgument::REQUIRED, 'url to be scanned'),
                new InputOption('webdriverhost', 'w', InputOption::VALUE_OPTIONAL, 'Webdriver host', 'localhost'),
                new InputOption('webdriverport', 'x', InputOption::VALUE_OPTIONAL, 'Webdriver port', 4444),
                new InputOption('webdriversleep', 't', InputOption::VALUE_OPTIONAL, 'Webdriver sleep', 1),
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
        $client = $this->getClient($input->getOption('webdriverhost'), $input->getOption('webdriverport'), $input->getOption('webdriversleep'));

        try {
            $headers = ['Accept-Encoding' => 'gzip', 'Connection' => 'keep-alive'];
            $response = $client->sendRequest(new Request('GET', new Uri($input->getArgument('url')), $headers));
        } catch (\Exception $e) {
            $client->close();
            $output->writeln("<error>" . $e->getMessage() . "</error>");
            exit(1);
        }

        $client->close();

        $urls = $response->getResources();

        $output->writeln("\n<info>Scanning " . $input->getArgument('url') . "</info>\n");

        foreach ($urls as $url) {
            $output->writeln(' - ' . $url['name']);
        }

        $output->writeln("\n<info>   " . count($urls) . " requests found</info>\n");
    }
}
