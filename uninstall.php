<?php
/**
 * Uninstall handler for Frontend Gatekeeper.
 *
 * Removes plugin settings from every site when the plugin is deleted from
 * WP Admin. Activation and deactivation leave data in place; only an
 * explicit delete triggers this file.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$fronga_option_name = 'fronga_settings';

if ( is_multisite() ) {
	$fronga_site_ids = get_sites(
		[
			'fields' => 'ids',
			'number' => 0,
		]
	);

	foreach ( $fronga_site_ids as $fronga_site_id ) {
		switch_to_blog( (int) $fronga_site_id );
		delete_option( $fronga_option_name );
		restore_current_blog();
	}

	return;
}

delete_option( $fronga_option_name );
