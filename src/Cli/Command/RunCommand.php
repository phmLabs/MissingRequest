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
use whm\MissingRequest\Reporter\XUnit;

class RunCommand extends Command
{
    protected function configure()
    {
        $this
            ->setDefinition(array(
                new InputArgument('requestfile', InputArgument::REQUIRED, 'file containing a list of mandatory requests'),
                new InputOption('outputfile', 'o', InputOption::VALUE_OPTIONAL, 'filename to store result', null),
                new InputOption('format', 'f', InputOption::VALUE_OPTIONAL, 'output format (default: xunit | available: xunit)', 'xunit'),
            ))
            ->setDescription('Checks if requests are fired')
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

        switch ($input->getOption('format')) {
            case 'xunit':
                $reporter = new XUnit($input->getOption("outputfile"));
                break;
            default:
                throw new \RuntimeException("Format (" . $input->getOption('format') . ") not found. ");
        }

        $urls = $this->getUrls($input->getArgument("requestfile"));

        foreach ($urls as $key => $test) {
            $har = $harRetriever->getHarFile(new Uri($test["url"]));
            $mandatoryRequests = $test["requests"];

            $entries = $har->getEntries();
            $currentRequests = array_keys($entries);

            foreach ($mandatoryRequests as $key => $mandatoryRequest) {

                $requestFound = false;
                foreach ($currentRequests as $currentRequest) {
                    if (preg_match("^" . $mandatoryRequest . "^", $currentRequest)) {
                        $requestFound = true;
                        break;
                    }
                }
                $reporter->addTestcase($test["url"], $mandatoryRequest, !$requestFound);
            }
        }
        $result = $reporter->getReport();

        if ($input->getOption('outputfile') == NULL) {
            $output->writeln($result);
        } else {
            file_put_contents($input->getOption('outputfile'), $result);
        }
    }
}
