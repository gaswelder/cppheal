<?php

class cpp
{
	static function process( $text, $constants ) {
		$buf = new buf( $text );
		$text = cpp_proc::read_text( $buf, $constants, array() );
		return $text;
	}
}


?>
