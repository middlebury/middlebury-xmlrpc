Middlebury XMLRPC
==================

This mu-plugin provides a set of XML-RPC methods that are used by the
Course Hub provisioning service to create course sites and manage
enrollments.

There are two sets of methods, those prefixed with 'midd.' require CAS
proxy-authentication to work. Those prefixed with 'midd2.' utilize a
super-admin service account for provisioning.

Dependencies
------------

This plugin relies on functions in Middlebury's DynamicAddUsers plugin
for actual user registration and lookup.

If using the 'midd.' methods, then phpCAS authentication must be done via
the wpcas mu-plugin.

XMLRPC Server changes
---------------------

This plugin extends the XMLRPC server to add logging of method calls
so that XMLRPC errors may be used to identify spam traffic and malicious
usage.

midd.* methods
--------------
`midd.*` methods utilize CAS proxy-authentication to take actions on a
currently-authenticated user's behalf. This prevents them from operating
as part of scheduled tasks, but ensures that actions are taken as the
current user.

- `midd.blogExists`

  Parameters:
  - string `name`

  Return:
  - boolean

- `midd.getBlog`

  Parameters:
  - string `name`

  Return:
  - array
    - int `blogid`
    - string `name`
    - string `title`
    - boolean `isAdmin`
    - boolean `isSubscriber`
    - boolean `canRead`
    - int `public`
    - int `deleted`
    - int `archived`
    - string `url`
    - string `xmlrpc`
    - array `synced_groups`

- `midd.searchBlogs`

  Parameters:
  - string `query`

  Return:
  - array
    - array
      - int `blogid`
      - string `name`
      - string `title`
      - boolean `isAdmin`
      - boolean `isSubscriber`
      - boolean `canRead`
      - int `public`
      - int `deleted`
      - int `archived`
      - string `url`
      - string `xmlrpc`
      - array `synced_groups`

- `midd.createBlog`

  Parameters:
  - string `name`
  - string `title`
  - int `public`

  Return:
  - string - a status message.

- `midd.addUser`

  Parameters:
  - string `cas_id`
  - int `blog_id`
  - string `role`

- `midd.removeUser`

  Parameters:
  - string `cas_id`
  - int `blog_id`

- `midd.getUserRoles`

  Parameters:
  - string `cas_id`
  - int `blog_id`

  Return:
  - array - An array of role-strings.

- `midd.addSyncedGroup`

  Parameters:
  - int `blog_id` OR string `blog_name`
  - string `group_dn`
  - string `role`

- `midd.getSyncedGroups`

  Parameters:
  - int `blog_id` OR string `blog_name`

  Return:
  - array
    - array
      - string `blog_id`
      - string `group_id`
      - string `role`
      - string `last_sync`

- `midd.removeSyncedGroup`

  Parameters:
  - int `blog_id` OR string `blog_name`
  - string `group_dn`


midd2.* methods
--------------
`midd2.*` methods utilize a site-admin service account rather than
directly authenticating the target user. This allows these methods to
be called from cron-jobs when there is no active user-session and still
populate enrollment changes.

- `midd2.blogExists`

  Parameters:
  - string `service_user`
  - string `service_password`
  - string `act_as_username`
  - string `name`

  Return:
  - boolean

- `midd2.getBlog`

  Parameters:
  - string `service_user`
  - string `service_password`
  - string `act_as_username`
  - string `name`

  Return:
  - array
    - int `blogid`
    - string `name`
    - string `title`
    - boolean `isAdmin`
    - boolean `isSubscriber`
    - boolean `canRead`
    - int `public`
    - int `deleted`
    - int `archived`
    - string `url`
    - string `xmlrpc`
    - array `synced_groups`

- `midd2.searchBlogs`

  Parameters:
  - string `service_user`
  - string `service_password`
  - string `act_as_username`
  - string `query`

  Return:
  - array
    - array
      - int `blogid`
      - string `name`
      - string `title`
      - boolean `isAdmin`
      - boolean `isSubscriber`
      - boolean `canRead`
      - int `public`
      - int `deleted`
      - int `archived`
      - string `url`
      - string `xmlrpc`
      - array `synced_groups`

- `midd2.createBlog`

  Parameters:
  - string `service_user`
  - string `service_password`
  - string `act_as_username`
  - string `name`
  - string `title`
  - int `public`

  Return:
  - string - a status message.

- `midd2.addUser`

  Parameters:
  - string `service_user`
  - string `service_password`
  - string `act_as_username`
  - string `cas_id`
  - int `blog_id` OR string `blog_name`
  - string `role`

- `midd2.removeUser`

  Parameters:
  - string `service_user`
  - string `service_password`
  - string `act_as_username`
  - string `cas_id`
  - int `blog_id` OR string `blog_name`

- `midd2.getUserRoles`

  Parameters:
  - string `service_user`
  - string `service_password`
  - string `act_as_username`
  - string `cas_id`
  - int `blog_id` OR string `blog_name`

  Return:
  - array - An array of role-strings.

- `midd2.addSyncedGroup`

  Parameters:
  - string `service_user`
  - string `service_password`
  - string `act_as_username`
  - int `blog_id` OR string `blog_name`
  - string `group_dn`
  - string `role`

- `midd2.getSyncedGroups`

  Parameters:
  - string `service_user`
  - string `service_password`
  - string `act_as_username`
  - int `blog_id` OR string `blog_name`

  Return:
  - array
    - array
      - string `blog_id`
      - string `group_id`
      - string `role`
      - string `last_sync`

- `midd2.removeSyncedGroup`

  Parameters:
  - string `service_user`
  - string `service_password`
  - string `act_as_username`
  - int `blog_id` OR string `blog_name`
  - string `group_dn`
