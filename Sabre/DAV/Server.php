<?php

/**
 * Main DAV server class
 * 
 * @package Sabre
 * @subpackage DAV
 * @version $Id: Server.php 213 2009-01-30 05:30:34Z evertpot $
 * @copyright Copyright (C) 2007-2009 Rooftop Solutions. All rights reserved.
 * @author Evert Pot (http://www.rooftopsolutions.nl/) 
 * @license http://code.google.com/p/sabredav/wiki/License Modified BSD License
 */
class Sabre_DAV_Server {

    /**
     * Inifinity is used for some request supporting the HTTP Depth header and indicates that the operation should traverse the entire tree
     */
    const DEPTH_INFINITY = -1;

    /**
     * Nodes that are files, should have this as the type property
     */
    const NODE_FILE = 1;

    /**
     * Nodes that are directories, should use this value as the type property
     */
    const NODE_DIRECTORY = 2;

    const PROP_SET = 1;
    const PROP_REMOVE = 2;


    /**
     * The tree object
     * 
     * @var Sabre_DAV_Tree 
     */
    protected $tree;

    /**
     * The base uri 
     * 
     * @var string 
     */
    protected $baseUri = '/';

    /**
     * httpResponse 
     * 
     * @var Sabre_HTTP_Response 
     */
    protected $httpResponse;

    /**
     * httpRequest
     * 
     * @var Sabre_HTTP_Request 
     */
    protected $httpRequest;

    /**
     * Class constructor 
     * 
     * @param Sabre_DAV_Tree $tree The tree object 
     * @return void
     */
    public function __construct(Sabre_DAV_Tree $tree) {

        $this->tree = $tree;
        $this->httpResponse = new Sabre_HTTP_Response();
        $this->httpRequest = new Sabre_HTTP_Request();

    }

    /**
     * Starts the DAV Server 
     *
     * @return void
     */
    public function exec() {

        try {

            $this->invoke();

        } catch (Sabre_DAV_Exception $e) {

            $this->httpResponse->sendStatus($e->getHTTPCode());
            $this->httpResponse->sendBody((string)$e);

        } catch (Exception $e) {

            $this->httpResponse->sendStatus(500);
            throw $e;

        }

    }

    /**
     * Sets the base responding uri
     * 
     * @param string $uri
     * @return void
     */
    public function setBaseUri($uri) {

        $this->baseUri = $uri;    

    }

    /**
     * Sets an alternative HTTP response object 
     * 
     * @param Sabre_HTTP_Response $response 
     * @return void
     */
    public function setHTTPResponse(Sabre_HTTP_Response $response) {

        $this->httpResponse = $response;

    }

    /**
     * Sets an alternative HTTP request object 
     * 
     * @param Sabre_HTTP_Request $request 
     * @return void
     */
    public function setHTTPRequest(Sabre_HTTP_Request $request) {

        $this->httpRequest = $request;

    }


    // {{{ HTTP Method implementations
    
    /**
     * HTTP OPTIONS 
     * 
     * @return void
     */
    protected function httpOptions() {

        $this->httpResponse->setHeader('Allow',strtoupper(implode(' ',$this->getAllowedMethods())));
        if ($this->tree->supportsLocks()) {
            $this->httpResponse->setHeader('DAV','1,2,3');
        } else {
            $this->httpResponse->setHeader('DAV','1,3');
        }
        $this->httpResponse->setHeader('MS-Author-Via','DAV');
        $this->httpResponse->sendStatus(200);

    }

    /**
     * HTTP GET
     *
     * This method simply fetches the contents of a uri, like normal
     * 
     * @return void
     */
    protected function httpGet() {

        $nodeInfo = $this->tree->getNodeInfo($this->getRequestUri(),0);

        if ($nodeInfo[0]['size']) $this->httpResponse->setHeader('Content-Length',$nodeInfo[0]['size']);

        $this->httpResponse->setHeader('Content-Type', 'application/octet-stream');
        $this->httpResponse->sendStatus(200);
        $this->httpResponse->sendBody($this->tree->get($this->getRequestUri()));

    }

    /**
     * HTTP HEAD
     *
     * This method is normally used to take a peak at a url, and only get the HTTP response headers, without the body
     * This is used by clients to determine if a remote file was changed, so they can use a local cached version, instead of downloading it again
     *
     * @todo currently not implemented
     * @return void
     */
    protected function httpHead() {

        $nodeInfo = $this->tree->getNodeInfo($this->getRequestUri(),0);
        if ($nodeInfo[0]['size']) $this->httpResponse->setHeader('Content-Length',$nodeInfo[0]['size']);
        $this->httpResponse->setHeader('Content-Type', 'application/octet-stream');
        $this->httpResponse->sendStatus(200);

    }

