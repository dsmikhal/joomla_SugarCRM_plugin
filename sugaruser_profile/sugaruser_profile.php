<?php
// Check to ensure this file is included in Joomla!
defined('_JEXEC') or die( 'Restricted access' );

jimport('joomla.plugin.plugin');

class plgUserSugaruser_profile extends JPlugin {

  protected static $sugarcrm_session = false;

  function onUserAfterSave($user, $isnew, $success, $msg) {
    jimport('joomla.user.helper');
    if ($isnew) return true;
    if (($instance = JFactory::getUser($user['id'])) != null)  {
      if (($sugarId = $instance->getParam("sugar_id", false)) !== false && isset($user['member'])) {
        $attributes = array();
        foreach ($user['member'] as $key=>$value) {
          $value = trim($value);
          if (isset($value) && !empty($value)) {
            $attributes[] = array('name'=>$key, 'value'=>$value);
          }
        }
        $attributes[] = array(
          'name'=>'email1',
          'value'=>$user['email'],
        );
        if (($sugarId = $instance->getParam("sugar_id", false)) !== false) {
          $this->sugarLogin();
          $attributes[] = array('name'=>'id', 'value'=>$sugarId);
          $attributes[] = array('name'=>'not_update_joomla_c', 'value'=>'1');

          $response = $this->callSugarRest("set_entry", array(
            'module_name'=>'Contacts',
            'name_value_list'=>$attributes,
          ));
          if ($response === null || !is_array($response)) {
            throw new Exception('Unable to save profile to CRM System.');
          }
        }
      }
    }
    return true;
  }

  function sugarLogin() {
    if (self::$sugarcrm_session === false) {
      $result = $this->callSugarRest(
        'login', array(
          'user_auth' => array(
            'user_name' => $this->params->get('username'),
            'password'  => md5($this->params->get('password')),
          )
        )
      );
      if (!is_array($result)) {
        throw new Exception('Unable to login to Sugar CRM.');
      }
      self::$sugarcrm_session = $result['id'];
    }
    return true;
  }

  function callSugarRest($method, $params) {
    $curl = curl_init($this->params->get('url'));
    curl_setopt($curl, CURLOPT_POST, true);
    curl_setopt($curl, CURLOPT_HEADER, false);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    if (self::$sugarcrm_session !== false) {
      $params = array_merge(array('session'=>self::$sugarcrm_session), $params);
    }
    $json     = json_encode($params);
    $postArgs = 'method=' . urlencode($method) . '&input_type=json&response_type=json&rest_data=' . urlencode($json);
    curl_setopt($curl, CURLOPT_POSTFIELDS, $postArgs);
    JLog::add("Call Sugar Rest $method (" . var_export($params, true) . ")");
    $response = curl_exec($curl);
    curl_close($curl);
    JLog::add("Sugar Rest Result: $response");
    return json_decode($response, true);
  }
}
?>