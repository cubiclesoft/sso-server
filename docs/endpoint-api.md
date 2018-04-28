Endpoint API
============

The SSO server endpoint serves as the initiator of all things related to the SSO server and the only method by which a SSO client can use to talk directly to the SSO server.  The endpoint API is an important aspect of all SSO server related communication.  This documentation is only relevant to those implementing custom endpoint API extensions to the SSO server and those porting the SSO client to other languages.

Access to the endpoint API is, by default, restricted to those who know the endpoint API URL, have a valid API key, and are accessing the endpoint API from a matching IP address according to the API key's IP address pattern restrictions.  The endpoint also supports hooks in a couple of locations but is fairly limited.  The actions listed below are reserved for the SSO server and can't be used in the `EndpointHook_CustomHandler()` callback.

The endpoint API will return errors to the client whenever a situation is encountered that is deemed a failure condition.  Most error messages are encrypted but it is possible to get back an unencrypted response (e.g. an invalid API key or the wrong version of the SSO client is being used).  The SSO client is responsible for handling both scenarios.  All communication is done with encoded JSON objects.

Once an API key has been validated and loaded, the next step is to locate the correct 'action', verify that the API key is allowed to perform that action, and then execute the action.

Action:  'test'
---------------

API key types:  normal, remote, custom

Inputs:  None

Returns:

* success - A boolean value of true.

This action is used by the SSO client installer to verify that the endpoint API URL and API key and secret are working as expected.  Diagnosing endpoint URL/API key issues post-installation is a bit more difficult.

Action:  'canautologin'
-----------------------

API key type:  normal

Inputs:

* ns - A string containing an encrypted namespace cookie.  Specifically, the 'sso_server_ns2' cookie.

Returns:

* success - A boolean value of true.

This action is used by the SSO client during the `CanAutoLogin()` call to check the 'sso_server_ns2' cookie and see if the user can be automatically be logged in.

Action:  'initlogin'
--------------------

API key type:  normal

Inputs:

* url - An optional string containing the URL to return to once the user is activated and validated.
* files - An optional integer specifying whether or not files were uploaded as part of the request.  This will display a warning to the user that uploaded files were lost when the browser is redirected to the sign in page.
* initmsg - An optional string to display to the user.  If the string is 'insufficient_permissions', the behavior of the server changes so that an infinite loop doesn't occur.
* extra - An optional object of key-value pairs used as part of the return URL.
* info - An optional string, preferably encrypted, containing data to return to the client later when it supplies the recovery ID.
* appurl - An optional string containing the URL of the application to redirect to if the user presses the back button later on in their web browser.

Returns:

* success - A boolean value of true.
* url - A string containing the URL that the client should redirect to.
* rid - A string containing the recovery ID.

This action is used by the SSO client to initiate a sign in.  The SSO client is expected to save the recovery ID to retrieve 'info' later and then redirect the browser to the returned URL.

Action:  'setlogin'
-------------------

API key type:  remote

Inputs:

* sso_id - A string containing the session ID.
* token - A string containing the validation token.
* user_id - A string containing the user ID.
* updateinfo - A string containing a JSON encoded object that contains field mapping information.

Returns:

* success - A boolean value of true.
* url - A string containing the URL that the client should redirect to.

This action is used by the SSO client to authorize a remote sign in.  The SSO client is expected to redirect the browser to the returned URL.  See the remote provider for details.

Action:  'getlogin'
-------------------

API key type:  normal

Inputs:

* sso_id - A string containing a session ID or temporary session token.
* expires - An integer containing the number of seconds a session is valid for.
* updateinfo - An optional string containing a JSON encoded object that contains field mapping information.
* delete_old - An optional integer that specifies whether or not the original session should be deleted.
* sso_id2 - An optional string containing the previous session ID.
* rid - A string containing the recovery ID.

Returns:

* success - A boolean value of true.
* sso_id - A string containing the session ID.
* id - A string containing the user ID.
* extra - A string containing a constant base token for the user that is intended for use in security nonce calculations.
* field_map - An object containing the field map for the user and API key.
* writable - An object containing a list of writable fields.
* tag_map - An object containing a list of mapped tags associated with the user.
* admin - A boolean that specifies whether or not the user is a site admin.
* rinfo - A string containing the data to return to the client that was submitted with 'initlogin'.

This action retrieves user sign in information and request recovery information that was sent when the 'initlogin' action was called.  When 'delete_old' is specified, the returned object only contains 'success' and is intended to be executed shortly after the first request returns so that the original session information is deleted from the SSO server.  When 'sso_id2' and 'rid' are specified, the original recovery data is returned via 'rinfo' and the real session ID via 'sso_id'.  The SSO client is responsible for restoring the state of the application as best as possible to the original state so that processing may continue where it left off when the original redirect happened to avoid data loss since data loss results in frustrated users.

Ideally, this action is called twice by the SSO client:  The first call is to retrieve the user's sign in information and application recovery information.  The second call is to delete the original session off the server and therefore secure the sign in.  Two steps are necessary because a correctly written SSO client will retry an operation multiple times to counteract any server communication failures because failures do happen.  The SSO client should never reveal the real session ID across the wire.

Action:  'logout'
-----------------

API key type:  normal

Inputs:

* sso_id - A string containing a session ID.

Returns:

* success - A boolean value of true.

This action signs out the user from the SSO server across all sign ins within the same namespace as the session specified by sso_id.
