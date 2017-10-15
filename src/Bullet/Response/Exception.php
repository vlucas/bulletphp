<?php

namespace Bullet\Response;

/**
 * Chunked Response Class
 *
 * Extends the Bullet Response class to be able to handle iterable
 * (list, or array-like) $content. Such content will be sent with the
 * HTTP chunked encoding.
 * 
 * @package Bullet\Response
 * @author Fábián Tamás László <giganetom@gmail.com>
 * @license http://www.opensource.org/licenses/mit-license.php
 */

class Exception extends \Exception
{
    public function __construct($code, $message = null, Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
