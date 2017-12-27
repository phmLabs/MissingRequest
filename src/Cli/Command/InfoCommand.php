<?php

namespace whm\MissingRequest\Cli\Command;

use Leankoala\Devices\DeviceFactory;
use phm\HttpWebdriverClient\Http\Client\HeadlessChrome\HeadlessChromeClient;
use phm\HttpWebdriverClient\Http\Request\BrowserRequest;
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
                new InputOption('client_timeout', 't', InputOption::VALUE_OPTIONAL, 'Client timeout', '31000'),
                new InputOption('device', 'd', InputOption::VALUE_OPTIONAL, 'Device', 'MacBookPro152017'),
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
        /** @var HeadlessChromeClient $client */
        $client = $this->getClient($input->getOption('client_timeout'), true);

        try {
            $headers = ['Accept-Encoding' => 'gzip', 'Connection' => 'keep-alive'];

            $request = new BrowserRequest('GET', new Uri($input->getArgument('url')), $headers);

            $deviceFactory = new DeviceFactory();
            $device = $deviceFactory->create($input->getOption('device'));
            $request->setDevice($device);

            $response = $client->sendRequest($request);
        } catch (\Exception $e) {
            $client->close();
            $output->writeln("<error>" . $e->getMessage() . "</error>");
            exit(1);
        }

        $client->close();

        $urls = $response->getResources();

        $output->writeln("\n<info>Scanning " . $input->getArgument('url') . "</info>\n");

        foreach ($urls as $url) {
            $output->writeln(' - ' . $url['name'] . ' (HTTP: ' . $url['http_status'] . ')');
        }

        $timeout = $response->isTimeout() ? 'true' : 'false';
        $output->writeln("\n<info>   " . count($urls) . " requests found</info> (timeout: " . $timeout . ")\n");
    }
}
