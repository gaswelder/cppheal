<?php
class num_token
{
	public $raw;
	public $val;

	function __construct($raw, $val)
	{
		$this->raw = $raw;
		$this->val = $val;
	}

	function __toString()
	{
		return $this->raw;
	}
}

class cpp_cond_parse
{
	/*
	 * Recognized operators
	 */
	private static $ops = array(
		"&&",
		"||",
		"<=",
		">=",
		"==",
		"!=",
		"<",
		">",
		"&",
		"|",
		"-",
		"+"
	);

	const ID_CHARS = "abcdefghijklmnopqrstuvwxyz_0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ";

	/*
	 * Parses a condition string and returns scalar, array or object
	 * representation.
	 */
	static function parse($str, &$error)
	{
		$error = null;
		$buf = new buf($str);

		$expr = self::expr($buf, $error);
		if ($error) return null;

		if ($buf->more()) {
			$error = $buf->err("Unexpected character: ".$buf->peek());
			return null;
		}

		return $expr;
	}

	// <expr>: <atom> [<op> <atom>]...
	private static function expr($buf, &$error)
	{
		$error = null;

		$buf->read_set("\r\n\t ");
		$left = self::read_atom($buf, $error);
		if ($error) {
			return null;
		}

		$buf->read_set("\r\n\t ");
		$op = self::read_op($buf);
		if (!$op) {
			return $left;
		}

		$right = self::expr($buf, $error);
		if ($error) return null;

		return (object)array(
			'op' => $op,
			'left' => $left,
			'right' => $right
		);
	}

	private static function read_op($buf)
	{
		$buf->read_set(" \r\n\t");

		foreach (self::$ops as $op) {
			if ($buf->get_str($op)) {
				return $op;
			}
		}
		return null;
	}

	/*
	 * Reads next "atomic" part of an expression.
	 */
	// <atom>: ( "!"? "defined(" <id> ")" ) | <id> | <num> | "(" <expr> ")"
	// | <id> "(" <args> ")"
	private static function read_atom($buf, &$error)
	{
		$error = null;
		$ops = array();

		$buf->read_set(" \t\r\n");
		while ($buf->get_str('!')) {
			$ops[] = '!';
			$buf->read_set(" \t\r\n");
		}

		// defined(...)
		if ($buf->get_str('defined')) {
			self::read_defined($buf, $ops, $error);
			if ($error) return null;
			return $ops;
		}

		// "(" <expr> ")"
		if ($buf->get_str('(')) {
			$node = self::expr($buf, $error);
			if ($error) return null;
			if (!$buf->get_str(')')) {
				$error = $buf->err(") expected");
				return null;
			}
			return $node;
		}

		// <num>
		if (ctype_digit($buf->peek())) {
			$n = self::read_number($buf, $error);
			if ($error) return null;
			return [$n];
		}

		// <id>
		$id = $buf->read_set(self::ID_CHARS);
		if (!$id) {
			$error = $buf->err("identifier expected");
			return null;
		}

		/*
		 * We don't really parse macro functions here, just treat the
		 * entire expression as a name. Also, we don't parse strings
		 * inside, which may screw the parsing if there happens to be
		 * a string with '(' or ')' symbols.
		 */
		if ($buf->get_str('(')) {
			$id .= '(';
			// <args>
			$braces = 1;
			while ($buf->more() && $braces > 0) {
				$ch = $buf->get();
				if ($ch == ')') {
					$braces--;
				}
				else if ($ch == '(') {
					$braces++;
				}

				$id .= $ch;
			}
			if ($braces != 0) {
				$error = $buf->err("')' expected");
				return null;
			}
		}

		return [$id];
	}

	// "defined" ( <id> | "(" <id> ")" )
	private static function read_defined($buf, &$ops, &$error)
	{
		$brace = false;

		$buf->read_set(" \t\r\n");
		if ($buf->peek() == '(') {
			$brace = true;
			$buf->get();
			$buf->read_set(" \t\r\n");
		}

		$id = $buf->read_set(self::ID_CHARS);
		$buf->read_set(" \t\r\n");

		if ($brace && $buf->get() != ')') {
			$error = $buf->err("')' expected");
			return false;
		}
		$buf->read_set(" \t");
		array_unshift($ops, 'defined');
		array_unshift($ops, $id);
		return $ops;
	}

	// <num>: (<digit>... | "0x" <hex-digit>...) U? L? L?
	private static function read_number($buf, &$error)
	{
		$digits = '0123456789';

		$num = $buf->get();

		if ($num == '0' && $buf->peek() == 'x') {
			$num .= $buf->get();
			$val = '';

			$digits .= 'abcdefABCDEF';
			$val = $buf->read_set($digits);

			if ($val === '') {
				$error = $buf->err("digit expected after 'x'");
				return false;
			}
			$num .= $val;
		}
		else {
			$num .= $buf->read_set($digits);
		}

		$val = intval($num, 0);

		if ($buf->peek() == 'U') {
			$num .= $buf->get();
		}

		if ($buf->peek() == 'L') {
			$num .= $buf->get();
		}

		if ($buf->peek() == 'L') {
			$num .= $buf->get();
		}

		return new num_token($num, $val);
	}

	static function compose($cond)
	{
		if (is_scalar($cond)) {
			return $cond;
		}

		if (is_array($cond)) {
			$s = '';
			foreach ($cond as $op) {
				switch ($op) {
				case 'defined':
					$s = "defined($s)";
					break;
				case '!':
					$s = "!$s";
					break;
				default:
					if ($s != ""){
						trigger_error("Unknown operation: $op");
						return null;
					}
					$s = $op;
				}
			}
			return $s;
		}

		if (is_object($cond)) {
			return sprintf("(%s) %s (%s)", self::compose($cond->left), $cond->op,
				self::compose($cond->right));
		}

		trigger_error("Unknown condition form");
		return "?";
	}
}

?>
