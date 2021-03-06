# php-edge
Automatically exported from code.google.com/p/php-edge
In order to install Edge on your system you will need

PHP 5.4
A web server (ie Apache)
Additionally,

To use memcached for caching you need php-memcache. Install via pecl install memcache
To use Redis for caching you need php-redis. Install via pecl install redis
To use MongoDB you need php-mongo. Install via pecl install mongo
Going into details on how you can install the above is out of the scope of this document, but a lot of information exists all over the web.

Checkout edge using a svn client

cd /var/www/
svn checkout http://php-edge.googlecode.com/svn/trunk/ php-edge
Within the folder you will find 3 folders and 1 SQL file

Edge includes the framework libraries
Application includes a skeleton structure. Use this for your project
Public contains the bootstrap index.php and it is the directory that you should make public in your web server.
structure.sql contains sql statements for user and RBAC tables. This file is optional for running Edge
Now, let's create a virtual host within Apache, so that we can access Edge through the browser. Paste the below directives to the apache config file

NameVirtualHost *:80

<VirtualHost *:80>
   DocumentRoot /var/www/php-edge/Public
   ServerName devel.edge.net
   <Directory /var/www/php-edge/Public>
        AllowOverride All
   </Directory>
</VirtualHost>
Restart apache in order for the changes to be applied.

Additionally, you need to add the domain we specified in the config section, to your hosts file.

You can now access Edge by visiting

http://devel.edge.net
You will be greeted with a Welcome page which can act as a starting point for your web application. You will find all the files under the Application folder.
