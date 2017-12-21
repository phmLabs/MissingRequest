<?php

namespace whm\MissingRequest\Cli\Command;

use phm\HttpWebdriverClient\Http\Client\Chrome\ChromeClient;
use phm\HttpWebdriverClient\Http\Client\Chrome\ChromeResponse;
use phm\HttpWebdriverClient\Http\Client\Decorator\CacheDecorator;
use phm\HttpWebdriverClient\Http\Client\Decorator\ClientDecorator;
use phm\HttpWebdriverClient\Http\Client\Decorator\FileCacheDecorator;
use phm\HttpWebdriverClient\Http\Client\HeadlessChrome\HeadlessChromeClient;
use phm\HttpWebdriverClient\Http\Client\HttpClient;
use phm\HttpWebdriverClient\Http\Client\TimeOutException;
use phm\HttpWebdriverClient\Http\Response\TimeoutAwareResponse;
use Psr\Http\Message\RequestInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use GuzzleHttp\Psr7\Request;
use Koalamon\Client\Reporter\Reporter as KoalamonReporter;
use whm\Html\Uri;

abstract class MissingRequestCommand extends Command
{
    const PROBE_COUNT = 1;

    private $client;

    /**
     * @return HeadlessChromeClient
     */
    protected function getClient($clientTimeOut = 31000, $nocache = false)
    {
        $client = new HeadlessChromeClient($clientTimeOut);
        if ($nocache) {
            return $client;
        } else {
            $chromeClient = $client;
            return new FileCacheDecorator($chromeClient);
        }
    }

    protected function runSingleRequest(RequestInterface $request, $collections, HttpClient $client, OutputInterface $output, $maxRetries = 1)
    {
        /** @var ChromeClient $client */

        $results = array();
        $failure = false;

        for ($i = 0; $i < static::PROBE_COUNT; $i++) {

            // only run n-th time of elements were not found
            if (!$failure && $i != 0) {
                $results[$i] = $results[$i - 1];
                continue;
            }

            try {
                $response = $client->sendRequest($request);
                /** @var ChromeResponse $response */
            } catch (\Exception $exception) {
                $client->close();
                throw $exception;
            }

            foreach ($collections as $collection) {

                foreach ($collection['requests'] as $mandatoryRequest) {

                    $name = $mandatoryRequest['name'];
                    $pattern = $mandatoryRequest['pattern'];
                    $count = $mandatoryRequest['value'];
                    $relation = $mandatoryRequest['relation'];

                    $numFound = $response->getResourceCount($pattern);

                    switch ($relation) {
                        case 'equals':
                            $result = $numFound == $count;
                            $message = 'Request was found ' . $numFound . ' times. Expected was ' . $count . ' times.';
                            break;
                        case  'less':
                            $result = $numFound < $count;
                            $message = 'Request was found ' . $numFound . ' times. Expected was less than ' . $count . ' times.';
                            break;
                        case 'greater':
                            $result = $numFound > $count;
                            $message = 'Request was found ' . $numFound . ' times. Expected was more than ' . $count . ' times.';
                            break;
                        default:
                            $result = false;
                            $message = 'The relation (' . $relation . ') was not found.';
                    }

                    if ($response instanceof TimeoutAwareResponse) {
                        $timeout = $response->isTimeout();
                    } else {
                        $timeout = false;
                    }

                    if ($result) {
                        $status = KoalamonReporter::RESPONSE_STATUS_SUCCESS;
                        $message = '';
                    } else {
                        if ($timeout) {
                            if ($maxRetries != 0) {
                                return $this->runSingleRequest($request, $collections, $client, $output, $maxRetries - 1);
                            } else {
                                throw new \RuntimeException('Timeout for ' . (string)$request->getUri());
                            }
                        }
                        $status = KoalamonReporter::RESPONSE_STATUS_FAILURE;
                        $failure = true;
                    }

                    $results[$i][] = array(
                        "url" => (string)$request->getUri(),
                        'mandatoryRequest' => $name . " (" . $pattern . ")",
                        'status' => $status,
                        'massage' => $message,
                        'groupName' => $collection['name'],
                        'pageKey' => $name,
                        'htmlContent' => $response->getBody(),
                        'requests' => $response->getResources(),
                        'timeout' => $timeout
                    );
                }
            }
        }

        return $results;
    }
}
