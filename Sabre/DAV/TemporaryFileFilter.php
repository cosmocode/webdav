<?php

/**
 * Temporary File Filter
 *
 * This object can be placed as a filter on top of other Sabre_DAV_Tree classes,
 * such as Sabre_DAV_ObjectTree.
 *
 * The purposes is that the Temporary File Filter can intercept known files editors
 * and operation systems generate, but do not actually contain any useful information.
 *
 * Currently it supports:
 *   * OS/X style resource forks and .DS_Store
 *   * desktop.ini and Thumbs.db (windows)
 *   * .*.swp (vim temporary files)
 *   * .dat.* (smultron temporary files)
 * 
 * The filter needs to put the files in a different directory. The reason for
 * this is because clients often check if a file exists, right after they
 * created them. 
 *
 * This class does not automatically delete these files. We recommend setting up a cronjob 
 * to delate temporary files over a certain age.
 *
 * @package Sabre
 * @subpackage DAV
 * @version $Id: TemporaryFileFilter.php 216 2009-01-30 05:48:54Z evertpot $
 * @copyright Copyright (C) 2008-2009 Rooftop Solutions. All rights reserved.
 * @author Evert Pot (http://www.rooftopsolutions.nl/) 
 * @license http://code.google.com/p/sabredav/wiki/License Modified BSD License
 */
class Sabre_DAV_TemporaryFileFilter extends Sabre_DAV_FilterTree {

    /**
     * Location for temporary files 
     * 
     * @var string 
     */
    private $dataDir = null;

    /**
     * This constant is a base64-encoded, gzipped .DS_Store file.
     * We serve this exact file (decoded) for every request for .DS_Store
     * files, because we've found it speeds up Finder (a little bit).
     */
    const FINDER_FORK =
        'eJzt1zFrAjEUB/AXodhbtDh0KkfmInI4uhVdHESoh5Ru8XzScLkk5CLit+pncuq30IhnqcLRzUHej4Rwj/cPuWyBh+cmNAAmIuPTGf/glWMNojD7AK3vsIbv1g6AxXD2BDXe0vQ9LJ1TAn7C/LxqaVT11zAyU/SEtQp7BXqxFF4M8slo7LEYl7PMIeqhsH7t8BRlYV9IALr1uYtUurUhubBKlj5JosdqF1adhF2dLDp3zjdSL83mv/523S0QQgghhBBCCLk1Bn+ebXH6JUvusDRrlyFfGZdzqT1qL40WSm25wpXnCyV0fnwH34HL//8tv0C83x8AUcxHTA==';

    /**
     * Sets the directory which should be used for temporary files 
     * 
     * @param string $path 
     * @return void
     */
    public function setDataDir($path) {

        $this->dataDir = $path;

    }

    /**
     * Checks whether or not a specific path is a postive match as a temporary file. 
     *
     * If this is the case, this method returns a path which should be used to 
     * store the file otherwise it will return false.
     * 
     * @param string $path 
     * @return void
     */
    public function isTempFile($path) {

        $tempPath = basename($path);
        
        $tempFiles = array(
            '/^._(.*)$/',      // OS/X resource forks
            '/^.DS_Store$/',   // OS/X custom folder settings
            '/^desktop.ini$/', // Windows custom folder settings
            '/^Thumbs.db$/',   // Windows thumbnail cache
            '/^.(.*).swp$/',   // ViM temporary files
            '/.dat(.*)$/',     // Smultron seems to create these
        );

        $match = false;
        foreach($tempFiles as $tempFile) {

            if (preg_match($tempFile,$tempPath)) $match = true; 

        }

        if ($match) {
            $dataDir = (is_null($this->dataDir)?ini_get('session.save_path').'/sabredav/':$this->dataDir);
            return $dataDir . '/sabredav_' . md5($path) . '.tempfile';
        } else {
            return false;
        }

    }

    /**
     * Intercepts HTTP PUT requests
     * 
     * @param string $path 
     * @param resource $data 
     * @return bool 
     */
    public function put($path,$data) {

        if ($tempPath = $this->isTempFile($path)) {

            file_put_contents($tempPath,$data);

        } else return parent::put($path,$data);

    }

    /**
     * Intercepts HTTP PUT requests 
     * 
     * @param string $path 
     * @param resource $data 
     * @return bool 
     */
    public function createFile($path,$data) {

        if ($tempPath = $this->isTempFile($path)) {

            file_put_contents($tempPath,$data);

        } else return parent::createFile($path,$data);

    }

    /**
     * Intercepts HTTP GET requests 
     * 
     * @param string $path 
     * @return mixed 
     */
    public function get($path) {

        if ($tempPath = $this->isTempFile($path)) {

            if (!file_exists($tempPath)) {
                if (strpos(basename($path),'._')===0) return gzuncompress(base64_decode(self::FINDER_FORK));
                else throw new Sabre_DAV_FileNotFoundException();
            } else { 
                return fopen($tempPath,'r');
            }

        } else return parent::get($path);

    }
    
    /**
     * Intercepts HTTP DELETE requests 
     * 
     * @param string $path 
     * @return bool 
     */
    public function delete($path) {

        if ($tempPath = $this->isTempFile($path)) {
            
            return(file_exists($tempPath) && unlink($tempPath));

        } else return parent::delete($path);

    }

    /**
     * Intercepts HTTP PROPFIND requests
     *
     * This method will ensure if information is requested for a specific
     * temporary file, it will be properly returned.
     * 
     * @param string $path 
     * @param int $depth 
     * @return void
     */
    public function getNodeInfo($path,$depth=0) {

        if (($tempPath = $this->isTempFile($path)) && !$depth) {

            //echo $tempPath;
            if (!file_exists($tempPath)) {
                if (strpos(basename($path),'._')===0) {
                    return array(array(
                        'name'         => '',
                        'type'         => Sabre_DAV_Server::NODE_FILE,
                        'lastmodified' => filemtime(__FILE__),
                        'size'         => 4096, 
                    ));
                    
                } else {
                    throw new Sabre_DAV_FileNotFoundException();
                }
            }
            $props = array(
                'name'         => '',
                'type'         => Sabre_DAV_Server::NODE_FILE,
                'lastmodified' => filemtime($tempPath),
                'size'         => filesize($tempPath), 
            );

            return array($props);

        } else return parent::getNodeInfo($path,$depth);

    }

}

