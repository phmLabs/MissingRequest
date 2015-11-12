<?php

namespace whm\MissingRequest\Cli\Command;

use GuzzleHttp\Psr7\Uri;
use phmLabs\XUnitReport\Elements\Failure;
use phmLabs\XUnitReport\Elements\TestCase;
use phmLabs\XUnitReport\XUnitReport;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;
use whm\MissingRequest\PhantomJS\HarRetriever;

class RunCommand extends Command
{
    protected function configure()
    {
        $this
            ->setDefinition(array(
                new InputArgument('url', InputArgument::REQUIRED, 'url to be scanned'),
                new InputArgument('requestfile', InputArgument::REQUIRED, 'file containing a list of mandatory requests'),
                new InputArgument('xunitfile', InputArgument::REQUIRED, 'xunit output file'),
            ))
            ->setDescription('check if requests are fired')
            ->setName('run');
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
        $urls = $this->getUrls($input->getArgument("requestfile"));

        $xUnitReport = new XUnitReport("MissingRequest - " . $input->getArgument("requestfile"));

        foreach ($urls as $key => $test) {
            $har = $harRetriever->getHarFile(new Uri($test["url"]));
            $mandatoryRequests = $test["requests"];

            $entries = $har->getEntries();
            $currentRequests = array_keys($entries);

            foreach ($mandatoryRequests as $key => $mandatoryRequest) {

                $testCase = new TestCase("MissingRequest", $test["url"] . " requests " . $mandatoryRequest, 0);

                $requestFound = false;
                foreach ($currentRequests as $currentRequest) {
                    if (preg_match("^" . $mandatoryRequest . "^", $currentRequest)) {
                        $requestFound = true;
                        break;
                    }
                }

                if (!$requestFound) {
                    $testCase->setFailure(new Failure("Missing request", "Request was not found (" . $mandatoryRequest . ")"));
                }

                $xUnitReport->addTestCase($testCase);
            }

        }

        file_put_contents($input->getArgument("xunitfile"), $xUnitReport->toXml());
    }
}