<?php

namespace whm\MissingRequest\Cli;

use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;
use whm\MissingRequest\Cli\Command\CrawlCommand;
use whm\MissingRequest\Cli\Command\CreateCommand;
use whm\MissingRequest\Cli\Command\InfoCommand;
use whm\MissingRequest\Cli\Command\JenkinsCommand;
use whm\MissingRequest\Cli\Command\KoalamonCommand;
use whm\MissingRequest\Cli\Command\KoalamonSystemCommand;
use whm\MissingRequest\Cli\Command\LeankoalaCommand;
use whm\MissingRequest\Cli\Command\RunCommand;

class Application extends \Symfony\Component\Console\Application
{
    public function __construct()
    {
        parent::__construct('MissingRequest', MISSING_VERSION);
    }

    /**
     * {@inheritdoc}
     */
    public function run(InputInterface $input = null, OutputInterface $output = null)
    {
        if (null === $output) {
            $styles = array();
            $styles['failure'] = new OutputFormatterStyle('red');
            $formatter = new OutputFormatter(false, $styles);
            $output = new ConsoleOutput(ConsoleOutput::VERBOSITY_NORMAL, null, $formatter);
        }

        return parent::run($input, $output);
    }

    /**
     * {@inheritdoc}
     */
    public function doRun(InputInterface $input, OutputInterface $output)
    {
        $this->registerCommands();

        return parent::doRun($input, $output);
    }

    /**
     * Initializes all the commands.
     */
    private function registerCommands()
    {
        $this->add(new InfoCommand());
        $this->add(new CreateCommand());
        $this->add(new LeankoalaCommand());
    }
}
