<?php

namespace whm\MissingRequest\Cli\Command;

use GuzzleHttp\Psr7\Uri;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Exception\RuntimeException;
use Symfony\Component\Yaml\Yaml;
use whm\MissingRequest\PhantomJS\HarRetriever;
use whm\MissingRequest\Reporter\Incident;
use whm\MissingRequest\Reporter\XUnit;

class JenkinsCommand extends Command
{
    protected function configure()
    {
        $this
            ->setDefinition(array(
                new InputArgument('requestfile', InputArgument::REQUIRED, 'file containing a list of mandatory requests'),
                new InputOption('outputfile', 'o', InputOption::VALUE_OPTIONAL, 'filename to store result', null),
            ))
            ->setDescription('Checks if requests are fired')
            ->setName('jenkins');
    }

    private function getUrls($filename)
    {
        $config = Yaml::parse(file_get_contents($filename));
        $urls = $config["urls"];

        return $urls;
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $harRetriever = new HarRetriever();

        $xunitReporter = new XUnit($input->getOption("outputfile"));
        $incidentReporter = new Incident();

        $urls = $this->getUrls($input->getArgument("requestfile"));

        foreach ($urls as $pageKey => $test) {
            $entries = $harRetriever->getHarFile(new Uri($test["url"]))->getEntries();
            $currentRequests = array_keys($entries);
            $requestGroups = $test["requests"];

            foreach($requestGroups as $groupName => $mandatoryRequests) {
                foreach ($mandatoryRequests as $mandatoryRequest) {
                    $requestFound = false;
                    foreach ($currentRequests as $currentRequest) {
                        if (preg_match("^" . $mandatoryRequest . "^", $currentRequest)) {
                            $requestFound = true;
                            break;
                        }
                    }
                    $xunitReporter->addTestcase($test["url"], $mandatoryRequest, !$requestFound, $groupName, $pageKey);
                    $incidentReporter->addTestcase($test["url"], $mandatoryRequest, !$requestFound, $groupName, $pageKey);
                }
            }
        }

        $result = $xunitReporter->getReport();
        $incidentReporter->getReport();

        if ($input->getOption('outputfile') == NULL) {
            $output->writeln($result);
        } else {
            file_put_contents($input->getOption('outputfile'), $result);
        }
    }
}