    /**
     * HTTP Delete 
     *
     * The HTTP delete method, deletes a given uri
     *
     * @return void
     */
    protected function httpDelete() {

        if (!$this->validateLock()) throw new Sabre_DAV_lockedException('The resource you tried to delete is locked');
        $this->tree->delete($this->getRequestUri());
        $this->httpResponse->sendStatus(204);

    }


    /**
     * WebDAV PROPFIND 
     *
     * This WebDAV method requests information about an uri resource, or a list of resources
     * If a client wants to receive the properties for a single resource it will add an HTTP Depth: header with a 0 value
     * If the value is 1, it means that it also expects a list of sub-resources (e.g.: files in a directory)
     *
     * The request body contains an XML data structure that has a list of properties the client understands 
     * The response body is also an xml document, containing information about every uri resource and the requested properties
     *
     * It has to return a HTTP 207 Multi-status status code
     *
     * @todo currently this method doesn't do anything with the request-body, and just returns a default set of properties 
     * @return void
     */
    protected function httpPropfind() {

        // $xml = new Sabre_DAV_XMLReader(file_get_contents('php://input'));
        $properties = $this->parsePropfindRequest($this->httpRequest->getBody(true));


        $depth = $this->getHTTPDepth(1);
        // The only two options for the depth of a propfind is 0 or 1 
        if ($depth!=0) $depth = 1;

        // The requested path
        $path = $this->getRequestUri();

        $fileList = $this->tree->getNodeInfo($path,$depth);

        foreach($fileList as $k=>$file) {
            $newProps = $this->tree->getProperties($path,$properties);
            $newProps['DAV:#getlastmodified'] =  (isset($file['lastmodified'])?$file['lastmodified']:time());
            $newProps['DAV:#getcontentlength'] = (isset($file['size'])?$file['size']:0);
            $newProps['DAV:#resourcetype'] =  $file['type'];
            if (isset($file['quota-used'])) $newProps['DAV:#quota-used-bytes'] = $file['quota-used'];
            if (isset($file['quota-available'])) $newProps['DAV:#quota-available-bytes'] = $file['quota-available'];
            $newProps['href'] = $file['name']; 
            //print_r($newProps);die();

            $fileList[$k] = $newProps;

        }


        // This is a multi-status response
        $this->httpResponse->sendStatus(207);
        $this->httpResponse->setHeader('Content-Type','text/xml; charset="utf-8"');
        $data = $this->generatePropfindResponse($fileList,$properties);
        $this->httpResponse->sendBody($data);

    }

    /**
     * WebDAV PROPPATCH
     *
     * This method is called to update properties on a Node. The request is an XML body with all the mutations.
     * In this XML body it is specified which properties should be set/updated and/or deleted
     *
     * @todo SabreDAV does not support custom properties yet, so this will be ignored
     * @return void
     */
    protected function httpPropPatch() {

        // Checking possible locks
        if (!$this->validateLock()) throw new Sabre_DAV_LockedException('The resource you tried to edit is locked');
       
        $mutations = $this->parsePropPatchRequest($this->httpRequest->getBody(true));

        $result = $this->tree->updateProperties($this->getRequestUri(),$mutations);

        if (!$result) {
            
            $result = array();
            foreach($mutations as $mutations) {
                $result[] = array($mutations[1],403);
            }

        }

        $this->httpResponse->sendStatus(207);
        echo $this->generatePropPatchResponse($this->getRequestUri(),$result);

    }

    /**
     * HTTP PUT method 
     * 
     * This HTTP method updates a file, or creates a new one.
     *
     * If a new resource was created, a 201 Created status code should be returned. If an existing resource is updated, it's a 200 Ok
     *
     * @return void
     */
    protected function httpPut() {

        // First we'll do a check to see if the resource already exists
        try {
            $info = $this->tree->getNodeInfo($this->getRequestUri(),0); 
            
            // Checking potential locks
            if (!$this->validateLock()) throw new Sabre_DAV_LockedException('The resource you tried to edit is locked');

            // We got this far, this means the node already exists.
            // This also means we should check for the If-None-Match header
            if ($this->httpRequest->getHeader('If-None-Match')) {

                throw new Sabre_DAV_PreconditionFailedException('The resource already exists, and an If-None-Match header was supplied');

            }
            
            // If the node is a collection, we'll deny it
            if ($info[0]['type'] == self::NODE_DIRECTORY) throw new Sabre_DAV_ConflictException('PUTs on directories are not allowed'); 

            $this->tree->put($this->getRequestUri(),$this->httpRequest->getBody());
            $this->httpResponse->sendStatus(200);

        } catch (Sabre_DAV_FileNotFoundException $e) {

            // If we got here, the resource didn't exist yet.

            // Validating the lock on the parent collection
            $parent = dirname($this->getRequestUri());
            if (!$this->validateLock($parent)) throw new Sabre_DAV_LockedException('You\'re creating a new file, but the parent collection is currently locked');

            // This means the resource doesn't exist yet, and we're creating a new one
            $this->tree->createFile($this->getRequestUri(),$this->httpRequest->getBody());
            $this->httpResponse->sendStatus(201);

        }

    }

