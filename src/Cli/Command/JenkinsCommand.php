<?php

namespace whm\MissingRequest\Cli\Command;

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
use whm\MissingRequest\Reporter\XUnit;

class JenkinsCommand extends Command
{
    protected function configure()
    {
        $this
            ->setDefinition(array(
                new InputArgument('apikey', InputArgument::REQUIRED, 'koalamon.com API key'),
                new InputArgument('requestfile', InputArgument::REQUIRED, 'file containing a list of mandatory requests'),
                new InputOption('outputfile', 'o', InputOption::VALUE_REQUIRED, 'filename to store result', null),
                new InputOption('debugdir', 'd', InputOption::VALUE_OPTIONAL, 'directory where to put the html files in case of an error'),
            ))
            ->setDescription('Checks if requests are fired')
            ->setName('jenkins');
    }

    private function getUrls($filename)
    {
        $config = Yaml::parse(file_get_contents($filename));
        $urls = $config['urls'];

        return $urls;
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $harRetriever = new HarRetriever();

        $xunitReporter = new XUnit($input->getOption('outputfile'));
        $incidentReporter = new Incident($input->getArgument('apikey'));

        $urls = $this->getUrls($input->getArgument('requestfile'));

        foreach ($urls as $pageKey => $test) {
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

            $requestNotFound = false;

            foreach ($requestGroups as $groupName => $mandatoryRequests) {
                $groupFailed = false;
                foreach ($mandatoryRequests as $mandatoryRequest) {
                    $requestFound = false;
                    foreach ($currentRequests as $currentRequest) {
                        if (preg_match('^' . $mandatoryRequest . '^', $currentRequest)) {
                            $requestFound = true;
                            break;
                        }
                    }
                    if (!$requestFound) {
                        $requestNotFound = true;
                        $groupFailed = true;
                    }

                    $xunitReporter->addTestcase($test['url'], $mandatoryRequest, !$requestFound, $groupName, $pageKey);
                    $incidentReporter->addTestcase($test['url'], $mandatoryRequest, !$requestFound, $groupName, $pageKey);
                }

                if ($groupFailed === false) {
                    $output->writeln("  check " . count($mandatoryRequests) . " URLs of '$groupName' for missing requests. [<info> OK </info>]");
                } else {
                    $output->writeln("  check " . count($mandatoryRequests) . " URLs of <error>'$groupName'</error> for missing requests. <error>[FAIL]</error>");
                }
            }

            if ($requestNotFound && $input->getOption('debugdir') != null) {
                $htmlFileName = $input->getOption('debugdir') . DIRECTORY_SEPARATOR . $pageKey . '.html';
                file_put_contents($htmlFileName, $htmlContent);

                $harFileName = $input->getOption('debugdir') . DIRECTORY_SEPARATOR . $pageKey . '.har';
                file_put_contents($harFileName, json_encode((array)$harInfo['harFile'], JSON_PRETTY_PRINT));
            }
        }

        $result = $xunitReporter->getReport();
        $incidentReporter->getReport();

        if ($input->getOption('outputfile') == null) {
            $output->writeln($result);
        } else {
            file_put_contents($input->getOption('outputfile'), $result);
        }
    }
}
