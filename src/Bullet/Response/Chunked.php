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

class Chunked extends \Bullet\Response
{
    /**
     * Maximal size of the emitted chunks.
     * 
     * Can be set to an arbitrary non-negative integer value.
     * 
     * Zero means that every element of the content will be sent as a whole
     * chunk.
     */
    public $chunkSize = 4096;
    protected $_items;

    public function __construct($items, $status = 200) {
        parent::__construct('', $status);
        $this->_items = $items;
    }

    protected function _sendChunk($buf) {
        printf("%x\r\n%s\r\n", strlen($buf), $buf);
        flush(); // HHVM doesn't do implicit flush, it eats all memory instead. Nice.
    }

    /**
     * Emits whole chunks and remove them from a buffer.
     */
    protected function _sendChunks(&$buf, $chunkSize) {
        $chunks = str_split($buf, $chunkSize);

        if (strlen(end($chunks)) != $chunkSize) {
            $buf = array_pop($chunks);
        } else {
            $buf = '';
        }

        foreach ($chunks as $chunk) {
            $this->_sendChunk($chunk);
        }
    }

    public function send() {
        // Write and close session
        if(session_id()) {
            session_write_close();
        }

        if (!headers_sent()) {
            $this->header('Transfer-Encoding', 'chunked');
            $this->header('Content-Encoding', 'identity');

            $this->sendStatus();
            $this->sendHeaders();
        }

        if ($this->chunkSize <= 0) {
            foreach ($this->_items as $chunk) {
                $this->_sendChunk((string) $chunk); // Just send chunks as they are, no need to buffer around.
            }
            // Emit a zero-length closing chunk
            $this->_sendChunk('');
        } else {
            // Everyday I'm buffering...
            $buf = '';
            foreach ($this->_items as $chunk) {
                $buf .= (string) $chunk; // Grow the buffer
                $this->_sendChunks($buf, $this->chunkSize); // Emit whole chunks. Might leave a partial chunk behind in $buf.
            }
            // The remaining buffer might still have a partial chunk, send it.
            if (strlen($buf) > 0) {
                $this->_sendChunk($buf);
            }
            $this->_sendChunk('');
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
        trigger_error("Chunked response is converted to string. This doesn not make sense. Use send() instead.", E_USER_ERROR);
    }
}
