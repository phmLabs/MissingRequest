<?php

namespace whm\MissingRequest\Cli\Command;

use Koalamon\Client\Reporter\Reporter;
use Koalamon\CookieMakerHelper\CookieMaker;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use whm\Html\Uri;
use whm\MissingRequest\PhantomJS\HarRetriever;
use whm\MissingRequest\PhantomJS\PhantomJsRuntimeException;
use whm\MissingRequest\Reporter\Incident;

// http://status.leankoala.com/p/integrations/missingrequest/rest/config?integration_key=b312997e-122a-45ac-b25b-f1f2fd8effe4
// koalamonsystem 'https://www.thewebhatesme.com/wp-admin/' -p 416C70E7-B3B5-4CF0-8B98-16C57843E40F -s https://monitor.leankoala.com/webhook/ -c '[{"name":"Google Analytics","requests":[{"name":"JavaScript Request","pattern":"http:\/\/www.google.de\/analytics.js","count":1}]}]' -i 101 -l '{"name":"User: Nils (Capital N)","action":"https:\/\/www.thewebhatesme.com\/wp-login.php","url":"https:\/\/www.thewebhatesme.com\/wp-login.php","fields":{"log":"Nils","pwd":"langner"}}'

class KoalamonSystemCommand extends Command
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
            ))
            ->setDescription('Checks if requests are fired and sends the results to koalamon')
            ->setName('koalamonsystem');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $harRetriever = new HarRetriever();

        $projectApiKey = $input->getOption('koalamon_project_api_key');
        $url = $input->getArgument('url');
        $collections = json_decode($input->getOption('koalamon_system_collections'), true);

        if ($input->getOption('koalamon_server')) {
            $incidentReporter = new Incident($projectApiKey, $input->getOption('koalamon_system_identifier'), $input->getOption('koalamon_system_id'), $input->getOption('koalamon_server'));
        } else {
            $incidentReporter = new Incident($projectApiKey, $input->getOption('koalamon_system_identifier', $input->getOption('koalamon_system_id')));
        }

        $output->writeln('Checking ' . $url . ' ...');

        $uri = new Uri($url);

        if ($input->getOption('login')) {
            $cookies = new CookieMaker();
            $cookies = $cookies->getCookies($input->getOption('login'));
            $uri->addCookies($cookies);
        }

        for ($i = 0; $i < self::PROBE_COUNT; $i++) {

            try {
                $harInfo = $harRetriever->getHarFile($uri, self::PHANTOM_TIMEOUT);
            } catch (PhantomJsRuntimeException $e) {
                $output->writeln("<error>" . $e->getMessage() . "</error>");
                exit(1);
            }

            $htmlContent = $harInfo['html'];
            $entries = $harInfo['harFile']->getEntries();

            $actualRequests = array_keys($entries);

            foreach ($collections as $collection) {

                foreach ($collection['requests'] as $mandatoryRequest) {
                    $name = $mandatoryRequest['name'];
                    $pattern = $mandatoryRequest['pattern'];
                    $count = $mandatoryRequest['count'];

                    $numFound = 0;

                    foreach ($actualRequests as $actualRequest) {
                        if (preg_match('^' . $pattern . '^', $actualRequest)) {
                            $numFound++;
                        }
                    }

                    if ($numFound == $count) {
                        $status = Reporter::RESPONSE_STATUS_SUCCESS;
                        $message = '';
                    } else {
                        $status = Reporter::RESPONSE_STATUS_FAILURE;
                        $message = 'Request was found ' . $numFound . ' times. Expected was ' . $count . ' times.';
                    }

                    $results[$i][] = array("url" => $url,
                        'mandatoryRequest' => $pattern . " (" . $name . ")",
                        'status' => $status,
                        'massage' => $message,
                        'groupName' => $collection['name'],
                        'pageKey' => $name,
                        'htmlContent' => $htmlContent,
                        'harContent' => json_encode((array)$harInfo['harFile']));
                }
            }
        }
        $this->processResult($results, $incidentReporter, $input->getOption('debugdir'));
    }

    private function processResult($results, Incident $incidentReporter, $debugDir)
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
