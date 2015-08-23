Single Sign On (SSO) Server
===========================

The SSO Server portion of the Barebones SSO Server/Client.  An awesome, scalable, secure, flexible login system that's become a bit ridiculous - but it still rocks anyway.

Warning:  This GitHub project is the live development branch and is frequently broken.  Do NOT use on production servers!  Use the official releases instead.

Features
--------

* Cross-domain and cross-server capable.  The SSO server can reside on its own domain and host.
* Massively scalable architecture.  The server understands master-slave database replication.  It is built to easily scale out to as many boxes as you have available.
* Resilient architecture.  The server can go offline or become unavailable and SSO client authenticated users can continue to work without negatively affecting the integrity of this system.
* Resource friendly.  Each frontend user uses an average footprint of 4MB RAM per connection in the out-of-the-box install.  The endpoint is only an average of 1MB RAM.
* Integrates with a variety of backend databases via CSDB.
* And much, much more.  See the official documentation for a more complete feature list.
* Also has a liberal open source license.  MIT or LGPL, your choice.
* Designed for relatively painless integration into your project.
* Sits on GitHub for all of that pull request and issue tracker goodness to easily submit changes and ideas respectively.

More Information
----------------

Documentation, examples, and official downloads of this project sit on the Barebones CMS website:

http://barebonescms.com/documentation/sso/

SSO Clients
-----------

PHP:  https://github.com/cubiclesoft/sso-client-php
ASP.NET (C#):  https://github.com/cubiclesoft/sso-client-aspnet

Related Projects
----------------

Native app framework/API:  https://github.com/cubiclesoft/sso-native-apps
