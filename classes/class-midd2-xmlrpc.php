<?php

include_once(dirname(__FILE__) . '/class-midd-base-xmlrpc.php');
include_once(dirname(__FILE__) . '/class-midd-xmlrpc-exception.php');

/**
 * Midd2_XMLRPC methods authenticate with a service account in order to take
 * action on behalf of users. This service account must be secured and should
 * be restricted for use to only trusted integration providers.
 *
 * All methods require these first three parameters:
 *   [
 *     0 => service_username,
 *     1 => service_password,
 *     2 => act_as_username,
 *   ]
 *
 * This class calls methods in the DynamicAddUsers mu-plugin in order to lookup
 * and provision users.
 */

class Midd2_XMLRPC extends Midd_Base_XMLRPC {

  protected static $instance;

  protected $auth_failed = false;
  protected $service_user;

  /**
   * Authenticate and set the current user id.
   *
   * @param array $args
   *   The user to act as.
   *
   * @return stdClass
   *   The current user.
   */
  protected function authenticate($args) {
    if (empty($args[0])) {
      throw new Midd_XMLRPC_Exception('Missing first parameter, service_username.', 400);
    }
    if (empty($args[1])) {
      throw new Midd_XMLRPC_Exception('Missing second parameter, service_password.', 400);
    }
    if (empty($args[2])) {
      throw new Midd_XMLRPC_Exception('Missing third parameter, act_as_username.', 400);
    }

    // Authenticate the service-user.
    if ( $this->auth_failed ) {
      $service_user = new WP_Error( 'login_prevented' );
    } else {
      $service_user = wp_authenticate( $args[0], $args[1] );
    }
    if ( is_wp_error( $service_user ) ) {
      throw new Midd_XMLRPC_Exception('Incorrect username or password.', 403);
      // Flag that authentication has failed once on this wp_xmlrpc_server instance
      $this->auth_failed = true;
    }

    // Store our service_user for later reference.
    $this->service_user = $service_user;

    // If the service_username is the same as the act_as_username, take actions
    // using the service_user account.
    if ($args[0] == $args[2]) {
      $user = $service_user;
    }
    // Allow the service user to take action as other users if authorized.
    else {
      // Verify that the service-user is authorized to take action on behalf of
      // others.
      // These network-manangement capabilities are possessed by super-admins.
      if (!user_can($service_user, 'manage_sites') || !user_can($service_user, 'manage_network_users')) {
        throw new Midd_XMLRPC_Exception('This account is not authorized to use this API method.', 403);
      }

      $user = get_userdatabylogin($args[2]);

      // If the user account doesn't exist, create them.
      if (!$user) {
        try {
          $info = dynamic_add_users()->getDirectory()->getUserInfo($args[2]);
          $user = dynamic_add_users()->getUserManager()->getOrCreateUser($info);
        } catch (Exception $e) {
          throw new Midd_XMLRPC_Exception('Could not create act-as-user account: ' . $e->getMessage(), 400);
        }
      }

      if ( is_wp_error( $user ) ) {
        throw new Midd_XMLRPC_Exception('Could not find or create the target user.', 500);
      }
    }

    // register the user as authenticated.
    wp_set_auth_cookie( $user->ID );
    do_action('wp_login', $user->user_login, $user);
    wp_set_current_user($user->ID, $user->user_login);

    return $user;
  }

  /**
   * Answer the prefix for methods when registering filter('xmlrpc_methods').
   *
   * @return string
   *   The prefix.
   */
  protected function xmlrpcPrefix() {
    return 'midd2';
  }

  /**
   * Determine if a blog exists
   *
   * @param array $args
   *   [
   *     0 => service_username,
   *     1 => service_password,
   *     2 => act_as_username,
   *     3 => blogname,
   *   ]
   *
   * @return boolean
   */
  public function blogExists ($args) {
    try {
      $user = $this->authenticate($args);
    } catch (Midd_XMLRPC_Exception $e) {
      return new IXR_Error($e->getCode(), $e->getMessage());
    }

    return $this->doBlogExists($args[3]);
  }

  /**
   * Answer the current user's blogs.
   *
   * @param array $args
   *   [
   *     0 => service_username,
   *     1 => service_password,
   *     2 => act_as_username,
   *   ]
   *
   * @return array
   */
  public function getUsersBlogs ($args) {
    try {
      $user = $this->authenticate($args);
    } catch (Midd_XMLRPC_Exception $e) {
      return new IXR_Error($e->getCode(), $e->getMessage());
    }

    return $this->doGetUsersBlogs($user->ID);
  }

  /**
   * Answer information about a blog. Answer false if doesn't exist.
   *
   * @param array $args
   *   [
   *     0 => service_username,
   *     1 => service_password,
   *     2 => act_as_username,
   *     3 => blogname,
   *   ]
   *
   * @return mixed array or FALSE
   */
  public function getBlog ($args) {
    try {
      $user = $this->authenticate($args);
    } catch (Midd_XMLRPC_Exception $e) {
      return new IXR_Error($e->getCode(), $e->getMessage());
    }
    return $this->doGetBlog($args[3]);
  }

