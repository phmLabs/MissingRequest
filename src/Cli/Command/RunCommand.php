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

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $mandatoryRequests = file($input->getArgument("requestfile"), FILE_IGNORE_NEW_LINES);

        $harRetriever = new HarRetriever();
        $har = $harRetriever->getHarFile(new Uri($input->getArgument('url')));

        $entries = $har->getEntries();

        $missingRequests = $mandatoryRequests;

        $currentRequests = array_keys($entries);

        foreach ($mandatoryRequests as $key => $mandatoryRequest) {

            foreach ($currentRequests as $currentRequest) {
                if (preg_match("^" . $mandatoryRequest . "^", $currentRequest)) {
                    unset($missingRequests[$key]);
                    break;
                }
            }
        }

        $xUnitReport = new XUnitReport("MissingRequest - " . $input->getArgument("url"));

        foreach ($mandatoryRequests as $mandatoryRequest) {
            $testCase = new TestCase("MissingRequest", $mandatoryRequest, 0);
            if (in_array($mandatoryRequest, $missingRequests)) {
                $testCase->setFailure(new Failure("MissingRequest", "The request " . $mandatoryRequest . " could not be found."));
            }
            $xUnitReport->addTestCase($testCase);
        }

        file_put_contents($input->getArgument("xunitfile"), $xUnitReport->toXml());
    }
}