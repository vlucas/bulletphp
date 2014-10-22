<?php
namespace Bullet;

/**
 * Request Class
 *
 * Wraps around request variables and provides useful helper methods
 *
 * @package Bullet
 * @author Vance Lucas <vance@vancelucas.com>
 * @license http://www.opensource.org/licenses/bsd-license.php
 */
class Request
{
    // Request URL
    protected $_method;
    protected $_url;
    protected $_format = null;

    // Request parameters
    protected $_headers = array();
    protected $_params = array();
    protected $_postParams = array();
    protected $_queryParams = array();
    protected $_accept;

    // Other
    protected $_raw;

    // Mimetypes
    protected $_mimeTypes = array(
        'txt' => 'text/plain',
        'html' => 'text/html',
        'xhtml' => 'application/xhtml+xml',
        'xml' => 'application/xml',
        'css' => 'text/css',
        'js' => 'application/javascript',
        'json' => 'application/json',
        'csv' => 'text/csv',

        // images
        'png' => 'image/png',
        'jpe' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'jpg' => 'image/jpeg',
        'gif' => 'image/gif',
        'bmp' => 'image/bmp',
        'ico' => 'image/vnd.microsoft.icon',
        'tiff' => 'image/tiff',
        'tif' => 'image/tiff',
        'svg' => 'image/svg+xml',
        'svgz' => 'image/svg+xml',

        // archives
        'zip' => 'application/zip',
        'rar' => 'application/x-rar-compressed',

        // adobe
        'pdf' => 'application/pdf'
    );

    /**
     * Setup request object and ensure magic quotes are not mucking up request data
     *
     * @param string HTTP Method
     * @param string Request URI
     * @param array  Request parameters
     * @param array  HTTP Headers
     */
    public function __construct($method = null, $url = null, array $params = array(), array $headers = array(), $rawBody = null)
    {
        // Die magic_quotes, just die...
        if(get_magic_quotes_gpc()) {
            $stripslashes_gpc = function(&$value, $key) {
                $value = stripslashes($value);
            };
            array_walk_recursive($_GET, $stripslashes_gpc);
            array_walk_recursive($_POST, $stripslashes_gpc);
            array_walk_recursive($_COOKIE, $stripslashes_gpc);
            array_walk_recursive($_REQUEST, $stripslashes_gpc);
        }

        $this->_postParams = $_POST;
        $this->_queryParams = $_GET;

        // Set Method
        if($method !== null) {
            $this->_method = $method;
        }

        // Set URL
        if($url !== null) {
            $this->url($url);
        }

        // Set params if given
        if(!empty($params)) {
            if($method === 'POST') {
                $this->_postParams = $params;
            } elseif($method === 'GET') {
                $this->_queryParams = $params;
            } else {
                $this->_params = $params;
            }
        }

        // Set Headers
        $this->_headers = $headers;
        if(empty($this->_headers)) {
            $this->_headers = $_SERVER;
        }

        // Set raw request body
        if($rawBody !== null) {
            $this->_raw = $rawBody;
        }

        // Parse 'Accept' header to see what format is requested
        $accept = $this->accept();
        if(!empty($accept)) {
            // Use first 'accept' type as the default format
            $firstType = array_shift($accept);
            $formatAny = in_array($firstType, array(null, '*/*', '', '*'), true);
            $this->format($formatAny ? null : $firstType);
        }

        // Properly handle PATCH, PUT, and DELETE requests, and POST requests with no params but post bodies
        if($this->isPatch() || $this->isPut() || $this->isDelete() || ($this->isPost() && empty($_POST) && empty($this->_params))) {
            // Get and parse raw request body
            $raw = $this->raw();

            // Handle any JSON request
            if(strpos($this->header('Content-Type'), 'json') !== false) {
                // Cleanup bad JSON so we can parse it
                $raw = stripslashes(str_replace(array('\r\n', '\n', '\r'), '', $raw));
                $json = json_decode($raw, true);
                if($json) {
                    $params = $json;
                }

            // Handle other requests (probably query params)
            } else {
                parse_str($raw, $params);

                // Check to ensure raw body was decoded correctly. If it wasn't, the whole raw string will be a key in the
                // resulting array instead of something sensible, like... I dunno... boolean false, maybe? (f#*@&! php).
                // Additionally, parse_str will convert spaces and dots to underscores so we have to watch for that.
                $raw_transformed = str_replace(array(" ", "."), "_", $raw);
                if(isset($params[$raw_transformed])) {
                    $params = array();
                    $json = json_decode($raw, true);
                    if($json !== false) {
                        $params = $json;
                    }
                }
            }

            // Set params if any decoding was successful
            if($params) {
                // Real POST and GET params take precidence over raw body
                if($method === 'POST') {
                    $this->_postParams = array_merge($params, $this->_postParams);
                } elseif($method === 'GET') {
                    $this->_queryParams = array_merge($params, $this->_queryParams);
                } else {
                    $this->setParams($params);
                }
            }
        }
    }


