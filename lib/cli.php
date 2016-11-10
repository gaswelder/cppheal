<?php
/*
 * Common functions for command-line utilities.
 */

function fatal( $message ) {
	fwrite( STDERR, $message."\n" );
	exit(1);
}

function err( $line ) {
	fwrite( STDERR, $line."\n" );
}

/*
 * Parses command line arguments.
 */
function parse_args( &$args, $defs )
{
	array_shift( $args );

	$vals = array(
		'bool' => false,
		'string[]' => array(),
		'string' => ''
	);

	$result = array();

	foreach( $defs as $key => $spec )
	{
		if( !isset( $vals[$spec] ) ) {
			fatal( "Unknown flag type: $spec" );
		}
		$result[$key] = $vals[$spec];
	}

	while( !empty( $args ) && $args[0][0] == '-' )
	{
		$arg = array_shift( $args );

		$key = substr( $arg, 1 );
		if( !isset( $defs[$key] ) ) {
			err( "Unknown flag: $arg" );
			return null;
		}

		$spec = $defs[$key];

		if( $spec == "bool" ) {
			$result[$key] = true;
			continue;
		}

		$val = array_shift( $args );
		if( $val === null ) {
			fatal( "The $arg flag requires an argument" );
		}

		if( $spec == "string[]" ) {
			$result[$key][] = $val;
			continue;
		}

		if( $spec == 'string' ) {
			$result[$key] = $val;
			continue;
		}

		fatal( "Unhandled flag type: $spec" );
	}

	return $result;
}

?>
