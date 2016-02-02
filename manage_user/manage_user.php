<?php
/**
 * @version		$Id: example.php 10381 2008-06-01 03:35:53Z pasamio $
 * @package		Joomla
 * @subpackage	JFramework
 * @copyright	Copyright (C) 2005 - 2008 Open Source Matters. All rights reserved.
 * @license		GNU/GPL, see LICENSE.php
 * Joomla! is free software. This version may have been modified pursuant
 * to the GNU General Public License, and as distributed it includes or
 * is derivative of works licensed under the GNU General Public License or
 * other free or open source software licenses.
 * See COPYRIGHT.php for copyright notices and details.
 */

// Check to ensure this file is included in Joomla!
defined( '_JEXEC' ) or die( 'Restricted access' );

jimport('joomla.plugin.plugin');
jimport('joomla.user.helper');
jimport('joomla.application.component.helper');
jimport('joomla.user.user');

require_once( JPATH_SITE .DS.'libraries'.DS.'joomla'.DS.'filesystem'.DS.'folder.php');

class plgRestapiManage_User extends JPlugin
{


	function plgRestapiManage_User( & $subject, $config ) {
		parent::__construct( $subject, $config );
	}


	function onRestCall($data) {
		$action=$data->action;
		$response = $this->$action($data);
		return $response;
	}
	
	function isValidEmail( $email ) {
	
		$pattern = "^[_a-z0-9-]+(\.[_a-z0-9-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,3})$";

    	if ( eregi( $pattern, $email )) {
    	  return true;
      } else {
        return false;
      }   
	}
	
	
	function create_user( $data )	
	{	
        $usersConfig=& JComponentHelper::getParams( 'com_users' );
		$user = clone(JFactory::getUser());
              
		$error_messages = array();
		$fieldname = array();
		$response = NULL;
		$validated = true;
		
		//Check if user already exists
		$id = JUserHelper::getUserId($data->email);
		if($id != 0)
		{
			$data->username = $data->email;
			$data->id = $id;
			return $this->update_user($data);
		}

		
		if($data->email==""){
			$validated  = false;
			$error_messages[] = array("id"=>1,"fieldname"=>"email","message"=>"Email cannot be blank");  
		} elseif($this->isValidEmail($data->email == false)) {
      		$validated  = false;
			$error_messages[] = array("id"=>1,"fieldname"=>"email","message"=>"Please set valid email id eg.(example@gmail.com). Check 'email' field in request");
		}
	        
		if( $data->password==""){
			$validated  = false;
			$error_messages[] = array("id"=>1,"fieldname"=>"password","message"=>"Password cannot be blank");
   		}
	        
		if( $data->name=="") {
    		$validated  = false;
			$error_messages[] = array("id"=>1,"fieldname"=>"name","message"=>"Name cannot be blank");
   		}	        
	        
  		
		if( true == $validated ) { 
			jimport('joomla.filesystem.file');
			jimport('joomla.utilities.utility');
			
		    $user->set('username', $data->email);
			$user->set('password', $data->password);
			$user->set('password_clear', $data->password);
			$user->set('name', $data->name);
			$user->set('email', $data->email);
			$user->set('sendEmail', 0);

			$user->setParam('sugar_id',$data->sugar_id); 
			$user->setParam('sugar_module',$data->sugar_module);

			// password encryption
			$salt  = JUserHelper::genRandomPassword(32);
			$crypt = JUserHelper::getCryptedPassword($user->password, $salt);
			$user->password = "$crypt:$salt";
			$user->block = 0;

		    $userConfig = JComponentHelper::getParams('com_users');
			// user group/type
			$user->set('id', '');
		    
			if(!isset($data->user_group)){
				$defaultUserGroup = array(2,12); //User group Registered,PP Member as default
			}else{
				$defaultUserGroup = explode('*',$data->user_group);
				array_push($defaultUserGroup,2); //Save default user group
				array_push($defaultUserGroup,12); //Save default user group
		       }

   			$user->set('groups', $defaultUserGroup);
			$date =& JFactory::getDate();
			$user->set('registerDate', $date->toMySQL());
			
			if(!$user->save()){               	                        	        
				$error_messages[] = array("id"=>1,"fieldname"=>"usernameoremail","message"=>"Username or Email already registered with Joomla ."); 
			}else{
		
				//SAVE USER PROFILE TO MEMBER PLUGIN 		
				$member['id'] =$user->id;
				foreach($data->member as $key=>$val)
				{
					$member['member'][$key] = $val;
				}
				if (!empty($member['member']['birthdate'])) {
					$date = new JDate($member['member']['birthdate']);
					$member['member']['birthdate'] = $date->format('Y-m-d');
				}
				$db = JFactory::getDbo();
				$tuples = array();
				$order	= 1;
	
				foreach ($member['member'] as $k => $v)
				{
					$tuples[] = '('.$user->id.', '.$db->quote('member.'.$k).', '.$db->quote(json_encode($v)).', '.$order++.')';
				}
	
				$db->setQuery('INSERT INTO #__user_profiles VALUES '.implode(', ', $tuples));
				$db->query();

			}
		}

		if(isset($error_messages) && count( $error_messages ) > 0) {
			$res= array(); 
			foreach( $error_messages as $key => $error_message ){
				$res[] = $error_message;
			}
			$response = array("id" => 0,'errors'=>$res);
		} else {
		    $response =  array("id" => $user->id,'data'=>$user);
		}
				
		return $response;
		
  	}
	
