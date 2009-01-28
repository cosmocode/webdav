<?php
/**
 * WebDAV endpoint
 *
 * @copyright Copyright (C) 2009 Rooftop Solutions. All rights reserved.
 * @author Evert Pot (http://www.rooftopsolutions.nl/)
 * @license http://code.google.com/p/sabredav/wiki/License Modified BSD License
 */

if(!defined('DOKU_INC')) define('DOKU_INC',dirname(__FILE__).'/../../../');
require_once(DOKU_INC.'inc/init.php');
require_once(DOKU_INC.'inc/common.php');
require_once(DOKU_INC.'inc/events.php');
require_once(DOKU_INC.'inc/parserutils.php');

require_once(DOKU_INC.'inc/auth.php');
//FIXME putting this here disables anonymous browsing :-/
if ($conf['useacl'] && !isset($_SERVER['REMOTE_USER'])) {
   header('WWW-Authenticate: Basic realm="DokuWiki WebDAV"');
   header('HTTP/1.0 401 Unauthorized');
   echo 'Please log in.';
   exit;
}
dbglog('login: '.$_SERVER['REMOTE_USER']);

require_once(DOKU_INC.'inc/pageutils.php');
require_once(DOKU_INC.'inc/search.php');

//close session
session_write_close();

define('WEBDAV_DIR',dirname(__FILE__).'/');
require_once(WEBDAV_DIR.'/Sabre/include.php');
require_once(WEBDAV_DIR.'/types/media.php'); //FIXME dynamically load these

// classes below

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
     * Constructor. Initializes the path components
     */
    public function __construct($path) {
        //FIXME clean path with cleanID?
        list($this->virtual,$this->namespace) = explode(':',$path,2);
        $this->path = $path;
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
     */
    public function getChildren() {
        global $conf;
        $children = array();

        if($this->virtual == ''){                    // handle virtual top namespace
            $children[] = new media_DAV_Directory('');
//            $children[] = new DokuWiki_DAV_Directory('txt'); //FIXME
            //FIXME list all available converters here
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


// main
$objectTree = new Sabre_DAV_ObjectTree(new DokuWiki_DAV_Directory(''));
$server = new Sabre_DAV_Server($objectTree);
$server->setBaseUri(DOKU_REL.'lib/plugins/webdav/dav.php');
$server->exec();


//Setup VIM: ex: et ts=4 enc=utf-8 :
