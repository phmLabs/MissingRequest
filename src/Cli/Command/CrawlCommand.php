<?php

namespace whm\MissingRequest\Cli\Command;

use Koalamon\CookieMakerHelper\CookieMaker;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use whm\Crawler\PageContainer\Decorator\Filter\Filter\SameDomainFilter;
use whm\Crawler\PageContainer\Decorator\Filter\Filter\SubstringNotExistsFilter;
use whm\Crawler\PageContainer\Decorator\Filter\FilterDecorator;
use whm\Crawler\PageContainer\PatternAwareContainer;
use whm\Html\Document;
use whm\Html\Uri;
use whm\MissingRequest\Reporter\EchoReporter;
use whm\MissingRequest\Reporter\Reporter;
use Koalamon\Client\Reporter\Reporter as KoalamonReporter;


class CrawlCommand extends MissingRequestCommand
{
    const PROBE_COUNT = 2;
    const PHANTOM_TIMEOUT = 2000;

    protected function configure()
    {
        $this
            ->setDefinition(array(
                new InputArgument('url', InputArgument::REQUIRED, 'The koalamon url'),
                new InputOption('login', 'l', InputOption::VALUE_OPTIONAL, 'Login credentials'),
                new InputOption('webdriverhost', 'w', InputOption::VALUE_OPTIONAL, 'Webdriver host', 'localhost'),
                new InputOption('webdriverport', 'x', InputOption::VALUE_OPTIONAL, 'Webdriver port', 4444),
                new InputOption('webdriversleep', 't', InputOption::VALUE_OPTIONAL, 'Webdriver sleep time', 5),
                new InputOption('mandatory_requests', 'r', InputOption::VALUE_REQUIRED, 'Mandatory requests.'),
                new InputOption('url_count', 'c', InputOption::VALUE_REQUIRED, 'Mandatory requests.', 2),
            ))
            ->setDescription('Checks if requests are fired and sends the results to koalamon')
            ->setName('crawl');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $startUri = new Uri($input->getArgument('url'));

        $mr2object = json_decode($input->getOption('mandatory_requests'), true);
        $collections = array();

        foreach ($mr2object['checkedRequests'] as $key => $element) {
            $collections[$element['name']]['requests'][] = $element;
            $collections[$element['name']]['name'] = $element['name'];
        }

        // $incidentReporter = new Leankoala($projectApiKey, $input->getOption('koalamon_system_identifier'), $input->getOption('koalamon_system_id'), $input->getOption('koalamon_server'));
        $client = $this->getClient($input->getOption('webdriverhost'), $input->getOption('webdriverport'), $input->getOption('webdriversleep'));

        if ($input->getOption('login') && $input->getOption('login') != "[]") {
            $cookies = new CookieMaker();
            $cookies = $cookies->getCookies($input->getOption('login'));
        } else {
            $cookies = [];
        }

        $output->writeln("");
        $output->writeln("<comment>Starting crawl.</comment>");
        $output->writeln("");

        $maxUrls = $input->getOption('url_count');
        $count = 1;

        $patternAwareContainer = new PatternAwareContainer();
        $urlContainer = new FilterDecorator($patternAwareContainer);
        $urlContainer->addFilter(new SubstringNotExistsFilter(['.js', '.css', '.png', '.jpg', '.jpeg', 'gif']));
        $urlContainer->addFilter(new SameDomainFilter($startUri));

        $nextUri = $startUri;

        while ($nextUri && $count <= $maxUrls) {
            /** @var $uri Uri */
            ++$count;
            $nextUri->addCookies($cookies);
            $output->write(" <question> crawling... </question> " . substr((string)$nextUri, 0, 90) . ' ');

            try {
                $results = $this->runSingleUrl($nextUri, $collections, $client, $output);
            } catch (\Exception $e) {
                $results[0][0] = array(
                    "url" => (string)$nextUri,
                    'mandatoryRequest' => "",
                    'status' => KoalamonReporter::RESPONSE_STATUS_ERROR,
                    'massage' => $e->getMessage(),
                    'groupName' => '',
                    'pageKey' => "",
                    'htmlContent' => "",
                    'requests' => []
                );
            }

            $htmlDocument = new Document($results[0][0]['htmlContent']);
            $dependencies = $htmlDocument->getDependencies($startUri);

            foreach ($dependencies as $dependency) {
                $urlContainer->push($dependency);
            }

            $this->processResult($results, new EchoReporter($output));

            $nextUris = $urlContainer->pop(1);
            if (count($nextUris) > 0) {
                $nextUri = $nextUris[0];
            } else {
                $nextUri = false;
            }
        }
        $output->writeln("");
    }

    private function processResult($results, Reporter $incidentReporter)
    {
        foreach ($results[0] as $key => $result) {

            $requestFound = false;
            for ($i = 0; $i < self::PROBE_COUNT; $i++) {
                if ($results[$i][$key]['status'] == KoalamonReporter::RESPONSE_STATUS_SUCCESS) {
                    $requestFound = true;
                    break;
                };
                if ($results[$i][$key]['status'] == KoalamonReporter::RESPONSE_STATUS_ERROR) {
                    $requestFound = false;
                    break;
                }
            }

            $incidentReporter->addTestcase($result["url"], $result['mandatoryRequest'], !$requestFound, $result['groupName'], $result['pageKey'], $result['massage']);
        }

        $incidentReporter->getReport();
    }
}