    /**
     * Return requested URL path
     *
     * Works for HTTP(S) requests and CLI requests using the -u flag for URL dispatch emulation
     *
     * @return string Requested URL path segement
     */
    public function url($url = null)
    {
        if(null === $this->_url) {
            if(null !== $url) {
                // SET url with what's given
                $requestUrl = $url;
            } else {
                // AUTO-DETECT url
                if($this->isCli()) {
                    // CLI request
                    $cliArgs = getopt("u:");

                    $requestUrl = isset($cliArgs['u']) ? $cliArgs['u'] : '/';
                    $qs = parse_url($requestUrl, PHP_URL_QUERY);
                    $cliRequestParams = array();
                    parse_str($qs, $cliRequestParams);

                    // Set parsed query params back on request object
                    $this->setParams($cliRequestParams);

                } else {
                    // HTTP request
                    if($url = $this->query('u')) {
                        $requestUrl = $url;
                    } else {
                        $requestUrl = $this->uri();
                    }
                }
            }

            // Set requestUrl and remove query string if present so router can parse it as expected
            if($qsPos = strpos($requestUrl, '?')) {
                $fullUrl = $requestUrl;
                $requestUrl = substr($requestUrl, 0, $qsPos);
                parse_str(substr($fullUrl, $qsPos+1), $urlRequestParams);
                // Set parsed query params back on request object
                $this->setParams($urlRequestParams);
            }

            $this->_url = $requestUrl;
        }

        return $this->_url;
    }


    /**
     * Access values contained in the superglobals as public members
     * Order of precedence: 1. GET, 2. POST, 3. COOKIE, 4. SERVER, 5. ENV
     *
     * @see http://msdn.microsoft.com/en-us/library/system.web.httprequest.item.aspx
     * @param string $key
     * @return mixed
     */
    public function get($key, $default = null)
    {
        switch (true) {
            case isset($this->_params[$key]):
                $value = $this->_params[$key];
                break;

            case isset($this->_queryParams[$key]):
                $value = $this->_queryParams[$key];
                break;

            case isset($this->_postParams[$key]):
                $value = $this->_postParams[$key];
                break;

            case isset($_COOKIE[$key]):
                $value = $_COOKIE[$key];
                break;

            case isset($_SERVER[$key]):
                $value = $_SERVER[$key];
                break;

            case isset($_ENV[$key]):
                $value = $_ENV[$key];
                break;

            default:
                $value = $default;
        }

        // Key not found, default is being used
        if($value === $default) {
            // Check for dot-separator (convenience access for nested array values)
            if(strpos($key, '.') !== false) {
                // Get all dot-separated key parts
                $keyParts = explode('.', $key);

                // Remove first key because we're going to start with it
                $keyFirst = array_shift($keyParts);

                // Get root value array to begin
                $value = $this->get($keyFirst);

                // Loop over remaining key parts to see if value can be found in resulting array
                foreach($keyParts as $keyPart) {
                    if(is_array($value)) {
                        if(isset($value[$keyPart])) {
                            $value = $value[$keyPart];
                        } else {
                            $value = $default;
                        }
                    }
                }
            }
        }

        return $value;
    }
    // Automagic companion function
    public function __get($key)
    {
        return $this->get($key);
    }


