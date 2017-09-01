<?php

namespace whm\MissingRequest\Cli\Command;

use Cache\Adapter\Filesystem\FilesystemCachePool;
use League\Flysystem\Adapter\Local;
use League\Flysystem\Filesystem;
use phm\HttpWebdriverClient\Http\Client\Chrome\ChromeClient;
use phm\HttpWebdriverClient\Http\Client\Chrome\ChromeResponse;
use phm\HttpWebdriverClient\Http\Client\Decorator\CacheDecorator;
use phm\HttpWebdriverClient\Http\Client\Decorator\FileCacheDecorator;
use phm\HttpWebdriverClient\Http\Client\HttpClient;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use GuzzleHttp\Psr7\Request;
use Koalamon\Client\Reporter\Reporter as KoalamonReporter;
use whm\Html\Uri;

abstract class MissingRequestCommand extends Command
{
    const PROBE_COUNT = 2;

    /**
     * @return ChromeClient
     */
    protected function getClient($host, $port, $sleep = 1)
    {
        $chromeClient = new ChromeClient($host, $port, $sleep, false);
        return new FileCacheDecorator($chromeClient);
    }

    protected function runSingleUrl(Uri $uri, $collections, HttpClient $client, OutputInterface $output)
    {
        $results = array();
        $failure = false;

        for ($i = 0; $i < static::PROBE_COUNT; $i++) {

            // only run n-th time of elements were not found
            if (!$failure && $i != 0) {
                $results[$i] = $results[$i - 1];
                continue;
            }

            $response = $client->sendRequest(new Request('GET', $uri));
            /** @var ChromeResponse $response */

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

                    if ($result) {
                        $status = KoalamonReporter::RESPONSE_STATUS_SUCCESS;
                        $message = '';
                    } else {
                        $status = KoalamonReporter::RESPONSE_STATUS_FAILURE;
                        $failure = true;
                    }

                    $results[$i][] = array(
                        "url" => (string)$uri,
                        'mandatoryRequest' => $name . " (" . $pattern . ")",
                        'status' => $status,
                        'massage' => $message,
                        'groupName' => $collection['name'],
                        'pageKey' => $name,
                        'htmlContent' => $response->getBody(),
                        'requests' => $response->getResources()
                    );
                }
            }
        }

        return $results;
    }
}
