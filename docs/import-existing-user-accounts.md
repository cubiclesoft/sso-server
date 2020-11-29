Import Existing User Accounts
=============================

Let's suppose you already have a large database of users and want to import them into the SSO server.  While this is possible, this is a fairly advanced task and you are somewhat on your own as far as programming goes.  There is some code later on in this section to give you an idea of how to proceed but you'll still ultimately have to do your own thing.

There are several approaches you can take when dealing with the issue of importing users.  You'll have to first decide which one is right for your situation.

* One option is to author a provider that allows existing users to sign into their existing account.  The LDAP provider is a simple enough model to follow and there is plenty of documentation on the topic of creating a new provider.  You don't necessarily have to allow users to create or recover their account, just sign in.  This approach can be useful when your existing system's passwords are hashed or encrypted.  The provider approach can also be an "Import old account" method that simply migrates their account to the Generic Login provider but doesn't actually sign them in.
* Another option is to not import anything.  Users recreate their account in the new system and then the application using the SSO client operates on e-mail addresses.  You don't have to do much to make this method work.  This has the added benefits of cleaning up the user database and when a former user signs in with their old e-mail address, the new account will be linked automatically to their old user regardless of how they arrive from the SSO server.
* The last option is to import accounts directly into the Generic Login provider.  If this approach is used with passwords that are already hashed or encrypted, the user will have to recover their account before they can access the system.  Doing that will be weird from an end-user perspective but you could reset their password for them and send it via e-mail during the import process.  If the passwords are plain-text, while you shouldn't have been doing that, this method will significantly upgrade your existing system and users will be able to sign in without having to recover their account.

The rest of this section is dedicated to importing user accounts into the Generic Login provider.

The Generic Login provider is quite versatile but it is also hard to integrate with because of both its flexibility and the security measures taken to prevent a data breach.  This is intentional but it does make it difficult to import accounts from other systems into this provider.  The recommended approach for importing large numbers of accounts in one go is to write a command-line script.  The following is an example to get you started.  The code is borrowed from both 'cron.php' and the Generic Login provider:

