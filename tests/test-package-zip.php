<?php
/**
 * ZIP packaging: WordPress plugin root must be expertstatsplugin-master/.
 * Version belongs in the filename and plugin header only — never the archive root.
 *
 * @package wpcable
 */

$script = WPCABLE_TEST_ROOT . '/scripts/package-zip.sh';
wpcable_assert( is_file( $script ), 'scripts/package-zip.sh exists' );

$header = file_get_contents( WPCABLE_TEST_ROOT . '/wp-codeable.php' );
wpcable_assert( 1 === preg_match( '/^\s*\*\s*Version:\s*(\S+)/m', $header, $vm ), 'plugin header has Version' );
$header_version = $vm[1];

$out = tempnam( sys_get_temp_dir(), 'wpcable-pkg-' ) . '.zip';
$cmd = 'bash ' . escapeshellarg( $script ) . ' ' . escapeshellarg( $out ) . ' 2>&1';
exec( $cmd, $pack_out, $pack_code );
wpcable_assert( 0 === $pack_code, 'package-zip.sh exits 0' );
wpcable_assert( is_file( $out ) && filesize( $out ) > 0, 'package-zip.sh wrote a non-empty zip' );

$listing = array();
$unzip_code = 0;
exec( 'unzip -Z1 ' . escapeshellarg( $out ) . ' 2>/dev/null', $listing, $unzip_code );
if ( 0 !== $unzip_code || empty( $listing ) ) {
	$listing = array();
	exec( 'zipinfo -1 ' . escapeshellarg( $out ) . ' 2>/dev/null', $listing, $unzip_code );
}
wpcable_assert( 0 === $unzip_code && ! empty( $listing ), 'zip listing is readable (unzip -Z1 / zipinfo -1)' );

$bootstrap       = 'expertstatsplugin-master/wp-codeable.php';
$found_bootstrap = false;
$bad_roots       = array();
$other_roots     = array();

foreach ( $listing as $name ) {
	$name = trim( $name );
	if ( '' === $name || '/' === substr( $name, -1 ) ) {
		continue;
	}
	if ( $bootstrap === $name ) {
		$found_bootstrap = true;
	}
	$slash = strpos( $name, '/' );
	$root  = false === $slash ? $name : substr( $name, 0, $slash );
	if ( 'expertstatsplugin-master' !== $root ) {
		$other_roots[ $root ] = true;
	}
	if ( preg_match( '/^expertstatsplugin-0\./', $root ) || preg_match( '/^expertstatsplugin-\d/', $root ) ) {
		$bad_roots[ $root ] = true;
	}
}

$found_backfill = false;
$found_tests    = false;
$found_shots    = false;
foreach ( $listing as $name ) {
	$name = trim( $name );
	if ( 'expertstatsplugin-master/functions/history-backfill.php' === $name ) {
		$found_backfill = true;
	}
	if ( false !== strpos( $name, '/tests/' ) || preg_match( '#^expertstatsplugin-master/tests(/|$)#', $name ) ) {
		$found_tests = true;
	}
	if ( false !== strpos( $name, 'docs/screenshots/' ) ) {
		$found_shots = true;
	}
}

wpcable_assert( $found_bootstrap, 'archive contains expertstatsplugin-master/wp-codeable.php' );
wpcable_assert( $found_backfill, 'lean ZIP includes functions/history-backfill.php' );
wpcable_assert( ! $found_tests, 'lean ZIP omits tests/' );
wpcable_assert( ! $found_shots, 'lean ZIP omits docs/screenshots/' );
wpcable_assert( empty( $other_roots ), 'every zip member is under expertstatsplugin-master/ (no extra top-level roots)' );
wpcable_assert( empty( $bad_roots ), 'archive root is not versioned (not expertstatsplugin-0.x/)' );
wpcable_assert( false === strpos( 'expertstatsplugin-master', $header_version ), 'folder name expertstatsplugin-master does not embed the header version' );

$extracted = sys_get_temp_dir() . '/wpcable-pkg-header-' . uniqid() . '.php';
exec(
	'unzip -p ' . escapeshellarg( $out ) . ' ' . escapeshellarg( $bootstrap ) . ' > ' . escapeshellarg( $extracted ) . ' 2>/dev/null',
	$unused,
	$extract_code
);
$stream = is_file( $extracted ) ? file_get_contents( $extracted ) : '';
@unlink( $extracted );
wpcable_assert( 0 === $extract_code && is_string( $stream ) && false !== strpos( $stream, 'Version:           ' . $header_version ), 'packaged bootstrap header Version matches source' );

@unlink( $out );
