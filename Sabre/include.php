<?php
/**
 * Includes all SabreDAV library files
 *
 * This file replaces the default Sabre.includes.php using a
 * SABRE_DAV define
 *
 * FIXME do we really use them all?
 */

if(!defined('SABRE_DAV')) define('SABRE_DAV',dirname(__FILE__));


// Utilities
include SABRE_DAV.'/PHP/Exception.php';
include SABRE_DAV.'/HTTP/Response.php';
include SABRE_DAV.'/HTTP/BasicAuth.php';
include SABRE_DAV.'/HTTP/Request.php';

// Basics
include SABRE_DAV.'/DAV/Lock.php';
include SABRE_DAV.'/DAV/Exception.php';

// Node interfaces
include SABRE_DAV.'/DAV/INode.php';
include SABRE_DAV.'/DAV/IFile.php';
include SABRE_DAV.'/DAV/IDirectory.php';
include SABRE_DAV.'/DAV/IProperties.php';
include SABRE_DAV.'/DAV/ILockable.php';
include SABRE_DAV.'/DAV/IQuota.php';

// Node abstract implementations
include SABRE_DAV.'/DAV/Node.php';
include SABRE_DAV.'/DAV/File.php';
include SABRE_DAV.'/DAV/Directory.php';

// Filesystem implementation
include SABRE_DAV.'/DAV/FS/Node.php';
include SABRE_DAV.'/DAV/FS/File.php';
include SABRE_DAV.'/DAV/FS/Directory.php';

// Advanced filesystem implementation
include SABRE_DAV.'/DAV/FSExt/Node.php';
include SABRE_DAV.'/DAV/FSExt/File.php';
include SABRE_DAV.'/DAV/FSExt/Directory.php';

// Lockmanagers
include SABRE_DAV.'/DAV/LockManager.php';
include SABRE_DAV.'/DAV/LockManager/FS.php';

// Trees
include SABRE_DAV.'/DAV/Tree.php';
include SABRE_DAV.'/DAV/FilterTree.php';
include SABRE_DAV.'/DAV/ObjectTree.php';
include SABRE_DAV.'/DAV/TemporaryFileFilter.php';

// Server
include SABRE_DAV.'/DAV/Server.php';

