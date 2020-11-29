Upgrading the SSO Server/Client
===============================

These upgrade instructions only apply to those who have previously installed an older version of the SSO Server/Client.  The SSO Server and SSO Client communicate with a tightly bound protocol.  Major version number jumps in the software package (e.g. 1.0 to 2.0) indicate that the underlying protocol and/or database schema has changed significantly and that all server and client components have to be upgraded.  Smaller version jumps (e.g. 2.0 to 2.1) indicate that there have been no protocol/database schema changes and upgrading just the server and whatever clients need to use a new feature will be necessary.

If you use the "Security Through Obscurity (STO)" feature of the SSO server, the 'admin.php' and 'endpoint.php' files will need to be uploaded separately to their respective directories on the web server when uploading files (i.e. 'admin_...' and 'endpoint_...').  If the files are uploaded by accident to the root SSO server directory with STO enabled, they won't function at the default location and the SSO client may not work properly because they will still be using the previous version of the endpoint.  STO makes it harder for a hacker to find the location of the admin and endpoint but also adds a little extra time to the upgrade process.

Major Version Upgrades
----------------------

Major version upgrades take time to complete and can be a little nerve-wracking.  You should schedule time for the SSO server and client to be offline/unavailable in which to work.  Total time for a major version upgrade depends on a lot of factors, including how large the database is, if there are schema changes, how many clients need to be upgraded, etc.

The upgrade procedure is as follows:

* Back up your database in case something goes horribly wrong.
* Read the official post carefully to understand the impact.
* Download the latest SSO Server/Client package, extract, and upload the 'server' files to the correct locations.  It is safe to overwrite existing files.
* You may wish to write an 'upgrade_hook.php' file to restrict how and where it may be run (e.g. by IP address).  For medium-sized databases, the recommendation is to restrict the upgrade tool to only run from the command-line.
* Run 'upgrade.php' either via a web browser or the command-line.  The command-line is generally more reliable because scripts can't get killed off by the web server for running too long.
* Delete 'upgrade.php' off the web server.  It technically shouldn't be possible for it to run again, but it is better to be safe than sorry.
* Upload the 'client' files to every client installation's directory.  Again, it is safe to overwrite existing files.
* Test each client to make sure nothing broke during the upgrade.

And that's it.  If you encounter any problems upgrading, open an issue on the issue tracker.

Minor Version Upgrades
----------------------

Minor version upgrades take less time and planning to execute than major version upgrades. The upgrade procedure is similar to major version upgrades:

* Back up your database in case something goes horribly wrong.
* Download the latest SSO Server/Client package, extract, and upload the 'server' files to the correct locations.  It is safe to overwrite existing files. Don't upload 'upgrade.php'.
* Upload the 'client' files to every client installation's directory that needs access to new features/changes.  Again, it is safe to overwrite existing files.
* Test each client to make sure nothing broke during the upgrade.

And that's it.  If you encounter any problems upgrading, open an issue on the issue tracker.
