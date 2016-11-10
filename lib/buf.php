<?php
class buf
{
	public $n;
	public $val;
	public $pos;
	private $linenum;
	private $filename;

	function __construct($val, $filename = "")
	{
		$this->val = $val;
		$this->n = strlen($val);
		$this->pos = 0;
		$this->linenum = 0;
		$this->filename = $filename;
	}

	function loc()
	{
		if ($this->filename) {
			return "$this->filename:$this->linenum";
		}
		return "line $this->linenum";
	}

	/*
	 * Returns true if there are more characters to get.
	 */
	function more()
	{
		return $this->pos < $this->n;
	}

	function get()
	{
		if (!$this->more()) {
			return null;
		}
		$ch = $this->val[$this->pos++];
		if ($ch == "\n") $this->linenum++;
		return $ch;
	}

	function unget($ch)
	{
		$this->pos--;
		assert($ch == $this->val[$this->pos]);
		if ($ch == "\n") $this->linenum--;
	}

	function peek()
	{
		if (!$this->more()) {
			return null;
		}
		return $this->val[$this->pos];
	}

	/*
	 * Reads a line.
	 */
	function get_line()
	{
		if (!$this->more()) {
			return null;
		}

		$p = strpos($this->val, "\n", $this->pos);
		if ($p === false) {
			$p = $this->n;
		}
		else {
			$p++;
			$this->linenum++;
		}

		$line = substr($this->val, $this->pos, $p - $this->pos);
		$this->pos = $p;

		return $line;
	}

	function unget_line($line)
	{
		$n = strlen($line);
		$p = $this->pos - $n;
		assert(substr($this->val, $p, $n) == $line);
		$this->pos = $p;
		if (strpos($line, "\n") !== false) {
			$this->linenum--;
		}
	}

	function error($msg)
	{
		$msg = $this->err($msg);
		trigger_error($msg);
		exit;
	}

	function err($msg)
	{
		$width = 20;

		$a = $this->pos - $width;
		if ($a < 0) $a = 0;

		$context = "...".substr($this->val, $a, $this->pos - $a);

		if ($this->pos < $this->n) {
			$curr = $this->val[$this->pos];
			$context .= '|'.$curr;
			$context .= '|'.substr($this->val, $this->pos+1, $width);
			$context .= '...';
		}
		else {
			$curr = "{end}";
			$context .= "|{end}";
		}

		return "$msg at '$curr': '$context'";
	}

	/*
	 * Skips a literal string. Returns false if the string wasn't
	 * there.
	 */
	function get_str($str)
	{
		if (strpos($this->val, $str, $this->pos) !== $this->pos) {
			return false;
		}
		$this->pos += strlen($str);
		return true;
	}

	function pos()
	{
		return $this->pos;
	}

	function reset()
	{
		$this->pos = 0;
	}

	function _peeks($n)
	{
		return substr($this->val, $this->pos, $n);
	}

	/*
	 * Skip to the next occurence of the given string.
	 */
	function seekstr($str)
	{
		$pos = strpos($this->val, $str, $this->pos);
		if ($pos === false) {
			return false;
		}
		$this->pos = $pos;
		return true;
	}

	function read_set($class)
	{
		$pos = $this->pos;
		while ($pos < $this->n) {
			$ch = $this->val[$pos];
			if (strpos($class, $ch) === false) {
				break;
			}
			$pos++;
		}

		$str = substr($this->val, $this->pos, $pos - $this->pos);
		$this->pos = $pos;
		return $str;
	}

	/*
	 * Returns string from current position to the first character
	 * from the given class.
	 */
	function until($class)
	{
		$pos = $this->pos;
		while ($pos < $this->n) {
			$ch = $this->val[$pos];
			if (strpos($class, $ch) !== false) {
				break;
			}
			$pos++;
		}

		$r = substr($this->val, $this->pos, $pos - $this->pos);
		$this->pos = $pos;
		return $r;

		while (!$this->end()) {
			$ch = $this->get();
			if (strpos($class, $ch) !== false) {
				$this->unget($ch);
				break;
			}
			$r .= $ch;
		}
		return $r;
	}
}

?>