	function update_user( $data )	
	{
		$error_messages = array();
		$response = NULL;

		if(isset($data->id) && $data->id != '')
			$id = $data->id;
		else
			$id = JUserHelper::getUserId($data->username);
		
		$dispatcher =& JDispatcher::getInstance();
		$user = new JUser();
		$user->load($id);
		$user->username = $data->username;
		if($user->email != $data->email)
		{
			$db = JFactory::getDbo();
			$db->setQuery("UPDATE #__discussions_users SET `username`='".$data->email."' where `username` = '".$user->email."'");
			$db->query();
			$user->email = $data->email;
		}
		$user->name = $data->name;
		$user->block = 0;
		$user->setParam('sugarRest',1);
		if(isset($data->sugar_id))
			$user->setParam('sugar_id',$data->sugar_id);
			
		if(isset($data->sugar_module))
			$user->setParam('sugar_module',$data->sugar_module);			
			
		if(!$user->save(true)){               	                        	        
			$error_messages[] = array("id"=>$user->id,"fieldname"=>"","message"=>"Data not updated for unknown reason ."); 
		}
		
		//SAVE USER PROFILE TO MEMBER PLUGIN 
		if(isset($data->member))
		{
			$mainframe =& JFactory::getApplication('site');
			JPluginHelper::importPlugin( 'user', 'member' );		
			$member['id'] =$user->id;
			foreach($data->member as $key=>$val)
			{
				$member['member'][$key] = $val;
			}
			//added empty string for argument 4
			//$plugin = $mainframe->triggerEvent( 'onUserAfterSave', array($member,false,true,'') );
		}
		
		if(isset($error_messages) && count( $error_messages ) > 0) {
			$res= array(); 
			foreach( $error_messages as $key => $error_message ){
				$res[] = $error_message;
			}
			$response = array("id" => 0,'errors'=>$res);
		} else {
		    $response = array("id" => $user->id,'data'=>$user);
		}
		
				
		return $response;
	}
	
	function block( $data )	
	{
		$error_messages = array();
		$response = NULL;
		
		$user = new JUser();
		$user->load($data->id);
		$user->block = $data->block;
        $user->setParam('sugarRest',1);

		
		if(!$user->save(true)){               	                        	        
			$error_messages[] = array("id"=>0,"fieldname"=>"block","message"=>"Not able to block/unblock user for some reason."); 
		}
		
		if(isset($error_messages) && count( $error_messages ) > 0) {
			$res= array(); 
			foreach( $error_messages as $key => $error_message ){
				$res[] = $error_message;
			}
			$response = array("id" => 0,'errors'=>$res);
		} else {
		    $response = array('id' => $user->id);
		}		
		return $response;		
	}
	
	function update_usergroup( $data )	
	{
		$error_messages = array();
		$response = NULL;
		
		if(isset($data->id) && $data->id != '')
			$id = $data->id;
		else
			$id = JUserHelper::getUserId($data->username);
		
			
		$user = new JUser();
		$user->load($id);
		
		if($data->user_group ==''){
			$defaultUserGroup = array(2,12); //User group Registered,PP Member as default
		}else{
			$defaultUserGroup = explode('*',$data->user_group);	
			array_push($defaultUserGroup,2); //Save default user group
			array_push($defaultUserGroup,12); //Save default user group
		}
				
		$user->set('groups', $defaultUserGroup);
        $user->setParam('sugarRest',1);

		if(!$user->save(true)){               	                        	        
			$error_messages[] = array("id"=>$user->id,"user_group"=>$data->user_group,"message"=>"Unable to update user group for unknown reason"); 
		}
		
		if(isset($error_messages) && count( $error_messages ) > 0) {
			$res= array(); 
			foreach( $error_messages as $key => $error_message ){
				$res[] = $error_message;
			}
			$response = array("id" => 0,'errors'=>$res);
		} else {
		    $response = array('id' => $user->id);
		}		
		return $response;
	}	
}
