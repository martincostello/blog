<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>WordPress &rsaquo; WP DB Abstraction Error</title>
<link rel="stylesheet" href="css/install.css" type="text/css" />

</head>
<body>
<h1 id="logo"><img alt="WordPress" src="images/wordpress-logo.png" /></h1>
<p><?php echo $error_message ?></p>
<h2>Installation and Upgrade Instructions for Wordpress Database Abstraction Plugin</h2>
<p>The WP-DB-Abstraction Plugin can only be installed as a
<a href="http://codex.wordpress.org/Must_Use_Plugins">Must Use</a> plugin. This means
that updates and installations must be performed manually and cannot be done using
the Plugin Administration area of your wordpress site.</p>
<h3>Instructions</h3>
<ol>
    <li>Always back up your database before beginning an upgrade or install. If
    you are migrating from MySQL to SQL Server, make sure export your database
    information as xml.</li>
    <li>Download the latest version of the plugin from <a
    href="http://wordpress.org/extend/plugins/wordpress-database-abstraction/">the
    plugin directory</a></li>
    <li>Unzip the files</li>
    <li>Find your wp-content folder. The default location is wordpress/wp-content
    Make sure this directory is writeable by your webserver</li>
    <li>Put the wp-db-abstraction directory and the wp-db-abstraction.php that
    you unzipped into wp-content/mu-plugins If the mu-plugins directory does not
    exist, please create it first. It should be parallel to your "themes" and
    "plugins" directories in wp-content. If you already had the plugin
    installed, simply overwrite any existing files in this directory. </li>
    <li>Open up wp-content/mu-plugins/wp-db-abstraction . There should be a file
    called db.php. Copy that file from its current location into wp-content . It
    should now be located in the same place as your mu-plugins directory, your
    plugins directory, and your themes directory. If you had the plugin
    installed previously, simply overwrite the existing db.php file. If you have
    a db.php file from a different plugin you'll need to rename the old db.php
    ($pluginname-db.php for example), copy the new db.php file from
    wp-db-abstraction to the wp-content directory, open the db.php file in a
    text editor and add include '$pluginname-db.php'; to the bottom of the
    new db.php file.</li>
    <li>If you are migrating from MySQL to SQL Server or this is a new
    installation please visit
    $your_wordpress_url/wp-content/mu-plugins/wp-db-abstraction/setup-config.php
    to generate your wp-config.php. Then follow the regular Wordpress
    installation steps. If you are migrating, you may then import the xml data
    you exported in step one using the Wordpress Administration Area.</li>
    <li>Make sure you have a file called fields_map.parsed_types.php in your
    wp-content directory after installation. The plugin will not function
    correctly without this file. If your wp-content directory was not writeable
    or some other error kept this file from being generated, you may copy the
    fields_map.parsed_types.example-3.2.1.php file from
    wp-content/mu-plugins/wp-db-abstraction to wp-content and rename it to
    fields_map.parsed_types.php</li>
</ol>
</body>
</html>