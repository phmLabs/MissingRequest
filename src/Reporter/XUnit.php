<?php

namespace whm\MissingRequest\Reporter;

use phmLabs\XUnitReport\Elements\Failure;
use phmLabs\XUnitReport\Elements\TestCase;
use phmLabs\XUnitReport\XUnitReport;

class XUnit implements Reporter
{
    private $report;

    private $tests;

    public function __construct()
    {
        $this->report = new XUnitReport('MissingRequest');
    }

    public function addTestcase($url, $mandatoryUrl, $isFailure, $groupKey, $urlKey, $message = '')
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
            $testCase = new TestCase('MissingRequest', $url, 0);
            foreach ($missingUrls as $missingUrl) {
                if ($missingUrl !== false) {
                    $testCase->addFailure(new Failure('Missing request', 'Request was not found (' . $missingUrl . ')'));
                }
            }
            $this->report->addTestCase($testCase);
        }

        return $this->report->toXml();
    }
}