```php
<?php
	define("SSO_FILE", 1);

	// Temporary root.
	$rootpath = str_replace("\\", "/", dirname(__FILE__));

	require_once $rootpath . "/config.php";
	require_once SSO_ROOT_PATH . "/" . SSO_SUPPORT_PATH . "/debug.php";
	require_once SSO_ROOT_PATH . "/" . SSO_SUPPORT_PATH . "/str_basics.php";
	require_once SSO_ROOT_PATH . "/" . SSO_SUPPORT_PATH . "/page_basics.php";
	require_once SSO_ROOT_PATH . "/" . SSO_SUPPORT_PATH . "/sso_functions.php";
	require_once SSO_ROOT_PATH . "/" . SSO_SUPPORT_PATH . "/random.php";

	// Initialize language settings.
	BB_InitLangmap(SSO_ROOT_PATH . "/" . SSO_LANG_PATH . "/", SSO_DEFAULT_LANG);
	BB_SetLanguage(SSO_ROOT_PATH . "/" . SSO_LANG_PATH . "/", SSO_ADMIN_LANG);

	// Initialize the global CSPRNG instance.
	$sso_rng = new CSPRNG();

	// Connect to the database and generate database globals.
	SSO_DBConnect(true);

	// Load in fields without admin select.
	SSO_LoadFields(false);

	// Load in $sso_settings and initialize it.
	SSO_LoadSettings();

	// Load the SMTP functions so e-mail addresses can be verified.
	define("CS_TRANSLATE_FUNC", "BB_Translate");
	require_once SSO_ROOT_PATH . "/" . SSO_SUPPORT_PATH . "/smtp.php";

	// Define SET_PASSWORD_MODE to one of the following:
	//   0 = Specify the user's password via $password.
	//   1 = Generate a password for the user.
	//   2 = Force the user to use account recovery options to set a password.
	define("SET_PASSWORD_MODE", 2);

	// Connect to your database here and run the query to extract user accounts.
	$numrows = 0;
	$result = $yourdb->query("SELECT * FROM yourusers");
	while ($row = $result->getrow())
	{
		// Put your code here to get the username, e-mail, and (optional) password out of your database row.
		$username = $row->username;
		$email = $row->email;
		$password = "";

		// Load up $mapinfo with field data.  Keys must match field names in the server.
		// Don't worry about e-mail address and username.  Those are dealt with later.
		$mapinfo = array();

		// Do not modify anything below this line unless you really know what you are doing.
		if ($sso_settings["sso_login"]["install_type"] == "email_username" || $sso_settings["sso_login"]["install_type"] == "email")
		{
			$result2 = SMTP::MakeValidEmailAddress($email);
			if (!$result2["success"])
			{
				echo BB_Translate("Invalid e-mail address.  %s\n", $email["error"]);
				continue;
			}

			$email = $result2["email"];
		}

		// Create the new user in the Generic Login database.
		$userinfo = array();
		$phrase = "";
		for ($x = 0; $x < 4; $x++)  $phrase .= " " . SSO_GetRandomWord();
		$phrase = preg_replace('/\s+/', " ", trim($phrase));
		if (SET_PASSWORD_MODE == 0)  $phrase = $password;

		$salt = $sso_rng->GenerateString();
		$data = $username . ":" . $email . ":" . $salt . ":" . $phrase;
		$userinfo["extra"] = $sso_rng->GenerateString();
		if (SET_PASSWORD_MODE == 0 || SET_PASSWORD_MODE == 1)
		{
			$passwordinfo = Blowfish::Hash($data, $sso_settings["sso_login"]["password_minrounds"], $sso_settings["sso_login"]["password_mintime"]);
			if (!$passwordinfo["success"])  BB_SetPageMessage("error", "Unexpected cryptography error.");
			else
			{
				$userinfo["salt"] = $salt;
				$userinfo["rounds"] = (int)$passwordinfo["rounds"];
				$userinfo["password"] = bin2hex($passwordinfo["hash"]);

				echo BB_Translate("Initial password for '%s' - '%s' has been set to '%s'.\n", $username, $email, $phrase);
			}
		}
		else
		{
			$userinfo["salt"] = "";
			$userinfo["rounds"] = 0;
			$userinfo["password"] = "";
		}

		$sso_db_sso_login_users = SSO_DB_PREFIX . "p_sso_login_users";
		$userinfo2 = SSO_EncryptDBData($userinfo);

		try
		{
			if ($sso_settings["sso_login"]["install_type"] == "email_username")
			{
				$sso_db->Query("INSERT", array($sso_db_sso_login_users, array(
					"username" => $username,
					"email" => $email,
					"verified" => (int)$verified,
					"created" => CSDB::ConvertToDBTime(time()),
					"info" => $userinfo2,
				), "AUTO INCREMENT" => "id"));
			}
			else if ($sso_settings["sso_login"]["install_type"] == "email")
			{
				$sso_db->Query("INSERT", array($sso_db_sso_login_users, array(
					"email" => $email,
					"verified" => (int)$verified,
					"created" => CSDB::ConvertToDBTime(time()),
					"info" => $userinfo2,
				), "AUTO INCREMENT" => "id"));
			}
			else if ($sso_settings["sso_login"]["install_type"] == "username")
			{
				$sso_db->Query("INSERT", array($sso_db_sso_login_users, array(
					"username" => $username,
					"created" => CSDB::ConvertToDBTime(time()),
					"info" => $userinfo2,
				), "AUTO INCREMENT" => "id"));
			}
			else
			{
				echo BB_Translate("Fatal error:  Login system is broken.\n");
				exit();
			}

			$userid = $sso_db->GetInsertID();

			$userrow = $sso_db->GetRow("SELECT", array(
					"*",
					"FROM" => "?",
					"WHERE" => "id = ?",
				), $sso_db_sso_login_users, $userid);
		}
		catch (Exception $e)
		{
			echo BB_Translate("Database query error.  %s\n", $e->getMessage());
			continue;
		}

		// Activate the user.
		if ($sso_settings["sso_login"]["install_type"] == "email_username" || $sso_settings["sso_login"]["install_type"] == "email")  $mapinfo[$sso_settings["sso_login"]["map_email"]] = $userrow->email;
		if ($sso_settings["sso_login"]["install_type"] == "email_username" || $sso_settings["sso_login"]["install_type"] == "username")  $mapinfo[$sso_settings["sso_login"]["map_username"]] = $userrow->username;

		SSO_ActivateUser($userrow->id, $userinfo["extra"], $mapinfo, false, false);

		$numrows++;
	}
?>
```

That code should provide a sufficient starting point.  Just make the necessary modifications to integrate with an existing system to import and activate each account.  The script is intended to be run from a command-line in the SSO server root directory.
