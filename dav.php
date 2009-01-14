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

//FIXME putting this here disables anonymous browsing :-/
if ($conf['useacl'] && !isset($_SERVER['PHP_AUTH_USER'])) {
   header('WWW-Authenticate: Basic realm="DokuWiki WebDAV"');
   header('HTTP/1.0 401 Unauthorized');
   echo 'Please log in.';
   exit;
}
dbglog($_SERVER['REMOTE_USER']);

require_once(DOKU_INC.'inc/auth.php');
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
            $children[] = new DokuWiki_DAV_Directory('txt'); //FIXME
            //FIXME list all available converters here
            return $children;

        }else {
            dbglog('DokuWiki_DAV_Directory ran into a virtual folder');
            throw new Sabre_DAV_FileNotFoundException('Shouldn\'t happen');
        }
        return $children;
    }


    // FIXME when's this called?
    public function getChild($name) {
        list($name) = explode('.',$name);

        // This version of getChild strips out any extensions
        foreach($this->getChildren() as $child) {

            $childName = $child->getName();
            list($childName) = explode('.',$childName);
            if ($childName==$name) return $child;

        }
        throw new Sabre_DAV_FileNotFoundException('File not found: ' . $name);

    }

}

/**
 * Provides access to a single file in the directory
 *
 * FIXME needs to handle file creation denials
 * FIXME still contains stuff from the txt access example
 */
class DokuWiki_DAV_File extends Sabre_DAV_File {

    private $path;

    public function __construct($path) {

        $this->path = $path;

    }

    public function getName() {

        $parts = explode(':',$this->path);
        return $parts[count($parts)-1] . '.txt';

    }

    public function get() {

        if(auth_quickaclcheck($this->path) < AUTH_READ){
            throw new Sabre_DAV_PermissionDeniedException('You are not allowed to view this page');
        }
        return rawWiki($this->path,'');

    }

    public function put($text) {

        global $TEXT;
        global $lang;

        $id    = cleanID($this->path);
        $TEXT  = trim($text);
        $sum   = '';
        $minor = '';

        if(auth_quickaclcheck($id) < AUTH_EDIT)
            throw new Sabre_DAV_PermissionDeniedException('You are not allowed to edit this page');

        // Check, if page is locked
        if(checklock($id))
            return new Sabre_DAV_PermissionDeniedException('The page is currently locked');

        // SPAM check
        if(checkwordblock())
            return new Sabre_DAV_PermissionDeniedException('Positive wordblock check');

        // autoset summary on new pages
        if(!page_exists($id) && empty($sum)) {
            $sum = $lang['created'];
        }

        // autoset summary on deleted pages
        if(page_exists($id) && empty($TEXT) && empty($sum)) {
            $sum = $lang['deleted'];
        }

        lock($id);
        saveWikiText($id,$TEXT,$sum,$minor);
        unlock($id);

    }

}

// main
$objectTree = new Sabre_DAV_ObjectTree(new DokuWiki_DAV_Directory(''));
$server = new Sabre_DAV_Server($objectTree);
$server->setBaseUri(DOKU_REL.'lib/plugins/webdav/dav.php');
$server->exec();



//Setup VIM: ex: et ts=4 enc=utf-8 :
