<?php
namespace Bullet\View\Template;
use Bullet\View\Template;

/**
 * View content block class to create and hold content for blocks
 *
 * @package Bullet
 * @author Vance Lucas <vance@vancelucas.com>
 * @license http://www.opensource.org/licenses/bsd-license.php
 */
class Block extends Template
{
    // Setup
    protected $_name;

    // Closures to make up block content
    protected $_closures = array();


    /**
     * Create new named content block
     */
    public function __construct($name, $defaultContent = null)
    {
        $this->_name = $name;

        if(null !== $defaultContent) {
            $this->content($defaultContent);
        }
    }


    /**
     * Block content
     * Static global content blocks that can be set and used across views and layouts
     * 
     * @param string $name Block name
     * @param closure $closure Closure or anonymous function for block to execute and display
     */
    public function content($content = null)
    {
        if($content !== null && !is_callable($content)) {
            throw new \InvalidArgumentException("First argument must be a valid callback or closure. Given argument was not callable.");
        }

        // Getter
        if(null === $content) {
            $content = "";
            if($closures = $this->_closures) {
                // Execute all closure callbacks
                ob_start();
                    foreach($closures as $closure) {
                        echo $closure();
                    }
                $content = ob_get_clean();
            }
            return $content;
        }

        // Setter
        $this->_closures = array($content);
        return $this;
    }


    /**
     * Append content to block (end of block stack)
     * 
     * @param closure $closure Closure or anonymous function for block to execute and display
     */
    public function append($closure)
    {
        $this->_closures[] = $closure;
        return $this;
    }


    /**
     * Prepend content to block (beginning of block stack)
     * 
     * @param closure $closure Closure or anonymous function for block to execute and display
     */
    public function prepend($closure)
    {
        array_unshift($this->_closures, $closure);
        return $this;
    }
}
