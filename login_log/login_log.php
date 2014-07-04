<?php
defined('_JEXEC') or die( 'Restricted access' );

jimport('joomla.plugin.plugin');
class plgUserLogin_log extends JPlugin
{

  protected static $sugarcrm_session = false;

  /**
   * Constructor
   *
   * @param JDispatcher $subject The object to observe
   * @param array  $config  An array that holds the plugin configuration
   * @since 1.5
   */
  function __construct(& $subject, $config) {
    parent::__construct($subject, $config);
    $subject->attach($this);
  }

  function onUserLogin($user, $options = array()) {
    jimport('joomla.user.helper');
    $instance = new JUser();
    if ($id = intval(JUserHelper::getUserId($user['username']))) {
      $instance->load($id);
      if (($sugarId = $instance->getParam("sugar_id", false)) !== false) {
        if ($this->sugarLogin()) {
          $this->callSugarRest("set_entry", array(
            'module_name'=>'L_Login_Logs',
            'name_value_list'=>array(
              array('name'=>'name', 'value'=>$_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] . '?' . $_SERVER['QUERY_STRING']),
              array('name'=>'contacts_l_login_logs_1contacts_ida', 'value'=>$sugarId),
              array('name'=>'last_login', 'value'=>gmdate('Y-m-d H:i:s')),
			  array('name'=>'ipaddress', 'value'=>$_SERVER['REMOTE_ADDR']),
            )
          ));
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
        return false;
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
