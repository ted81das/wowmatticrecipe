<?php defined('WPINC') or die ?><?php

// Your PHP code goes here!// Remove WordPress Widgets from Beaver Builder Modules
function remove_wp_widgets_from_bb( $modules ) {
unset( $modules['wordpress'] );
return $modules;
}
add_filter( 'fl_builder_available_modules', 'remove_wp_widgets_from_bb' );

// Remove Pro Modules from Beaver Builder Based on Category 
function remove_bb_pro_modules_by_category( $modules ) { foreach ( $modules as $module_key => $module_class ) { if ( isset( $module_class->category ) && $module_class->category == 'Pro Modules' ) { unset( $modules[ $module_key ] ); } } return $modules; } 
add_filter( 'fl_builder_available_modules', 'remove_bb_pro_modules_by_category' );
