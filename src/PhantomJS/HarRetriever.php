<?php

namespace whm\MissingRequest\PhantomJS;

use Psr\Http\Message\UriInterface;

class HarRetriever
{
    private $phantomJSExec = "phantomjs";
    private $netsniffFile = "netsniff.js";
    private $netSniffTempFile;

    public function __construct($phantomJSExec = null)
    {
        if (!is_null($phantomJSExec)) {
            $this->phantomJSExec = $phantomJSExec;
        }

        $this->netSniffTempFile = \tempnam("missing", 'netsniff_');
        copy(__DIR__ . "/" . $this->netsniffFile, $this->netSniffTempFile);
    }

    public function getHarFile(UriInterface $uri)
    {
        $command = $this->phantomJSExec . " " . $this->netSniffTempFile . " " . (string)$uri;

        exec($command, $output);

        $rawOutput = implode($output, "\n");

        $leftTruncatedOutput = substr($rawOutput, strpos($rawOutput, "{"));
        $jsonOutput = substr($leftTruncatedOutput, 0, strrpos($leftTruncatedOutput, "}") + 1);

        return new HarArchive(json_decode($jsonOutput));
    }

    public function __destruct()
    {
        unlink($this->netSniffTempFile);
    }
}
