Fejlvarp
===

Fejlvarp is an incident logger, primarily for PHP based systems. It's similar in functionality to the hosted service getexceptional.com and others.

The tool provides you with a place to log runtime errors that happen in production. It also includes a utility to watch apache error logs for PHP fatal errors and report these to the Fejlvarp service.

The service can notify when an incident first happens or is reopened via mail or through pushover.net.

It offers a web based interface to see debug info about the incident.

Install
---

Fejlvarp has two parts - a Server and a client. The client is simply an error handler in your app. The server is a simple php-application, using MySql.

To install the server, mount the `public` folder on an internal server.

You will have to adjust the `.htaccess` file to limit access from your production web server(s). This could be the same machine that you run Fejlvarp on or somewhere else. You'd also want to allow access for your self, either through password auth or by IP.

Create the database with `install.sql` and edit the config file to suit your setup.

In you applications error handler, log errors using code similar to that in `example_full.php`. If you create a handler for a public app/framework and want to contribute it, please feel free.

Fatal Errors
---

You may optionally install the errorlog watcher by placing `scripts/errorlog_parse` on your application server and install it as a cronjob. It will then scan your Apache error logs for PHP fatal errors. The script is fairly cheap on resources, saving its progress each time it's run, so it won't have to scan through all logs each time. See the source of the script for configuration options.

