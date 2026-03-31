<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php wp_title( '|', true, 'right' ); ?><?php bloginfo( 'name' ); ?></title>
<style>*,*::before,*::after{box-sizing:border-box;}html,body{margin:0;padding:0;min-height:100%;background:#f8fafc;}</style>
<?php wp_head(); ?>
</head>
<body <?php body_class( 'aosai-portal-fullpage' ); ?>>
<?php
if ( have_posts() ) {
    while ( have_posts() ) {
        the_post();
        the_content();
    }
}
?>
<?php wp_footer(); ?>
</body>
</html>
