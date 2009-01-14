<?php
/**
 * Implements WebDAV access to the media directory
 *
 * @author Andreas Gohr <gohr@cosmocode.de>
 */

/**
 * Represents one directory and provides methods to access its contents
 */
class media_DAV_Directory extends Sabre_DAV_Directory {  #FIXME inherit from DokuWiki_* instead?
    /**
     * the namespace inside the media directory
     */
    private $path;

    /**
     * Constructor. Initializes the path components
     */
    public function __construct($path) {
        $this->path = cleanID($path);
    }

    /**
     * Returns the basename of the current directory
     *
     * This is what will be shown in the file browser's dir listing
     */
    public function getName() {
        if(!$this->path) return 'media'; //FIXME a bit ugly. just fixes display name of the virtual dir
        return noNS($this->path);
    }

    /**
     * Return a directory listing of the current media namespace
     */
    public function getChildren() {
        global $conf;
        $children = array();

        $data = array();
        search($data,$conf['mediadir'],array($this,'search_callback'),array(),$this->path);
        foreach($data as $file){
            if($file['isdir']){
                $children[] = new media_DAV_Directory($file['id']);
            }else{
                $children[] = new media_DAV_File($file['id']);
            }
        }

        return $children;
    }


    // FIXME when's this called?
/*
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
*/

    /**
     * Callback for search(), lists all files and directories in media folder
     */
    public function search_callback(&$data,$base,$file,$type,$lvl,$opts){
        $info           = array();
        $info['id']     = pathID($file,true);
        if($info['id'] != cleanID($info['id'])) return false; // not valid pageid

        // is a directory?
        if($type == 'd') {
            $info['isdir'] = true;
        }else{
            $info['isdir'] = false;
            //check ACL for namespace (we have no ACL for mediafiles)
            if(auth_quickaclcheck(getNS($info['id']).':*') < AUTH_READ){
                return false;
            }
        }

        // add to result
        $data[] = $info;
        return false;
    }
}

/**
 * Provides access to a single file in the directory
 */
class media_DAV_File extends Sabre_DAV_File {

    private $id;
    private $path;

    public function __construct($id) {
        $this->id   = cleanID($id);
        $this->path = mediaFN($this->id);
    }

    public function getName() {
        return noNS($this->id);
    }

    public function getSize() {
        return filesize($this->path);
    }

    // FIXME seems not to work? all files have today's date
    public function getLastModified() {
        return filemtime($this->path);
    }

    public function get() {
        //check ACL for namespace (we have no ACL for mediafiles)
        if(auth_quickaclcheck(getNS($this->id).':*') < AUTH_READ){
            throw new Sabre_DAV_PermissionDeniedException('You are not allowed to access this file');
        }
        return io_readFile($this->path,false); //FIXME inefficient for large data
    }

    public function put($data) {
        //FIXME
    }

}




//Setup VIM: ex: et ts=4 enc=utf-8 :
