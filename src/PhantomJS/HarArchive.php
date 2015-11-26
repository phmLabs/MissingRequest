<?php

namespace whm\MissingRequest\PhantomJS;

use GuzzleHttp\Psr7\Uri;

/**
 * Created by PhpStorm.
 * User: nils.langner
 * Date: 11.11.15
 * Time: 15:24.
 */
class HarArchive
{
    private $rawContent;

    public function __construct($rawContent)
    {
        $this->rawContent = $rawContent;
    }

    public function getEntries()
    {
        $entries = array();

        foreach ($this->rawContent->log->entries as $entry) {
            $entries[$entry->request->url] = new Uri($entry->request->url);
        }

        return $entries;
    }
}
