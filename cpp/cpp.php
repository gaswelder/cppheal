<?php

class cpp
{
	/*
	 * Main preprocessor function.
	 * $text is a C text.
	 * $constants is a macros map (name=>value).
	 * If macro value is false, it is assumed to be undefined.
	 * All macros not in the map are untouched.
	 */
	static function process( $text, $constants ) {
		$buf = new buf( $text );
		$text = cpp_proc::read_text( $buf, $constants );
		return $text;
	}
}


?>
