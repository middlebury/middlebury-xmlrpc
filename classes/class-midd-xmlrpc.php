<?php

include_once(dirname(__FILE__) . '/class-midd-base-xmlrpc.php');
include_once(dirname(__FILE__) . '/class-midd-xmlrpc-exception.php');

class Midd_XMLRPC extends Midd_Base_XMLRPC {

  protected static $instance;

  /**
   * Authenticate and set the current user id.
   *
   * Some base classes may ignore the ID passed if they are authenticating
   * user-accounts directly. Others which connect with an admin role account may
   * pass the target user_id to act as.
   *
   * @return stdClass
   *   The current user.
   */
  protected function authenticate() {
    phpCAS::forceAuthentication();
    $user = get_userdatabylogin(phpCAS::getUser());

    // If the user account doesn't exist, create them.
    // This is the same as wpcas_nowpuser(), but without forcing redirect via wpCAS::authenticate()
    if (!$user) {
      midd_wpcas_nowpuser(phpCAS::getUser());
      $user = get_userdatabylogin(phpCAS::getUser());
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
    return 'midd';
  }

  /**
   * Answer the current user's blogs.
   *
   * @return array
   */
  public function getUsersBlogs () {
    $user = $this->authenticate();
    return $this->doGetUsersBlogs($user->ID);
  }

  /**
   * Determine if a blog exists
   *
   * @param string $name
   *      A blog name.
   * @return boolean
   */
  public function blogExists ($name) {
    $this->authenticate();
    return $this->doBlogExists($name);
  }

  /**
   * Answer information about a blog. Answer false if doesn't exist.
   *
   * @param string $name
   *      The first a blog name.
   * @return mixed array or FALSE
   */
  public function getBlog ($name) {
    $this->authenticate();
    return $this->doGetBlog($name);
  }


  /**
   * search for blogs
   *
   * @param string $query
   *      The search query.
   * @return array
   */
  public function searchBlogs ($query) {
    if (!strlen($query))
      return(new IXR_Error(400, __("This method takes one parameter, a query string.")));

    $this->authenticate();
    return $this->doSearchBlogs($query);
  }


  /**
   * Create a new blog
   *
   * @param array $args '
   * @return array
   */
  function createBlog ($args) {
    if (!is_array($args) || count($args) != 3)
      return(new IXR_Error(400, __("This method requires 3 parameters, a name, a title, and a visibility setting.")));
    $name = $args[0];
    $title = $args[1];
    $public = $args[2];
    $user = $this->authenticate();
    return $this->doCreateBlog($user, $name, $title, $public);
  }

  /**
   * Add a user to a blog.
   *
   * @param array $args
   *   [
   *     0 => cas_id,
   *     1 => blog_id_or_name,
   *     2 => role,
   *   ]
   *
   * @return array
   */
  public function addUser ($args) {
    $this->authenticate();
    return $this->doAddUser($args[0], $args[1], $args[2]);
  }

  /**
   * Remove a user from a blog.
   *
   * @param array $args
   *   [
   *     0 => cas_id,
   *     1 => blog_id_or_name,
   *   ]
   *
   * @return array
   */
  public function removeUser ($args) {
    $this->authenticate();
    return $this->doRemoveUser($args[0], $args[1]);
  }

  /**
   * Add a synced group to a blog.
   *
   * @param array $args
   *   [
   *     0 => blog_id_or_name,
   *     1 => group_dn,
   *     2 => role,
   *   ]
   *
   * @return array
   */
  public function addSyncedGroup ($args) {
    $this->authenticate();
    return $this->doAddSyncedGroup($args[0], $args[1], $args[2]);
  }

  /**
   * Get the synced groups for a blog.
   *
   * @param array $args
   *   [
   *     0 => blog_id_or_name,
   *   ]
   *
   * @return array
   */
  public function getSyncedGroups ($args) {
    $this->authenticate();

    if (is_string($args)) {
      $blog_id_or_name = $args;
    } else if (is_array($args)) {
      if(count($args) != 1)
        return(new IXR_Error(400, __("This method requires 1 parameter, a blog ID or name.")));
      else
        $blog_id_or_name = $args[0];
    }

    return $this->doGetSyncedGroups($blog_id_or_name);
  }

  /**
   * Remove a synced group from a blog.
   *
   * @param array $args
   *   [
   *     0 => blog_id_or_name,
   *     1 => group_dn,
   *   ]
   *
   * @return array
   */
  public function removeSyncedGroup ($args) {
    $this->authenticate();
    return $this->doRemoveSyncedGroup($args[0], $args[1]);
  }

}
