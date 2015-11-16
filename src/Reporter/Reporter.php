<?php

namespace whm\MissingRequest\Reporter;

interface Reporter
{
    public function addTestcase($url, $mandatoryUrl, $isFailure);
    public function getReport();
}