    /**
     *	Override request parameter value
     *
     *	@param string $key
     *	@param string $value
     */
    public function set($key, $value)
    {
        $this->_params[$key] = $value;
    }
    // Automagic companion function
    public function __set($key, $value)
    {
        $this->set($key, $value);
    }


    /**
    * Check to see if a property is set
    *
    * @param string $key
    * @return boolean
    */
    public function __isset($key)
    {
        switch (true) {
            case isset($this->_params[$key]):
            return true;
            case isset($this->_queryParams[$key]):
            return true;
            case isset($this->_postParams[$key]):
            return true;
            case isset($_COOKIE[$key]):
            return true;
            case isset($_SERVER[$key]):
            return true;
            case isset($_ENV[$key]):
            return true;
            default:
            return false;
        }
    }


    /**
     * Get array of accept formats or check if request accepts a given format
     */
    public function accept($format = null)
    {
        if($this->_accept === null) {
            $this->parseAcceptHeader();
        }

        // Check if request accepts a particular format
        if($format !== null) {
            // blah
            if(isset($this->_mimeTypes[$format])) {
                return in_array($this->_mimeTypes[$format], $this->_accept);
            }
            return false;
        }

        return $this->_accept;
    }


    /**
    * Retrieve request parameters
    *
    * @return array Returns array of all GET, POST, and set params
    */
    public function params(array $params = array())
    {
        // Setting
        if(count($params) > 0) {
            foreach($params as $pKey => $pValue) {
                $this->set($pKey, $pValue);
            }

        // Getting
        } else {
            $params = array_merge($this->_queryParams, $this->_postParams, $this->_params);
        }
        return $params;
    }


    /**
    * Set additional request parameters
    */
    public function setParams($params)
    {
        if($params && is_array($params)) {
            foreach($params as $pKey => $pValue) {
                $this->set($pKey, $pValue);
            }
        }
    }


    /**
    * Retrieve a member of the $params set variables
    *
    * If no $key is passed, returns the entire $params array.
    *
    * @todo How to retrieve from nested arrays
    * @param string $key
    * @param mixed $default Default value to use if key not found
    * @return mixed Returns null if key does not exist
    */
    public function param($key = null, $default = null)
    {
        if (null === $key) {
            return $this->_params;
        }

        return (isset($this->_params[$key])) ? $this->_params[$key] : $default;
    }


    /**
    * Retrieve a member of the $_GET superglobal
    *
    * If no $key is passed, returns the entire $_GET array.
    *
    * @todo How to retrieve from nested arrays
    * @param string $key
    * @param mixed $default Default value to use if key not found
    * @return mixed Returns null if key does not exist
    */
    public function query($key = null, $default = null)
        {
        if (null === $key) {
            // Return _GET params without routing param or other params set by Alloy or manually on the request object
            return array_diff_key($this->_queryParams, $this->param() + array('u' => 1));
        }

        return (isset($this->_queryParams[$key])) ? $this->_queryParams[$key] : $default;
    }


    /**
    * Retrieve a member of the $_POST superglobal
    *
    * If no $key is passed, returns the entire $_POST array.
    *
    * @todo How to retrieve from nested arrays
    * @param string $key
    * @param mixed $default Default value to use if key not found
    * @return mixed Returns null if key does not exist
    */
    public function post($key = null, $default = null)
    {
        if (null === $key) {
            return $this->_postParams;
        }

        return (isset($this->_postParams[$key])) ? $this->_postParams[$key] : $default;
    }


    /**
    * Retrieve a member of the $_COOKIE superglobal
    *
    * If no $key is passed, returns the entire $_COOKIE array.
    *
    * @todo How to retrieve from nested arrays
    * @param string $key
    * @param mixed $default Default value to use if key not found
    * @return mixed Returns null if key does not exist
    */
    public function cookie($key = null, $default = null)
    {
        if (null === $key) {
            return $_COOKIE;
        }

        return (isset($_COOKIE[$key])) ? $_COOKIE[$key] : $default;
    }


