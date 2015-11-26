<?php

namespace whm\MissingRequest\Reporter;

use phmLabs\XUnitReport\Elements\Failure;

class Incident implements Reporter
{
    private $tests;

    private $incidentUrl = 'http://dashboard.phmlabs.com/app_dev.php/webhook/';

    public function __construct($incidentUrl = null)
    {
        if (!is_null($incidentUrl)) {
            $this->incidentUrl = $incidentUrl;
        }
    }

    public function addTestcase($url, $mandatoryUrl, $isFailure, $groupKey, $urlKey)
    {
        if ($isFailure) {
            $this->tests[$url][$urlKey][$groupKey][] = $mandatoryUrl;
        } else {
            $this->tests[$url][$urlKey][$groupKey][] = false;
        }
    }

    public function getReport()
    {
        foreach ($this->tests as $url => $urlKeys) {
            foreach ($urlKeys as $urlKey => $groups) {
                $message = '';
                $status = 'success';
                foreach ($groups as $groupName => $missingUrls) {
                    $groupFound = false;
                    foreach ($missingUrls as $missingUrl) {
                        if ($missingUrl !== false) {
                            if (!$groupFound) {
                                $message .= 'Requests for <strong>'.$groupName.'</strong> were not found.';
                                $message .= '<ul>';
                                $groupFound = true;
                            }
                            $message .= '<li>'.stripslashes($missingUrl).'</li>';
                        }
                    }
                    if ($groupFound) {
                        $message .= '</ul>';
                        $status = 'failure';
                    }
                }
            }
            $parts = parse_url($url);
            $system = $parts['host'];
            $identifier = 'MissingRequest_'.$url;
            $this->doReport($system, $status, $message, $identifier);
        }

        // $this->doReport($system, $status, $message, $identifier);


        return 'Incident was sent';
    }

    private function doReport($system, $status, $message, $identifier)
    {
        $curl = curl_init();

        $responseBody = array(
            'system' => str_replace('http://', '', $system),
            'status' => $status,
            'message' => $message,
            'identifier' => $identifier,
            'type' => 'missingrequest',
        );

        curl_setopt_array($curl, array(
            CURLOPT_URL => $this->incidentUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode($responseBody),
        ));

        $response = curl_exec($curl);

        $err = curl_error($curl);

        curl_close($curl);

        return $err;
    }
}
