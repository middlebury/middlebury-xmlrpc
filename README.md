Middlebury XMLRPC
==================

This mu-plugin provides a set of XML-RPC methods that are used by the
Course Hub provisioning service to create course sites and manage
enrollments.


Dependencies
------------

This plugin relies on functions in Middlebury's DynamicAddUsers plugin
for actual user registration and lookup.


XMLRPC Server changes
---------------------

This plugin extends the XMLRPC server to add logging of method calls
so that XMLRPC errors may be used to identify spam traffic and malicious
usage.


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
