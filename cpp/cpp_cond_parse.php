<?php

class cpp_cond_parse
{
	/*
	 * Recognized operators
	 */
	private static $ops = array(
		"&&", "||", "<=", ">=", "==", "!=",
		"<", ">", "&", "|", "-", "+"
	);

	const ID_CHARS = "abcdefghijklmnopqrstuvwxyz_0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ";

	/*
	 * Parses a condition string and returns scalar, array or object
	 * representation.
	 */
	static function parse( $str )
	{
		$buf = new buf( $str );

		$expr = self::expr( $buf );
		if( $buf->more() ) {
			$buf->error( "Unexpected character: " . $buf->peek() );
			return null;
		}

		return $expr;
	}

	// <expr>: <atom> [<op> <atom>]...
	private static function expr( $buf )
	{
		$buf->read_set( "\r\n\t " );

		$left = self::read_atom( $buf );

		$buf->read_set( "\r\n\t " );
		$op = self::read_op( $buf );
		if( !$op ) {
			return $left;
		}

		$right = self::expr( $buf );

		return (object) array(
			'op' => $op,
			'left' => $left,
			'right' => $right
		);
	}

	private static function read_op( $buf )
	{
		$buf->read_set( " \r\n\t" );

		foreach( self::$ops as $op ) {
			if( $buf->get_str( $op ) ) {
				return $op;
			}
		}
		return null;
	}

	// <atom>: ( "!"? "defined(" <id> ")" ) | <id> | <num> | "(" <expr> ")"
	// | <id> "(" <args> ")"
	private static function read_atom( $buf )
	{
		$ops = array();

		$buf->read_set( " \t\r\n" );
		while( $buf->get_str( '!' ) ) {
			$ops[] = '!';
			$buf->read_set( " \t\r\n" );
		}

		// "defined" "(" <id> ")"
		if( $buf->get_str( 'defined' ) )
		{
			$brace = false;

			$buf->read_set( " \t\r\n" );
			if( $buf->peek() == '(' ) {
				$brace = true;
				$buf->get();
				$buf->read_set( " \t\r\n" );
			}

			$id = $buf->read_set( self::ID_CHARS );
			$buf->read_set( " \t\r\n" );

			if( $brace && $buf->get() != ')' ) {
				$buf->error( "')' expected" );
				return false;
			}
			$buf->read_set( " \t" );
			array_unshift( $ops, 'defined' );
			array_unshift( $ops, $id );
			return $ops;
		}

		// "(" <expr> ")"
		if( $buf->get_str( '(' ) )
		{
			$node = self::expr( $buf );
			if( !$buf->get_str( ')' ) ) {
				$buf->error( ") expected" );
				return false;
			}
			return $node;
		}

		// <num>
		if( ctype_digit( $buf->peek() ) ) {
			$num = $buf->read_set( '0123456789' );
			return [$num];
		}

		// <id>
		$id = $buf->read_set( self::ID_CHARS );
		/*
		 * We don't really parse macro functions here, just treat the
		 * entire expression as a name. Also, we don't parse strings
		 * inside, which may screw the parsing if there happens to be
		 * a string with '(' or ')' symbols.
		 */
		if( $buf->get_str( '(' ) ) {
			$id .= '(';
			// <args>
			$braces = 1;
			while( $buf->more() && $braces > 0 ) {
				$ch = $buf->get();
				if( $ch == ')' ) {
					$braces--;
				}
				else if( $ch == '(' ) {
					$braces++;
				}

				$id .= $ch;
			}
			if( $braces != 0 ) {
				$buf->error( "')' expected" );
			}
		}
		if( !$id ) {
			$buf->error( "id expected" );
		}
		return [$id];
	}

	static function compose( $cond )
	{
		if( is_scalar( $cond ) ) {
			return $cond;
		}

		if( is_array( $cond ) ) {
			$s = '';
			foreach( $cond as $op ) {
				switch( $op ) {
					case 'defined':
						$s = "defined($s)";
						break;
					case '!':
						$s = "!$s";
						break;
					default:
						if( $s != "" ) {
							trigger_error( "Unknown operation: $op" );
							return null;
						}
						$s = $op;
				}
			}
			return $s;
		}

		if( is_object( $cond ) )
		{
			return sprintf( "(%s) %s (%s)",
				self::compose( $cond->left ),
				$cond->op,
				self::compose( $cond->right )
			);
		}

		trigger_error( "Unknown condition form" );
		return "?";
	}
}

?>
