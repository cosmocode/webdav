<?php

/**
 * File class
 *
 * This is a helper class, that should aid in getting file classes setup.
 * Most of its methods are implemented, and throw permission denied exceptions 
 * 
 * @package Sabre
 * @subpackage DAV
 * @version $Id: File.php 212 2009-01-30 05:26:11Z evertpot $
 * @copyright Copyright (C) 2007-2009 Rooftop Solutions. All rights reserved.
 * @author Evert Pot (http://www.rooftopsolutions.nl/) 
 * @license http://code.google.com/p/sabredav/wiki/License Modified BSD License
 */
abstract class Sabre_DAV_File extends Sabre_DAV_Node implements Sabre_DAV_IFile {

    /**
     * Updates the data
     *
     * data is a readable stream resource.
     * 
     * @param resource $data 
     * @return void 
     */
    public function put($data) { 

        throw new Sabre_DAV_PermissionDeniedException('Permission denied to change data');

    }

    /**
     * Returns the data 
     *
     * This method may either return a string or a readable stream resource
     *
     * @return mixed 
     */
    public function get() { 

        throw new Sabre_DAV_PermissionDeniedException('Permission denied to read this file');

    }

}

