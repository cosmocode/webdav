<?php
/**
 * Virtual directory handler
 *
 * @author  Andreas Gohr <andi@splitbrain.org>
 * @author  Evert Pot (http://www.rooftopsolutions.nl/)
 * @license GPL 2
 */

if(!defined('DOKU_INC')) die();
if(!defined('WEBDAV_DIR')) die();

/**
 * Represents one directory and provides methods to access its contents
 */
class DokuWiki_DAV_Directory extends Sabre_DAV_Directory {

    /**
     * Virtual mount point, decides on what data to access
     */
    private $virtual;

    /**
     * The real path below the virtual mount point
     */
    private $namespace;

    /**
     * The whole path ($virtual and $namespace)
     */
    private $path;

    /**
     * A list of registered types
     */
    private $types = array();

    /**
     * Initialize all needed components
     *
     * Initializes the path components and load the type definitions
     */
    public function __construct($path) {
        //FIXME clean path with cleanID?
        list($this->virtual,$this->namespace) = explode(':',$path,2);
        $this->path = $path;

        $types = glob(WEBDAV_DIR.'/types/*.php');
        foreach($types as $type){
            require_once($type);
            $this->types[] = basename($type,'.php'); //strip extension
        }
    }

    /**
     * Returns the basename of the current directory
     *
     * This is what will be shown in the file browser's dir listing
     */
    public function getName() {
        return noNS($this->path);
    }

    /**
     * Display an info and die
     *
     * This function is only called when accessing the endpoint from a browser.
     *
     * @author Andreas Gohr <gohr@cosmocode.de>
     * @todo   link endpoint URL with pseudo uri scheme if supported by browser
     */
    public function get() {
        $url = DOKU_URL.'lib/plugins/webdav/dav.php';

        header('Content-Type: text/html; charset=utf-8');
        echo <<<EOT
  <!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN"
   "http://www.w3.org/TR/html4/loose.dtd">
  <html>
    <head><title>DokuWiki WebDAV Endpoint</title></head>
    <body style="font-family: Arial, sans-serif">
      <div style="width:60%; margin: auto; background-color: #ccf;
                  border: 1px solid #aaf; padding: 0.5em 1em;">
      <h1 style="font-size: 120%">WebDAV endpoint</h1>
      <p>This is a WebDAV endpoint. Access it with a WebDAV client, not with your browser</p>

      <p>Endpoint: $url</p>
      </div>
    </body>
  </html>
EOT;
        exit;
    }


    /**
     * Return a "directory listing"
     *
     * This function returns all the virtual directory names for the different
     * types the backend makes accessible
     */
    public function getChildren() {
        global $conf;
        $children = array();

        if($this->virtual == ''){                    // handle virtual top namespace
            foreach($this->types as $type){
                $class = $type.'_DAV_Directory';
                $children[] = new $class('');
            }
            return $children;

        }else {
            dbglog('DokuWiki_DAV_Directory ran into a virtual folder');
            throw new Sabre_DAV_FileNotFoundException('Shouldn\'t happen');
        }
        return $children;
    }

    // FIXME implement
    //public function getChild($name);

}

