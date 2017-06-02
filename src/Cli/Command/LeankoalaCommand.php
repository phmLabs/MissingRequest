<?php

namespace whm\MissingRequest\Cli\Command;

use Koalamon\Client\Reporter\Reporter;
use Koalamon\CookieMakerHelper\CookieMaker;
use phm\HttpWebdriverClient\Http\Client\Chrome\ChromeClient;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use whm\Html\Uri;
use whm\MissingRequest\Reporter\Leankoala;

// http://status.leankoala.com/p/integrations/missingrequest/rest/config?integration_key=b312997e-122a-45ac-b25b-f1f2fd8effe4
// leankoala 'https://www.thewebhatesme.com/' -p ED7BD15E-86EA-430F-AFBD-A8F8A7ABC4367 -s https://monitor.leankoala.com/webhook/ -c '{"checkedRequests":{"89819915":{"pattern":"example","relation":"equals","value":"1", "name": "Google Analytics"}}}' -i 575 -z 640 -l '' -w webdriver

class LeankoalaCommand extends MissingRequestCommand
{
    const PROBE_COUNT = 2;
    const PHANTOM_TIMEOUT = 2000;

    const FALLBACK_STRING = 'FALLBACK';

    protected function configure()
    {
        $this
            ->setDefinition(array(
                new InputArgument('url', InputArgument::REQUIRED, 'The koalamon url'),
                new InputOption('koalamon_system_identifier', 'i', InputOption::VALUE_REQUIRED, 'Koalamon Server'),
                new InputOption('koalamon_system_url', 'u', InputOption::VALUE_REQUIRED, 'Koalamon System Identifier'),
                new InputOption('koalamon_system_collections', 'c', InputOption::VALUE_REQUIRED, 'Koalamon System Identifier'),
                new InputOption('koalamon_project_api_key', 'p', InputOption::VALUE_REQUIRED, 'Koalamon System Identifier'),
                new InputOption('koalamon_server', 's', InputOption::VALUE_OPTIONAL, 'Koalamon System Identifier'),
                new InputOption('koalamon_system_id', 'z', InputOption::VALUE_REQUIRED, 'Koalamon System ID'),
                new InputOption('login', 'l', InputOption::VALUE_OPTIONAL, 'Login credentials'),
                new InputOption('webdriverhost', 'w', InputOption::VALUE_OPTIONAL, 'Webdriver host', 'localhost'),
                new InputOption('webdriverport', 'x', InputOption::VALUE_OPTIONAL, 'Webdriver port', 4444),
                new InputOption('webdriversleep', 't', InputOption::VALUE_OPTIONAL, 'Webdriver sleep time', 5),
            ))
            ->setDescription('Checks if requests are fired and sends the results to koalamon')
            ->setName('leankoala');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $isFallbackServer = getenv('IS_FALLBACK_SERVER');

        $projectApiKey = $input->getOption('koalamon_project_api_key');

        $uri = new Uri($input->getArgument('url'));

        $incidentReporter = new Leankoala($projectApiKey, $input->getOption('koalamon_system_identifier'), $input->getOption('koalamon_system_id'), $input->getOption('koalamon_server'));

        $rawCollections = $input->getOption('koalamon_system_collections');

        if ($rawCollections == "[]") {
            $incidentReporter->addTestcase('', '', false, '', '', 'No requests defined.', [], "");
            $incidentReporter->getReport();
            return;
        }

        $mr2object = json_decode($rawCollections, true);

        $collections = array();

        foreach ($mr2object['checkedRequests'] as $key => $element) {
            $collections[$element['name']]['requests'][] = $element;
            $collections[$element['name']]['name'] = $element['name'];
        }

        /** @var ChromeClient $client */
        $client = $this->getClient($input->getOption('webdriverhost'), $input->getOption('webdriverport'), $input->getOption('webdriversleep'));

        $output->writeln('Checking ' . (string)$uri . ' ...');

        if ($input->getOption('login') && $input->getOption('login') != "[]") {
            $cookies = new CookieMaker();
            $cookies = $cookies->getCookies($input->getOption('login'));
        } else {
            $cookies = [];
        }

        $uri->addCookies($cookies);

        try {
            $results = $this->runSingleUrl($uri, $collections, $client, $output);
        } catch (\Exception $e) {
            if ($isFallbackServer !== "true") {
                die(self::FALLBACK_STRING);
            } else {
                throw $e;
            }
        }
        $client->close();

        $this->processResult($results, $incidentReporter);
    }

    private function processResult($results, Leankoala $incidentReporter)
    {
        foreach ($results[0] as $key => $result) {

            $requestFound = false;
            for ($i = 0; $i < self::PROBE_COUNT; $i++) {
                if ($results[$i][$key]['status'] == Reporter::RESPONSE_STATUS_SUCCESS) {
                    $requestFound = true;
                    break;
                };
            }

            $incidentReporter->addTestcase($result["url"], $result['mandatoryRequest'], !$requestFound, $result['groupName'], $result['pageKey'], $result['massage'], $result['requests'], $result['htmlContent']);
        }

        $incidentReporter->getReport();
    }
}
