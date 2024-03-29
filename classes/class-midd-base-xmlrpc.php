<?php

abstract class Midd_Base_XMLRPC {

  /**
   * Answer an instance of the handler
   *
   * @return Midd_Base_XMLRPC
   *   The instance.
   */
  public static function instance() {
    if (empty(static::$instance)) {
      static::$instance = new static();
    }
    return static::$instance;
  }

  /**
   * Answer the prefix for methods when registering filter('xmlrpc_methods').
   *
   * @return string
   *   The prefix.
   */
  abstract protected function xmlrpcPrefix();

  /**
   * Add methods to those available via XMLRPC.
   *
   * This is a callback for filter('xmlrpc_methods').
   *
   * @param array $methods
   *   The XMLRPC methods already defined.
   *
   * @return array
   *   The methods.
   */
  public static function methods(array $methods) {
    $instance = static::instance();
    $prefix = $instance->xmlrpcPrefix();
    return array_merge($methods, [
        $prefix . '.blogExists' => [$instance, 'blogExists'],
        $prefix . '.getUsersBlogs' => [$instance, 'getUsersBlogs'],
        $prefix . '.searchBlogs' => [$instance, 'searchBlogs'],
        $prefix . '.getBlog' => [$instance, 'getBlog'],
        $prefix . '.createBlog' => [$instance, 'createBlog'],
        $prefix . '.addUser' => [$instance, 'addUser'],
        $prefix . '.removeUser' => [$instance, 'removeUser'],
        $prefix . '.getUserRoles' => [$instance, 'getUserRoles'],
        $prefix . '.addSyncedGroup' => [$instance, 'addSyncedGroup'],
        $prefix . '.getSyncedGroups' => [$instance, 'getSyncedGroups'],
        $prefix . '.removeSyncedGroup' => [$instance, 'removeSyncedGroup'],
    ]);
  }

  /**
   * Answer a user's blogs.
   *
   * @param string $user_id.
   *
   * @return array
   */
  protected function doGetUsersBlogs($user_id) {
    global $current_site;
    $blogs = (array) get_blogs_of_user( $user_id );
    $blogInfo = array();

    foreach ( $blogs as $blog ) {
      // Don't include blogs that aren't hosted at this site
      if ( $blog->site_id != $current_site->id )
        continue;

      $blogInfo[] = $this->blogInfo($blog->userblog_id);
    }

    return $blogInfo;
  }

  /**
   * Determine if a blog exists
   *
   * @param string $name
   *      A blog name.
   * @return boolean
   */
  protected function doBlogExists ($name) {
    if (!strlen($name))
      return(new IXR_Error(400, __("This method takes one parameter, a blog name string.")));

    $id = get_id_from_blogname($name);
    return !is_null($id);
  }

  /**
   * Answer information about a blog. Answer false if doesn't exist.
   *
   * @param string $name
   *      The first a blog name.
   * @return mixed array or FALSE
   */
  protected function doGetBlog ($name) {
    if (!strlen($name))
      return(new IXR_Error(400, __("This method takes one parameter, a blog name string.")));

    $id = get_id_from_blogname($name);
    if(is_null($id))
      return false;
    $info = $this->blogInfo($id);
    if ($info['canRead']) {
      return $info;
    } else {
      return new IXR_Error(403, __("You are not authorized to view this blog."));
    }
  }


  /**
   * search for blogs
   *
   * @param string $query
   *      The search query.
   * @return array
   */
  protected function doSearchBlogs ($query) {
    if (!strlen($query))
      return(new IXR_Error(400, __("This method takes one parameter, a query string.")));

    $blogInfo = array();

    global $wpdb, $current_site;
    $blogIds = $wpdb->get_col($wpdb->prepare("SELECT blog_id FROM wp_blogs WHERE path LIKE (%s) ORDER BY path", $current_site->path.$query.'%'));
    foreach ($blogIds as $blogId) {
      $info = $this->blogInfo($blogId);
      if ($info['canRead'])
        $blogInfo[] = $info;
    }

    return $blogInfo;
  }

