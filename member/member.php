<?php
/**
 * @version		$Id: member.php 2012-07-13 dex $
 * @copyright	Copyright (C) 2005 - 2011 Open Source Matters, Inc. All rights reserved.
 * @license		GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('JPATH_BASE') or die;
jimport('joomla.utilities.date');

/**
 * member account details plugin.
 *
 * @package		Joomla.Plugin
 * @subpackage	User.member
 * @version		1.6
 */
class plgUserMember extends JPlugin
{
	/**
	 * Constructor
	 *
	 * @access      protected
	 * @param       object  $subject The object to observe
	 * @param       array   $config  An array that holds the plugin configuration
	 * @since       1.5
	 */
	public function __construct(& $subject, $config)
	{
		parent::__construct($subject, $config);
		$this->loadLanguage();
	}

	/**
	 * @param	string	$context	The context for the data
	 * @param	int		$data		The user id
	 * @param	object
	 *
	 * @return	boolean
	 * @since	1.6
	 */
	function onContentPrepareData($context, $data)
	{
		// Check we are manipulating a valid form.
		if (!in_array($context, array('com_users.profile','com_users.user', 'com_users.registration', 'com_admin.profile'))) {
			return true;
		}

		if (is_object($data))
		{
			$userId = isset($data->id) ? $data->id : 0;

			if (!isset($data->profile) and $userId > 0) {

				// Load the profile data from the database.
				$db = JFactory::getDbo();
				$db->setQuery(
					'SELECT profile_key, profile_value FROM #__user_profiles' .
					' WHERE user_id = '.(int) $userId." AND profile_key LIKE 'member.%'" .
					' ORDER BY ordering'
				);
				$results = $db->loadRowList();

				// Check for a database error.
				if ($db->getErrorNum())
				{
					$this->_subject->setError($db->getErrorMsg());
					return false;
				}

				// Merge the profile data.
				$data->member = array();

				foreach ($results as $v)
				{
					$k = str_replace('member.', '', $v[0]);
					$data->member[$k] = json_decode($v[1], true);
					if ($data->member[$k] === null)
					{
						$data->member[$k] = $v[1];
					}
				}
			}

			if (!JHtml::isRegistered('users.calendar')) {
				JHtml::register('users.calendar', array(__CLASS__, 'calendar'));
			}
		}

		return true;
	}
	
	public static function calendar($value)
	{
		if (empty($value)) {
			return JHtml::_('users.value', $value);
		} else {
			return JHtml::_('date', $value, null, null);
		}
	}

	/**
	 * @param	JForm	$form	The form to be altered.
	 * @param	array	$data	The associated data for the form.
	 *
	 * @return	boolean
	 * @since	1.6
	 */
	function onContentPrepareForm($form, $data)
	{
		
		if (!($form instanceof JForm))
		{
			$this->_subject->setError('JERROR_NOT_A_FORM');
			return false;
		}

		// Check we are manipulating a valid form.
		$name = $form->getName();
		if (!in_array($name, array('com_admin.profile', 'com_users.user', 'com_users.profile', 'com_users.registration'))) {
			return true;
		}

		// Add the registration fields to the form.
		JForm::addFormPath(dirname(__FILE__).'/members');
		$form->loadFile('member', false);
		$fields = array(
			'first_name',
			'last_name',
			'phone_home',
			'phone_mobile',
			'phone_work',
			'primary_address_street',
			'primary_address_city',
			'primary_address_state',
			'primary_address_country',
			'primary_address_postalcode',
			'birthdate',
		);

		foreach ($fields as $field) {
			// Case using the users manager in admin
			if ($name == 'com_users.user') {
				// Remove the field if it is disabled in registration and profile
				if ($this->params->get('register-require_' . $field, 1) == 0 &&
					$this->params->get('profile-require_' . $field, 1) == 0) {
					$form->removeField($field, 'member');
				}
			}
			// Case registration
			elseif ($name == 'com_users.registration') {
				// Toggle whether the field is required.
				if ($this->params->get('register-require_' . $field, 1) > 0) {
					$form->setFieldAttribute($field, 'required', ($this->params->get('register-require_' . $field) == 2) ? 'required' : '', 'member');
				}
				else {
					$form->removeField($field, 'member');
				}
			}
			// Case profile in site or admin
			elseif ($name == 'com_users.profile' || $name == 'com_admin.profile') {
				// Toggle whether the field is required.
				if ($this->params->get('profile-require_' . $field, 1) > 0) {
					$form->setFieldAttribute($field, 'required', ($this->params->get('profile-require_' . $field) == 2) ? 'required' : '', 'member');
				}
				else {
					$form->removeField($field, 'member');
				}
			}
		}

		return true;
	}

	function onUserAfterSave($data, $isNew, $result, $error)
	{
		if(!$isNew)
		{
			$userId	= JArrayHelper::getValue($data, 'id', 0, 'int');
	
			if ($userId && $result && isset($data['member']) && (count($data['member'])))
			{
				try
				{
					//Sanitize the date
					if (!empty($data['member']['birthdate'])) {
						$date = new JDate($data['member']['birthdate']);
						$data['member']['birthdate'] = $date->format('Y-m-d');
					}
					
					$db = JFactory::getDbo();
					$db->setQuery(
						'DELETE FROM #__user_profiles WHERE user_id = '.$userId .
						" AND profile_key LIKE 'member.%'"
					);
	
					if (!$db->query()) {
						throw new Exception($db->getErrorMsg());
					}
	
					$tuples = array();
					$order	= 1;
	
					foreach ($data['member'] as $k => $v)
					{
						$tuples[] = '('.$userId.', '.$db->quote('member.'.$k).', '.$db->quote(json_encode($v)).', '.$order++.')';
					}
	
					$db->setQuery('INSERT INTO #__user_profiles VALUES '.implode(', ', $tuples));
	
					if (!$db->query()) {
						throw new Exception($db->getErrorMsg());
					}
	
				}
				catch (JException $e)
				{
					$this->_subject->setError($e->getMessage());
					return false;
				}
			}
		}

		return true;
	}

	/**
	 * Remove all user profile information for the given user ID
	 *
	 * Method is called after user data is deleted from the database
	 *
	 * @param	array		$user		Holds the user data
	 * @param	boolean		$success	True if user was succesfully stored in the database
	 * @param	string		$msg		Message
	 */
	function onUserAfterDelete($user, $success, $msg)
	{
		if (!$success) {
			return false;
		}

		$userId	= JArrayHelper::getValue($user, 'id', 0, 'int');

		if ($userId)
		{
			try
			{
				$db = JFactory::getDbo();
				$db->setQuery(
					'DELETE FROM #__user_profiles WHERE user_id = '.$userId .
					" AND profile_key LIKE 'member.%'"
				);

				if (!$db->query()) {
					throw new Exception($db->getErrorMsg());
				}
			}
			catch (JException $e)
			{
				$this->_subject->setError($e->getMessage());
				return false;
			}
		}

		return true;
	}
}