    /**
     * HTTP POST method
     *
     * This a WebDAV extension. This WebDAV server supports HTTP POST file uploads, coming from for example a browser.
     * It works the exact same as a PUT, only accepts 1 file and can either create a new file, or update an existing one
     *
     * If a post variable 'redirectUrl' is supplied, it will return a 'Location: ' header, thus redirecting the client to the given location
     */
    protected function httpPOST() {

        foreach($_FILES as $file) {

            $this->tree->put($this->getRequestUri().'/' . basename($file['name']),fopen($file['tmp_name'],'r'));
            break;

        }

        // We assume > 5.1.2, which has the header injection attack prevention
        if (isset($_POST['redirectUrl']) && is_string($_POST['redirectUrl'])) $this->httpResponse->setHeader('Location', $_POST['redirectUrl']);

    }


    /**
     * WebDAV MKCOL
     *
     * The MKCOL method is used to create a new collection (directory) on the server
     *
     * @return void
     */
    protected function httpMkcol() {

        if (!$this->validateLock()) throw new Sabre_DAV_LockedException('The resource you tried to edit is locked');

        $requestUri = $this->getRequestUri();

        // If there's a body, we're supposed to send an HTTP 415 Unsupported Media Type exception
        $requestBody = $this->httpRequest->getBody(true);
        if ($requestBody) throw new Sabre_DAV_UnsupportedMediaTypeException();

        // We'll check if the parent exists, and if it's a collection. If this is not the case, we need to throw a conflict exception
        
        try {
            if ($nodeInfo = $this->tree->getNodeInfo(dirname($requestUri),0)) {
                if ($nodeInfo[0]['type']==self::NODE_FILE) {
                    throw new Sabre_DAV_ConflictException('Parent node is not a directory');
                }
            }
        } catch (Sabre_DAV_FileNotFoundException $e) {

            // This means the parent node doesn't exist, and we need to throw a 409 Conflict
            throw new Sabre_DAV_ConflictException('Parent node does not exist');

        }

        try {
            $nodeInfo = $this->tree->getNodeInfo($requestUri);

            // If we got here.. it means there's already a node on that url, and we need to throw a 405
            throw new Sabre_DAV_MethodNotAllowedException('The directory you tried to create already exists');

        } catch (Sabre_DAV_FileNotFoundException $e) {
            // This is correct
        }

        $this->tree->createDirectory($this->getRequestUri());
        $this->httpResponse->sendStatus(201);

    }

    /**
     * WebDAV HTTP MOVE method
     *
     * This method moves one uri to a different uri. A lot of the actual request processing is done in getCopyMoveInfo
     * 
     * @return void
     */
    protected function httpMove() {

        $moveInfo = $this->getCopyAndMoveInfo();

        if (!$this->validateLock(array($moveInfo['source'],$moveInfo['destination']))) throw new Sabre_DAV_LockedException('The resource you tried to edit is locked');

        $this->tree->move($moveInfo['source'],$moveInfo['destination']);

        // If a resource was overwritten we should send a 204, otherwise a 201
        $this->httpResponse->sendStatus($moveInfo['destinationExists']?204:201);

    }

    /**
     * WebDAV HTTP COPY method
     *
     * This method copies one uri to a different uri, and works much like the MOVE request
     * A lot of the actual request processing is done in getCopyMoveInfo
     * 
     * @return void
     */
    protected function httpCopy() {

        $copyInfo = $this->getCopyAndMoveInfo();

        if (!$this->validateLock($copyInfo['destination'])) throw new Sabre_DAV_LockedException('The resource you tried to edit is locked');

        $this->tree->copy($copyInfo['source'],$copyInfo['destination']);

        // If a resource was overwritten we should send a 204, otherwise a 201
        $this->httpResponse->sendStatus($copyInfo['destinationExists']?204:201);

    }