    /**
    * Retrieve a member of the $_SERVER superglobal
    *
    * If no $key is passed, returns the entire $_SERVER array.
    *
    * @param string $key
    * @param mixed $default Default value to use if key not found
    * @return mixed Returns null if key does not exist
    */
    public function server($key = null, $default = null)
    {
        if (null === $key) {
            return $_SERVER;
        }

        return (isset($_SERVER[$key])) ? $_SERVER[$key] : $default;
    }


    /**
    * Retrieve a member of the $_ENV or $_SERVER superglobal (via `getenv`)
    *
    * If no $key is passed, returns the entire $_ENV array.
    *
    * @param string $key
    * @param mixed $default Default value to use if key not found
    * @return mixed Returns null if key does not exist
    */
    public function env($key = null, $default = null)
    {
        if (null === $key) {
            return $_ENV;
        }

        $var = getenv($key);
        return ($var !== false) ? $var : $default;
    }


    /**
    * Format getter/setter
    *
    * If no $format is passed, returns the current format
    *
    * @param string $key
    * @return string Format
    */
    public function format($format = null)
    {
        if (null !== $format) {
            // If using full mime type, we only need the extension
            if(strpos($format, '/') !== false && in_array($format, $this->_mimeTypes)) {
                $format = array_search($format, $this->_mimeTypes);
            }
            $this->_format = $format;
        }

        // Detect extension and assign it as the requested format (overrides 'Accept' header)
        $dotPos = strpos($this->url(), '.');
        if($dotPos !== false) {
            $ext = substr($this->url(), $dotPos+1);
            $this->_format = $ext;
        }

        return $this->_format;
    }


    /**
    * Return the value of the given HTTP header. Pass the header name as the
    * plain, HTTP-specified header name. Ex.: Ask for 'Accept' to get the
    * Accept header, 'Accept-Encoding' to get the Accept-Encoding header.
    *
    * @param string $header HTTP header name
    * @return string|false HTTP header value, or false if not found
    */
    public function header($header)
    {
        // Try to get it from the keys that $_SERVER populates first
        $temp = 'HTTP_' . strtoupper(str_replace('-', '_', $header));
        if (!empty($this->_headers[$temp])) {
            return $this->_headers[$temp];
        }

        // Try to get direct header key now
        if (!empty($this->_headers[$header])) {
            return $this->_headers[$header];
        }

        // This seems to be the only way to get the Authorization header on Apache
        if (function_exists('apache_request_headers')) {
            $headers = apache_request_headers();
            if (!empty($headers[$header])) {
                return $headers[$header];
            }
        }

        return false;
    }


    /**
    * Return the method by which the request was made. Always returns HTTP_METHOD in UPPERCASE.
    *
    * @return string HTTP Request method in UPPERCASE
    */
    public function method()
    {
        if($this->_method === null) {
            $sm = strtoupper($this->server('REQUEST_METHOD', 'GET'));

            // POST + '_method' override to emulate REST behavior in browsers that do not support it
            if('POST' == $sm && $this->get('_method')) {
                return strtoupper($this->get('_method'));
            }

            $this->_method = $sm;
        }

        return $this->_method;
    }


    /**
     * Request URI, as seen by PHP
     *
     * @return string request URI from $_SERVER superglobal
     */
    public function uri()
    {
        return $this->server('REQUEST_URI');
    }


    /**
     * Request scheme (http, https, or cli)
     *
     * @return string 'http', 'https', or 'cli'
     */
    public function scheme()
    {
        return ($this->isCli() ? 'cli' : ($this->isSecure() ? 'https' : 'http' ));
    }


    /**
     * Request subdomain
     *
     * @return String request subdomain
     */
    public function subdomain()
    {
        $parts = explode('.', $this->host());
        $count = count($parts);
        return ($count > 2 ? $parts[0] : false);
    }


    /**
     * Request HTTP_HOST
     *
     * @return String request host from $_SERVER superglobal
     */
    public function host()
    {
        return $this->header('Host');
    }


    /**
     * Request port
     *
     * @return integer request port
     */
    public function port()
    {
        return $this->server('SERVER_PORT');
    }


