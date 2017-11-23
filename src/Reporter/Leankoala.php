<?php

namespace whm\MissingRequest\Reporter;

use GuzzleHttp\Client;
use Koalamon\Client\Reporter\Event;

class Leankoala implements Reporter
{
    private $tests;

    private $apiKey;

    private $server = 'https://webhook.koalamon.com';

    private $system;

    private $systemId;

    public function __construct($apiKey, $system, $systemId, $server = null)
    {
        $this->apiKey = $apiKey;

        if ($server) {
            $this->server = $server;
        }

        $this->systemId = $systemId;
        $this->system = $system;
    }

    /**
     * @param boolean $isFailure
     */
    public function addTestcase($url, $mandatoryUrl, $isFailure, $groupKey, $urlKey, $message = '', $requests = [], $content = "", $isTimeout = false)
    {
        if ($isFailure) {
            $this->tests[$url][$groupKey][$urlKey][] =
                [
                    'url' => $mandatoryUrl,
                    'message' => $message,
                    'requests' => $requests,
                    'content' => $content,
                    'isTimeout' => $isTimeout
                ];
        } else {
            $this->tests[$url][$groupKey][$urlKey][] = false;
        }
    }

    public function getReport()
    {
        foreach ($this->tests as $url => $urlKeys) {
            $message = '';
            $status = 'success';

            $message .= 'Some mandatory requests on ' . $url . ' were not found.<ul>';
            $requests = [];
            $content = "";
            $isTimeout = false;

            foreach ($urlKeys as $groupIdentifier => $groups) {
                foreach ($groups as $groupName => $missingUrls) {
                    foreach ($missingUrls as $missingUrl) {
                        if ($missingUrl !== false) {
                            $requests = $missingUrl['requests'];
                            $content = $missingUrl['content'];
                            $isTimeout = $missingUrl['isTimeout'];
                            $message .= '<li>' . stripslashes($missingUrl['url']) . ': ' . $missingUrl['message'] . '</li>';
                            $status = 'failure';
                        }
                    }
                }
            }
            $message .= '</ul>';

            if ($status == 'success') {
                $message = 'All mandatory requests for ' . $url . ' were found.';
            }

            $this->doReport($status, $message, $requests, $content, $isTimeout);
        }

        return 'Incident was sent';
    }

    /**
     * @param string $status
     * @param string $message
     * @param string $identifier
     */
    private function doReport($status, $message, $requests, $content, $isTimeout)
    {
        $identifier = 'MissingRequest2_' . $this->systemId;

        $reporter = new \Koalamon\Client\Reporter\Reporter('', $this->apiKey, new Client(), $this->server);

        $reporter->setEventProcessor(Event\Processor\MongoDBProcessor::createByEnvironmentVars('leankoala'));

        $event = new Event($identifier, $this->system, $status, 'MissingRequest2', $message, '', '', $this->systemId);

        $event->addAttribute(new Event\Attribute('requests', json_encode($requests), true));
        $event->addAttribute(new Event\Attribute('html content', $content, true));
        $event->addAttribute(new Event\Attribute('timeout', $isTimeout, false));

        $reporter->sendEvent($event);
    }
}
