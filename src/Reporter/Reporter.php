<?php

namespace whm\MissingRequest\Reporter;

interface Reporter
{
    public function addTestcase($url, $mandatoryUrl, $isFailure, $groupKey, $urlKey, $message = '', $requests = []);

    public function getReport();
}
