<?php

/**
 *  Base curl class, create curl handler (ch) and provide get(), post(), head(), request() methods with result return
 *  
 *  
 *  Using these cUrl options:
 *  
 *  0:CURLOPT_URL
 *  1:CURLOPT_POSTFIELDS
 *  2:CURLOPT_COOKIE
 *  3:CURLOPT_HEADER
 *  4:CURLOPT_NOBODY
 *  5:CURLOPT_REFERER
 *  6:CURLOPT_COOKIEFILE
 *  7:CURLOPT_USERAGENT
 *  8:CURLOPT_INTERFACE
 *  9:CURLOPT_HTTPHEADER
 *
 *  Call 
 * - Curl::setCookieDir(COOKIE_DIR); 
 * - $curl->setNullConfig(1);
 * before usage recommended
 *  
 */


class Curl
{
    
    protected static $cookie_dir;
    
    //CURL handler
    protected $ch;
    //Error code
    public $error = 0;
    //Error message        //TODO
    public $error_msg;
    //Responce Header
    public $header;
    //Body of responce
    public $body;
    //info about last request as curl_getinfo()
    public $exec_info;
    
    public $cookie_name = "common";    
    
    public $useragent = "Mozilla/5.0 (Windows NT 6.1; Win64; x64; rv:58.0) Gecko/20100101 Firefox/58.0";
    
    public $debug = 0;
    
    public $extra_debug = 0;
    //request timeout
    public $timeout = 60;                    
    //timeout between two requests in microseconds
    public $sleep = 0;        

    public $responce_as_is = 0;
    
    public $incl_header = 0;        //fixit
    
    /**
     *  Initialise curl handler
     *
     * @param int $debug 0|1|2
     * @return bool
     */
    public function __construct ($debug = 0)
    {
        $this->debug = $debug ? 1 : 0;
        if($debug > 1){
            $this->extra_debug = 1;
        }
        
        $this->ch = curl_init();

        $this->setDefaultOptions();
        
        return true;
    }

    /**
     *  Close $ch
     *
     * @return bool
     */
    public function __destruct()
    {
        curl_close($this->ch);
    }

    /**
     * Set dir for cookie files
     *
     * @param string $dir
     * @return void
     */
    public static function setCookieDir($dir)
    {
        self::$cookie_dir = $dir;
    }
    
    /**
     * Makes an HTTP GET request to the specified $url with an optional array or string of $params
     *
     * @param string $url
     * @param array|string $params
     * @return string|array|boolean
     */
    public function get($url, $params = [], $headers = [], $referer = '')
    {
        if (!empty($params)) {
            $url .= (stripos($url, '?') !== false) ? '&' : '?';
            $url .= (is_string($params)) ? $params : http_build_query($params, '', '&');
        }
        
        return $this->request('GET', $url, null, $headers, $referer);
    }

    /**
     * Makes an HTTP HEAD request to the specified $url with an optional array or string of $params
     *
     * @param string $url
     * @param array|string $params        TODO: test it
     * @return string|array|boolean
     */
    public function head($url, $params = [], $headers = [])
    {
        return $this->request('HEAD', $url, $params, $headers);
    }

    /**
     * Makes an HTTP POST request to the specified $url with an optional array or string of $params
     *
     * @param string $url
     * @param array|string $params
     * @return string|array|boolean
     */
    public function post($url, $params = [], $headers = [], $referer = '')
    {
        return $this->request('POST', $url, $params, $headers, $referer);
    }

    
    /**
     *  Drop all request-dependent params to defaults
     *  Calling it after each request
     *
     * @param int $force
     */
    public function setNullConfig($force = 0)
    {
        $this->setMethod();
        $this->setPost();
        $this->setCookie();
        $this->setReferer();
        $this->setHttpHeader();
        
        if($force){
            $this->setCookieFile();
            $this->setRespHeader();
            $this->setUserAgent();
        }
    }
    
    /**
     * @param string $url
     */
    public function setUrl($url)
    {
        curl_setopt($this->ch, CURLOPT_URL, $url);
    }

    /**
     *  @param array|string|null $post
     */
    public function setPost($post = null)
    {
        if(is_array($post))
            $post = http_build_query($post);
        
        curl_setopt($this->ch, CURLOPT_POSTFIELDS, $post);    
    }
    
    /**
     *  @param array|string $post
     */
    public function setCookie($str = '')
    {
        curl_setopt($this->ch, CURLOPT_COOKIE, $str);
    }
    
    /**
     *  Optional. Stateful.
     *  Save state between $ch executes
     *  May be called one time for several net requests
     *
     * @param string $cookie_name
     */
    public function setCookieFile($cookie_name = null)
    {
        if(empty($cookie_name))
            $cookie_name = $this->cookie_name;
        
        $cookie_dir = self::$cookie_dir;
        if(!$cookie_dir || !is_dir($cookie_dir) || !is_writable($cookie_dir))
            $cookie_dir = sys_get_temp_dir();
        
        $fcookie = $cookie_dir . "/" . $cookie_name . ".cookie";
        
        curl_setopt($this->ch, CURLOPT_COOKIEFILE, $fcookie);
        curl_setopt($this->ch, CURLOPT_COOKIEJAR, $fcookie);    
    }

    /**
     * Optional. Stateful.
     *
     * @param int $incl_header
     */
    public function setRespHeader($incl_header = 0)
    {
        $this->incl_header = $incl_header;
        curl_setopt($this->ch, CURLOPT_HEADER, $incl_header);
    }
    
    /**
     *  Optional. Stateful.
     *
     * @param int $no_parse
     */
    public function setRespNoParse($no_parse = 0)
    {
        $this->responce_as_is = $no_parse;
    }

