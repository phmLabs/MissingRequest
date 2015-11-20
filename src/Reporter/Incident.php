<?php

namespace whm\MissingRequest\Reporter;

use phmLabs\XUnitReport\Elements\Failure;
use phmLabs\XUnitReport\Elements\TestCase;
use phmLabs\XUnitReport\XUnitReport;

class Incident implements Reporter
{
    private $tests;

    private $incidentUrl = "http://dashboard.phmlabs.com/app_dev.php/webhook/";

    public function __construct($incidentUrl = null)
    {
        if (!is_null($incidentUrl)) {
            $this->incidentUrl = $incidentUrl;
        }
    }

    public function addTestcase($url, $mandatoryUrl, $isFailure, $urlKey)
    {
        if ($isFailure) {
            $this->tests[$url][] = $mandatoryUrl;
        } else {
            $this->tests[$url][] = false;
        }
    }

    public function getReport()
    {
        foreach ($this->tests as $url => $missingUrls) {

            $message = "Following requests are missing: <ul>";
            $status = "success";

            foreach ($missingUrls as $missingUrl) {
                if ($missingUrl !== false) {
                    $message .= "<li>" . urldecode($missingUrl)."</li>";
                    $status = "failure";
                }
            }

            $message .= "</ul>";

            $parts = parse_url($url);

            $system = $parts["host"];
            $identifier = "MissingRequest_". $url;

            $this->doReport($system, $status, $message, $identifier);
        }

        return "Incident was sent";
    }

    private function doReport($system, $status, $message, $identifier)
    {
        $curl = curl_init();

        $responseBody = array(
            'system' => str_replace("http://", '', $system),
            'status' => $status,
            'message' => $message,
            'identifier' => $identifier,
            'url' => $system,
            'type' => 'missingrequest'
        );

        curl_setopt_array($curl, array(
            CURLOPT_URL => $this->incidentUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => json_encode($responseBody),
        ));

        $response = curl_exec($curl);

        $err = curl_error($curl);

        curl_close($curl);

        return $err;
    }
}