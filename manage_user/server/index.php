<?php

// Set flag that this is a parent file
define( '_JEXEC', 1 );
define( 'JPATH_BASE', dirname(__FILE__) );
define( 'DS', DIRECTORY_SEPARATOR );
define( 'LOGLEVEL', 2 );

require_once JPATH_BASE.DS.'includes'.DS.'defines.php';
require_once JPATH_BASE.DS.'includes'.DS.'framework.php';
require_once 'classes.php';
jimport('joomla.utilities.arrayhelper');
// We want to echo the errors so that the xmlrpc client has a chance to capture them in the payload
JError::setErrorHandling( E_ERROR,	 'die' );
JError::setErrorHandling( E_WARNING, 'ignore' );
JError::setErrorHandling( E_NOTICE,	 'ignore' );

// create the mainframe object
$mainframe =& JFactory::getApplication('administrator');
$document =& JFactory::getDocument();
$db =& JFactory::getDBO();

// Identify request method
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
	$req = JRequest::get('post');
} elseif ($_SERVER['REQUEST_METHOD'] == 'GET') {
	$req = JRequest::get('get');
} else {
	$req = $_REQUEST;
}

$data = json_decode($req['rest_data']);

// authenticate the api caller.
$credentials['username'] = $data->auth_user;
$credentials['password'] = $data->auth_pass;
$options = array();
$options['autoregister'] = false;
$options['group'] = 'Super Users';
$authenticated = $mainframe->login($credentials, $options);
$user = JFactory::getUser();

// Unset access credos
unset($data->auth_user,$data->auth_pass);

// Run method only if login succeds and user is > Administrator
if (($authenticated === true && $user->id) || $noauth) {

	// load all available remote calls
	JPluginHelper::importPlugin( 'restapi', 'manage_user' );
	$plugin = $mainframe->triggerEvent( 'onRestCall', array($data) );

} else {
	$plugin[0] = array('errors'=>'Cannot login - Please provide correct auth_user and auth_password');
}


	$document->setMimeEncoding('application/json');
	$op = json_encode($plugin[0]);


// Logout
$mainframe->logout();


/* **** 
	* LOGLEVEL 0 - No logs save
	* 1 - Save only date, Method, IP
	* 2 - Save All. 
*/	

if(LOGLEVEL != 0) 
{

	if(LOGLEVEL == 2) 
	{		
		ApiHelperLogs::simpleLog($url['path'] . " request: " . JArrayHelper::toString($req));
		ApiHelperLogs::simpleLog("Response: ". $op);
	} else if(LOGLEVEL == 1) {
		ApiHelperLogs::simpleLog($url['path'] . " request: " . JArrayHelper::toString($req));
	}	
} 	



// Deliver response to client
echo $op;
jexit();


class ApiHelperLogs
{
    /**
     * Simple log
     * @param string $comment  The comment to log
     * @param int $userId      An optional user ID
     */
    function simpleLog($comment, $level=1)
    {
        // Include the library dependancies
        jimport('joomla.error.log');
        $my = JFactory::getUser();
        $options = array('format' => "{DATE} - {TIME} - {IP} - {COMMENT}");
        // Create the instance of the log file in case we use it later
        $log = &JLog::getInstance('restapi.log');
        $log->addEntry(array('comment' => $comment, 'ip' => $_SERVER['REMOTE_ADDR']));
    }
}	
