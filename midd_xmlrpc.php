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

	// Create the blog (based on validate_blog_signup() in wp-signup.php)
	$result = wpmu_validate_blog_signup($name, $title, $user);
	extract($result);
	if ($errors->get_error_code()) {
		return(new IXR_Error(400, $errors->get_error_message()));
	}

// 	return(new IXR_Error(400, "<pre>".print_r($result, true)."</pre>"));

	$meta = array ('lang_id' => 1, 'public' => $public);
	$meta = apply_filters( "add_signup_meta", $meta );

	wpmu_signup_blog($domain, $path, $blog_title, $user->user_login, $user->user_email, $meta);

	ob_start();
	print "<p style='font-weight: bold; font-size: larger;'>";
	printf( __( 'Congratulations! Your new site, %s, is almost ready.' ), "<a href='http://{$domain}{$path}'>{$blog_title}</a>" );
	print "</p>";
	print "<p>";
	_e( 'But, before you can start using your site, <strong>you must activate it</strong>.' );
	print "</p>";
	print "<p>";
	printf( __( 'Check your inbox at <strong>%s</strong> and click the link given.' ),  $user->user_email);
	print "</p>";
	print "<p>";
	_e( 'If you do not activate your site within two days, you will have to sign up again.' );
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