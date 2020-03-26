PHP SSO Client Class
====================

The [PHP SSO client](https://github.com/cubiclesoft/sso-client-php) is the gold standard implementation for interacting with the SSO server.

A set of global wrapper functions around the PHP SSO client class are also available via 'client/functions.php'.

SSO_Client_Base::SetDebugCallback($callback, $callbackopts = false)
-------------------------------------------------------------------

Access:  public

Parameters:

* $callback - A valid callback function that takes three parameters:  callback($type, $data, &$opts).
* $callbackopts - A value to pass as the $opts parameter to the callback.

Returns:  Nothing.

This function sets a debug callback.  The debug callback, when set, is called in various critical locations within the SSO client to aid in diagnosing SSO client/server issues.  The only thing that a callback should do is write the information to a log file in a restricted location (e.g. /tmp).  For maximum effectiveness, call this function right BEFORE calling Init().

Example usage:

```php
<?php
	require_once "client/config.php";
	require_once SSO_CLIENT_ROOT_PATH . "/index.php";

	function WriteSSODebugLogEntry($msg)
	{
		$fp = fopen("/tmp/sso_client_debug.log", "ab");
		fwrite($fp, "[" . date("Y-m-d H:i:s") . "]  " . $msg . "\n");
		fclose($fp);
	}

	function RecordSSOClientActivity($type, $data, &$opts)
	{
		WriteSSODebugLogEntry($type . (is_string($data) ? " - " . $data : (is_bool($data) ? "" : "\n" . json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT))));
	}

	WriteSSODebugLogEntry("----------------------------------------------");
	WriteSSODebugLogEntry("Request start:  " . $_SERVER["REQUEST_URI"] . "\n" . json_encode($_COOKIE, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));

	$sso_client = new SSO_Client;

	// Replace the IP address with the IP address to record SSO client activity for.
	if ($_SERVER["REMOTE_ADDR"] === "127.0.0.1")  $sso_client->SetDebugCallback("RecordSSOClientActivity");

	$sso_client->Init(array("sso_impersonate", "sso_remote_id"));
```

SSO_Client_Base::SendRequest($action, $options, $endpoint, $apikey, $secretkey)
-------------------------------------------------------------------------------

Access:  public

Parameters:

* $action - A string representing the API action to execute.
* $options - An array containing options related to the API action (Default is array()).
* $endpoint - A string containing the URL of the SSO server endpoint (Default is SSO_SERVER_ENDPOINT_URL).
* $apikey - A string containing a valid SSO server API key (Default is SSO_SERVER_APIKEY).
* $secretkey - A string containing a valid SSO server secret key (Default is SSO_SERVER_SECRETKEY).

Returns:  An array containing status and results of the query.

This function is the workhorse behind how the SSO client communicates with a SSO server endpoint.  In general, there should be no need to call this function directly.

SSO_Client_Base::GetRemoteIP()
------------------------------

Access:  public static

Parameters:  None.

Returns:  An array containing normalized IP address information.

This static function normalizes the incoming IP address for various operations such as communicating with the SSO server.  This is the result of a `SSO_IPAddr::GetRemoteIP()` call that includes any trusted proxies.

SSO_Client_Base::ProcPOSTStr($data)
-----------------------------------

Access:  protected

Parameters:

* $data - A string to clean up.

Returns:  A string that is trim()'ed and magic quote free.

This internal function is called by `ProcessSingleInput()` to clean up strings so there is no surrounding whitespace and no magic quotes (if enabled).

SSO_Client_Base::ProcessSingleInput($data)
------------------------------------------

Access:  protected

Parameters:

* $data - A superglobal to integrate into the internal request variable.

Returns:  Nothing.

This internal function is called by `ProcessAllInput()` to clean up a PHP superglobal and overwrite existing values in the internal request variable.

SSO_Client_Base::ProcessAllInput()
----------------------------------

Access:  protected

Parameters:  None.

Returns:  Nothing.

This internal function processes and filters $_COOKIE, $_GET, and $_POST into the internal request variable.  This function allows $_GET and $_POST to override any $_COOKIE variables that were set of the same name, trim()'s user input, and removes magic quotes.

SSO_Client_Base::SetCookieFixDomain($name, $value = "", $expires = 0, $path = "", $domain = "", $secure = false, $httponly = false)
-----------------------------------------------------------------------------------------------------------------------------------

Access:  public

Parameters:

* $name - A string containing the name of the cookie to set.
* $value - A string containing the value of the cookie to set (Default is "").
* $expires - An integer representing the expiration date of the cookie in UNIX timestamp format (Default is 0).
* $path - A string containing the path on which the cookie is valid (Default is "").
* $domain - A string containing the domain on which the cookie is valid (Default is "").
* $secure - A boolean that tells the browser to only send the cookie over HTTPS (Default is false).
* $httponly - A boolean that tells the browser whether or not Javascript should be able to access the cookie's value (Default is false).

Returns:  Nothing.

This function outputs a cookie and also updates the relevant superglobals for the current request.

SSO_Client_Base::IsSSLRequest()
-------------------------------

Access:  public static

Parameters:  None.

Returns:  A boolean of true if the browser is loading the page via SSL, false otherwise.

This static function attempts to detect a SSL connection.  Not all web servers accurately provide the status of SSL to scripting languages.

SSO_Client_Base::GetRequestHost($protocol = "")
-----------------------------------------------

Access:  public

Parameters:

* $protocol - A string containing one of "", "http", or "https" (Default is "").

Returns:  A string containing the host in URL format.

This function retrieves the host in URL format and looks like `http[s]://www.something.com[:port]` based on the current page request.  The result of this function is cached.  The `$protocol` parameter defaults to whatever type the connection is detected with IsSSLRequest() but can be overridden by specifying "http" or "https".

SSO_Client_Base::GetRequestURLBase()
------------------------------------

Access:  public static

Parameters:  None.

Returns:  A string containing the path part of the request URL (excludes query string).

This static function retrieves the path of request URL.  The $_SERVER["REQUEST_URI"] variable is parsed and the protocol, host, and query string parts are removed if they exist.  This function is used to calculate the destination for generated forms.

SSO_Client_Base::GetFullRequestURLBase($protocol = "")
------------------------------------------------------

Access:  public

Parameters:

* $protocol - A string containing one of "", "http", or "https" (Default is "").

Returns:  A string containing the full request URL.

This function combines `GetRequestHost()` and `GetRequestURLBase()` to obtain the full request URL.

SSO_Client_Base::Translate($format, ...)
----------------------------------------

Access:  protected

Parameters:

* $format - A string containing valid sprintf() format specifiers.

Returns:  A string containing a translation.

This protected function provides multilingual translation of an input formatting string into a single output string based on the information in `SSO_Client_Base::$langmap`, `$this->client_lang`, and `$this->client_def_lang`.

SSO_Client_Base::PostTranslate($str)
------------------------------------

Access:  protected

Parameters:

* $str - A string to run partial or complete translations of.

Returns:  A translated version of the string.

This protected function runs specialized partial or complete translations of the input string based on the information in `SSO_Client_Base::$langmap`, `$this->client_lang`, and `$this->client_def_lang`.

SSO_Client_Base::SetLanguage($path, $lang)
------------------------------------------

Access:  public

Parameters:

* $path - A string containing a valid path to the language packs.
* $lang - A string containing the name of the language pack to load and set.

Returns:  An array that indicates success and contains an error string on failure.

This function loads in the specified language pack (if not already loaded) and sets the `$this->client_lang` variable to the language.

SSO_Client_Base::InitLangmap($path, $default = "")
--------------------------------------------------

Access:  protected

Parameters:

* $path - A string containing a valid path to the language packs.
* $default - A string containing the name of the default language pack to load and set.

Returns:  Nothing.

This protected function initializes `SSO_Client_Base::$langmap`, `$this->client_lang`, and `$this->client_def_lang` based on the browser's preferences and available language packs.  Failures are ignored.

SSO_Client_Base::ProcessLogin($info, $fromserver = false)
---------------------------------------------------------

Access:  protected

Parameters:

* $info - An array containing information loaded from the SSO server via the 'getlogin' API.
* $fromserver - A boolean that specifies if the information is coming from the SSO server (Default is false).

Returns:  Nothing.

This protected function processes the results of the 'getlogin' API if the call was successful.  If return information was requested - that is, this request just came back from the SSO server - that information is decrypted and loaded into the PHP superglobals so that the script can pick up where it left off.

The second parameter is stored so a future call to `FromSSOServer()` can be used to determine if the current request came from the return from the SSO server.  The SSO client tends to overwrite all input variables, so this helps preserve enough information to be able to avoid entering infinite loops (e.g. if the return url is 'login.php', then this avoids returning to the SSO server and the browser can be redirected to another URL).

SSO_Client_Base::SafeRedirect($removekeys)
------------------------------------------

Access:  protected

Parameters:

* $removekeys - An array containing key names to remove from the query string of the current URL.

Returns:  Nothing.

This protected function performs a redirect to a URL similar to the current URL but without any of the keys specified in the `$removekeys` and `$sso_removekeys` arrays.

SSO_Client_Base::LoggedIn()
---------------------------

Access:  public

Parameters:  None.

Returns:  A boolean of true if the user is logged in, false otherwise.

This function checks for an encrypted cookie, decrypts it, loads decrypted cookie information, checks the timestamp, and may query the SSO server using the 'getlogin' API to determine if the user is actually logged in under a number of different conditions.  The results of this call are cached.

The SSO client may also be configured to force logging in whenever the IP address changes via `SSO_COOKIE_RESET_IPADDR_CHANGES` in 'config.php'.

SSO_Client_Base::FromSSOServer()
--------------------------------

Access:  public

Parameters:  None.

Returns:  A boolean of true if the current request is from the SSO server, false otherwise.

This function is used to differentiate regular requests from requests that are from the SSO server.  The SSO client tends to overwrite all input variables when a request comes in from the SSO server, so this helps preserve enough information to be able to avoid entering infinite loops.  For example, if the return url is 'login.php', then this can be used to avoid returning to the SSO server and the browser can sensibly be redirected to another URL.

SSO_Client_Base::CanAutoLogin()
-------------------------------

Access:  public

Parameters:  None.

Returns:  A boolean of true if the user is able to be automatically logged in.

This function tests to see if the user is able to be automatically logged in.  The server must have the namespace subdomain cookie option enabled and have the correct number of namespace keys and IVs before this function will work.  This function makes an expensive SSO server endpoint call, so a call to `LoggedIn()` should be made before calling this function.

SSO_Client_Base::Init($removekeys = array())
--------------------------------------------

Access:  public

Parameters:

* $removekeys - An array containing a set of keys to remove from the URL (Default is array()).

Returns:  Nothing.

This function performs all of the initialization steps required for correct SSO client operations.  This function is called automatically when the client 'functions.php' file is 'require'd by another PHP script.  Including 'index.php' and initializing an instance of 'SSO_Client' should be done as soon as possible during a script's execution cycle to minimize potential issues with returning from the SSO server.

SSO_Client_Base::Login($lang = "", $msg = "", $extra = array(), $appurl = "")
-----------------------------------------------------------------------------

Access:  public

Parameters:

* $lang - A string containing an IANA language code to pass to the SSO server.
* $msg - A string containing a custom message or 'insufficient_permissions' to pass to the SSO server that will be displayed to the user.
* $extra - An array containing extra information to pass onto the SSO server via the return URL (Default is array()).
* $appurl - A string containing the application URL.

Returns:  Nothing.

This function uses the SSO server 'initlogin' API to initialize a session.  The current request is encrypted and sent to the SSO server as part of the session setup for later retrieval so that the application can continue processing the request as if nothing happened.  The only circumstance where this won't work is if a file was uploaded to the web server.  The file will have to be uploaded again after returning to the SSO client.

When $msg is "insufficient_permissions", the SSO server treats the request differently and will always display the sign in options so the user has the opportunity to sign in with a different account with access to the requested resource.

The $extra array is information passed onto the SSO server that ends up as part of the URL that the browser will be redirected to.  Used primarily by the Remote Login provider.

SSO_Client_Base::CanRemoteLogin()
---------------------------------

Access:  public

Parameters:  None.

Returns:  A boolean of true if there is valid Remote Login information in the request, false otherwise.

This function is used by the Deployer while integrating a Remote Login into their sign in infrastructure.  There is no point in continuing to execute code if this returns false as it would be impossible to complete the sign in.

SSO_Client_Base::RemoteLogin($userid, $fieldmap = array(), $endpoint = SSO_SERVER_ENDPOINT_URL, $apikey = SSO_SERVER_APIKEY, $secretkey = SSO_SERVER_SECRETKEY)
---------------------------------------------------------------------------------------------------------------------------------------------------------------

Access:  public

Parameters:

* $userid - A string containing a valid user ID that uniquely identifies the user.
* $fieldmap - An array containing the name of a field and the value it should map to.
* $endpoint - A string containing the URL of the SSO server endpoint (Default is SSO_SERVER_ENDPOINT_URL).
* $apikey - A string containing a valid SSO server Remote API key (Default is SSO_SERVER_APIKEY).
* $secretkey - A string containing a valid SSO server secret key (Default is SSO_SERVER_SECRETKEY).

Returns:  Nothing.

This function performs a remote login using a Remote API key and secret.  Information is pushed into an existing temporary session in the SSO server and the browser is redirected to a preconfigured URL in the SSO server front end.  This function will only work when `CanRemoteLogin()` returns true.  On error, this function outputs the error message and immediately stops execution.

SSO_Client_Base::Logout()
-------------------------

Access:  public

Parameters:  None.

Returns:  Nothing.

This function uses the SSO server 'logout' API to delete a session.  The cookies for the current session are also deleted.  It is up to the application to redirect the browser somewhere after logging out.

SSO_Client_Base::HasDBData()
----------------------------

Access:  public

Parameters:  None.

Returns:  A boolean of true if there is database-backed data storage for the user, false otherwise.

This function is only useful when there is a local database with data storage for user information.  Use this function to determine if there is cached data to be stored in a database to avoid hitting the SSO server endpoint later.  The database data storage mechanism of the SSO client is optional.

SSO_Client_Base::LoadDBData($data)
----------------------------------

Access:  public

Parameters:

* $data - A string containing encrypted data.

Returns:  A boolean of true if the data was loaded successfully, false otherwise.

This function accepts the data of a previous `SaveDBData()` call, base64 decodes, decrypts (Blowfish), uncompresses, and unserializes the data into internal information structures.  The database data storage mechanism of the SSO client is optional.

SSO_Client_Base::SaveDBData()
-----------------------------

Access:  public

Parameters:  None.

Returns:  A string containing encrypted data for storage in a database.

This function serializes, compresses, encrypts (Blowfish), and base64 encodes internal information suitable for storage into a database cache.  The database data storage mechanism of the SSO client is optional.

SSO_Client_Base::IsSiteAdmin()
------------------------------

Access:  public

Parameters:  None.

Returns:  A boolean of true if the user is a SSO server site admin, false otherwise.

This function checks for the SSO server site admin tag and returns its value.  This is a special tag that is included in all 'getlogin' API results.  Note that if the SSO client has been configured to not accept site admins (via `SSO_CLIENT_ACCEPT_SITE_ADMIN` in 'config.php'), this will always return false.

SSO_Client_Base::HasTag($name)
------------------------------

Access:  public

Parameters:

* $name - A string containing a tag name.

Returns:  A boolean of true if the user has the tag, false otherwise.

This function checks the API key mapped tag set for the specified tag.

SSO_Client_Base::LoadUserInfo($savefirst = false)
-------------------------------------------------

Access:  public

Parameters:

* $savefirst - A boolean that indicates whether or not to save field information to the database.

Returns:  A boolean of true if the user information was successfully loaded, false otherwise.

This function is used to force user information to be loaded from the SSO server via the 'getlogin' API.  If `$savefirst` is true, the SSO client will attempt to update user information before loading it.

SSO_Client_Base::UserLoaded()
-----------------------------

Access:  public

Parameters:  None.

Returns:  A boolean of true if the user information was loaded from the SSO server, false otherwise.

This function is useful to determine if information needs to be cached.  There are both encrypted cookie and database storage mechanisms that can be used to minimize the number of requests to the SSO server endpoint.

SSO_Client_Base::GetField($key, $default = false)
-------------------------------------------------

Access:  public

Parameters:

* $key - A string containing a field name.
* $default - A default value to use if the field $key does not exist (Default is false).

Returns:  A string containing the field information from the SSO server or $default if no information is found.

This function looks for a SSO client field from the API key field mapping and returns the value.  `$default` is returned if the information is not found.

SSO_Client_Base::GetEditableFields()
------------------------------------

Access:  public

Parameters:  None.

Returns:  An array containing the fields that can be written to in the SSO server.

This function returns the writeable SSO server fields that the API key has access to.  The field must be read/write in the API key and not protected by the provider.  Useful for building a user profile editor.

SSO_Client_Base::SetField($key, $value)
---------------------------------------

Access:  public

Parameters:

* $key - A string containing a key.
* $value - A string containing the value to set.

Returns:  A boolean of true if the field information was successfully set, false otherwise.

This function checks the writeable fields to make sure $key can be written to and then sets the user information to $value.  Useful for building a user profile editor.

SSO_Client_Base::GetData($key, $default = false)
------------------------------------------------

Access:  public

Parameters:

* $key - A string containing a data storage key.
* $default - A default value to use if the data for $key does not exist (Default is false).

Returns:  A string containing the information or $default if no information is found.

This function retrieves user data from either the encrypted cookie information or database storage.  Cached data is trustworthy because it is timestamped and encrypted with a key utilized solely by the SSO client.

SSO_Client_Base::SetData($key, $value, $maxcookielen = 50)
----------------------------------------------------------

Access:  public

Parameters:

* $key - A string containing a data storage key.
* $value - A string containing the value to store.
* $maxcookielen - An integer containing the maximum length of strlen($key) + strlen($value) for inclusion in the encrypted cookie.

Returns:  A boolean of true if the $value was cached, false otherwise.

This function caches the key-value pair for saving later to the encrypted cookie and optional encrypted database storage.  The main purpose for using this function is to cache SSO server data locally so that the SSO client doesn't constantly make requests to the SSO server endpoint.  Cached data is trustworthy because it is timestamped and encrypted with a key utilized solely by the SSO client.  Fewer requests to the SSO server will significantly improve the performance of an application.  Note that data that is too large to include in the cookie is made available for local database storage.  Database storage is optional but can further improve application performance.

SSO_Client_Base::GetMappedUserInfo($fieldmap, $object = true, $save = true)
---------------------------------------------------------------------------

Access:  public

Parameters:

* $fieldmap - An array containing the set of fields to retrieve.
* $object - A boolean indicating whether to return an object or array with the resulting field data.
* $save - A boolean indicating whether or not to send the browser cookie data at the end of the call.

Returns:  An object or array (depending on $object) containing the user's information.

This function simplifies access to user data that is returned from the SSO server.  It calls `GetField()`, `SetData()`, and `GetData()` in a standard fashion but makes it much easier to work with in most common cases.

SSO_Client_Base::SaveUserInfo($usedb = false)
---------------------------------------------

Access:  public

Parameters:

* $usedb - A boolean that indicates whether or not local database storage is being used (Default is false).

Returns:  Nothing.

This function sends the cookies to the web browser containing the encrypted cookie and validation cookies.  Cookies are only sent if user information changed.

SSO_Client_Base::GetUserID()
----------------------------

Access:  public

Parameters:  None.

Returns:  A large integer containing the ID of the user.

This function returns the unique user ID in the SSO server.

SSO_Client_Base::GetSecretToken()
---------------------------------

Access:  public

Parameters:  None.

Returns:  A string containing a secret token.

This function returns a secret token for the current user that can be used to generate CSRF/XSRF defense tokens.
