<?php

namespace whm\MissingRequest\Reporter;
use phmLabs\XUnitReport\Elements\Failure;
use phmLabs\XUnitReport\Elements\TestCase;
use phmLabs\XUnitReport\XUnitReport;

class XUnit implements Reporter
{
    private $report;

    public function __construct()
    {
        $this->report = new XUnitReport("MissingRequest");
    }

    public function addTestcase($url, $mandatoryUrl, $isFailure)
    {
        $testCase = new TestCase("MissingRequest", $url . " requests " . $mandatoryUrl, 0);
        if ($isFailure) {
            $testCase->setFailure(new Failure("Missing request", "Request was not found (" . $mandatoryUrl . ")"));
        }

        $this->report->addTestCase($testCase);
    }

    public function getReport()
    {
        return $this->report->toXml();
    }
}