    /**
     * Locks an uri
     *
     * The WebDAV lock request can be operated to either create a new lock on a file, or to refresh an existing lock
     * If a new lock is created, a full XML body should be supplied, containing information about the lock such as the type 
     * of lock (shared or exclusive) and the owner of the lock
     *
     * If a lock is to be refreshed, no body should be supplied and there should be a valid If header containing the lock
     *
     * Additionally, a lock can be requested for a non-existant file. In these case we're obligated to create an empty file as per RFC4918:S7.3
     * 
     * @return void
     */
    protected function httpLock() {

        $uri = $this->getRequestUri();

        $lastLock = null;
        if (!$this->validateLock($uri,$lastLock)) {

            // If ohe existing lock was an exclusive lock, we need to fail
            if (!$lastLock || $lastLock->scope == Sabre_DAV_Lock::EXCLUSIVE) {
                //var_dump($lastLock);
                throw new Sabre_DAV_LockedException('You tried to lock a url that was already locked'  . print_r($lastLock,true));
            }

        }

        if ($body = $this->httpRequest->getBody(true)) {
            // There as a new lock request
            $lockInfo = Sabre_DAV_Lock::parseLockRequest($body);
            $lockInfo->depth = $this->getHTTPDepth(0); 
            $lockInfo->uri = $uri;
            if($lastLock && $lockInfo->scope != Sabre_DAV_Lock::SHARED) throw new Sabre_DAV_LockedException('You tried to lock a url that was already locked');

        } elseif ($lastLock) {

            // This must have been a lock refresh
            $lockInfo = $lastLock;

        } else {
            
            // There was neither a lock refresh nor a new lock request
            throw new Sabre_DAV_BadRequestException('An xml body is required for lock requests');

        }

        if ($timeout = $this->getTimeoutHeader()) $lockInfo->timeout = $timeout;

        // If we got this far.. we should go check if this node actually exists. If this is not the case, we need to create it first
        try {
            $nodeInfo = $this->tree->getNodeInfo($uri,0);
        } catch (Sabre_DAV_FileNotFoundException $e) {
            
            // It didn't, lets create it 
            $this->tree->createFile($uri,fopen('data://text/plain,'));
            
            // We also need to return a 201 in this case
            $this->httpResponse->sendStatus(201);

        }

        $this->tree->lockNode($uri,$lockInfo);
        $this->httpResponse->setHeader('Lock-Token','opaquelocktoken:' . $lockInfo->token);
        echo $this->generateLockResponse($lockInfo);

    }

    /**
     * Unlocks a uri
     *
     * This WebDAV method allows you to remove a lock from a node. The client should provide a valid locktoken through the Lock-token http header
     * The server should return 204 (No content) on success
     *
     * @return void
     */
    protected function httpUnlock() {

        $uri = $this->getRequestUri();
        
        $lockToken = $this->httpRequest->getHeader('Lock-Token');

        // If the locktoken header is not supplied, we need to throw a bad request exception
        if (!$lockToken) throw new Sabre_DAV_BadRequestException('No lock token was supplied');

        $locks = $this->tree->getLocks($uri);

        // We're grabbing the node information, just to rely on the fact it will throw a 404 when the node doesn't exist 
        $this->tree->getNodeInfo($uri,0); 

        foreach($locks as $lock) {

            if ('<opaquelocktoken:' . $lock->token . '>' == $lockToken) {

                $this->tree->unlockNode($uri,$lock);
                $this->httpResponse->sendStatus(204);
                return;

            }

        }

        // If we got here, it means the locktoken was invalid
        throw new Sabre_DAV_PreconditionFailedException('The uri wasn\'t locked, or the supplied locktoken was incorrect' . print_r($locks,true));

    }

    // }}}
    // {{{ HTTP/WebDAV protocol helpers 

    /**
     * Handles a http request, and execute a method based on its name 
     * 
     * @return void
     */
    protected function invoke() {

        $method = strtolower($this->httpRequest->getMethod()); 

        // Make sure this is a HTTP method we support
        if (in_array($method,$this->getAllowedMethods())) {

            call_user_func(array($this,'http' . $method));

        } else {

            // Unsupported method
            throw new Sabre_DAV_MethodNotImplementedException();

        }

    }

    /**
     * Returns an array with all the supported HTTP methods 
     * 
     * @return array 
     */
    protected function getAllowedMethods() {

        $methods = array('options','get','head','post','delete','trace','propfind','mkcol','put','proppatch','copy','move');
        if ($this->tree->supportsLocks()) array_push($methods,'lock','unlock');
        return $methods;

    }

