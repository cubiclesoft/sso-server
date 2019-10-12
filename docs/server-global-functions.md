SSO Server Global Functions
===========================

The SSO server has a number of functions available that make common tasks easier.  Since the SSO server uses [Admin Pack](https://github.com/cubiclesoft/admin-pack), all the functions for Admin Pack are also available.  The SSO server also uses a number of other [CubicleSoft PHP libraries](https://github.com/cubiclesoft/php-libs).

Most functions begin with the reserved prefix of 'SSO_'.

The available functions below are broken down by the file they are defined in.  Wherever the file is not specified, the function appears in 'support/sso_functions.php'.

SSO_CreateConfigURL($action2, $extra = array())
-----------------------------------------------

File:  admin.php

Access:  global

Parameters:

* $action2 - A string containing the 'action2' to execute.
* $extra - An array containing extra query string parameters in name => value pairs. Each value must be a string. (Default is array()).

Returns:  A string containing a URL to the target configuration page.

This global function is for a provider to easily generate a URL that gets to the correct page of the provider configuration.

SSO_CreateConfigLink($title, $action2, $extra = array(), $confirm = "")
-----------------------------------------------------------------------

File:  admin.php

Access:  global

Parameters:

* $title - A string containing the text to display to the user.
* $action2 - A string containing the 'action2' to execute.
* $extra - An array containing extra query string parameters in name => value pairs (Default is array()).  Each value must be a string.
* $confirm - A string to display in a confirmation dialog before continuing (Default is ""). Useful for confirming deletions.

Returns:  A string containing a hyperlink to the target configuration page.  Includes surrounding 'a' tags.

This global function is similar to `SSO_CreateConfigURL()` but surrounds the link in HTML 'a' tags.

SSO_ConfigRedirect($action2, $extra = array(), $msgtype = "", $msg = "")
------------------------------------------------------------------------

File:  admin.php

Access:  global

Parameters:

* $action2 - A string containing the 'action2' to execute.
* $extra - An array containing extra query string parameters in name => value pairs (Default is array()).  Each value must be a string.
* $msgtype - A string containing one of 'error', 'success', or 'info' (Default is "").
* $msg - A string containing a message to display to the user on the redirected page (Default is "").

Returns:  Nothing.

This global function is similar to SSO_CreateConfigURL() but performs a redirect to the URL with an optional Admin Pack message.

SSO_EndpointOutput($result)
---------------------------

File:  endpoint.php

Access:  global

Parameters:

* $result - An array containing the data to send to the client.

Returns:  Nothing.

This global function outputs the '$result' in JSON format, possibly encrypted using the shared secret, then exits.

SSO_EndpointError($msg, $info = "")
-----------------------------------

File:  endpoint.php

Access:  global

Parameters:

* $msg - A string containing the error message that may be displayed to the user.
* $info - A string containing additional information about the error that should only be used for debugging purposes.

Returns:  Nothing.

This global function outputs an error result using the `SSO_EndpointOutput()` function.

SSO_DisplayError($msg)
----------------------

File:  index.php

Access:  global

Parameters:

* $msg - A string containing the error message to display to the user.

Returns:  Nothing.

This global function outputs the specified error message and exits.

SSO_OutputHeartbeat()
---------------------

File:  index.php

Access:  global

Parameters:  None.

Returns:  Nothing.

This global function outputs the Javascript logic for the AJAX heartbeat that keeps the temporary server session alive for the maximum configured amount of time.  Without the heartbeat, the temporary session usually expires in 30 minutes.

SSO_FrontendField($name)
------------------------

File:  index.php

Access:  global

Parameters:

* $name - A string containing a human-readable name.

Returns:  A string containing a hashed name based on a random seed, session ID, API key, and $name.

This global function takes the usual human-readable name and turns it into a hashed name.  Designed for use in forms that users fill out.  Nearly transparent to the average user but introduces a serious problem for bots.

This function introduces minor hash collision issues.  It is theoretically possible that two different $name values will hash to the same result for the same session ID and API key but not likely.

SSO_FrontendFieldValue($name, $default = false)
-----------------------------------------------

File:  index.php

Access:  global

Parameters:

* $name - A string containing a human-readable name.
* $default - The value that will be the default if the name can't be found (Default is false).

Returns:  A UTF-8 encoded string if the hashed name exists in $_REQUEST, otherwise $default.

This global function locates the `SSO_FrontendField()` hashed name in $_REQUEST and returns it if it exists, otherwise it returns the value of $default.  A convenient function to deal with retrieving data from a submitted form that used SSO_FrontendField() to generate names.

SSO_IsSSLRequest()
------------------

Access:  global

Parameters:  None.

Returns:  A boolean of true if the browser is loading the page via SSL, false otherwise.

This global function is a clone of `BB_IsSSLRequest()` for the endpoint to utilize in order to keep RAM usage down a bit.

SSO_GetRemoteIP()
-----------------

Access:  global

Parameters:  None.

Returns:  An array containing normalized IP address information.

This global function normalizes the incoming IP address for various operations such as checking whitelists and DNSRBLs.  This is the result of a `IPAddr::GetRemoteIP()` call that includes any trusted proxies.

SSO_GetSupportedDatabases()
---------------------------

Access:  global

Parameters:  None.

Returns:  An array containing information about supported databases.

This global function is used by the installer and `SSO_DBConnect()` to determine allowed database types and the generic features that those databases support.  The returned array will generally mirror supported CSDB databases.

SSO_DBConnect($full)
--------------------

Access:  global

Parameters:

* $full - A boolean that indicates whether or not to load the full database class.

Returns:  Nothing but throws an exception on error.

This global function initializes the database connection and the various '$sso_db_...' global variables to be able to run queries against the database.  The admin, cron, install, and upgrade code loads the full CSDB database class.  The endpoint and frontend load the 'lite' version for reduced memory usage.

SSO_LoadFields($loadselect)
---------------------------

Access:  global

Parameters:

* $loadselect - A boolean that indicates whether or not the dropdown select for the admin interface should be loaded.

Returns:  A boolean of true if it succeeds, false otherwise.

This global function loads in the enabled fields into $sso_fields as well as the encryption status of each field.  If $loadselect is true, $sso_select_fields is set with the options for the SSO field mapping dropdowns used by providers.

SSO_GenerateNamespaceKeys()
---------------------------

Access:  global

Parameters:  None.

Returns:  Nothing.

This global function resets the namespace keys and initialization vectors.  The caller still has to call SSO_SaveSettings() to apply the changes.

SSO_LoadSettings()
------------------

Parameters:  None.

Returns:  Nothing.

This global function loads in the SSO server settings array and sets defaults if necessary.

SSO_CreatePHPStorageData($data)
-------------------------------

Access:  global

Parameters:

* $data - A generic variable to prepare for saving to a file.

Returns:  A string that is ready to be stored in a .php file assigned to a variable.

This global function prepares variables to save them to a file.  It uses the SSO_USE_LESS_SAFE_STORAGE configuration item to determine how to generate the data.  When less safe storage is disabled, data is serialized and then base64 encoded.  When less safe storage is enabled, data is var_export()'ed.  Both methods perform about the same but the latter is slightly less secure but readability is vastly improved.

SSO_SaveSettings()
------------------

Access:  global

Parameters:  None.

Returns:  A boolean of true if the settings were saved successfully, false otherwise.

This global function saves the SSO server settings.

SSO_GetProviderList()
---------------------

Access:  global

Parameters:  None.

Returns:  An array containing internal provider names.

This global function reads the list of available providers and returns the directory names.  This is only preliminary information, it is up to the caller to do the actual loading, initialization, and perform various checks.

SSO_GetDirectoryList($path)
---------------------------

Access:  global

Parameters:

* $path - A string containing a path from root (preferably an absolute path).

Returns:  An array of two arrays of subdirectories ("dirs") and files ("files") within the directory specified by $path.

This global function retrieves a list of subdirectories and files within the specified directory and sorts them with `natcasesort()`.  Subdirectories go into one array of the array that is returned and is called "dirs".  Files go into another array and is called "files".

SSO_RandomSleep()
-----------------

Access:  global

Parameters:  None.

Returns:  Nothing.

This global function sleeps for a random amount of time up to 250 milliseconds for a timing attack defense and forces all clients to take longer per request.

SSO_AddSortedOutput(&$outputmap, $numkey, $strkey, $data)
---------------------------------------------------------

Access:  global

Parameters:

* $outputmap - An array of integer ($numkey) to string ($strkey) to string ($data) mappings.
* $numkey - An integer containing the order of the $data.
* $strkey - A string containing the name of the $data to avoid overwriting something else in the map that has the same $numkey.
* $data - A string containing the data to add to $outputmap.

Returns:  Nothing.

This global function is used to order a collection of HTML output that has been captured and stored until the output has been sorted.

SSO_DisplaySortedOutput($outputmap)
-----------------------------------

Access:  global

Parameters:

* $outputmap - An array of integer ($numkey) to string ($strkey) to string ($data) mappings.

Returns:  Nothing.

This global function sorts and outputs the array contents.

SSO_IsField($name)
------------------

Access:  global

Parameters:

* $name - A string containing a SSO field name.

Returns: A boolean of true if the field name exists, false otherwise.

This global function checks to see if $name is in the $sso_fields. During development, this function used to do a lot more, then it was drastically simplified at some point.
SSO_SaveIPAddrInfo()

Access:  global

Parameters: None.

Returns: A boolean of true if the $sso_ipaddr_info array was saved successfully, false otherwise.

This global function is intended to be called after $sso_ipaddr_info has been modified.
SSO_GetGeoIPOpts()

Access:  global

Parameters: None.

Returns: An array of key-value pairs where the key is an internal GeoIP name and value is a boolean of true.

This internal global function is used by other Geolocation IP address functions.
SSO_InitIPFields()

Access:  global

Parameters: None.

Returns: An array initialized for use with other Geolocation IP address functions.

This global function is intended to be used in the Init() function of a provider's 'iprestrict' configuration settings.
SSO_ProcessIPFields($full = false)

Access:  global

Parameters:

* $full - A boolean that indicates whether or not this is the global configuration (Default is false).

Returns:  An array containing Geolocation IP address information.

This global function validates the inputs from SSO_AppendIPFields() and returns an array of Geolocation IP information to the caller.

SSO_AppendIPFields(&$contentopts, $info, $full = false)
-------------------------------------------------------

Access:  global

Parameters:

* $contentopts - An array containing Admin Pack page information.
* $info - An array containing current Geolocation IP information.
* $full - A boolean that indicates whether or not this is the global configuration (Default is false).

Returns:  Nothing.

This global function appends standard Geolocation IP address configuration fields to $contentopts.  Fields are validated with `SSO_ProcessIPFields()`.

SSO_GenerateSearchOutputCheckbox($name, $checked)
-------------------------------------------------

Access:  global

Parameters:

* $name - A string containing the name of the checkbox.  Must be unique.
* $checked - A boolean that indicates whether or not the checkbox should be checked.

Returns:  A string containing a HTML 'input' element with clickable text that says 'Include in output'.

This global function generates a custom checkbox for use with the 'htmldesc' option in search forms in the admin interface.

SSO_GetGeoIPInfo()
------------------

Access:  global

Parameters:  None.

Returns:  An array containing the location information for the remote IP address if a GeoIP database exists, a boolean of false otherwise.

This global function looks up the remote IPv6 address in a GeoIP database and returns the information to the caller.

SSO_IsSpammer($info)
--------------------

Access:  global

Parameters:

* $info - An array of Geolocation IP information.

Returns:  A boolean of true if the IP address is a known spammer, false otherwise.

This global function checks DNSRBL database records and GeoIP location blacklists for both the global configuration and a provider configuration against the remote IP address.  The results are cached so that the queries against the various databases and services are minimized.  Information is cached for the specified length of time determined by the configuration.

SSO_IsIPAllowed($info)
----------------------

Access:  global

Parameters:

* $info - An array of Geolocation IP information.

Returns:  A boolean of true if the remote IP address passes all whitelist filters, false otherwise.

This global function checks the global IP address whitelist as well as any provider or API key filter.  Note that IP addresses can be spoofed, so this isn't flawless protection.

SSO_GetRandomWord($randcapital = true, $words = array())
--------------------------------------------------------

Access:  global

Parameters:

* $randcapital - A boolean that determines whether or not the first letter of the chosen word will be randomly capitalized (Default is true).
* $words - An array of words to choose from (Default is array(), which uses the dictionary).

Returns:  A string containing a randomly selected word.

This global function returns a randomly selected word.  When $words is empty (the default), a random dictionary word is chosen.  If $words contains a set of words, one of the words in the array is chosen.  The Generic Login anti-phishing module utilizes the latter when constructing a random sentence.

SSO_SaveSessionInfo()
---------------------

Access:  global

Parameters:  None.

Returns:  A boolean of true is the session information was successfully saved, false otherwise.

This global function is intended to be called after modifying $sso_session_info.

SSO_GetDNSServers()
-------------------

Access:  global

Parameters:  None.

Returns:  An array of DNS servers.

This global function returns the configured DNS server settings as an array.

SSO_MakeValidEmailAddress($addr)
--------------------------------

Access:  global

Parameters:

* $addr - A string containing an e-mail address.

Returns:  A standard array of information.

This global function calls `SMTP::MakeValidEmailAddress()` from the Ultimate E-mail Toolkit using the DNS servers as returned by `SSO_GetDNSServers()`.

SSO_SendEmail($fromaddr, $toaddr, $subject, $htmlmsg, $textmsg)
---------------------------------------------------------------

Access:  global

Parameters:

* $fromaddr - A string containing the 'From' e-mail address.
* $toaddr - A string containing the 'To' e-mail address.
* $subject - A string containing the subject line.
* $htmlmsg - A string containing the HTML body.
* $textmsg - A string containing the text body.

Returns:  A boolean of true if the e-mail was successfully sent, false otherwise.

This global function sends a simple e-mail message using the Ultimate E-mail Toolkit.

SSO_EncryptDBData($data)
------------------------

Access:  global

Parameters:

* $data - The data to encrypt.

Returns:  A string containing the serialized, single/dual encrypted (Blowfish or AES-256), and base64 encoded data.

This global function prepares encrypted data to be used in a SQL query.

SSO_DecryptDBData($data)
------------------------

Access:  global

Parameters:

* $data - The data to decrypt.

Returns:  A string containing the base64 decoded, single/dual decryptyed (Blowfish or AES-256), and unserialized data on success, a boolean of false otherwise.

This global function decrypts data from the database.

SSO_LoadDecryptedUserInfo($row)
-------------------------------

Access:  global

Parameters:

* $row - An object containing user row information from the database.

Returns:  An array containing both the decrypted and regular user information.

This global function loads the unencrypted user information field and then decrypts the encrypted user information field into a single array of information from a row containing both 'info' and 'info2' object variables and returns the combined array to the caller.

SSO_CreateEncryptedUserInfo(&$userinfo)
---------------------------------------

Access:  global

Parameters:

* $userinfo - An array containing user information.

Returns:  A string containing encrypted user information ready for a SQL query.

This global function checks $sso_fields for the encryption status of the field and moves fields that are supposed to be encrypted out of $userinfo and into a separate array.  Then the separate array is encrypted and returned to the caller.  The net effect is that unencrypted fields are left alone in the $userinfo array such that both sets of data are ready for a SQL query.

SSO_AddGeoIPMapFields(&$info)
-----------------------------

Access:  global

Parameters:

* $info - An array containing user information.

Returns:  Nothing.

This internal global function looks at the global Geolocation IP address to SSO field mappings and writes the information to the user information array based on the remote IP address' cached information.

SSO_IsLockedUser($id)
---------------------

Access:  global

Parameters:

* $id - A string containing a user ID.

Returns:  A boolean of true if the user account is locked, false otherwise.

This global function checks to see if a user account is locked.

SSO_ActivateUserSession($row, $automate)
----------------------------------------

Access:  global

Parameters:

* $row - An object containing a row from the user database.
* $automate - A boolean that specifies if the validation phase should be automated.

Returns:  A boolean of false if activation fails. The browser is redirected on success.

This global function activates a user and then proceeds to the next step (validation).

SSO_LoadNamespaces($real, $data = false)
----------------------------------------

Access:  global

Parameters:

* $real - A boolean indicating which decryption key set to use.
* $data - An optional string containing the data to decrypt.

Returns:  An array containing the namespace session information.

This global function reads the encrypted browser cookie or submitted data and attempts to decrypt it into a namespace array.  The SSO server only cookie (sso_server_ns) contains a complete session ID while the optional subdomain cookie (sso_server_ns2) only contains the integer portion of the session ID.

SSO_SaveNamespaces($realnamespaces, $exposednamespaces)
-------------------------------------------------------

Access:  global

Parameters:

* $realnamespaces - An array containing the results of a `SSO_LoadNamespaces(true)` call.
* $exposednamespaces - An array containing the results of a `SSO_LoadNamespaces(false)` call.

Returns:  Nothing.

This global function saves the signed in namespaces to cookies if the relevant features are enabled/relevant.

SSO_RemoveSavedNamespace($namespace)
------------------------------------

Access:  global

Parameters:

* $namespace - A string containing a namespace to remove.

Returns:  Nothing.

This global function removes a signed in namespace and calls `SSO_SaveNamespaces()` to update the browser cookies.

SSO_ActivateNamespaceUser()
---------------------------

Access:  global

Parameters: None.

Returns: A boolean of false if the namespace is invalid or activation fails.  The browser is redirected on success.

This global function locates the namespace of the current API key, looks for an existing session in the same namespace, and activates a new session if a match is found.  The behavior can be overridden by setting `use_namespaces` to 0 in the request.

SSO_ActivateUser($id, $entropy, $info, $created = false, $automate = false, $activatesession = true)
----------------------------------------------------------------------------------------------------

Access:  global

Parameters:

* $id - A string containing the provider ID for the user being activated.
* $entropy - A string containing optional, extra entropy.  No longer used by this function and MAY be removed in the future.
* $info - An array containing user information.
* $created - A boolean of false or a UNIX timestamp (that will be GMT encoded) to set the 'created' field mapping with.
* $automate - A boolean that specifies if the validation phase should be automated (Default is false).
* $activatesession - A boolean that specifies if a session should be activated for the user (Default is true).

Returns:  A boolean of false if activation fails.  The browser is redirected on success.

This global function activates a user and then proceeds to the next step (validation).  Activating a user either inserts a new record in the database or updates an existing record with the information from the provider.

The $activatesession variable is only used from within the SSO server admin where it makes sense to create an account but not sign in to it.  This option is used primarily for the Generic Login provider when creating new accounts within the SSO server admin.

SSO_SetUserVersion($version)
----------------------------

Access:  global

Parameters:

* $version - An integer containing the new version of the user account.

Returns:  A boolean of true if the version was successfully set, false otherwise.

This global function sets the version of the user account as well as saving any changes to the $sso_user_info array.

SSO_ExternalRedirect($url)
--------------------------

Access:  global

Parameters:

* $url - A string containing the URL to redirect to.

Returns:  Nothing.

This global function outputs HTML that causes the browser to redirect to the URL.  Javascript appears first for those browsers that have Javascript enabled and a meta refresh of three seconds is used for Javascript disabled scenarios.  This function is called wherever it becomes possible to exceed browser internal redirection limits (e.g. Internet Explorer will give up after 10 redirects).  This function bypasses those limits.

SSO_ValidateUser()
------------------

Access:  global

Parameters: None.

Returns: A boolean of false if validation fails.  The browser is redirected on success.

This global function validates the session, clears temporary SSO server cookies, sets the namespace session to the new session ID, and redirects the browser back to the SSO client with a fully validated session.
