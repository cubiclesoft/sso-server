<?php
	// CubicleSoft basic debugging routines.
	// (C) 2011 CubicleSoft.  All Rights Reserved.

	// http://www.php.net/manual/en/errorfunc.constants.php
	// Default PHP configuration is E_ALL & ~E_NOTICE.  The default here is much more strict.
	function SetDebugLevel($Level = E_ALL)
	{
		error_reporting($Level);
		ini_set("display_errors", ($Level ? "1" : "0"));
	}
?>
