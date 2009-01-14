<?php

/**
 * This interface represents a file or leaf in the tree.
 *
 * The nature of a file is, as you might be aware of, that it doesn't contain sub-nodes and has contents
 * 
 * @package Sabre
 * @subpackage DAV
 * @version $Id: IFile.php 182 2009-01-12 22:56:52Z evertpot $
 * @copyright Copyright (C) 2007-2009 Rooftop Solutions. All rights reserved.
 * @author Evert Pot (http://www.rooftopsolutions.nl/) 
 * @license http://code.google.com/p/sabredav/wiki/License Modified BSD License
 */
interface Sabre_DAV_IFile extends Sabre_DAV_INode {

    /**
     * Updates the data 
     * 
     * @param string $data 
     * @return void 
     */
    function put($data);

    /**
     * Returns the data 
     * 
     * @return string 
     */
    function get();

}

