<?php

/**
 * Base Curl class, create curl handler (ch), configure it and provide several send methods with result return
 *
 * Список полей запроса:
 * Data array format
 *
 * 0:CURLOPT_URL
 * 1:CURLOPT_POSTFIELDS
 * 3:CURLOPT_HTTPHEADER  // An array of HTTP header fields to set, in the format array('Content-type: text/plain',
 * 'Content-length: 100')
 * 5:CURLOPT_REFERER
 * 2:CURLOPT_COOKIE
 * 6:CURLOPT_COOKIEFILE
 * 7:CURLOPT_USERAGENT
 * 4:CURLOPT_NOBODY
 * 9:CURLOPT_HEADER         //TRUE to include the header in the output.
 * 8:CURLOPT_INTERFACE
 */
class Curl
{

    //CURL handler
    protected $ch;
    //Error
    public $error = 0;
    //Responce Header
    public $header;
    //Body of responce
    public $body;
    //Info about last request as curl_getinfo()
    public $execInfo;

    public $cookieName = "common";        //TODO
    protected static $cookieDir;

    /** @var int 0 = no, 1 = yes, 2 = extra */
    public $debug = 0;
    public $timeout = 60;                    //таймаут операции

    protected $userAgent = "Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:67.0) Gecko/20100101 Firefox/67.0";

    protected $httpHeaders = [
        "Accept: text/html, application/xml;q=0.9, application/xhtml+xml, image/png, image/webp, image/jpeg, image/gif, image/x-xbitmap, */*;q=0.1",
        "Accept-Language: ru-RU,ru;q=0.9,en;q=0.8",
        "Connection: Keep-Alive",
        "Keep-Alive: 300",
    ];

    protected $expectHeader = 0;

