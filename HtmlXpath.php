<?php

class HtmlXpath
{
    public function html2domx($html)
    {
        $doc = new DOMDocument();
        $doc->recover = true;
        $doc->strictErrorChecking = false;
        libxml_use_internal_errors(true);
        $r = $doc->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NOBLANKS | LIBXML_COMPACT);
        $xpath = new DOMXpath($doc);

        return $xpath;
    }

    public function getHtmlTitle($html)
    {
        $xpath = $this->html2domx($html);

        return $xpath->query("//title")->item(0)->nodeValue;
    }

}