    /**
     * Gets the uri for the request, keeping the base uri into consideration 
     * 
     * @return string
     */
    public function getRequestUri() {

        return $this->calculateUri($this->httpRequest->getUri());

    }

    /**
     * Calculates the uri for a request, making sure that the base uri is stripped out 
     * 
     * @param string $uri 
     * @throws Sabre_DAV_PermissionDeniedException A permission denied exception is thrown whenever there was an attempt to supply a uri outside of the base uri
     * @return string
     */
    public function calculateUri($uri) {

        if ($uri[0]!='/' && strpos($uri,'://')) {

            $uri = parse_url($uri,PHP_URL_PATH);

        }

        $uri = str_replace('//','/',$uri);

        if (strpos($uri,$this->baseUri)===0) {

            return trim(urldecode(substr($uri,strlen($this->baseUri))),'/');

        } else {

            throw new Sabre_DAV_PermissionDeniedException('Requested uri (' . $uri . ') is out of base uri (' . $this->baseUri . ')');

        }

    }

    /**
     * Returns the HTTP depth header
     *
     * This method returns the contents of the HTTP depth request header. If the depth header was 'infinity' it will return the Sabre_DAV_Server::DEPTH_INFINITY object
     * It is possible to supply a default depth value, which is used when the depth header has invalid content, or is completely non-existant
     * 
     * @param mixed $default 
     * @return int 
     */
    public function getHTTPDepth($default = self::DEPTH_INFINITY) {

        // If its not set, we'll grab the default
        $depth = $this->httpRequest->getHeader('Depth');
        if (!$depth) $depth = $default;

        // Infinity
        if ($depth == 'infinity') $depth = self::DEPTH_INFINITY;
        else {
            // If its an unknown value. we'll grab the default
            if ($depth!=="0" && (int)$depth==0) $depth == $default;
        }

        return $depth;

    }

    /**
     * validateLock should be called when a write operation is about to happen
     * It will check if the requested url is locked, and see if the correct lock tokens are passed 
     *
     * @param mixed $urls List of relevant urls. Can be an array, a string or nothing at all for the current request uri
     * @param mixed $lastLock This variable will be populated with the last checked lock object (Sabre_DAV_Lock)
     * @return bool
     */
    protected function validateLock($urls = null,&$lastLock = null) {

        if (is_null($urls)) {
            $urls = array($this->getRequestUri());
        } elseif (is_string($urls)) {
            $urls = array($urls);
        } elseif (!is_array($urls)) {
            throw new Sabre_DAV_Exception('The urls parameter should either be null, a string or an array');
        }

        $conditions = $this->getIfConditions();

        // We're going to loop through the urls and make sure all lock conditions are satisfied
        foreach($urls as $url) {

            $locks = $this->tree->getLocks($url);

            // If there were no conditions, but there were locks, we fail 
            if (!$conditions && $locks) {
                reset($locks);
                $lastLock = current($locks);
                return false;
            }
          
            // If there were no locks or conditions, we go to the next url
            if (!$locks && !$conditions) continue;

            foreach($conditions as $condition) {

                $conditionUri = $condition['uri']?$this->calculateUri($condition['uri']):'';

                // If the condition has a url, and it isn't part of the affected url at all, check the next condition
                if ($conditionUri && strpos($url,$conditionUri)!==0) continue;

                // The tokens array contians arrays with 2 elements. 0=true/false for normal/not condition, 1=locktoken
                // At least 1 condition has to be satisfied
                foreach($condition['tokens'] as $conditionToken) {

                    // Match all the locks
                    foreach($locks as $lockIndex=>$lock) {

                        $lockToken = 'opaquelocktoken:' . $lock->token;
                        // Checking NOT
                        if (!$conditionToken[0] && $lockToken != $conditionToken[1]) {

                            // Condition valid, onto the next
                            continue 3;
                        }
                        if ($conditionToken[0] && $lockToken == $conditionToken[1]) {

                            $lastLock = $lock;
                            // Condition valid and lock matched
                            unset($locks[$lockIndex]);
                            continue 3;

                        }

                    }
               }
               // No conditions matched, so we fail
               throw new Sabre_DAV_PreconditionFailedException('The tokens provided in the if header did not match');
            }

            // Conditions were met, we'll also need to check if all the locks are gone
            if (count($locks)) {

                // There's still locks, we fail
                $lastLock = current($locks);
                return false;

            }


        }

        // We got here, this means every condition was satisfied
        return true;

    }

