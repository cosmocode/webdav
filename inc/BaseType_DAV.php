<?php
/**
 * Base Classes to inherit for various types
 *
 * @author Andreas Gohr <gohr@cosmocode.de>
 */

/**
 * Represents one directory and provides methods to access its contents
 */
abstract class BaseType_DAV_Directory extends Sabre_DAV_Directory {

    /**
     * The type this class implements
     */
    protected $type;

    /**
     * the namespace inside the media directory
     */
    protected $ns;

    /**
     * Constructor. Initializes the path components
     */
    public function __construct($ns) {
        $this->ns = cleanID($ns);
        $this->type = preg_replace('/_DAV_Directory$/i','',get_class($this));
    }

    /**
     * Returns the basename of the current directory
     *
     * This is what will be shown in the file browser's dir listing.
     * For correct display in the root of the FS it needs to return
     * the type name when $this->ns is empty
     *
     * FIXME can we avoid this somehow?
     */
    public function getName() {
        if(!$this->ns) return $this->type;
        return noNS($this->ns);
    }

    /**
     * Return a directory listing of the current media namespace
     */
    //public function getChildren();
    // FIXME implement
    // public function getChild($name) {


    /**
     * Handle file creation
     *
     * This default just pushes the work to $type_DAV_File->put
     */
    public function createFile($id, $stream = null){
        $class = $this->type.'_DAV_File';
        $obj   = new $class($this->ns.':'.$id);
        $obj->put($stream);
    }
}


/**
 * Provides access to a single file in the directory
 */
abstract class BaseType_DAV_File extends Sabre_DAV_File {
    /**
     * The current ID
     */
    protected $id;

    /**
     * The current path (same as ID but full FS path)
     */
    protected $path;

    /**
     * Constructor. Sets $this->id but not $this->path, you
     * probably want to override this
     */
    public function __construct($id) {
        $this->id   = cleanID($id);
    }

    /**
     * Return the (basename) of the file
     */
    public function getName() {
        return noNS($this->id);
    }

    /**
     * Return the size of the file
     */
    public function getSize() {
        return @filesize($this->path);
    }

    /**
     * Return the last modified date of the file
     */
    public function getLastModified() {
        return @filemtime($this->path);
    }

    /**
     * Reads from the given stream and writes to the given file
     *
     * The file will be created or truncated depending on existance.
     * Works similar to io_saveFile() as it takes care of locking
     * and directory creation
     *
     * @todo might be worth to be moved to core
     *
     * @return boolean  true when file was written successfully
     */
    protected function _streamWriter($stream,$file) {
        global $conf;

        $fileexists = @file_exists($file);
        io_makeFileDir($file);
        io_lock($file);
        $fh = @fopen($file,'wb');
        if(!$fh){
            io_unlock($file);
            return false;
        }

        while ( ($buf=fread( $stream, 8192 )) != '' ) {
            fwrite($fh,$buf);
        }

        fclose($fh);
        if(!$fileexists and !empty($conf['fperm'])) chmod($file, $conf['fperm']);
        io_unlock($file);
        return true;
    }

    /**
     * Reads a given stream into a string and returnes it
     */
    protected function _streamReader($stream) {
        $data = '';
        while ( ($buf=fread( $stream, 8192 )) != '' ) {
            $data .= $buf;
        }
        return $data;
    }
}




//Setup VIM: ex: et ts=4 enc=utf-8 :
