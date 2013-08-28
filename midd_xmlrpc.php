<?php
/*
Plugin Name:    Midd XML-RPC Methods
Plugin URI:
Description:    XML-RPC methods for searching for, creating, and checking permissions on blogs. Makes use of CAS authentication.
Version:        0.1
Author:         Adam Franco
Author URI:     http://www.adamfranco.com/
 */

add_filter( 'xmlrpc_methods', 'midd_xmlrpc_methods' );
function midd_xmlrpc_methods( $methods ) {
	return array_merge($methods, array (
	'midd.blogExists' => 'midd_xmlrpc_blogExists',
	'midd.getUsersBlogs' => 'midd_xmlrpc_getUsersBlogs',
	'midd.searchBlogs' => 'midd_xmlrpc_searchBlogs',
	'midd.getBlog' => 'midd_xmlrpc_getBlog',
	'midd.createBlog' => 'midd_xmlrpc_createBlog',
	'midd.addUser' => 'midd_xmlrpc_addUser',
	'midd.addSyncedGroup' => 'midd_xmlrpc_addSyncedGroup',
	'midd.getSyncedGroups' => 'midd_xmlrpc_getSyncedGroups',
	'midd.removeSyncedGroup' => 'midd_xmlrpc_removeSyncedGroup',
    ));
}

/**
 * Get the current user's blogs
 *
 * @param array $args
 *      The first argument should be a blog name string.
 * @return array
 */
function midd_xmlrpc_getUsersBlogs () {
	// WPCAS always redirects to /wp-admin/ after login, so we need to call our
	// own login function
	$user = midd_xmlrpc_authenticate();

	global $current_site;
	$blogs = (array) get_blogs_of_user( $user->ID );
	$blogInfo = array();

	foreach ( $blogs as $blog ) {
		// Don't include blogs that aren't hosted at this site
		if ( $blog->site_id != $current_site->id )
			continue;

		$blogInfo[] = midd_xmlrpc_blogInfo($blog->userblog_id);
	}

	return $blogInfo;
}


/**
 * Determine if a blog exists
 *
 * @param string $name
 *      The first a blog name.
 * @return boolean
 */