    /**
     * This method is created to extract information from the WebDAV HTTP 'If:' header
     *
     * The If header can be quite complex, and has a bunch of features. We're using a regex to extract all relevant information
     * The function will return an array, containg structs with the following keys
     *
     *   * uri   - the uri the condition applies to. This can be an empty string for 'every relevant url'
     *   * tokens - The lock token. another 2 dimensional array containg 2 elements (0 = true/false.. If this is a negative condition its set to false, 1 = the actual token)
     * 
     * @return void
     */
    function getIfConditions() {

        $header = $this->httpRequest->getHeader('If'); 
        if (!$header) return array();

        $matches = array();

        $regex = '/(?:\<(?P<uri>.*?)\>\s)?\((?P<not>Not\s)?\<(?P<token>.*?)\>\)/im';
        preg_match_all($regex,$header,$matches,PREG_SET_ORDER);

        $conditions = array();

        foreach($matches as $match) {
            $condition = array(
                'uri'   => $match['uri'],
                'tokens' => array(
                    array($match['not']==false,$match['token'])
                ),    
            );

            if (!$condition['uri'] && count($conditions)) $conditions[count($conditions)-1]['tokens'][] = array($match['not']==false,$match['token']);
            else {
                $conditions[] = $condition;
            }

        }

        return $conditions;

    }

    function getTimeoutHeader() {

        $header = $this->httpRequest->getHeader('Timeout');
        
        if ($header) {

            if (stripos($header,'second-')===0) $header = (int)(substr($header,7));
            else if (strtolower($header)=='infinite') $header=Sabre_DAV_Lock::TIMEOUT_INFINITE;
            else throw new Sabre_DAV_BadRequestException('Invalid HTTP timeout header');

        } else {

            $header = 0;

        }

        return $header;

    }

    
    /**
     * Returns information about Copy and Move requests
     * 
     * This function is created to help getting information about the source and the destination for the 
     * WebDAV MOVE and COPY HTTP request. It also validates a lot of information and throws proper exceptions 
     * 
     * The returned value is an array with the following keys:
     *   * source - Source path
     *   * destination - Destination path
     *   * destinationExists - Wether or not the destination is an existing url (and should therefore be overwritten)
     *
     * @return array 
     */
    function getCopyAndMoveInfo() {

        $source = $this->getRequestUri();

        // Collecting the relevant HTTP headers
        if (!$this->httpRequest->getHeader('Destination')) throw new Sabre_DAV_BadRequestException('The destination header was not supplied');
        $destination = $this->calculateUri($this->httpRequest->getHeader('Destination'));
        $overwrite = $this->httpRequest->getHeader('Overwrite');
        if (!$overwrite) $overwrite = 'T';
        if (strtoupper($overwrite)=='T') $overwrite = true;
        elseif (strtoupper($overwrite)=='F') $overwrite = false;

        // We need to throw a bad request exception, if the header was invalid
        else throw new Sabre_DAV_BadRequestException('The HTTP Overwrite header should be either T or F');

        // Collection information on relevant existing nodes
        $sourceInfo = $this->tree->getNodeInfo($source);

        try {
            $destinationParentInfo = $this->tree->getNodeInfo(dirname($destination));
            if ($destinationParentInfo[0]['type'] == self::NODE_FILE) throw new Sabre_DAV_UnsupportedMediaTypeException('The destination node is not a collection');
        } catch (Sabre_DAV_FileNotFoundException $e) {

            // If the destination parent node is not found, we throw a 409
            throw new Sabre_DAV_ConflictException('The destination node is not found');

        }

        try {

            $destinationInfo = $this->tree->getNodeInfo($destination);
            
            // If this succeeded, it means the destination already exists
            // we'll need to throw precondition failed in case overwrite is false
            if (!$overwrite) throw new Sabre_DAV_PreconditionFailedException('The destination node already exists, and the overwrite header is set to false');

        } catch (Sabre_DAV_FileNotFoundException $e) {

            // Destination didn't exist, we're all good
            $destinationInfo = false;

        }

        // These are the three relevant properties we need to return
        return array(
            'source'            => $source,
            'destination'       => $destination,
            'destinationExists' => $destinationInfo==true,
        );

    }


    // }}} 
    // {{{ XML Readers & Writers  
    
    
    /**
     * Generates a WebDAV propfind response body based on a list of nodes 
     * 
     * @param array $list The list with nodes
     * @param array $properties The properties that should be returned
     * @return string 
     */
    private function generatePropfindResponse($list,$properties) {

        $xw = new XMLWriter();
        $xw->openMemory();
        // Windows XP doesn't like indentation
        //$xw->setIndent(true);
        $xw->startDocument('1.0','utf-8');
        $xw->startElementNS('d','multistatus','DAV:');

        foreach($list as $entry) {

            $this->writeProperty($xw,$this->httpRequest->getUri(),$entry, $properties);

        }

        $xw->endElement();
        return $xw->outputMemory();

    }

