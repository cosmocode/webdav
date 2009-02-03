<?php
/**
 * WebDAV endpoint
 *
 * @author  Andreas Gohr <andi@splitbrain.org>
 * @author  Evert Pot (http://www.rooftopsolutions.nl/)
 * @license GPL 2
 */

if(!defined('DOKU_INC')) define('DOKU_INC',dirname(__FILE__).'/../../../');
require_once(DOKU_INC.'inc/init.php');
require_once(DOKU_INC.'inc/common.php');
require_once(DOKU_INC.'inc/events.php');
require_once(DOKU_INC.'inc/parserutils.php');

require_once(DOKU_INC.'inc/auth.php');
dbglog('connect from '.clientIP());

//FIXME putting this here disables anonymous browsing :-/
//FIXME http://support.microsoft.com/kb/841215 Basic Auth disabled in Windows
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
require_once(WEBDAV_DIR.'/inc/BaseType_DAV.php');
require_once(WEBDAV_DIR.'/inc/DokuWiki_DAV.php');

// main
$lockManager = new Sabre_DAV_LockManager_FS($conf['cachedir']); //FIXME use our own?
$objectTree = new Sabre_DAV_ObjectTree(new DokuWiki_DAV_Directory(''));
$objectTree->setLockManager($lockManager);
$server = new Sabre_DAV_Server($objectTree);
$server->setBaseUri(DOKU_REL.'lib/plugins/webdav/dav.php');
$server->exec();


//Setup VIM: ex: et ts=4 enc=utf-8 :