    /**
     * Get a user's correct IP address
     * Retrieves IP's behind firewalls or ISP proxys like AOL
     *
     * @return string IP Address
     */
    public function ip()
    {
        $ip = FALSE;

        if( !empty( $_SERVER["HTTP_CLIENT_IP"] ) )
            $ip = $_SERVER["HTTP_CLIENT_IP"];

        if( !empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ){
            // Put the IP's into an array which we shall work with shortly.
            $ips = explode( ", ", $_SERVER['HTTP_X_FORWARDED_FOR'] );
            if( $ip ){
                array_unshift( $ips, $ip );
                $ip = false;
            }

            for( $i = 0; $i < count($ips); $i++ ){
                if (!eregi ("^(10|172\.16|192\.168)\.", $ips[$i])) {
                    $ip = $ips[$i];
                    break;
                }
            }
        }
        return ($ip ? $ip : $_SERVER['REMOTE_ADDR']);
    }


    /**
     * Get raw, unparsed request body (useful for PUT and POST requests with encoded body - like JSON)
     * Exists because PHP cannot retrieve the contents of php://input more than once
     */
    public function raw()
    {
        if(null === $this->_raw) {
            $this->_raw = file_get_contents('php://input');
        }
        return $this->_raw;
    }


    /**
     *	Determine is incoming request is POST
     *
     *	@return boolean
     */
    public function isPost()
    {
        return ($this->method() == "POST");
    }


    /**
     *	Determine is incoming request is GET
     *
     *	@return boolean
     */
    public function isGet()
    {
        return ($this->method() == "GET");
    }


    /**
     *	Determine is incoming request is PUT
     *
     *	@return boolean
     */
    public function isPut()
    {
        return ($this->method() == "PUT");
    }


    /**
     *	Determine is incoming request is DELETE
     *
     *	@return boolean
     */
    public function isDelete()
    {
        return ($this->method() == "DELETE");
    }


    /**
     *	Determine is incoming request is PATCH
     *
     *	@return boolean
     */
    public function isPatch()
    {
        return ($this->method() == "PATCH");
    }


    /**
     *	Determine is incoming request is HEAD
     *
     *	@return boolean
     */
    public function isHead()
    {
        return ($this->method() == "HEAD");
    }


    /**
     *  Determine is incoming request is OPTIONS
     *
     *  @return boolean
     */
    public function isOptions()
    {
        return ($this->method() == "OPTIONS");
    }


    /**
     *	Determine is incoming request is secure HTTPS
     *
     *	@return boolean
     */
    public function isSecure()
    {
        return  (bool) ((!isset($_SERVER['HTTPS']) || strtolower($_SERVER['HTTPS']) != 'on') ? false : true);
    }


    /**
     * Is the request a Javascript XMLHttpRequest?
     *
     * Works with Prototype/Script.aculo.us, jQuery, YUI, Dojo, possibly others.
     *
     * @return boolean
     */
    public function isAjax()
    {
        return ($this->header('X_REQUESTED_WITH') == 'XMLHttpRequest');
    }