function midd_xmlrpc_blogExists ($name) {
	if (!strlen($name))
		return(new IXR_Error(400, __("This method takes one parameter, a blog name string.")));

	// WPCAS always redirects to /wp-admin/ after login, so we need to call our
	// own login function
	$user = midd_xmlrpc_authenticate();

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
function midd_xmlrpc_getBlog ($name) {
	if (!strlen($name))
		return(new IXR_Error(400, __("This method takes one parameter, a blog name string.")));

	// WPCAS always redirects to /wp-admin/ after login, so we need to call our
	// own login function
	$user = midd_xmlrpc_authenticate();

	$id = get_id_from_blogname($name);
	if(is_null($id))
		return false;
	return midd_xmlrpc_blogInfo($id);
}


/**
 * search for blogs
 *
 * @param string $query
 *      The search query.
 * @return array
 */
function midd_xmlrpc_searchBlogs ($query) {
	if (!strlen($query))
		return(new IXR_Error(400, __("This method takes one parameter, a query string.")));

	// WPCAS always redirects to /wp-admin/ after login, so we need to call our
	// own login function
	$user = midd_xmlrpc_authenticate();

	$blogInfo = array();

	global $wpdb, $current_site;
	$blogIds = $wpdb->get_col($wpdb->prepare("SELECT blog_id FROM wp_blogs WHERE path LIKE (%s) ORDER BY path", $current_site->path.$query.'%'));
	foreach ($blogIds as $blogId) {
		$info = midd_xmlrpc_blogInfo($blogId);
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
function midd_xmlrpc_createBlog ($args) {
	global $wpdb;

	if (!is_array($args) || count($args) != 3)
		return(new IXR_Error(400, __("This method requires 3 parameters, a name, a title, and a visibility setting.")));
	$name = $args[0];
	$title = $args[1];
	$public = $args[2];
	if (!strlen($name))
		return(new IXR_Error(400, __("This method requires a blog name string.")));
	if (!strlen($title))
		return(new IXR_Error(400, __("This method requires a blog title string.")));
	if (!is_int($public))
		return(new IXR_Error(400, __("This method requires an integer visibility parameter.")));

	// WPCAS always redirects to /wp-admin/ after login, so we need to call our
	// own login function
	$user = midd_xmlrpc_authenticate();

	// Create the blog (based on validate_blog_signup() in wp-signup.php and wpmu_activate_signup() in includes/ms-functions.php)

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

	$result = wpmu_validate_blog_signup($name, $title, $user);
	extract($result);
	if ($errors->get_error_code()) {
		return(new IXR_Error(400, $errors->get_error_message()));
	}

// 	return(new IXR_Error(400, "<pre>".print_r($result, true)."</pre>"));

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
 * Authenticate the user.
 *
 * WPCAS always redirects to /wp-admin/ after login, so we need our own login function.
 *
 * @return stdclass
 *      The user.
 */
function midd_xmlrpc_authenticate () {
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
	do_action('wp_login', $user->user_login);
	wp_set_current_user($user->ID, $user->user_login);

	return $user;
}

/**
 * Answer info about the current blog as related to the current user.
 *
 * @param int $blog_id
 * @return array
 *      An array of info about the blog.
 */
function midd_xmlrpc_blogInfo ($blog_id) {
	switch_to_blog($blog_id);
	$info = array(
		'blogid'		=> $blog_id,
		'name'			=> midd_xmlrpc_get_blogname_from_id($blog_id),
		'title'			=> get_option( 'blogname' ),
		'isAdmin'		=> current_user_can('manage_options'),
		'isSubscriber'	=> current_user_can('read'),
		'canRead'		=> (current_user_can('read') || intval(get_option('blog_public')) >= -1),
		'public'		=> intval(get_option('blog_public')),
		'url'			=> get_option( 'home' ) . '/',
		'xmlrpc'		=> site_url( 'xmlrpc.php' ),
		'synced_groups'	=> dynaddusers_get_synced_groups(),
	);
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
function midd_xmlrpc_get_blogname_from_id ($id) {
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

/**
 * Add a user to a blog.
 *
 * @param string args
 */
function midd_xmlrpc_addUser ( $args )	{
	global $wpdb;

	if (!is_array($args) || count($args) != 3)
		return(new IXR_Error(400, __("This method requires 3 parameters, a CAS ID, a blog ID, and a WordPress role.")));
	$cas_id = $args[0];
	$blog_id = $args[1];
	$role = $args[2];

	if (!strlen($cas_id))
		return(new IXR_Error(400, __("This method requires a CAS ID string.")));
	if (!is_int($blog_id))
		return(new IXR_Error(400, __("This method requires a blog ID integer.")));
	if (!strlen($role))
		return(new IXR_Error(400, __("This method requires a WordPress role string.")));

	try {
		$xpath = dynaddusers_midd_lookup(array(
			'action' => 'get_user',
			'id' => $cas_id,
		));
	} catch(Exception $e) {
		// Exception thrown when we cannot load the XML information for a user.
		throw $e;
	}

	$entries = $xpath->query('/cas:results/cas:entry');
	if ($entries->length !== 1)
		throw new Exception('Could not get user. Expecting 1 entry, found '.$entries->length);

	$user = dynaddusers_get_user($user_info);

	switch_to_blog($blog_id);
	try {
	  dynaddusers_add_user_to_blog($user, $role);
	} catch (Exception $e) {
		// Exception thrown if the blog does not exist or if the user is already a member.
		throw $e;
	}
	restore_current_blog( );
}

/**
 * Add a group of users to a blog.
 *
 * @param string args
 */
function midd_xmlrpc_addSyncedGroup ( $args )	{
	global $wpdb;

	if (!is_array($args) || count($args) != 3)
		return(new IXR_Error(400, __("This method requires 3 parameters, a group dn, a blog ID or name, and a WordPress role.")));
	$blog_id_or_name = $args[0];
	$group_dn = $args[1];
	$role = $args[2];

	if (empty($blog_id_or_name))
		return(new IXR_Error(400, __("This method requires a blog ID integer or blog name string.")));
	if (!strlen($group_dn))
		return(new IXR_Error(400, __("This method requires a group dn string.")));
	if (!strlen($role))
		return(new IXR_Error(400, __("This method requires a WordPress role string.")));

	// WPCAS always redirects to /wp-admin/ after login, so we need to call our
	// own login function
	$user = midd_xmlrpc_authenticate();

	if (is_numeric($blog_id_or_name))
		$blog_id = intval($blog_id_or_name);
	else
		$blog_id = get_id_from_blogname($blog_id_or_name);
	switch_to_blog($blog_id);
	if (!current_user_can('edit_users'))
		return false;

	try {
		$memberInfo = dynaddusers_get_member_info($group_dn);
		if (!is_array($memberInfo))
			throw new Exception("Could not find group members for ".$group_dn);
	} catch (Exception $ex) {
		restore_current_blog();
		return false;
	}

	dynaddusers_keep_in_sync($group_dn, $role);
	dynaddusers_sync_group($blog_id, $group_dn, $role);
	restore_current_blog();
	return true;
}

/**
 * Answer the groups that are being kept in sync
 *
 * @param string args
 */
function midd_xmlrpc_getSyncedGroups ( $args )	{
	global $wpdb;

	if (is_string($args)) {
		$blog_id_or_name = $args;
	} else if (is_array($args)) {
		if(count($args) != 1)
			return(new IXR_Error(400, __("This method requires 1 parameter, a blog ID or name.")));
		else
			$blog_id_or_name = $args[0];
	}

	if (empty($blog_id_or_name))
		return(new IXR_Error(400, __("This method requires a blog ID integer or blog name string.")));

	// WPCAS always redirects to /wp-admin/ after login, so we need to call our
	// own login function
	$user = midd_xmlrpc_authenticate();

	if (is_numeric($blog_id_or_name))
		$blog_id = intval($blog_id_or_name);
	else
		$blog_id = get_id_from_blogname($blog_id_or_name);
	switch_to_blog($blog_id);
	if (!current_user_can('edit_users'))
		return false;

	return dynaddusers_get_synced_groups();
}

/**
 * Add a group of users to a blog.
 *
 * @param string args
 */
function midd_xmlrpc_removeSyncedGroup ( $args )	{
	global $wpdb;

	if (!is_array($args) || count($args) != 2)
		return(new IXR_Error(400, __("This method requires 3 parameters, a group dn, a blog ID or name, and a WordPress role.")));
	$blog_id_or_name = $args[0];
	$group_dn = $args[1];

	if (empty($blog_id_or_name))
		return(new IXR_Error(400, __("This method requires a blog ID integer or blog name string.")));
	if (!strlen($group_dn))
		return(new IXR_Error(400, __("This method requires a group dn string.")));

	// WPCAS always redirects to /wp-admin/ after login, so we need to call our
	// own login function
	$user = midd_xmlrpc_authenticate();

	if (is_numeric($blog_id_or_name))
		$blog_id = intval($blog_id_or_name);
	else
		$blog_id = get_id_from_blogname($blog_id_or_name);
	switch_to_blog($blog_id);
	if (!current_user_can('edit_users'))
		return false;

	ob_start();
	dynaddusers_remove_users_in_group($group_dn);
	dynaddusers_stop_syncing($group_dn);
	ob_end_clean();
	return true;
}