<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
  "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en" dir="ltr">
  <head>
    <title>Access denied | Drupal Site</title>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<link rel="shortcut icon" href="http://10.0.2.7/misc/favicon.ico" type="image/vnd.microsoft.icon" />
<meta name="Generator" content="Drupal 7 (http://drupal.org)" />
    <style type="text/css" media="all">@import url("http://10.0.2.7/modules/system/system.base.css?pn6csl");
@import url("http://10.0.2.7/modules/system/system.admin.css?pn6csl");
@import url("http://10.0.2.7/modules/system/system.menus.css?pn6csl");
@import url("http://10.0.2.7/modules/system/system.messages.css?pn6csl");
@import url("http://10.0.2.7/modules/system/system.theme.css?pn6csl");
@import url("http://10.0.2.7/modules/system/system.maintenance.css?pn6csl");</style>
<style type="text/css" media="screen">@import url("http://10.0.2.7/themes/seven/reset.css?pn6csl");
@import url("http://10.0.2.7/themes/seven/style.css?pn6csl");</style>

<!--[if lte IE 8]>
<link type="text/css" rel="stylesheet" href="http://10.0.2.7/themes/seven/ie.css?pn6csl" media="all" />
<![endif]-->

<!--[if lte IE 7]>
<link type="text/css" rel="stylesheet" href="http://10.0.2.7/themes/seven/ie7.css?pn6csl" media="all" />
<![endif]-->

<!--[if lte IE 6]>
<link type="text/css" rel="stylesheet" href="http://10.0.2.7/themes/seven/ie6.css?pn6csl" media="all" />
<![endif]-->
    <script type="text/javascript" src="http://10.0.2.7/misc/jquery.js?v=1.4.4"></script>
<script type="text/javascript" src="http://10.0.2.7/misc/jquery.once.js?v=1.2"></script>
<script type="text/javascript" src="http://10.0.2.7/misc/drupal.js?pn6csl"></script>
<script type="text/javascript">
<!--//--><![CDATA[//><!--
jQuery.extend(Drupal.settings, {"basePath":"\/","pathPrefix":"","ajaxPageState":{"css":{"modules\/system\/system.base.css":1,"modules\/system\/system.admin.css":1,"modules\/system\/system.menus.css":1,"modules\/system\/system.messages.css":1,"modules\/system\/system.theme.css":1,"modules\/system\/system.maintenance.css":1,"themes\/seven\/reset.css":1,"themes\/seven\/style.css":1,"themes\/seven\/ie.css":1,"themes\/seven\/ie7.css":1,"themes\/seven\/ie6.css":1},"js":{"misc\/jquery.js":1,"misc\/jquery.once.js":1,"misc\/drupal.js":1}}});
//--><!]]>
</script>
  </head>
  <body class="maintenance-page in-maintenance db-offline no-sidebars">

  
  <div id="branding">
    <h1 class="page-title">Access denied</h1>  </div>

  <div id="page">

    
    <div id="content" class="clearfix">
                  <p>Access denied. You are not authorized to access this page. Log in using either an account with the <em>administer software updates</em> permission or the site maintenance account (the account you created during installation). If you cannot log in, you will have to edit <code>settings.php</code> to bypass this access check. To do this:</p>
<ol>
 <li>With a text editor find the settings.php file on your system. From the main Drupal directory that you installed all the files into, go to <code>sites/your_site_name</code> if such directory exists, or else to <code>sites/default</code> which applies otherwise.</li>
 <li>There is a line inside your settings.php file that says <code>$update_free_access = FALSE;</code>. Change it to <code>$update_free_access = TRUE;</code>.</li>
 <li>As soon as the update.php script is done, you must change the settings.php file back to its original form with <code>$update_free_access = FALSE;</code>.</li>
 <li>To avoid having this problem in the future, remember to log in to your website using either an account with the <em>administer software updates</em> permission or the site maintenance account (the account you created during installation) before you backup your database at the beginning of the update process.</li>
</ol>    </div>

  </div>

  
  </body>
</html>
