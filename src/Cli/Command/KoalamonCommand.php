<?php

namespace whm\MissingRequest\Cli\Command;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Uri;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;
use whm\MissingRequest\PhantomJS\HarRetriever;
use whm\MissingRequest\PhantomJS\PhantomJsRuntimeException;
use whm\MissingRequest\Reporter\Incident;

class KoalamonCommand extends Command
{
    protected function configure()
    {
        $this
            ->setDefinition(array(
                new InputArgument('url', InputArgument::REQUIRED, 'The koalamon url'),
                new InputOption('debugdir', 'd', InputOption::VALUE_OPTIONAL, 'directory where to put the html files in case of an error'),
            ))
            ->setDescription('Checks if requests are fired and sends the results to koalamon')
            ->setName('koalamon');
    }

    private function getUrls($url)
    {
        $httpClient = new Client();
        $content = $httpClient->get(new Uri($url));

        $config = json_decode($content->getBody());

        $projects = array();

        foreach ($config as $configElement) {

            $urls = array();

            $pageKey = $configElement->system->name;
            $url = $configElement->system->url;

            $urls[$pageKey]["url"] = $url;
            $urls[$pageKey]["project"] = $configElement->system->project;

            $requests = array();

            foreach ($configElement->collections as $collection) {
                $requests[$collection->name] = array();
                foreach ($collection->requests as $collectionRequest) {
                    $requests[$collection->name][] = $collectionRequest->pattern;
                }
            }

            $urls[$pageKey]['requests'] = $requests;

            if (!array_key_exists($configElement->system->project->identifier, $projects)) {
                $projects[$configElement->system->project->identifier] = array();
            }
            if (!array_key_exists('urls', $projects[$configElement->system->project->identifier])) {
                $projects[$configElement->system->project->identifier]['urls'] = [];
            }

            $projects[$configElement->system->project->identifier]['project'] = $configElement->system->project;
            $projects[$configElement->system->project->identifier]['urls'] = array_merge($urls, $projects[$configElement->system->project->identifier]['urls']);
        }

        return $projects;
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $testCount = 2;
        $harRetriever = new HarRetriever();

        $projects = $this->getUrls($input->getArgument('url'));

        $results = array();

        foreach ($projects as $project) {

            $incidentReporter = new Incident($project['project']->api_key);

            foreach ($project['urls'] as $pageKey => $test) {
                $output->writeln('Checking ' . $test['url'] . ' ...');
                for ($i = 0; $i < $testCount; $i++) {

                    try {
                        $harInfo = $harRetriever->getHarFile(new Uri($test['url']));
                    } catch (PhantomJsRuntimeException $e) {
                        $output->writeln("<error>" . $e->getMessage() . "</error>");
                        exit($e->getExitCode());
                    }

                    $htmlContent = $harInfo['html'];

                    $entries = $harInfo['harFile']->getEntries();

                    $currentRequests = array_keys($entries);
                    $requestGroups = $test['requests'];

                    foreach ($requestGroups as $groupName => $mandatoryRequests) {
                        foreach ($mandatoryRequests as $mandatoryRequest) {
                            $requestFound = false;
                            foreach ($currentRequests as $currentRequest) {
                                if (preg_match('^' . $mandatoryRequest . '^', $currentRequest)) {
                                    $requestFound = true;
                                    break;
                                }
                            }
                            $results[$i][] = array("url" => $test["url"],
                                'mandatoryRequest' => $mandatoryRequest,
                                'requestFound' => $requestFound,
                                'groupName' => $groupName,
                                'pageKey' => $pageKey,
                                'htmlContent' => $htmlContent,
                                'harContent' => json_encode((array)$harInfo['harFile']));
                        }
                    }
                }
            }


            foreach ($results[0] as $key => $result) {

                $requestFound = false;
                for ($i = 0; $i < $testCount; $i++) {
                    if ($results[$i][$key]['requestFound']) {
                        $requestFound = true;
                        break;
                    };
                }

                $incidentReporter->addTestcase($result["url"], $result['mandatoryRequest'], !$requestFound, $result['groupName'], $result['pageKey']);

                if (!$requestFound && $input->getOption('debugdir') != null) {
                    $htmlFileName = $input->getOption('debugdir') . DIRECTORY_SEPARATOR . $result['pageKey'] . '.html';
                    $harFileName = $input->getOption('debugdir') . DIRECTORY_SEPARATOR . $result['pageKey'] . '.har';

                    file_put_contents($htmlFileName, $result['htmlContent']);
                    file_put_contents($harFileName, $result['harContent'], JSON_PRETTY_PRINT);
                }
            }

            $incidentReporter->getReport();
        }
    }
}
