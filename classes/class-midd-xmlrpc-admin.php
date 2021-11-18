<?php

/**
 * Class to generate the AddUsers interface that is presented to site-admins.
 */
class Midd_Xmlrpc_Admin {

  /**
   * Create a new instance.
   */
  public static function init() {
    static $networkSettings;
    if (!isset($networkSettings)) {
      $networkSettings = new static();
    }
    return $networkSettings;
  }

  /**
   * Create a new instance.
   */
  protected function __construct() {
    add_action('network_admin_menu', [$this, 'networkAdminMenu']);
  }

  /**
   * Handler for the 'network_admin_menu' action. Create our menu item[s].
   */
  public function networkAdminMenu () {
    add_submenu_page('settings.php', 'Middlebury XMLRPC', 'Middlebury XMLRPC', 'manage_network', 'middlebury_xmlrpc', [$this, 'settingsController']);
  }

  /**
   * Common entry point for our settings pages.
   */
  public function settingsController () {
    if (!current_user_can('manage_network')) {
      wp_die('You don\'t have permissions to use this page.');
    }

    print "\n<div class='wrap'>";
    $this->settingsPage();

    print "\n</div>";
  }

  /**
   * Page for viewing/saving options.
   */
  function settingsPage () {

    // Save our form values.
    if ($_POST) {
      check_admin_referer('middlebury_xmlrpc_settings');
      try {
        if ($_POST['middlebury_xmlrpc__log_calls'] == '1') {
          update_site_option('middlebury_xmlrpc__log_calls', true);
        }
        else {
          update_site_option('middlebury_xmlrpc__log_calls', false);
        }
        if ($_POST['middlebury_xmlrpc__log_errors'] == '1') {
          update_site_option('middlebury_xmlrpc__log_errors', true);
        }
        else {
          update_site_option('middlebury_xmlrpc__log_errors', false);
        }
      }
      catch(Exception $e) {
        print "<div id='message' class='error'><p>Error saving options: " . esc_html($e->getMessage()) . "</p></div>";
      }
      print "<div id='message' class='updated'><p>Options Saved</p></div>";
    }

    // Print out our form.
    print "\n<form method='post' action=''>";
    wp_nonce_field( 'middlebury_xmlrpc_settings' );
    print "\n\t<input type='hidden' name='form_section' value='general'>";

    print "\n<h2>Middlebury XMLRPC settings</h2>";
    print "\n<table class='form-table'>";

    print "\n\t<tr valign='top'>";
    print "\n\t\t<th scope='row'>Call Logging</th>";
    print "\n\t\t<td>";
    print '<select name="middlebury_xmlrpc__log_calls" id="middlebury_xmlrpc__log_calls">';
    $current = get_site_option('middlebury_xmlrpc__log_calls');
    print '<option value="0"' . ((!$current)? ' selected="selected"':'') . '>No</option>';
    print '<option value="1"' . (($current)? ' selected="selected"':'') . '>Yes</option>';
    print "</select>";
    print "\n\t\t\t<p class='description'>Log all XMLRPC calls via <code>trigger_error('XMLRPC call ' . \$methodname . '(' . json_encode(\$args) . ')', E_USER_NOTICE);</code> to identify XMLRPC abuse.</p>";
    print "\n\t\t</td>";
    print "\n\t</tr>";

    print "\n\t<tr valign='top'>";
    print "\n\t\t<th scope='row'>Error/Fault Logging</th>";
    print "\n\t\t<td>";
    print '<select name="middlebury_xmlrpc__log_errors" id="middlebury_xmlrpc__log_errors">';
    $current = get_site_option('middlebury_xmlrpc__log_errors');
    print '<option value="0"' . ((!$current)? ' selected="selected"':'') . '>No</option>';
    print '<option value="1"' . (($current)? ' selected="selected"':'') . '>Yes</option>';
    print "</select>";
    print "\n\t\t\t<p class='description'>Log all XMLRPC calls via <code>trigger_error('XMLRPC fault ['.\$result['faultCode'].'] '. \$result['faultString'], E_USER_WARNING);</code> to identify XMLRPC abuse or other issues.</p>";
    print "\n\t\t</td>";
    print "\n\t</tr>";

    print "\n</table>";
    submit_button();
    print "\n</form>";

  }

}
