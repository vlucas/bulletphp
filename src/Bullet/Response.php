<?php
namespace Bullet;

/**
 * Response Class
 *
 * Contians response body, status, headers, etc.
 *
 * @package Bullet
 * @author Vance Lucas <vance@vancelucas.com>
 * @license http://www.opensource.org/licenses/bsd-license.php
 */
class Response
{
    protected $_status = 200;
    protected $_content;
    protected $_cacheTime;
    protected $_encoding = "UTF-8";
    protected $_contentType = "text/html";
    protected $_protocol = "HTTP/1.1";
    protected $_headers = array();


    /**
     * Constructor Function
     */
    public function __construct($content = null, $status = 200)
    {
        // Allow composition of response objects
        $class = __CLASS__;
        if($content instanceof $class) {
            $this->_content = $content->content();
            $this->_status = $content->status();
        } else {
            $this->_content = $content;
            $this->_status = $status;
        }
        $this->_protocol = isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'http';
    }


    /**
     * Set or get HTTP header
     *
     * @param string $type HTTP header type
     * @param string $content header content/value
     * @param boolean $replace Whether to replace existing headers
     * @return mixed
     */
    public function header($type, $content = null, $replace = true)
    {
        if($content === null) {
            if(isset($this->_headers[$type])) {
                return $this->_headers[$type];
            }
            return false;
        }

        // Normalize headers to ensure proper case
        for($tmp = explode("-", $type), $i=0;$i<count($tmp);$i++) {
            $tmp[$i] = ucfirst($tmp[$i]);
        }

        $type = implode("-", $tmp);
        if($type == 'Content-Type') {
            if (preg_match('/^(.*);\w*charset\w*=\w*(.*)/', $content, $matches)) {
                $this->_contentType = $matches[1];
                $this->_encoding = $matches[2];
            } else {
                $this->_contentType = $content;
            }
            return $this;
        }

        if ($replace) {
            $this->_headers[$type] = $content;
        } else {
            $this->appendHeader($type, $content);
        }
        return $this;
    }

    /**
     * Append a header to the list of headers with the same name.
     *
     * @param string $type HTTP header type
     * @param string $content Header content/value
     * @return \Bullet\Response
     */
    protected function appendHeader($type, $content)
    {
        // If the header hasn't already been set, make it an array at the start.
        if(!isset($this->_headers[$type])) {
            $this->_headers[$type] = array($content);
            return $this;
        }

        // If the header isn't already an array of content values, turn it into
        // an array.
        if(!is_array($this->_headers[$type])) {
            $this->_headers[$type] = array($this->_headers[$type]);
        }
        $this->_headers[$type][] = $content;
        return $this;
    }

    /**
     * Get array of all HTTP headers
     *
     * @return array
     */
    public function headers()
    {
        return $this->_headers;
    }


    /**
     * Set HTTP status to return
     *
     * @param int $status HTTP status code
     */
    public function status($status = null)
    {
        if(null === $status) {
            return $this->_status;
        }
        $this->_status = $status;
        return $this;
    }


    /**
     * Set HTTP cache time
     *
     * @param mixed $time Boolean false, integer time, or string for strtotime
     */
    public function cache($time = null)
    {
        if(null === $time) {
            return $this->_cacheTime;
        }

        if($time instanceof \DateTime) {
            $time = $time->getTimestamp();
        } elseif(is_string($time)) {
            $time = strtotime($time);
        } elseif(is_int($time)) {
            // Given time not a timestamp, assume seconds to add to current time
            if(strlen($time) < 10) {
                $time = time() + $time;
            }
        }

        if($time === false) {
            // Explicit no cache
            $this->header('Cache-Control', 'no-cache, no-store');
        } else {
            // Max-age is seconds from now
            $this->header('Cache-Control', 'public, max-age=' . ($time - time()));
            $this->header('Expires', gmdate("D, d M Y H:i:s", $time));
        }

        $this->_cacheTime = $time;
        return $this;
    }


    /**
     * Set HTTP encoding to use
     *
     * @param string $encoding Charset encoding to use
     */
    public function encoding($encoding = null)
    {
        if(null === $encoding) {
            return $this->_encoding;
        }
        $this->_encoding = $encoding;
        return $this;
    }


    /**
     * Set HTTP response body
     *
     * @param string $content Content
     */
    public function content($content = null)
    {
        if(null === $content) {
            return $this->_content;
        }
        $this->_content = $content;
    }
    public function appendContent($content)
    {
        $this->_content .= $content;
    }


