<?php
/*
Plugin Name:    Midd XML-RPC Methods and server.
Plugin URI:
Description:    XML-RPC methods for searching for, creating, and checking permissions on blogs. Makes use of CAS authentication.
Version:        0.1
Author:         Adam Franco
Author URI:     http://www.adamfranco.com/
 */

// Add a custom XMLRPC server to log XMLRPC errors so that they may be used to
// identify spam and attacker traffic during log-analysis.
add_filter( 'wp_xmlrpc_server_class', 'middlebury_xmlrpc_server_class');
function middlebury_xmlrpc_server_class () {
  return "middlebury_xmlrpc_server";
}
include_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/' . WPINC . '/IXR/class-IXR-server.php');
include_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/' . WPINC . '/class-wp-xmlrpc-server.php');
class middlebury_xmlrpc_server extends wp_xmlrpc_server {

  function call($methodname, $args)
  {
    // Additions by Adam Franco 12/1/2017 to identify XMLRPC abuse.
    trigger_error('XMLRPC call ' . $methodname . '(' . json_encode($args) . ')', E_USER_NOTICE);

    return parent::call($methodname, $args);
  }

  function multiCall($methodcalls)
  {
    $results = parent::multiCall($methodcalls);
    // multicall doesn't call error(), so log our faults here.
    foreach ($results as $result) {
      if (!empty($result['faultCode'])) {
        trigger_error('XMLRPC fault ['.$result['faultCode'].'] '. $result['faultString'], E_USER_WARNING);
      }
    }
    return $results;
  }

  function error($error, $message = false)
  {
    // Accepts either an error object or an error code and message
    if ($message && !is_object($error)) {
        $error = new IXR_Error($error, $message);
    }
    // Additions by Adam Franco 12/1/2017 to identify XMLRPC abuse.
    trigger_error('XMLRPC fault ['.$error->code.'] '. $error->message, E_USER_WARNING);

    $this->output($error->getXml());
  }
}

// Add our custom XMLRPC methods.
include_once(dirname(__FILE__) . '/classes/class-midd-xmlrpc.php');
add_filter( 'xmlrpc_methods', ['Midd_XMLRPC', 'methods'] );
include_once(dirname(__FILE__) . '/classes/class-midd2-xmlrpc.php');
add_filter( 'xmlrpc_methods', ['Midd2_XMLRPC', 'methods'] );
