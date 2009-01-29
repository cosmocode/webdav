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
        return io_readFile($this->path,false); //FIXME inefficient for large data
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

    public function put($data) {
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
        if(io_saveFile($this->path,$data)){
            chmod($this->path, $conf['fmode']);
//FIXME            media_notify($this->id,$this->path,$mime);
        }else{
            throw new Sabre_DAV_Exception($lang['uploadfail']);
        }
    }

}




//Setup VIM: ex: et ts=4 enc=utf-8 :
