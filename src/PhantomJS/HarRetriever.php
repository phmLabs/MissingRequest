<?php

namespace whm\MissingRequest\PhantomJS;

use Psr\Http\Message\UriInterface;

class HarRetriever
{
    private $phantomJSExec = 'phantomjs';
    private $netsniffFile = 'netsniff.js';
    private $netSniffTempFile;

    public function __construct($phantomJSExec = null)
    {
        if (!is_null($phantomJSExec)) {
            $this->phantomJSExec = $phantomJSExec;
        }

        $this->netSniffTempFile = \tempnam('missing', 'netsniff_');
        copy(__DIR__.'/'.$this->netsniffFile, $this->netSniffTempFile);
    }

    public function getHarFile(UriInterface $uri)
    {
        $command = $this->phantomJSExec.' '.$this->netSniffTempFile.' '.(string) $uri;

        exec($command, $output);

        $rawOutput = implode($output, "\n");

        $harStart = strpos($rawOutput, '##HARFILE-BEGIN') + 16;
        $harEnd = strpos($rawOutput, '##HARFILE-END');
        $harLength = $harEnd - $harStart;
        $harContent = substr($rawOutput, $harStart, $harLength);

        $htmlStart = strpos($rawOutput, '##CONTENT-BEGIN') + 15;

        $htmlEnd = strpos($rawOutput, '##CONTENT-END');
        $htmlLength = $htmlEnd - $htmlStart;
        $htmlContent = substr($rawOutput, $htmlStart, $htmlLength);

        return array('harFile' => new HarArchive(json_decode($harContent)), 'html' => $htmlContent);
    }

    public function __destruct()
    {
        unlink($this->netSniffTempFile);
    }
}