  /**
   * Create a new blog
   *
   * @param string $args '
   * @return string
   */
  protected function doCreateBlog ($user, $name, $title, $public, $overrideRegistrationCheck = false) {
    global $wpdb;

    if (!strlen($name))
      return(new IXR_Error(400, __("This method requires a blog name string.")));
    if (!strlen($title))
      return(new IXR_Error(400, __("This method requires a blog title string.")));
    if (!is_int($public))
      return(new IXR_Error(400, __("This method requires an integer visibility parameter.")));

    // Create the blog (based on validate_blog_signup() in wp-signup.php and wpmu_activate_signup() in includes/ms-functions.php)

    if ($overrideRegistrationCheck) {
      // Trust that our caller method has verified that the user can create sites.
      // This is useful for a course-only WordPress instance that doens't allow
      // direct self-registration, but does allow creation via API.
    } else {
      // Check if site creation is currently enabled for the current user.
      $active_signup = get_site_option( 'registration' );
      if ( !$active_signup )
        $active_signup = 'all';
      $active_signup = apply_filters( 'wpmu_active_signup', $active_signup ); // return "all", "none", "blog" or "user"
      if ( $active_signup == 'none' ) {
        return(new IXR_Error(403, 'Registration has been disabled.' ));
      } elseif ( $active_signup == 'blog' && !$user->ID ) {
        return(new IXR_Error(403, 'You must be authenticated to create a site.' ));
      } elseif ($active_signup != 'all' && $active_signup != 'blog' ) {
        return(new IXR_Error(403, 'Registration has been disabled.' ));
      }
    }

    $result = wpmu_validate_blog_signup($name, $title, $user);
    extract($result);
    if ($errors->get_error_code()) {
      return(new IXR_Error(400, $errors->get_error_message()));
    }

    $meta = array ('lang_id' => 1, 'public' => $public);
    $meta = apply_filters( "add_signup_meta", $meta );

    $blog_id = wpmu_create_blog( $domain, $path, $title, $user->ID, $meta, $wpdb->siteid );

    // TODO: What to do if we create a user but cannot create a blog?
    if ( is_wp_error($blog_id) ) {
      return(new IXR_Error(400, $blog_id->get_error_message()));
    }

    do_action('wpmu_activate_blog', $blog_id, $user->ID, NULL, $title, $meta);

    ob_start();
    print "<p>";
    printf( __( 'Congratulations! Your new site, %s, is ready.' ), "<a href='http://{$domain}{$path}'>{$blog_title}</a>" );
    print "</p>";

    return ob_get_clean();
  }

  /**
   * Add a user to a blog.
   *
   * @param mixed $blog_id_or_name
   * @param string $cas_id
   * @param string $role
   */
  protected function doAddUser ( $blog_id_or_name, $cas_id, $role ) {
    global $wpdb;

    if (!strlen($cas_id))
      return(new IXR_Error(400, __("This method requires a CAS ID string.")));
    if (empty($blog_id_or_name))
      return(new IXR_Error(400, __("This method requires a blog ID integer or blog name string.")));
    if (!strlen($role))
      return(new IXR_Error(400, __("This method requires a WordPress role string.")));

    try {
      $info = dynamic_add_users()->getDirectory()->getUserInfo($cas_id);
      $user = dynamic_add_users()->getUserManager()->getOrCreateUser($info);
    } catch (Exception $e) {
      return new IXR_Error(400, 'Could not create user account: ' . $e->getMessage());
    }

    if (is_numeric($blog_id_or_name))
      $blog_id = intval($blog_id_or_name);
    else
      $blog_id = get_id_from_blogname($blog_id_or_name);
    switch_to_blog($blog_id);

    // Check permissions for the current user.
    if (!current_user_can('promote_users')) {
      return(new IXR_Error(403, __("You are not an authorized to promote_users for this site.")));
    }

    try {
      dynamic_add_users()->getUserManager()->addUserToBlog($user, $role);
    } catch (Exception $e) {
      // Exception thrown if the blog does not exist or if the user is already a member.
      return new IXR_Error(200, $e->getMessage());
    }
    restore_current_blog( );
  }

