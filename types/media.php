<?php
/**
 * Implements WebDAV access to the media directory
 *
 * @todo The functions in inc/media.php should be refactored to be reusable here
 *       and the XMLRPC interface to avoid all that duplicate code
 *
 * @author Andreas Gohr <gohr@cosmocode.de>
 */

/**
 * Represents one directory and provides methods to access its contents
 */
class media_DAV_Directory extends BaseType_DAV_Directory {

    /**
     * Return a directory listing of the current media namespace
     */
    public function getChildren() {
        global $conf;
        $children = array();
        $data = array();
        search($data,$conf['mediadir'],array($this,'search_callback'),array(),$this->ns);
        foreach($data as $file){
            if($file['isdir']){
                $children[] = new media_DAV_Directory($file['id']);
            }else{
                $children[] = new media_DAV_File($file['id']);
            }
        }

        return $children;
    }


    // FIXME implement
    // public function getChild($name) {


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

    public function createDirectory($dir){
        global $conf;
        if(auth_quickaclcheck($this->ns.':*') < AUTH_CREATE){
            throw new Sabre_DAV_PermissionDeniedException('Insufficient Permissions');
        }

        // no dir hierarchies
        $dir = strtr($dir, array(':'=>$conf['sepchar'],
                                 '/'=>$conf['sepchar'],
                                 ';'=>$conf['sepchar']));
        $dir = cleanID($dir);

        $dir = mediaFN($this->ns.':'.$dir);
        if(@file_exists($dir)){
            throw new Sabre_DAV_PermissionDeniedException('Directory exists');
        }

        if(!io_mkdir_p($dir)){
            throw new Sabre_DAV_PermissionDeniedException('Directory creation failed, filepermissions?');
        }
    }

    public function delete(){
        $dir = mediaFN($this->ns);
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
class media_DAV_File extends BaseType_DAV_File {

    public function __construct($id) {
        $this->id   = cleanID($id);
        $this->path = mediaFN($this->id);
    }

    public function get() {
        //check ACL for namespace (we have no ACL for mediafiles)
        if(auth_quickaclcheck(getNS($this->id).':*') < AUTH_READ){
            throw new Sabre_DAV_PermissionDeniedException('You are not allowed to access this file');
        }

        $fh = fopen($this->path,'rb');
        if(!$fh) throw new Sabre_DAV_PermissionDeniedException('Failed to open file for reading'.$this->path);

        return $fh;
    }

    //FIXME is missing a few checks media_delete would do
    public function delete() {
        if(auth_quickaclcheck(getNS($this->id).':*') < AUTH_DELETE){
            throw new Sabre_DAV_PermissionDeniedException('Insufficient Permissions');
        }
        if(!unlink($this->path)){
            throw new Sabre_DAV_PermissionDeniedException('Insufficient Permissions');
        }
    }

    public function put($stream) {
        global $lang;
        global $conf;

        // check ACL permissions
        if(@file_exists($this->path)){
            $perm_needed = AUTH_DELETE;
        }else{
            $perm_needed = AUTH_UPLOAD;
        }
        if(auth_quickaclcheck(getNS($this->id).':*') < $perm_needed){
            throw new Sabre_DAV_PermissionDeniedException('Insufficient Permissions');
        }

        // get and check mime type
        list($ext,$mime,$dl) = mimetype($this->id);
        $types = array_keys(getMimeTypes());
        $types = array_map(create_function('$q','return preg_quote($q,"/");'),$types);
        $regex = join('|',$types);
        if(!preg_match('/\.('.$regex.')$/i',$this->id))
            throw new Sabre_DAV_PermissionDeniedException($lang['uploadwrong'].' YAR');

        // execute content check FIXME currently needs a file path!
        /*
        $ok = media_contentcheck($this->path,$mime);
        if($ok == -1){
            throw new Sabre_DAV_PermissionDeniedException(sprintf($lang['uploadbadcontent'],".$iext"));
        }elseif($ok == -2){
            throw new Sabre_DAV_PermissionDeniedException($lang['uploadspam']);
        }elseif($ok == -3){
            throw new Sabre_DAV_PermissionDeniedException($lang['uploadxss']);
        }
        */

        //FIXME should MEDIA_UPLOAD_FINISH be triggered here?

        // prepare directory (shouldn't be needed as it should already exist, but doesn't harm)
        io_createNamespace($this->id, 'media');

        // save the file
        if(!$this->_streamWriter($stream,$this->path)){
            throw new Sabre_DAV_Exception($lang['uploadfail']);
        }
    }

}




//Setup VIM: ex: et ts=4 enc=utf-8 :
