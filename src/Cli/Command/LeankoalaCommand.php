<?php

namespace whm\MissingRequest\Cli\Command;

use Cache\Adapter\Filesystem\FilesystemCachePool;
use GuzzleHttp\Psr7\Request;
use Koalamon\Client\Reporter\Reporter;
use Koalamon\CookieMakerHelper\CookieMaker;
use League\Flysystem\Adapter\Local;
use League\Flysystem\Filesystem;
use phm\HttpWebdriverClient\Http\ChromeClient;
use phm\HttpWebdriverClient\Http\Decorator\CacheDecorator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use whm\Html\Uri;
use whm\MissingRequest\Reporter\Leankoala;

// http://status.leankoala.com/p/integrations/missingrequest/rest/config?integration_key=b312997e-122a-45ac-b25b-f1f2fd8effe4
// koalamonsystem 'https://www.thewebhatesme.com/wp-admin/' -p 416C70E7-B3B5-4CF0-8B98-16C57843E40F -s https://monitor.leankoala.com/webhook/ -c '[{"name":"Google Analytics","requests":[{"name":"JavaScript Request","pattern":"http:\/\/www.google.de\/analytics.js","count":1}]}]' -i 101 -l '{"name":"User: Nils (Capital N)","action":"https:\/\/www.thewebhatesme.com\/wp-login.php","url":"https:\/\/www.thewebhatesme.com\/wp-login.php","fields":{"log":"Nils","pwd":"langner"}}'

// leankoala 'https://www.thewebhatesme.com/' 1 demo 22F112F4-1974-4DBA-A94B-1DF7ED4A99BD '{"checkedRequests":{"89819915":{"pattern":"example","relation":"equals","value":"1"}}}' http://lean.xcel.io/webhook/ 2 ''

// leankoala       'https://www.thewebhatesme.com/'       -p ED7BD15E-86EA-430F-AFBD-A8F8A7ABC4367 -s https://monitor.leankoala.com/webhook/ -c '{"checkedRequests":{"89819915":{"pattern":"example","relation":"equals","value":"1", "name": "Google Analytics"}}}' -i 575 -z 640 -l '' -w webdriver

// koalamonsystem 'https://cafe.finanzcheck-test.de/flow?amount=10000&term=48&purpose=other&productId=36&configuration=erzZBpdsmNJd&__definitions=angestellter,angestellter' -p ED7BD15E-86EA-430F-AFBD-A8F8A7ABC4367 -s https://monitor.leankoala.com/webhook/ -c '{"checkedRequests":{"89819915":{"pattern":"example","relation":"equals","value":"1"}}}' -i 575 -z 640 -l '' -w webdriver

class LeankoalaCommand extends Command
{
    const PROBE_COUNT = 2;
    const PHANTOM_TIMEOUT = 2000;

    protected function configure()
    {
        $this
            ->setDefinition(array(
                new InputArgument('url', InputArgument::REQUIRED, 'The koalamon url'),
                new InputOption('debugdir', 'd', InputOption::VALUE_OPTIONAL, 'directory where to put the html files in case of an error'),
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

    private function getClient($host, $port, $sleep)
    {
        $chromeClient = new ChromeClient($host, $port, $sleep);

        $filesystemAdapter = new Local('/tmp/cached/missing/');
        $filesystem = new Filesystem($filesystemAdapter);
        $cachePoolInterface = new FilesystemCachePool($filesystem);
        $client = new CacheDecorator($chromeClient, $cachePoolInterface);

        return $client;
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $projectApiKey = $input->getOption('koalamon_project_api_key');
        $uri = new Uri($input->getArgument('url'));

        $mr2object = json_decode($input->getOption('koalamon_system_collections'), true);
        $collections = array();

        foreach ($mr2object['checkedRequests'] as $key => $element) {
            $collections[$element['name']]['requests'][] = $element;
            $collections[$element['name']]['name'] = $element['name'];
        }

        $incidentReporter = new Leankoala($projectApiKey, $input->getOption('koalamon_system_identifier'), $input->getOption('koalamon_system_id'), $input->getOption('koalamon_server'));

        $output->writeln('Checking ' . (string)$uri . ' ...');

        if ($input->getOption('login') && $input->getOption('login') != "[]") {
            $cookies = new CookieMaker();
            $cookies = $cookies->getCookies($input->getOption('login'));
            $uri->addCookies($cookies);
        }

        $client = $this->getClient($input->getOption('webdriverhost'), $input->getOption('webdriverport'), $input->getOption('webdriversleep'));

        $results = array();
        $failure = false;

        for ($i = 0; $i < self::PROBE_COUNT; $i++) {

            // only run n-th time of elements were not found
            if (!$failure && $i != 0) {
                $results[$i] = $results[$i - 1];
                continue;
            }

            try {
                $response = $client->sendRequest(new Request('GET', $uri));
            } catch (\Exception $e) {
                $output->writeln("<error>" . $e->getMessage() . "</error>");
                exit(1);
            }

            foreach ($collections as $collection) {

                foreach ($collection['requests'] as $mandatoryRequest) {

                    $name = $mandatoryRequest['name'];
                    $pattern = $mandatoryRequest['pattern'];
                    $count = $mandatoryRequest['value'];
                    $relation = $mandatoryRequest['relation'];

                    $numFound = $response->getRequestCount($pattern);

                    switch ($relation) {
                        case 'equals':
                            $result = $numFound == $count;
                            $message = 'Request was found ' . $numFound . ' times. Expected was ' . $count . ' times.';
                            break;
                        case  'less':
                            $result = $numFound < $count;
                            $message = 'Request was found ' . $numFound . ' times. Expected was less than ' . $count . ' times.';
                            break;
                        case 'greater':
                            $result = $numFound > $count;
                            $message = 'Request was found ' . $numFound . ' times. Expected was more than ' . $count . ' times.';
                            break;
                        default:
                            $result = false;
                            $message = 'The relation (' . $relation . ') was not found.';
                    }

                    if ($result) {
                        $status = Reporter::RESPONSE_STATUS_SUCCESS;
                        $message = '';
                    } else {
                        $status = Reporter::RESPONSE_STATUS_FAILURE;
                        $failure = true;
                    }

                    $results[$i][] = array(
                        "url" => (string)$uri,
                        'mandatoryRequest' => $name . " (" . $pattern . ")",
                        'status' => $status,
                        'massage' => $message,
                        'groupName' => $collection['name'],
                        'pageKey' => $name,
                        'htmlContent' => $response->getBody(),
                    );
                }
            }
        }

        $this->processResult($results, $incidentReporter, $input->getOption('debugdir'));
    }

    private function processResult($results, Leankoala $incidentReporter, $debugDir)
    {
        foreach ($results[0] as $key => $result) {

            $requestFound = false;
            for ($i = 0; $i < self::PROBE_COUNT; $i++) {
                if ($results[$i][$key]['status'] == Reporter::RESPONSE_STATUS_SUCCESS) {
                    $requestFound = true;
                    break;
                };
            }

            $incidentReporter->addTestcase($result["url"], $result['mandatoryRequest'], !$requestFound, $result['groupName'], $result['pageKey'], $result['massage']);

            if (!$requestFound && $debugDir != null) {
                $htmlFileName = $debugDir . DIRECTORY_SEPARATOR . $result['pageKey'] . '.html';
                $harFileName = $debugDir . DIRECTORY_SEPARATOR . $result['pageKey'] . '.har';

                file_put_contents($htmlFileName, $result['htmlContent']);
                file_put_contents($harFileName, $result['harContent'], JSON_PRETTY_PRINT);
            }
        }

        $incidentReporter->getReport();
    }
}