    /**
     * Constructor initialise curl handler
     *
     * @param int $debug Debug level: 0 - off, 1 - on, 2 - extra
     */
    public function __construct($debug = 0)
    {
        $this->debug = $debug;

        $this->ch = curl_init();

        curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, true);
        //Debug
        $debugOn = (bool)$this->debug;
        curl_setopt($this->ch, CURLOPT_VERBOSE, $debugOn);
        curl_setopt($this->ch, CURLINFO_HEADER_OUT, $debugOn);
        curl_setopt($this->ch, CURLOPT_CERTINFO, $debugOn);
        curl_setopt($this->ch, CURLOPT_FILETIME, $debugOn);
        //Turn off SSL verification
        curl_setopt($this->ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($this->ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($this->ch, CURLOPT_SSL_VERIFYSTATUS, false);
        //set timeout
        curl_setopt($this->ch, CURLOPT_CONNECTTIMEOUT, $this->timeout);
        curl_setopt($this->ch, CURLOPT_TIMEOUT, $this->timeout);

        curl_setopt($this->ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($this->ch, CURLOPT_MAXREDIRS, 20);

        curl_setopt($this->ch, CURLOPT_ENCODING, "");                //send all supported
        curl_setopt($this->ch, CURLOPT_AUTOREFERER, true);

        curl_setopt($this->ch, CURLOPT_DNS_USE_GLOBAL_CACHE, true);
        curl_setopt($this->ch, CURLOPT_FAILONERROR, true);

        curl_setopt($this->ch, CURLOPT_FORBID_REUSE, false);
        curl_setopt($this->ch, CURLOPT_FRESH_CONNECT, false);

        curl_setopt($this->ch, CURLOPT_PATH_AS_IS, true);
        curl_setopt($this->ch, CURLOPT_TCP_FASTOPEN, true);

        if (empty(self::$cookieDir)) {
            self::$cookieDir = sys_get_temp_dir();
        }

        //curl_setopt($this->ch, CURLOPT_WRITEHEADER, fopen(self::$cookieDir . "/tmp.log", "w"));

        return true;
    }

    public function reset()
    {
        curl_reset($this->ch);
    }

    public function timeout($timeout = 60)
    {
        curl_setopt($this->ch, CURLOPT_TIMEOUT, $timeout);
    }

    public function __destruct()
    {
        curl_close($this->ch);
    }

    //Execute configured handler and return result
    protected function exec()
    {
        $start = microtime(true);
        $result = curl_exec($this->ch);
        $time = microtime(true) - $start;
        $this->execInfo = curl_getinfo($this->ch);

        if ($this->debug) {
            echo("\t -- exec time:: $time:");
            echo("\n ---------- REQ INFO ------------- : \n" . print_r($this->execInfo, true) . "\n--------------\n");
        }

        $this->error = curl_errno($this->ch);
        if ($this->error != 0) {
            echo('curl_error: ' . $this->error . ', ' . curl_error($this->ch));

            return false;
        }

        return $result;
    }

    public function get($url, $headers = [], $referer = null)
    {
        $r = $this->send([
            0 => $url,
            3 => $headers,
            5 => $referer,
        ]);

        return $r ? $this->body : false;
    }

    public function post($url, $params = [], $headers = [], $referer = null)
    {
        $r = $this->send([
            0 => $url,
            1 => $params,
            3 => $headers,
            5 => $referer,
        ]);

        return $r ? $this->body : false;
    }

    //Configure handler, send request and return result
    //Low level func
    public function send($data, $respAsIs = 0)
    {
        $ok = $this->setOpts($data);
        if (!$ok) {
            return false;
        }

        $r = $this->exec();

        if (!$r) {
            $this->header = $this->body = null;
        } elseif ($respAsIs || !$this->expectHeader) {            //вернуть как есть
            $this->body = $r;
        } else {                //разобрать на заголовок и тело
            $header_size = curl_getinfo($this->ch, CURLINFO_HEADER_SIZE);

            $this->header = trim(substr($r, 0, $header_size));
            $this->body = trim(substr($r, $header_size));
        }

        if ($this->debug) {
            echo "\n ---------- RESP HEADER ---------- \n" .
                $this->header .
                "\n ----------------------- \n";
        }

        if ($this->debug == 2) {
            echo "\n\n\n\n ---------- RESP BODY ---------- \n" .
                htmlentities($this->body) .
                "\n ----------------------- \n";
        }

        return $r;
    }

    /* Set request options */
    protected function setOpts($data)
    {
        if ($this->debug) {
            echo(print_r($data, true));
        }

        if (empty($data[0])) {
            echo "Request url absent.";

            return false;
        }

        curl_setopt($this->ch, CURLOPT_URL, $data[0]);

        if (!empty($data[1])) {
            curl_setopt($this->ch, CURLOPT_POST, 1);

            $postBody = (is_array($data[1]) || is_object($data[1])) ? http_build_query($data[1]) : $data[1];

            curl_setopt($this->ch, CURLOPT_POSTFIELDS, $postBody);
        } elseif (isset($data[4])) {
            curl_setopt($this->ch, CURLOPT_NOBODY, true);       //HTTP HEAD method
        } else {
            curl_setopt($this->ch, CURLOPT_HTTPGET, 1);
        }

        if (isset($data[2])) {
            curl_setopt($this->ch, CURLOPT_COOKIE, $data[2]);
        } else {
            $cookieName = $data[6] ?? $this->cookieName;

            $cookieFile = self::$cookieDir . "/" . $cookieName . ".cookie";

            curl_setopt($this->ch, CURLOPT_COOKIEFILE, $cookieFile);
            curl_setopt($this->ch, CURLOPT_COOKIEJAR, $cookieFile);
        }


        $header = $data[3] ?? $this->httpHeaders;
        curl_setopt($this->ch, CURLOPT_HTTPHEADER, $header);

        if (isset($data[5])) {
            $referer = $data[5];
        } else {
            $comp = parse_url($data[0]);
            $referer = $comp['scheme'] . "://" . $comp['host'] . "/";
        }
        curl_setopt($this->ch, CURLOPT_REFERER, $referer);

        $userAgent = $data[7] ?? $this->userAgent;
        curl_setopt($this->ch, CURLOPT_USERAGENT, $userAgent);

        if (isset($data[8])) {
            curl_setopt($this->ch, CURLOPT_INTERFACE, $data[8]);
        }

        $h = $data[9] ?? null;
        $this->expectHeader = ((bool)$h || $this->debug);
        curl_setopt($this->ch, CURLOPT_HEADER, $this->expectHeader);

        return true;
    }

    public function setCookieName($cookieName)
    {
        $this->cookieName = $cookieName;
    }

    //Drop previous "session" cookie, start new session
    public function newSession()
    {
        curl_setopt($this->ch, CURLOPT_COOKIESESSION, true);
    }

    /**
     * Set dir for cookie files
     *
     * @param string $dir
     *
     * @return void
     */
    public static function setCookieDir($dir)
    {
        self::$cookieDir = $dir;
    }


    public function setTorProxy($proxy, $controlPort = 9051)
    {
        //curl_setopt($this->ch, CURLOPT_DNS_USE_GLOBAL_CACHE, false);
        //curl_setopt($this->ch, CURLOPT_DNS_CACHE_TIMEOUT, 1);
        //curl_setopt($this->ch, CURLOPT_DNS_LOCAL_IP4, '127.0.0.1');
        //curl_setopt($this->ch, CURLOPT_DNS_INTERFACE, "lo");

        curl_setopt($this->ch, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5_HOSTNAME);
        curl_setopt($this->ch, CURLOPT_PROXY, $proxy);
        $this->torControlPort = $controlPort;
    }

    public function newTorIdentity()
    {
        $port = $this->torControlPort;
        echo `(echo authenticate '""'; echo signal newnym; echo quit) | nc localhost $port`;
    }

    /**
     * @param string $method
     */
    protected function setMethod($method = 'GET')
    {
        switch (strtoupper($method)) {
            case 'HEAD':
                curl_setopt($this->ch, CURLOPT_NOBODY, 1);
                break;
            case 'GET':
                curl_setopt($this->ch, CURLOPT_HTTPGET, 1);
                break;
            case 'POST':
                curl_setopt($this->ch, CURLOPT_POST, 1);
                break;
            default:
                curl_setopt($this->ch, CURLOPT_CUSTOMREQUEST, $method);
        }
    }

    public function getCookieInfo()
    {
        return curl_getinfo($this->ch, CURLINFO_COOKIELIST);
    }
}