  /**
   * Remove a user from a blog.
   *
   * @param mixed $blog_id_or_name
   * @param string $cas_id
   */
  protected function doRemoveUser ( $blog_id_or_name, $cas_id ) {
    global $wpdb;

    if (!strlen($cas_id))
      return(new IXR_Error(400, __("This method requires a CAS ID string.")));
    if (empty($blog_id_or_name))
      return(new IXR_Error(400, __("This method requires a blog ID integer or blog name string.")));

    try {
      $info = dynamic_add_users()->getDirectory()->getUserInfo($cas_id);
      $user = dynamic_add_users()->getUserManager()->getOrCreateUser($info);
    } catch (Exception $e) {
      return new IXR_Error(400, 'Invalid user account: ' . $e->getMessage());
    }

    if (is_numeric($blog_id_or_name))
      $blog_id = intval($blog_id_or_name);
    else
      $blog_id = get_id_from_blogname($blog_id_or_name);
    switch_to_blog($blog_id);

    // Check permissions for the current user.
    if (!current_user_can('remove_users')) {
      return(new IXR_Error(403, __("You are not an authorized to promote_users for this site.")));
    }

    // remove the user from the blog if they are a member
    if (is_user_member_of_blog($user->ID, $blog_id)) {
      remove_user_from_blog($user->ID, $blog_id);
    }
    restore_current_blog( );
  }

  /**
   * Answer the user's roles in the blog.
   *
   * @param mixed $blog_id_or_name
   * @param string $cas_id
   */
  protected function doGetUserRoles ( $blog_id_or_name, $cas_id ) {
    global $wpdb;

    if (!strlen($cas_id))
      return(new IXR_Error(400, __("This method requires a CAS ID string.")));
    if (empty($blog_id_or_name))
      return(new IXR_Error(400, __("This method requires a blog ID integer or blog name string.")));

    try {
      $info = dynamic_add_users()->getDirectory()->getUserInfo($cas_id);
      $user = dynamic_add_users()->getUserManager()->getOrCreateUser($info);
    } catch (Exception $e) {
      return new IXR_Error(400, 'Invalid user account: ' . $e->getMessage());
    }

    if (is_numeric($blog_id_or_name))
      $blog_id = intval($blog_id_or_name);
    else
      $blog_id = get_id_from_blogname($blog_id_or_name);
    switch_to_blog($blog_id);

    // Check permissions for the current user.
    if (!current_user_can('list_users')) {
      return(new IXR_Error(403, __("You are not an authorized to list_users for this site.")));
    }

    // Get the roles.
    $info = get_userdata($user->ID);
    restore_current_blog( );
    return $info->roles;
  }

  /**
   * Add a group of users to a blog.
   *
   * @param mixed $blog_id_or_name
   * @param string $group_dn
   * @param string $role
   */
  protected function doAddSyncedGroup ( $blog_id_or_name, $group_dn, $role) {
    global $wpdb;

    if (empty($blog_id_or_name))
      return(new IXR_Error(400, __("This method requires a blog ID integer or blog name string.")));
    if (!strlen($group_dn))
      return(new IXR_Error(400, __("This method requires a group dn string.")));
    if (!strlen($role))
      return(new IXR_Error(400, __("This method requires a WordPress role string.")));

    if (is_numeric($blog_id_or_name))
      $blog_id = intval($blog_id_or_name);
    else
      $blog_id = get_id_from_blogname($blog_id_or_name);
    switch_to_blog($blog_id);

    // Check permissions for the current user.
    if (!current_user_can('promote_users')) {
      return(new IXR_Error(403, __("You are not an authorized to promote_users for this site.")));
    }

    try {
      $memberInfo = dynamic_add_users()->getDirectory()->getGroupMemberInfo($group_dn);
      if (!is_array($memberInfo))
        return(new IXR_Error(400, __("Could not find group members for ".$group_dn)));
    } catch (Exception $e) {
      return(new IXR_Error(400, __("Could not find group members for ".$group_dn)));
    }

    dynamic_add_users()->getGroupSyncer()->keepGroupInSync($group_dn, $role);
    dynamic_add_users()->getGroupSyncer()->syncGroup($blog_id, $group_dn, $role);
    restore_current_blog();
    return true;
  }

