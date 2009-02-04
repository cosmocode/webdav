<?php
/**
 * Implements WebDAV access to the wiki pages in raw format
 *
 * @author Andreas Gohr <gohr@cosmocode.de>
 */

/**
 * Represents one directory and provides methods to access its contents
 */
class txt_DAV_Directory extends BaseType_DAV_Directory {

    /**
     * Return a directory listing of the current media namespace
     */
    public function getChildren() {
        global $conf;
        $children = array();
        $data = array();
        search($data,$conf['datadir'],array($this,'search_callback'),array(),$this->ns);
        foreach($data as $file){
            if($file['isdir']){
                $children[] = new txt_DAV_Directory($file['id']);
            }else{
                $children[] = new txt_DAV_File($file['id']);
            }
        }

        return $children;
    }


    // FIXME implement
    // public function getChild($name) {


    /**
     * Callback for search(), lists all pages and namespaces in given folder
     */
    public function search_callback(&$data,$base,$file,$type,$lvl,$opts){
        $info           = array();

        if($type == 'd') {
            $info['id']    = pathID($file,true);
            $info['isdir'] = true;
        }else{
            if(substr($file,-4) != '.txt') return false; // not a page
            $info['isdir'] = false;
            $info['id']    = pathID($file,false);

            if(auth_quickaclcheck($info['id']) < AUTH_READ){
                return false;
            }
        }

        // add to result
        $data[] = $info;
        return false;
    }

    public function createDirectory($dir){

        global $conf;
        if(auth_quickaclcheck($this->ns.':*') < AUTH_CREATE){
            throw new Sabre_DAV_PermissionDeniedException('Insufficient Permissions');
        }

        // no dir hierarchies
        $dir = strtr($dir, array(':'=>$conf['sepchar'],
                                 '/'=>$conf['sepchar'],
                                 ';'=>$conf['sepchar']));
        $dir = cleanID($this->ns.':'.$dir.':fake'); //add fake pageid


        io_createNamespace($dir,'pages');
        // missing return value!
    }

    public function delete(){
        $dir = dirname(wikiFN($this->ns.':fake'));
        if(@!file_exists($dir)){
            throw new Sabre_DAV_FileNotFoundException('Directory does not exist');
        }

        $files = glob("$dir/*");
        if(count($files)){
            throw new Sabre_DAV_PermissionDeniedException('Directory not empty');
        }

        if(!rmdir($dir)){
            throw new Sabre_DAV_PermissionDeniedException('failed to delete directory');
        }
    }
}

/**
 * Provides access to a single file in the directory
 */
class txt_DAV_File extends BaseType_DAV_File {

    public function __construct($id) {
        $this->id   = cleanID($id);
        $this->id   = preg_replace('/\.txt$/','',$this->id);
        $this->path = wikiFN($this->id);
    }

    public function getName() {
        return noNS($this->id).'.txt';
    }

    public function get() {
        if(auth_quickaclcheck($this->id) < AUTH_READ){
            throw new Sabre_DAV_PermissionDeniedException('You are not allowed to access this file');
        }

        $fh = fopen($this->path,'rb');
        if(!$fh) throw new Sabre_DAV_PermissionDeniedException('Failed to open file for reading'.$this->path);

        return $fh;
    }

    public function delete() {
        $this->put(null); // it's just a save with empty content
    }

    public function put($stream) {
        global $lang;
        global $conf;

        // check ACL permissions
        if(auth_quickaclcheck($this->id) < AUTH_EDIT){
            throw new Sabre_DAV_PermissionDeniedException('Insufficient Permissions');
        }

        // read the whole page to memory
        $text = '';
        if(!is_null($stream)){
            while ( ($buf=fread( $stream, 8192 )) != '' ) {
                $text .= $buf;
            }
        }

        if(!utf8_check($text)){
            throw new Sabre_DAV_PermissionDeniedException('Seems not to be valid UTF-8 text');
        }

        $summary = 'changed via webdav'; #FIXME
        saveWikiText($this->id,$text,$summary,false);

        #FIXME how to know it worked??
    }

}




//Setup VIM: ex: et ts=4 enc=utf-8 :