    /**
     * @param string $ref
     */
    public function setReferer($ref = '')
    {
        curl_setopt($this->ch, CURLOPT_REFERER, $ref);
    }
    
    /**
     *  Optional. Stateful.
     *
     *  @param string|bool $str If string (empty or not) - set it as is, if bool - set default value
     */
    public function setUserAgent($str = true)
    {
        if($str === true)                //set default value
            $str = $this->useragent;
        curl_setopt($this->ch, CURLOPT_USERAGENT, $str);
    }
    
    /**
     *  Set CURLOPT_HTTPHEADER passed via get(), post() and request() methods
     *  @param array|bool $hdr  If array (empty or not) - set it as is, if bool - set default value
     */
    protected function setHttpHeader($hdr = true)
    {    
        if($hdr === true){
            $hdr = [
                    //"Accept: text/html, application/xml;q=0.9, application/xhtml+xml, image/png, image/webp, image/jpeg, image/gif, image/x-xbitmap, */*;q=0.1",
                    //"Accept-Language: ru-RU,ru;q=0.9,en;q=0.8",
                    "Accept: */*",
                    "Accept-Language: en-US,en;q=0.8",
                    "Accept-Encoding: gzip, deflate",
                    "Connection: Keep-Alive",
                    "Keep-Alive: 300",
            ];
        }
        curl_setopt($this->ch, CURLOPT_HTTPHEADER, $hdr);
    }
    
        
    /**
     *  Set CURLOPT_TIMEOUT
     *
     * @param int $timeout
     */
    public function setTimeout($timeout = 60)
    {
        curl_setopt( $this->ch, CURLOPT_TIMEOUT, $timeout );
    }
    
    /**
     *  Configure handler, send request and return result
     */
    protected function request($method, $url, $params = [], $headers = [], $referer = '')
    {
        if(empty($url))
            return false;
        
        $this->setMethod($method);
        $this->setUrl($url);
        
        if($params)
            $this->setPost($params);
        
        $this->setHttpHeader($headers);
        if($referer)
            $this->setReferer($referer);
        
        $this->error = 0;
        $this->exec_info = [];
        
        /*if($this->debug){
            pr("$method, $url", $params);
        }*/
        
        $r = $this->exec();
        
        $this->exec_info = curl_getinfo($this->ch);
        
        /*if($this->debug){
            pr($this->exec_info);    
        }*/
        
        $header_size = $this->exec_info['header_size'];
        $this->http_code = $this->exec_info['http_code'];
        //$this->exec_time = $this->exec_info["total_time"];
        
        $this->sleep();
        $this->setNullConfig();
        
        if(empty($r) || $this->responce_as_is || !$this->incl_header){            //return result as is
        
            if($this->debug){
                echo "\n ---------- REQ HEADER ------------- \n {$this->exec_info[request_header]} \n ----------------------- \n";
                echo "\n ---------- RESP ------------- \n $r \n ----------------------- \n";
            }
            if($this->extra_debug){
                echo "\n\n\n\n ---------- EXEC INFO ------------- \n ".print_r($this->exec_info, 1)." \n ----------------------- \n";
            }
            return $r;
        }else{                //parse to body and header
            $this->header = trim(substr($r, 0, $header_size));
            $this->body = trim(substr($r, $header_size));
            
            if($this->debug){
                echo "\n ---------- REQ HEADER ------------- \n {$this->exec_info[request_header]} \n ----------------------- \n";
                echo "\n ---------- RESP HEADER ------------- \n $this->header \n ----------------------- \n";
            }
            if($this->extra_debug){
                echo "\n\n\n\n ---------- RESP BODY ------------- \n ".htmlentities($this->body)." \n ----------------------- \n";
                echo "\n\n\n\n ---------- EXEC INFO ------------- \n ".print_r($this->exec_info, 1)." \n ----------------------- \n";
            }
            return [$this->header, $this->body];
        }
        
    }
    

    /**
     * Execute configured handler and return result
     *
     * @return bool|mixed
     */
    public function exec()
    {
        $result = curl_exec($this->ch);
        
        if(curl_errno($this->ch) != 0){
            $this->error = curl_errno($this->ch);
            $this->error_msg = curl_error($this->ch);
            echo("CURL_error: #$this->error ($this->error_msg)");
            return false;
        }
        return $result;
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

    /**
     *  Set request-independent defaults after object created
     */
    protected function setDefaultOptions()
    {
        curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($this->ch, CURLOPT_SAFE_UPLOAD, 0);
        //Debug
        curl_setopt($this->ch, CURLOPT_VERBOSE , $this->debug);
        curl_setopt($this->ch, CURLINFO_HEADER_OUT, $this->debug);
        //Turn off SSL verification
        curl_setopt($this->ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($this->ch, CURLOPT_SSL_VERIFYHOST, 0);
        //set timeout
        curl_setopt( $this->ch, CURLOPT_CONNECTTIMEOUT, 60 );
        curl_setopt( $this->ch, CURLOPT_TIMEOUT, $this->timeout );
        //others
        curl_setopt( $this->ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt( $this->ch, CURLOPT_MAXREDIRS, 20 );
        curl_setopt($this->ch, CURLOPT_ENCODING, '');
        curl_setopt($this->ch, CURLOPT_AUTOREFERER, 1);
        
        //curl_setopt($this->ch, CURLOPT_COOKIESESSION, 1);
        //curl_setopt($this->ch, CURLOPT_INTERFACE, $data[8]);
        
        $this->setUserAgent();
    }
        
    /**
     *  Sleep between requests
     */
    protected function sleep()
    {
        usleep($this->sleep);
    }


    public function getCookieInfo()
    {
        return curl_getinfo($this->ch, CURLINFO_COOKIELIST);
    }
}
