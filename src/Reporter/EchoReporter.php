<?php

namespace whm\MissingRequest\Reporter;

use Koalamon\Client\Reporter\Reporter as KoalamonReporter;
use Symfony\Component\Console\Output\OutputInterface;

class EchoReporter implements Reporter
{
    private $tests;

    private $output;

    public function __construct(OutputInterface $output)
    {
        $this->output = $output;
    }

    /**
     * @param boolean $isFailure
     */
    public function addTestcase($url, $mandatoryUrl, $isFailure, $groupKey, $urlKey, $message = '', $requests = [], $content = "")
    {
        if ($isFailure) {
            $this->tests[$url][$groupKey][$urlKey][] = ['url' => $mandatoryUrl, 'message' => $message];
        } else {
            $this->tests[$url][$groupKey][$urlKey][] = false;
        }
    }

    public function getReport()
    {
        foreach ($this->tests as $url => $urlKeys) {
            $message = '';
            $status = 'success';

            foreach ($urlKeys as $groupIdentifier => $groups) {
                foreach ($groups as $groupName => $missingUrls) {
                    foreach ($missingUrls as $missingUrl) {
                        if ($missingUrl !== false) {
                            $message .= ', ' . stripslashes($missingUrl['url']) . ': ' . $missingUrl['message'];
                            $status = 'failure';
                        }
                    }
                }
            }

            if ($status == 'success') {
                $message = ', all mandatory requests were found.';
            }

            $this->doReport($status, $message, $url);
        }

        return 'Incident was sent';
    }

    /**
     * @param string $status
     * @param string $message
     * @param string $identifier
     */
    private function doReport($status, $message, $url)
    {
        if ($status == KoalamonReporter::RESPONSE_STATUS_SUCCESS) {
            $this->output->write("\033[200D");
            $this->output->writeln('  <info>success      </info>' . substr((string)$url, 0, 90) . $message);
        } elseif ($status == KoalamonReporter::RESPONSE_STATUS_FAILURE) {
            $this->output->write("\033[200D");
            $this->output->writeln(' <error> failure     </error> ' . substr((string)$url, 0, 90) . $message);
        } else {
            $this->output->write("\033[200D");
            $this->output->writeln(' <comment> error      </comment> ' . substr((string)$url, 0, 90) . $message);

        }
    }
}