  /**
   * search for blogs
   *
   * @param array $args
   *   [
   *     0 => service_username,
   *     1 => service_password,
   *     2 => act_as_username,
   *     3 => query,
   *   ]
   *
   * @return array
   */
  public function searchBlogs ($args) {
    try {
      $user = $this->authenticate($args);
    } catch (Midd_XMLRPC_Exception $e) {
      return new IXR_Error($e->getCode(), $e->getMessage());
    }
    return $this->doSearchBlogs($args[3]);
  }

  /**
   * Create a new blog
   *
   * @param array $args
   *   [
   *     0 => service_username,
   *     1 => service_password,
   *     2 => act_as_username,
   *     3 => name,
   *     4 => title,
   *     5 => public,
   *   ]
   *
   * @return array
   */
  function createBlog ($args) {
    try {
      $user = $this->authenticate($args);
    } catch (Midd_XMLRPC_Exception $e) {
      return new IXR_Error($e->getCode(), $e->getMessage());
    }
    $name = $args[3];
    $title = $args[4];
    $public = $args[5];
    $user = $this->authenticate($args);
    // Registration may be restricted to CAS-Authenticated group membership
    // by the midd-limit-blog-registration plugin. Override the registration
    // check if our service_user is a network-admin.
    if (!empty($this->service_user) && user_can($this->service_user, 'manage_sites')) {
      $overrideRegistrationCheck = true;
    } else {
      $overrideRegistrationCheck = false;
    }
    return $this->doCreateBlog($user, $name, $title, $public, $overrideRegistrationCheck);
  }

  /**
   * Add a user to a blog.
   *
   * @param array $args
   *   [
   *     0 => service_username,
   *     1 => service_password,
   *     2 => act_as_username,
   *     3 => blog_id_or_name,
   *     4 => cas_id,
   *     5 => role,
   *   ]
   *
   * @return array
   */
  public function addUser ($args) {
    try {
      $user = $this->authenticate($args);
    } catch (Midd_XMLRPC_Exception $e) {
      return new IXR_Error($e->getCode(), $e->getMessage());
    }
    return $this->doAddUser($args[3], $args[4], $args[5]);
  }

  /**
   * Remove a user from a blog.
   *
   * @param array $args
   *   [
   *     0 => service_username,
   *     1 => service_password,
   *     2 => act_as_username,
   *     3 => blog_id_or_name,
   *     4 => cas_id,
   *   ]
   *
   * @return array
   */
  public function removeUser ($args) {
    try {
      $user = $this->authenticate($args);
    } catch (Midd_XMLRPC_Exception $e) {
      return new IXR_Error($e->getCode(), $e->getMessage());
    }
    return $this->doRemoveUser($args[3], $args[4]);
  }

  /**
   * Get a user's roles for a blog.
   *
   * @param array $args
   *   [
   *     0 => service_username,
   *     1 => service_password,
   *     2 => act_as_username,
   *     3 => blog_id_or_name,
   *     4 => cas_id,
   *   ]
   *
   * @return array
   */
  public function getUserRoles ($args) {
    try {
      $user = $this->authenticate($args);
    } catch (Midd_XMLRPC_Exception $e) {
      return new IXR_Error($e->getCode(), $e->getMessage());
    }
    return $this->doGetUserRoles($args[3], $args[4]);
  }

  /**
   * Add a synced group to a blog.
   *
   * @param array $args
   *   [
   *     0 => service_username,
   *     1 => service_password,
   *     2 => act_as_username,
   *     3 => blog_id_or_name,
   *     4 => group_dn,
   *     5 => role,
   *   ]
   *
   * @return array
   */
  public function addSyncedGroup ($args) {
    try {
      $user = $this->authenticate($args);
    } catch (Midd_XMLRPC_Exception $e) {
      return new IXR_Error($e->getCode(), $e->getMessage());
    }
    return $this->doAddSyncedGroup($args[3], $args[4], $args[5]);
  }

  /**
   * Get the synced groups for a blog.
   *
   * @param array $args
   *   [
   *     0 => service_username,
   *     1 => service_password,
   *     2 => act_as_username,
   *     3 => blog_id_or_name,
   *   ]
   *
   * @return array
   */
  public function getSyncedGroups ($args) {
    try {
      $user = $this->authenticate($args);
    } catch (Midd_XMLRPC_Exception $e) {
      return new IXR_Error($e->getCode(), $e->getMessage());
    }
    return $this->doGetSyncedGroups($args[3]);
  }

  /**
   * Remove a synced group from a blog.
   *
   * @param array $args
   *   [
   *     0 => service_username,
   *     1 => service_password,
   *     2 => act_as_username,
   *     3 => blog_id_or_name,
   *     4 => group_dn,
   *   ]
   *
   * @return array
   */
  public function removeSyncedGroup ($args) {
    try {
      $user = $this->authenticate($args);
    } catch (Midd_XMLRPC_Exception $e) {
      return new IXR_Error($e->getCode(), $e->getMessage());
    }
    return $this->doRemoveSyncedGroup($args[3], $args[4]);
  }

}