    /**
     * Generates the xml for a single item in a propfind response.
     *
     * This method is called by generatePropfindResponse
     * 
     * @param XMLWriter $xw 
     * @param string $baseurl 
     * @param array $data
     * @param array $properties
     * @return void
     */
    private function writeProperty(XMLWriter $xw,$baseurl,$data, $properties) {

        $xw->startElement('d:response');
        $xw->startElement('d:href');

        // Base url : /services/dav/mydirectory
        $url = rtrim(urldecode($baseurl),'/');

        // Adding the node in the directory
        if (isset($data['href']) && trim($data['href'],'/')) $url.= '/' . trim((isset($data['href'])?$data['href']:''),'/');

        $url = explode('/',$url);

        foreach($url as $k=>$item) $url[$k] = rawurlencode($item);

        $url = implode('/',$url);

        // Adding the protocol and hostname. We'll also append a slash if this is a collection
        $xw->text(/*'http://' . $_SERVER['HTTP_HOST'] .*/ $url . ($data['DAV:#resourcetype']==self::NODE_DIRECTORY?'/':''));
        $xw->endElement(); //d:href

        $xw->startElement('d:propstat');

        // We have to collect the properties we don't know
        $notFound = array();

        $xw->startElement('d:prop');
        if (!$properties) $properties = array_keys($data);

        $nsList = array(
            'DAV:' => 'd',
        );

        foreach($properties as $property) {

            // We can skip href
            if ($property=='href') continue;

            if(!isset($data[$property])) {
                $notFound[] = $property;
                continue;
            }

            $value = $data[$property];

            $propName = explode('#',$property,2);
           
            if (isset($nsList[$propName[0]])) {

                $xw->startElement($nsList[$propName[0]] . ':' . $propName[1]);

            } else {

                $xw->startElement($propName[1]);
                $xw->writeAttribute('xmlns',$propName[0]);

            }

            switch($property) {
                case 'DAV:#getlastmodified' :
                    $xw->writeAttribute('xmlns:b','urn:uuid:c2f41010-65b3-11d1-a29f-00aa00c14882/');
                    $xw->writeAttribute('b:dt','dateTime.rfc1123');
                    if (!(int)$value) $value = strtotime($value);
                    $xw->text(date(DATE_RFC1123,$value));
                    break;

                case 'DAV:#resourcetype' :
                    if ($value==self::NODE_DIRECTORY) $xw->writeRaw('<d:collection />');
                    break;

                default :
                    if (is_scalar($value)) {
                        $xw->text($value);
                    } else {
                        $xw->text($value->textContent);
                    }
                    break;

            }

            $xw->endElement();

        }

        $xw->endElement(); // d:prop
       
        $xw->writeElement('d:status',$this->httpResponse->getStatusMessage(200));

        $xw->endElement(); // :d:propstat

        if ($notFound) { 
            $xw->startElement('d:propstat');
            $xw->startElement('d:prop');
            foreach($notFound as $property) {
                list($ns,$tagName) = explode('#',$property,2);
                if ($ns=='DAV:') {
                    $xw->writeElement('d:' . $tagName,'');
                } else {
                    $xw->startElement($tagName);
                    $xw->writeAttribute('xmlns',$ns);
                    $xw->endElement();
                }
            }
            $xw->endElement(); // d:prop
            $xw->writeElement('d:status',$this->httpResponse->getStatusMessage(404));
            $xw->endElement(); // :d:propstat

        }
        $xw->endElement(); // d:response
    }

    function generateLockResponse($lockInfo) {

        $xw = new XMLWriter();
        $xw->openMemory();
        $xw->setIndent(true);
        $xw->startDocument('1.0','utf-8');
        $xw->startElementNS('d','prop','DAV:');
            $xw->startElement('d:lockdiscovery');
                $xw->startElement('d:activelock');
                    $xw->startElement('d:lockscope');
                        $xw->writeRaw($lockInfo->scope==Sabre_DAV_Lock::EXCLUSIVE?'<d:exclusive />':'<d:shared />');
                    $xw->endElement();
                    $xw->startElement('d:locktype');
                        $xw->writeRaw('<d:write />');
                    $xw->endElement();
                    $xw->writeElement('d:depth',($lockInfo->depth == self::DEPTH_INFINITY?'infinity':$lockInfo->depth));
                    $xw->writeElement('d:timeout','Second-' . $lockInfo->timeout);
                    $xw->startElement('d:locktoken');
                        $xw->writeElement('d:href','opaquelocktoken:' . $lockInfo->token);
                    $xw->endElement();
                    $xw->writeElement('d:owner',$lockInfo->owner);
                $xw->endElement();
            $xw->endElement();    
        $xw->endElement();
        return $xw->outputMemory();

    }