  /**
   * Answer the groups that are being kept in sync
   *
   * @param mixed $blog_id_or_name
   */
  protected function doGetSyncedGroups ( $blog_id_or_name ) {
    global $wpdb;

    if (empty($blog_id_or_name))
      return(new IXR_Error(400, __("This method requires a blog ID integer or blog name string.")));

    if (is_numeric($blog_id_or_name))
      $blog_id = intval($blog_id_or_name);
    else
      $blog_id = get_id_from_blogname($blog_id_or_name);
    switch_to_blog($blog_id);

    // Check permissions for the current user.
    if (!current_user_can('list_users')) {
      return(new IXR_Error(403, __("You are not an authorized to list_users for this site.")));
    }

    return dynamic_add_users()->getGroupSyncer()->getSyncedGroups();
  }

  /**
   * Add a group of users to a blog.
   *
   * @param mixed $blog_id_or_name
   * @param string $group_dn
   */
  function doRemoveSyncedGroup ( $blog_id_or_name, $group_dn ) {
    global $wpdb;

    if (empty($blog_id_or_name))
      return(new IXR_Error(400, __("This method requires a blog ID integer or blog name string.")));
    if (!strlen($group_dn))
      return(new IXR_Error(400, __("This method requires a group dn string.")));

    if (is_numeric($blog_id_or_name))
      $blog_id = intval($blog_id_or_name);
    else
      $blog_id = get_id_from_blogname($blog_id_or_name);
    switch_to_blog($blog_id);

    // Check permissions for the current user.
    if (!current_user_can('remove_users')) {
      return(new IXR_Error(403, __("You are not an authorized to promote_users for this site.")));
    }

    ob_start();
    dynamic_add_users()->getGroupSyncer()->removeUsersInGroup($group_dn);
    dynamic_add_users()->getGroupSyncer()->stopSyncingGroup($group_dn);
    ob_end_clean();
    return true;
  }

  /**
   * Answer info about the current blog as related to the current user.
   *
   * @param int $blog_id
   * @return array
   *      An array of info about the blog.
   */
  protected function blogInfo ($blog_id) {
    switch_to_blog($blog_id);
    $blog = get_blog_details();
    $info = array(
      'blogid'        => $blog_id,
      'name'          => $this->blognameFromId($blog_id),
      'title'         => get_option( 'blogname' ),
      'isAdmin'       => current_user_can('manage_options'),
      'isSubscriber'  => current_user_can('read'),
      'canRead'       => (current_user_can('read') || intval(get_option('blog_public')) >= -1),
      'public'        => intval(get_option('blog_public')),
      'deleted'       => intval($blog->deleted),
      'archived'      => intval($blog->archived),
      'url'           => get_option( 'home' ) . '/',
      'xmlrpc'        => site_url( 'xmlrpc.php' ),
    );
    if (current_user_can('list_users')) {
      $info['synced_groups'] = dynamic_add_users()->getGroupSyncer()->getSyncedGroups();
    }
    restore_current_blog( );
    return $info;
  }


  /**
   * Answer a blog-name from an id.
   *
   * This is the reverse of the implemenation of get_id_from_blogname() in ms-blogs.php
   *
   * @param int $id
   * @return string
   */
  protected function blognameFromId ($id) {
    global $wpdb, $current_site;
    $name = wp_cache_get( "midd_xmlrpc_get_blogname_from_id_" . $id, 'blog-details' );
    if ( $name )
      return $name;

    if ( is_subdomain_install() ) {
      $domain = $wpdb->get_var( $wpdb->prepare("SELECT domain FROM {$wpdb->blogs} WHERE blog_id = %s", $id) );
      $name = substr($domain, 0, strpos($domain, '.'));
    } else {
      $path = $wpdb->get_var( $wpdb->prepare("SELECT path FROM {$wpdb->blogs} WHERE blog_id = %s", $id) );
      $name = trim(substr($path, strlen($current_site->path)), '/');
    }


    wp_cache_set( 'midd_xmlrpc_get_blogname_from_id_' . $id, $name, 'blog-details' );
    return $name;
  }

}
