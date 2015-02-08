<?php
/**
 * The Header for our theme
 *
 * Displays all of the <head> section and everything up till <div id="main">
 *
 * @package WordPress
 * @subpackage Twenty_Fourteen
 * @since Twenty Fourteen 1.0
 */
?><!DOCTYPE html>
<!--[if IE 7]>
<html class="ie ie7" <?php language_attributes(); ?>>
<![endif]-->
<!--[if IE 8]>
<html class="ie ie8" <?php language_attributes(); ?>>
<![endif]-->
<!--[if !(IE 7) & !(IE 8)]><!-->
<html <?php language_attributes(); ?>>
<!--<![endif]-->
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="author" content="<?php the_author(); ?>" />
    <meta name="copyright" content="&copy; Martin Costello 2014-2015" />
    <meta name="description" content="<?php bloginfo('description'); ?>" />
    <meta name="language" content="en" />
    <meta name="robots" content="INDEX" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, minimum-scale=1, maximum-scale=1" />
    <meta property="og:description" content="<?php bloginfo('description'); ?>" />
    <meta property="og:locale" content="en_GB" />
    <meta property="og:site_name" content="<?php bloginfo('name'); ?>" />
    <meta property="og:title" content="<?php the_title(); ?>" />
    <meta property="og:type" content="article" />
    <meta property="og:url" content="" />
    <meta name="twitter:card" content="summary">
    <meta name="twitter:creator" content="@martin_costello">
    <meta name="twitter:description" content="<?php bloginfo('description'); ?>">
    <meta name="twitter:domain" content="blog.martincostello.com" />
    <meta name="twitter:site" content="@martin_costello">
    <meta name="twitter:title" content="<?php the_title(); ?>">
    <meta name="google-site-verification" content="ji6SNsPQEbNQmF252sQgQFswh-b6cDnNOa3AHvgo4J0" />
    <meta name="msvalidate.01" content="D6C2E7551C902F1A396D8564C6452930" />
    <title><?php wp_title( '|', true, 'right' ); ?></title>
    <link rel="profile" href="http://gmpg.org/xfn/11">
    <link rel="pingback" href="<?php bloginfo( 'pingback_url' ); ?>">
    <!--[if lt IE 9]>
    <script src="<?php echo get_template_directory_uri(); ?>/js/html5.js"></script>
    <![endif]-->
    <?php wp_head(); ?>
        <script type="text/javascript">
        if (self == top) {
            document.documentElement.className = document.documentElement.className.replace(/\bjs-flash\b/, '');
        }
        else {
            top.location = self.location;
        }
    </script>
</head>

<body <?php body_class(); ?>>
<div id="page" class="hfeed site">
    <?php if ( get_header_image() ) : ?>
    <div id="site-header">
        <a href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home">
            <img src="<?php header_image(); ?>" width="<?php echo get_custom_header()->width; ?>" height="<?php echo get_custom_header()->height; ?>" alt="<?php echo esc_attr( get_bloginfo( 'name', 'display' ) ); ?>">
        </a>
    </div>
    <?php endif; ?>

    <header id="masthead" class="site-header" role="banner">
        <div class="header-main">
            <h1 class="site-title"><a href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home"><?php bloginfo( 'name' ); ?></a></h1>

            <div class="search-toggle">
                <a href="#search-container" class="screen-reader-text"><?php _e( 'Search', 'twentyfourteen' ); ?></a>
            </div>

            <nav id="primary-navigation" class="site-navigation primary-navigation" role="navigation">
                <button class="menu-toggle"><?php _e( 'Primary Menu', 'twentyfourteen' ); ?></button>
                <a class="screen-reader-text skip-link" href="#content"><?php _e( 'Skip to content', 'twentyfourteen' ); ?></a>
                <?php wp_nav_menu( array( 'theme_location' => 'primary', 'menu_class' => 'nav-menu' ) ); ?>
            </nav>
        </div>

        <div id="search-container" class="search-box-wrapper hide">
            <div class="search-box">
                <?php get_search_form(); ?>
            </div>
        </div>
    </header><!-- #masthead -->

    <div id="main" class="site-main">
