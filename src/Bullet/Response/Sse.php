<?php

namespace Bullet\Response;

/**
 * Server Sent Events Response Class
 *
 * Extends the Bullet Response class to be able to handle iterable
 * (list, or array-like) list of events.
 * 
 * Each event is an array with fields "event", "data", "id", and "retry".
 * 
 * The class does not check event format.
 * 
 * Fields with null values will be sent as line with only the field name.
 * 
 * Comments can be sent by adding a field name starting with a colon. Such
 * field may or may not have a non-null value.
 * 
 * Empty arrays are valid according to the specification.
 * 
 * @see https://html.spec.whatwg.org/multipage/server-sent-events.html#server-sent-events
 * 
 * @package Bullet\Response
 * @author Fábián Tamás László <giganetom@gmail.com>
 * @license http://www.opensource.org/licenses/mit-license.php
 */

class Sse extends \Bullet\Response
{
    protected $_events;

    public function __construct($events, $status = 200)
    {
        parent::__construct('', $status);
        $this->_events = $events;
    }

    public static function cleanupOb()
    {
        while (ob_get_level() > 0) {
            ob_end_flush();
        }
    }

    protected function _sendEvent(array $event) {
        $buf = '';
        foreach ($event as $k => $v) {
            if (null === $v) {
                $buf .= sprintf("%s\r\n", $k);
            } else {
                $buf .= sprintf("%s: %s\r\n", $k, $v);
            }
        }
        $buf .= "\r\n";
        printf("%x\r\n%s\r\n", strlen($buf), $buf);
        flush(); // HHVM doesn't do implicit flush, it eats all memory instead. Nice.
    }

    public function send() {
        // Write and close session
        if(session_id()) {
            session_write_close();
        }

        if (!headers_sent()) {
            $this->header('Content-Type', 'text/event-stream');
            $this->header('Transfer-Encoding', 'chunked');
            $this->header('Content-Encoding', 'identity');
            $this->header('X-Accel-Buffering', 'no');

            $this->sendStatus();
            $this->sendHeaders();
        }

        foreach ($this->_events as $event) {
            $this->_sendEvent($event);
        }
    }

    /**
     * This is bad, such response cannot be converted to
     * a string.
     * 
     * Always return empty string, but use send() as a side-effect.
     */
    public function __toString()
    {
        trigger_error("Tried to convert an SSE response to string. This does not make sense. Use send() instead.", E_USER_ERROR);
    }
}
