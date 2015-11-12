<?php

namespace whm\MissingRequest\PhantomJS;

use Psr\Http\Message\UriInterface;

class HarRetriever
{
    private $phantomJSExec = "phantomjs";
    private $netsniffFile = "netsniff.js";

    public function __construct($phantomJSExec = null)
    {
        if (!is_null($phantomJSExec)) {
            $this->phantomJSExec = $phantomJSExec;
        }
    }

    public function getHarFile(UriInterface $uri)
    {
        $command = $this->phantomJSExec . " " . __DIR__ . "/" . $this->netsniffFile . " " . (string)$uri;
        exec($command, $output);
        return new HarArchive(json_decode(implode($output, "\n")));
    }
}