    /**
     * Is the request coming from a mobile device?
     *
     * Works with iPhone, Android, Windows Mobile, Windows Phone 7, Symbian, and other mobile browsers
     *
     * @return boolean
     */
    public function isMobile()
    {
        $op = strtolower($_SERVER['HTTP_X_OPERAMINI_PHONE']);
        $ua = strtolower($_SERVER['HTTP_USER_AGENT']);
        $ac = strtolower($_SERVER['HTTP_ACCEPT']);

        return (
            strpos($ac, 'application/vnd.wap.xhtml+xml') !== false
            || strpos($ac, 'text/vnd.wap.wml') !== false
            || $op != ''
            || strpos($ua, 'iphone') !== false
            || strpos($ua, 'android') !== false
            || strpos($ua, 'iemobile') !== false 
            || strpos($ua, 'kindle') !== false
            || strpos($ua, 'sony') !== false 
            || strpos($ua, 'symbian') !== false 
            || strpos($ua, 'nokia') !== false 
            || strpos($ua, 'samsung') !== false 
            || strpos($ua, 'mobile') !== false
            || strpos($ua, 'windows ce') !== false
            || strpos($ua, 'epoc') !== false
            || strpos($ua, 'opera mini') !== false
            || strpos($ua, 'nitro') !== false
            || strpos($ua, 'j2me') !== false
            || strpos($ua, 'midp-') !== false
            || strpos($ua, 'cldc-') !== false
            || strpos($ua, 'netfront') !== false
            || strpos($ua, 'mot') !== false
            || strpos($ua, 'up.browser') !== false
            || strpos($ua, 'up.link') !== false
            || strpos($ua, 'audiovox') !== false
            || strpos($ua, 'blackberry') !== false
            || strpos($ua, 'ericsson,') !== false
            || strpos($ua, 'panasonic') !== false
            || strpos($ua, 'philips') !== false
            || strpos($ua, 'sanyo') !== false
            || strpos($ua, 'sharp') !== false
            || strpos($ua, 'sie-') !== false
            || strpos($ua, 'portalmmm') !== false
            || strpos($ua, 'blazer') !== false
            || strpos($ua, 'avantgo') !== false
            || strpos($ua, 'danger') !== false
            || strpos($ua, 'palm') !== false
            || strpos($ua, 'series60') !== false
            || strpos($ua, 'palmsource') !== false
            || strpos($ua, 'pocketpc') !== false
            || strpos($ua, 'smartphone') !== false
            || strpos($ua, 'rover') !== false
            || strpos($ua, 'ipaq') !== false
            || strpos($ua, 'au-mic,') !== false
            || strpos($ua, 'alcatel') !== false
            || strpos($ua, 'ericy') !== false
            || strpos($ua, 'up.link') !== false
            || strpos($ua, 'vodafone/') !== false
            || strpos($ua, 'wap1.') !== false
            || strpos($ua, 'wap2.') !== false
        );
    }


    /**
     * Is the request coming from a bot or spider?
     *
     * Works with Googlebot, MSN, Yahoo, possibly others.
     *
     * @return boolean
     */
    public function isBot()
    {
        $ua =strtolower($this->server('HTTP_USER_AGENT'));
        $botList =array(
            'googlebot',
            'msnbot',
            'yahoo',
            'slurp',
            'bot',
            'spider',
            'nutch',
            'crawler',
            'facebook',
            'bing',
            'siteanalyzer',
            'dnsqueries',
            'httpclient',
            'indy library',
            'netcraftsurveyagent',
        );
        foreach($botList as $bot){
            if(false !== strpos($ua, $bot)){
                return true;
            }
        }
        return false;
   }


    /**
     * Is the request from CLI (Command-Line Interface)?
     *
     * @return boolean
     */
    public function isCli()
    {
        return !isset($_SERVER['HTTP_HOST']);
    }


    /**
     * Is the request from HHVM (HipHop)?
     *
     * @return boolean
     */
    public function isHHVM()
    {
        return defined('HHVM_VERSION');
    }


    /**
     * Is this a Flash request?
     * 
     * @return bool
     */
    public function isFlash()
    {
        return ($this->header('USER_AGENT') == 'Shockwave Flash');
    }


    /**
     * Parse 'Accept' HTTP header
     */
    protected function parseAcceptHeader()
    {
        $hdr = $this->header('Accept');
        $accept = array();

        foreach (preg_split('/\s*,\s*/', $hdr) as $i => $term) {
            $o = new \stdclass;
            $o->pos = $i;
            if (preg_match(",^(\S+)\s*;\s*(?:q|level)=([0-9\.]+),i", $term, $M)) {
                $o->type = $M[1];
                $o->q = (double) $M[2];
            } else {
                $o->type = $term;
                $o->q = 1;
            }
            $accept[] = $o;
        }

        usort($accept, function ($a, $b) {
            /* first tier: highest q factor wins */
            $diff = $b->q - $a->q;
            if ($diff > 0) {
                $diff = 1;
            } else if ($diff < 0) {
                $diff = -1;
            } else {
                /* tie-breaker: first listed item wins */
                $diff = $a->pos - $b->pos;
            }
            return $diff;
        });

        $this->_accept = array();
        foreach ($accept as $a) {
            if(empty($a->type)) { continue; }
            $this->_accept[$a->type] = $a->type;
        }
        return $this->_accept;
    }
}

