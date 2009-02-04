<?php
/**
 * Implements WebDAV access to the wiki pages in Open Office format
 *
 * @author Andreas Gohr <gohr@cosmocode.de>
 */

// dependency fix
require_once(WEBDAV_DIR.'/types/txt.php');

/**
 * Represents one directory and provides methods to access its contents
 *
 * Inherits everything from txt
 */
class odt_DAV_Directory extends txt_DAV_Directory {

}

/**
 * Provides access to a single file in the directory
 *
 * Inherits a lot from txt
 */
class odt_DAV_File extends txt_DAV_File {

    public function __construct($id) {
        $this->id   = cleanID($id);
        $this->id   = preg_replace('/\.odt$/','',$this->id);
        $this->path = wikiFN($this->id);
    }

    public function getName() {
        return noNS($this->id).'.odt';
    }

    public function get() {
        if(auth_quickaclcheck($this->id) < AUTH_READ){
            throw new Sabre_DAV_PermissionDeniedException('You are not allowed to access this file');
        }

        // here is the magic
        $data = p_cached_output(wikiFN($this->id,''),'odt');
        return $data;
    }

    public function delete() {
        $this->_saveContent(''); // it's just a save with empty content
    }

    public function put($stream) {
        throw new Sabre_DAV_MethodNotImplementedException('No Write Support yet');
        //$this->_saveContent($this->_streamReader($stream));
    }

}




//Setup VIM: ex: et ts=4 enc=utf-8 :
