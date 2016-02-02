<?php
defined('_JEXEC') or die( 'Restricted access' );

jimport('joomla.plugin.plugin');
class plgUserMkto_on_track extends JPlugin
{


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

  function onUserAfterLogin($user, $options = array()) {
    jimport('joomla.user.helper');
    $instance = new JUser();
    if ($id = intval(JUserHelper::getUserId($user['username']))) {
      $instance->load($id);
      //Marketo associate Lead
        $debug = true;

        $marketoSoapEndPoint     = $this->params->get('marketoSoapEndPoint');  // CHANGE ME
        $marketoUserId           = $this->params->get('marketoUserId');  // CHANGE ME
        $marketoSecretKey        = $this->params->get('marketoSecretKey');  // CHANGE ME
        $marketoNameSpace        = "http://www.marketo.com/mktows/";

        // Create Signature
        $dtzObj = new DateTimeZone("Australia/Sydney");
        $dtObj  = new DateTime('now', $dtzObj);
        $timeStamp = $dtObj->format(DATE_W3C);
        $encryptString = $timeStamp . $marketoUserId;
        $signature = hash_hmac('sha1', $encryptString, $marketoSecretKey);

        // Create SOAP Header
        $attrs = new stdClass();
        $attrs->mktowsUserId = $marketoUserId;
        $attrs->requestSignature = $signature;
        $attrs->requestTimestamp = $timeStamp;
        $authHdr = new SoapHeader($marketoNameSpace, 'AuthenticationHeader', $attrs);
        $options = array("connection_timeout" => 20, "location" => $marketoSoapEndPoint);
        if ($debug) {
            $options["trace"] = true;
        }

        // Create Request
        $leadKey = new stdClass();
        $leadKey->Email = $instance->email;

        $fullname = explode(' ',$instance->name);
        // Lead attributes to update
        $attr1 = new stdClass();
        $attr1->attrName  = "FirstName";
        $attr1->attrValue = $fullname[0];

        $attr2= new stdClass();
        $attr2->attrName  = "LastName";
        $attr2->attrValue = $fullname[1];

        $attrArray = array($attr1, $attr2);
        $attrList = new stdClass();
        $attrList->attribute = $attrArray;
        $leadKey->leadAttributeList = $attrList;

        $leadRecord = new stdClass();
        $leadRecord->leadRecord = $leadKey;
        $leadRecord->returnLead = false;
        $params = array("paramsSyncLead" => $leadRecord);

        $soapClient = new SoapClient($marketoSoapEndPoint ."?WSDL", $options);
        try {
            $result = $soapClient->__soapCall('syncLead', $params, $options, $authHdr);
        }
        catch(Exception $ex) {
            var_dump($ex);
        }

        if ($debug) {
            print "RAW request:\n" .$soapClient->__getLastRequest() ."\n";
            print "RAW response:\n" .$soapClient->__getLastResponse() ."\n";
        }
        print_r($result);
      
    }
    return true;
  }

}
