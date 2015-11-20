<?php

namespace whm\MissingRequest\Reporter;

interface Reporter
{
    public function addTestcase($url, $mandatoryUrl, $isFailure, $groupKey, $urlKey);
    public function getReport();
}