<?php

namespace whm\MissingRequest\Cli\Command;

use GuzzleHttp\Psr7\Uri;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Dumper;
use whm\MissingRequest\PhantomJS\HarRetriever;
use whm\MissingRequest\PhantomJS\PhantomJsRuntimeException;

class CreateCommand extends Command
{
    protected function configure()
    {
        $this
            ->setDefinition(array(
                new InputArgument('url', InputArgument::REQUIRED, 'url to be scanned'),
                new InputArgument('output', InputArgument::REQUIRED, 'output file'),
                new InputArgument('identifier', InputArgument::REQUIRED, 'url identifier'),
            ))
            ->setDescription('Creates a config file')
            ->setName('create');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $harRetriever = new HarRetriever();
        try {
            $harInfo = $harRetriever->getHarFile(new Uri($input->getArgument('url')));
        } catch (PhantomJsRuntimeException $e) {
            $output->writeln("<error>" . $e->getMessage() . "</error>");
            exit($e->getExitCode());
        }

        $urls = array_keys($harInfo['harFile']->getEntries());

        $escapedUrls = array();
        foreach ($urls as $url) {
            $escapedUrls[] = preg_quote($url);
        }

        $config['urls'] = array();
        $config['urls'][$input->getArgument('identifier')] = array();
        $config['urls'][$input->getArgument('identifier')]['url'] = $input->getArgument('url');
        $config['urls'][$input->getArgument('identifier')]['requests']['standard'] = $escapedUrls;

        $dumper = new Dumper();
        $yaml = $dumper->dump($config, 5);

        // if file already exists append/merge the yaml
        file_put_contents($input->getArgument('output'), $yaml);

        $output->writeln("\n<info>   Config file was written (" . $input->getArgument('output') . '). ' . count($escapedUrls) . " requests found.</info>\n");
    }
}
