<?php
class cpp_proc
{
	/*
	 * Reads C text from the buffer, applies values specified in
	 * the $constants map and returns the result.
	 *
	 * If $stopnames array is not empty, stops before the line with
	 * one of the macros specified in the array. This allows, for
	 * example, to parse one condition branch and stop.
	 */
	static function read_text($buf, $constants, $stopnames = array())
	{
		$text = '';

		while ($buf->more()) {
			$line = $buf->get_line();
			list($name, $val) = self::parse_macro($line);
			/*
			 * If this line is not a macro, pass it.
			 */
			if(!$name) {
				$text .= $line;
				continue;
			}

			/*
			 * If this is a stop macro, don't parse it and put the
			 * line back.
			 */
			if (in_array($name, $stopnames)) {
				$buf->unget_line($line);
				return $text;
			}

			/*
			 * If this is a conditional macro, process it.
			 */
			if ($name == 'if' || $name == 'ifdef' || $name == 'ifndef') {
				/*
				 * Macro can be split into several lines. In the code
				 * above, we didn't bother with that, but not we have
				 * to return the line back and read the full macro
				 * properly.
				 */
				$buf->unget_line($line);
				$macro = self::read_macro($buf);

				$text .= self::read_if($macro, $buf, $constants);
				continue;
			}

			/*
			 * Don't bother with other macros.
			 */
			$text .= $line;
		}

		return $text;
	}

	private static function read_if($macro, $buf, $constants)
	{
		$name = $macro->name;
		$val = $macro->val;

		/*
		 * Rewrite ifdef ... as if defined(...)
		 */
		if ($macro->name == 'ifdef') {
			$macro->name = 'if';
			$macro->val = "defined($macro->val)";
		}
		else if ($macro->name == 'ifndef') {
			$macro->name = 'if';
			$macro->val = "!defined($macro->val)";
		}

		$branches = array();

		/*
		 * First branch ('if')
		 */
		$cond = cpp_cond_parse::parse($macro->val);
		$text = self::read_text($buf, $constants, array(
			'endif',
			'else',
			'elif'
		));
		$branches[] = array(
			$cond,
			$text,
			$macro->orig
		);

		/*
		 * Zero or more 'elif' branches
		 */
		$macro = self::read_macro($buf);
		while ($macro && $macro->name == 'elif') {
			$cond = cpp_cond_parse::parse($macro->val);
			$text = self::read_text($buf, $constants, array(
				'elif',
				'else',
				'endif'
			));
			$branches[] = array(
				$cond,
				$text,
				$macro->orig
			);

			$macro = self::read_macro($buf);
		}

		/*
		 * One optional 'else' branch
		 */
		if ($macro && $macro->name == 'else') {
			$text = self::read_text($buf, $constants, array(
				'endif'
			));
			$branches[] = array(
				true,
				$text,
				$macro->orig
			);

			$macro = self::read_macro($buf);
		}

		/*
		 * Endif
		 */
		if (!$macro || $macro->name != 'endif') {
			$buf->error("#endif expected");
		}

		$changed = cpp_proc_reduce::reduce($branches, $constants);
		return self::compose_branches($branches, $changed, $macro->orig);
	}

	private static function read_macro($buf)
	{
		$line = $buf->get_line();

		/*
		 * If not a macro, return nothing.
		 */
		list($name, $val) = self::parse_macro($line);
		if (!$name) {
			$buf->unget_line($line);
			return null;
		}

		/*
		 * Keep the original formatting of the macro in case
		 * we don't touch it.
		 */
		$original = $line;

		/*
		 * If this is a macro we need to parse, unwrap lines broken
		 * with a backslash.
		 */
		while (preg_match('/\\\r?\n$/', $line, $m)) {
			$n = strlen($line);

			if ($line[$n-2] == "\r") {
				$eol = "\r\n";
			}
			else {
				$eol = "\n";
			}

			$next = $buf->get_line();
			$original .= $next;

			$line = substr($line, 0, -(strlen($eol)+1));
			$line .= $eol.$next;
		}
		list($name, $val) = self::parse_macro($line);

		return (object)array(
			'name' => $name,
			'val' => $val,
			'orig' => $original
		);
	}

	private static function parse_macro($line)
	{
		$p = '/^\s*#\s*([a-z]+)\s+/';
		if (!preg_match($p, $line, $m)) {
			return array(null, null);
		}

		$name = $m[1];
		$val = trim(substr($line, strlen($m[0])));

		while (1) {
			$p = strpos($val, '/*');
			if ($p === false) break;
			$p2 = strpos($val, '*/', $p);
			if ($p2 === false) break;
			$orig = $val;
			$val = substr_replace($val, '', $p, ($p2 - $p)+2);
		}

		return array($name, $val);
	}

	private static function compose_branches($branches, $changed, $orig_endif)
	{
		$text = '';

		$n = count($branches);
		if (!$n) return $text;

		if ($n == 1 && cpp_cond_calc::is_true($branches[0][0])) {
			return $branches[0][1];
		}

		list($cond, $body, $orig) = $branches[0];
		if ($changed) {
			$text = '#if '.cpp_cond_parse::compose($cond)."\n";
		}
		else {
			$text = $orig;
		}
		$text .= $body;

		$i = 1;
		while ($i < $n) {
			list($cond, $body, $orig) = $branches[$i++];

			if (!$changed) {
				$text .= $orig;
				$text .= $body;
				continue;
			}

			if (cpp_cond_calc::is_true($cond) && $i == $n) {
				$text .= "#else\n";
			}
			else {
				$text .= "#elif ".cpp_cond_parse::compose($cond)."\n";
			}
			$text .= $body;
		}
		if ($changed) {
			$text .= "#endif\n";
		}
		else {
			$text .= $orig_endif;
		}

		return $text;
	}
}

?>