    /**
     * Set HTTP content type
     *
     * @param string $contentType Content-type for response
     */
    public function contentType($contentType = null)
    {
        if(null == $contentType) {
            return $this->_contentType;
        }
        $this->_contentType = $contentType;
        return $this;
    }


    /**
     * Clear any previously set HTTP headers
     */
    public function clearHeaders()
    {
        $this->_headers = array();
        return $this;
    }


    /**
     * Clear any previously set HTTP redirects
     */
    public function clearRedirects()
    {
        if(isset($this->_headers['Location'])) {
            unset($this->_headers['Location']);
        }
        return $this;
    }


    /**
     * See if the response has any redirects set
     *
     * @return boolean
     */
    public function hasRedirects()
    {
        return isset($this->_headers['Location']);
    }


    /**
     * See if the response has any redirects set
     *
     * @param string $location URL
     * @param int $status HTTP status code for redirect (3xx)
     */
    public function redirect($location, $status = 302)
    {
        $this->status($status);
        $this->header('Location', $location);
        return $this;
    }


    /**
     * Send HTTP status header
     */
    protected function sendStatus()
    {
        // Send HTTP Header
        header($this->_protocol . " " . $this->_status . " " . $this->statusText($this->_status));
    }


    /**
     * Get HTTP header response text from status code
     */
    public function statusText($statusCode)
    {
        $responses = array(
            100 => 'Continue',
            101 => 'Switching Protocols',
            102 => 'Processing',

            200 => 'OK',
            201 => 'Created',
            202 => 'Accepted',
            203 => 'Non-Authoritative Information',
            204 => 'No Content',
            205 => 'Reset Content',
            206 => 'Partial Content',
            207 => 'Multi-Status',
            226 => 'IM Used',

            300 => 'Multiple Choices',
            301 => 'Moved Permanently',
            302 => 'Found',
            303 => 'See Other',
            304 => 'Not Modified',
            305 => 'Use Proxy',
            306 => 'Reserved',
            307 => 'Temporary Redirect',

            400 => 'Bad Request',
            401 => 'Unauthorized',
            402 => 'Payment Required',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            406 => 'Not Acceptable',
            407 => 'Proxy Authentication Required',
            408 => 'Request Timeout',
            409 => 'Conflict',
            410 => 'Gone',
            411 => 'Length Required',
            412 => 'Precondition Failed',
            413 => 'Request Entity Too Large',
            414 => 'Request-URI Too Long',
            415 => 'Unsupported Media Type',
            416 => 'Requested Range Not Satisfiable',
            417 => 'Expectation Failed',
            422 => 'Unprocessable Entity',
            423 => 'Locked',
            424 => 'Failed Dependency',
            426 => 'Upgrade Required',
            428 => 'Precondition Required',
            429 => 'Too Many Requests',
            431 => 'Request Header Fields Too Large',

            500 => 'Internal Server Error',
            501 => 'Not Implemented',
            502 => 'Bad Gateway',
            503 => 'Service Unavailable',
            504 => 'Gateway Timeout',
            505 => 'HTTP Version Not Supported',
            506 => 'Variant Also Negotiates',
            507 => 'Insufficient Storage',
            510 => 'Not Extended',
            511 => 'Network Authentication Required'
        );

        $statusText = false;
        if(isset($responses[$statusCode])) {
            $statusText = $responses[$statusCode];
        }

        return $statusText;
    }


    /**
     * Send all set HTTP headers
     */
    public function sendHeaders()
    {
        if(isset($this->_contentType)) {
            header('Content-Type: '.$this->_contentType."; charset=".$this->_encoding);
        }

        // Send all headers
        foreach($this->_headers as $key => $value) {
            if(is_null($value)) {
                continue;
            }
            if(is_array($value)) {
                foreach($value as $content) {
                    header($key . ': ' . $content, false);
                }
                continue;
            }

            header($key . ": " . $value);
        }
    }


    /**
     * Send HTTP body content
     */
    public function sendBody()
    {
        echo $this->_content;
    }


    /**
     * Send HTTP response - headers and body
     */
    public function send()
    {
        echo $this; // Executes __toString below
    }


    /**
     * Send HTTP response on string conversion
     */
    public function __toString()
    {
        // Get body content to return
        try {
            $content = (string) $this->content();
        } catch(\Exception $e) {
            $content = (string) $e;
            $this->status(500);
        }

        // Write and close session
        if(session_id()) {
            session_write_close();
        }

        // Send headers if not already sent
        if(!headers_sent()) {
            $this->sendStatus();
            $this->sendHeaders();
        }

        return $content;
    }
}