    /**
     * This method parses a PropPatch request 
     * 
     * @param string $body xml body
     * @return array list of properties in need of updating or deletion
     */
    protected function parsePropPatchRequest($body) {

        //We'll need to change the DAV namespace declaration to something else in order to make it parsable
        $body = preg_replace("/xmlns(:[A-Za-z0-9_])?=(\"|\')DAV:(\"|\')/","xmlns\\1=\"urn:DAV\"",$body);

        $errorsetting =  libxml_use_internal_errors(true);
        libxml_clear_errors();
        $dom = new DOMDocument();
        $dom->loadXML($body,LIBXML_NOWARNING | LIBXML_NOERROR);
        $dom->preserveWhiteSpace = false;

        
        if ($error = libxml_get_last_error()) {
            switch ($error->code) {
                // Error 100 is a non-absolute namespace, which WebDAV allows
                case 100 :
                    break;
                default :    
                    throw new Sabre_DAV_BadRequestException('The request body was not a valid proppatch request: ' . print_r($error,true));

            }
        }
        

        $operations = array();

        foreach($dom->firstChild->childNodes as $child) {

            if ($child->namespaceURI != 'urn:DAV' || ($child->localName != 'set' && $child->localName !='remove')) continue; 
            
            $propList = $this->parseProps($child);
            foreach($propList as $k=>$propItem) {

                $operations[] = array($child->localName=='set'?1:2,$k,$propItem);

            }

        }

        return $operations;

    }

    protected function parsePropFindRequest($body) {

        // If the propfind body was empty, it means IE is requesting 'all' properties
        if (!$body) return array();

        $errorsetting =  libxml_use_internal_errors(true);
        libxml_clear_errors();
        $dom = new DOMDocument();
        $body = preg_replace("/xmlns(:[A-Za-z0-9_])?=(\"|\')DAV:(\"|\')/","xmlns\\1=\"urn:DAV\"",$body);
        $dom->preserveWhiteSpace = false;
        $dom->loadXML($body,LIBXML_NOERROR);
        if($error = libxml_get_last_error()) {
            switch($error->code) {
                // Error 100 is a non-absolute namespace, which WebDAV allows
                case 100 :
                    break;
                default :
                    throw new Sabre_DAV_BadRequestException('The request body was not a valid proppatch request' . print_r($error,true));
            }
        }
        libxml_use_internal_errors($errorsetting); 
        $elem = $dom->getElementsByTagNameNS('urn:DAV','propfind')->item(0);
        return array_keys($this->parseProps($elem)); 

    }

    protected function parseProps(DOMNode $prop) {

        $propList = array(); 
        foreach($prop->childNodes as $propNode) {

            if ($propNode->namespaceURI == 'urn:DAV' && $propNode->localName == 'prop') {

                foreach($propNode->childNodes as $propNodeData) {

                    /* if ($propNodeData->attributes->getNamedItem('xmlns')->value == "") {
                        // If the namespace declaration is an empty string, litmus expects us to throw a HTTP400
                        throw new Sabre_DAV_BadRequestException('Invalid namespace: ""');
                    } */

                    if ($propNodeData->namespaceURI=='urn:DAV') $ns = 'DAV:'; else $ns = $propNodeData->namespaceURI;
                    $propList[$ns . '#' . $propNodeData->localName] = $propNodeData->textContent;
                }

            }

        }
        return $propList; 

    }

    protected function generatePropPatchResponse($href,$mutations) {

        $xw = new XMLWriter();
        $xw->openMemory();
        $xw->setIndent(true);
        $xw->startDocument('1.0','utf-8');
        $xw->startElementNS('d','multistatus','DAV:');
            $xw->startElement('d:response');
                $xw->writeElement('d:href',$href);
                foreach($mutations as $mutation) {

                    $xw->startElement('d:propstat');
                        $xw->startElement('d:prop');
                            $element = explode('#',$mutation[0]);
                            $xw->writeElementNS('X',$element[1],$element[0],null);
                        $xw->endElement(); // d:prop
                        $xw->writeElement('d:status',$this->httpResponse->getStatusMessage($mutation[1]));
                    $xw->endElement(); // d:propstat

                }
            $xw->endElement(); // d:response
        $xw->endElement(); // d:multistatus
        return $xw->outputMemory();

    }

    // }}}

}

