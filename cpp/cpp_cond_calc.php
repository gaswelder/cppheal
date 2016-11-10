<?php
class cpp_cond_calc
{
	/*
	 * The macro condition, which is an expression with operators like
	 * '&&' and '||', is represented as a tree. A node in that tree can
	 * be an 'operation' or a 'scalar'. An operation node is represented
	 * as an array with 'op' key containing the operator's identifier,
	 * and 'left' and 'right' keys pointing to operands, which are nodes
	 * themselves.
	 *
	 * To simplify the expression using the given constants, we simply
	 * perform the recursive calculation replacing constants with
	 * available values.
	 */
	static function calc(&$cond, $constants)
	{
		if (is_scalar($cond)) {
			return false;
		}

		if (is_array($cond)) {
			return self::calc_array($cond, $constants);
		}

		return self::calc_node($cond, $constants);
	}

	private static function calc_array(&$cond, $constants)
	{
		$id = $cond[0];

		/*
		 * If we don't know anything about this constant, return
		 * the expression as it is.
		 */
		if (!isset($constants[$id])) {
			return false;
		}

		/*
		 * Start with the known value.
		 */
		$val = $constants[$id];

		/*
		 * The 'defined' keyword will be at position 1, if at all.
		 */
		$n = count($cond);
		$i = 1;
		if ($i < $n && $cond[$i] == 'defined') {
			if ($val !== false) {
				$val = true;
			}
			$i++;
		}

		while ($i < $n) {
			$op = $cond[$i];
			$i++;
			switch ($op) {
			case '!':
				$val = !$val;
				break;
			default:
				trigger_error("Unknown operation: $op");
			}
		}

		$cond = $val;

		return true;
	}

	private static function calc_node(&$cond, $constants)
	{
		$changed = self::calc($cond->left, $constants) || self::calc($cond->right,
			$constants);

		if (!$changed) {
			return false;
		}

		/*
		 * Hide the left/right distinction to avoid dealing with two
		 * cases. Note that this works if both operands are scalars too.
		 */
		if (is_scalar($cond->left)) {
			$val = $cond->left;
			$other = $cond->right;
		}
		else if (is_scalar($cond->right)) {
			$val = $cond->right;
			$other = $cond->left;
		}
		else {
			// Can't do anything more, so return as is.
			return true;
		}
		switch ($cond->op) {
		case '&&':
			if ($val == '0'){
				$cond = '0';
			}
			else {
				$cond = $other;
			}
			break;
		case '||':
			if ($val == '0'){
				$cond = $other;
			}
			else {
				$cond = '1';
			}
			break;
		default:
			trigger_error("Unknown operator: $cond->op");
		}

		return true;
	}

	static function is_true($cond)
	{
		if (!is_scalar($cond)) {
			return false;
		}

		return $cond == '1' || $cond === true;
	}

	static function is_false($cond)
	{
		if (!is_scalar($cond)) {
			return false;
		}

		return $cond == '0' || $cond === false;
	}
}

?>
