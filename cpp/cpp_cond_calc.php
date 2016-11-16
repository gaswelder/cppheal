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
	 *
	 * Returns true if the condition has been modified.
	 */
	static function calc(&$cond, $constants, &$error)
	{
		$error = null;

		if (is_scalar($cond)) {
			return false;
		}

		if (is_array($cond)) {
			return self::calc_array($cond, $constants);
		}

		return self::calc_node($cond, $constants, $error);
	}

	private static function calc_array(&$cond, $constants)
	{
		$id = $cond[0];

		/*
		 * The parsed condition may be just a one-element array
		 * with a numeric literal.
		 */
		if ($id instanceof num_token) {
			if (count($cond) != 1) {
				var_dump($cond);
				trigger_error("Unexpected condition format");
				return false;
			}
			$cond = $id->val;
			return true;
		}

		if (!ctype_alpha($id[0]) && $id[0] != '_') {
			trigger_error("Unknown identifier form: $id");
			return false;
		}

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

	private static function calc_node(&$cond, $constants, &$error)
	{
		/*
		 * Calculate both operands. If nothing changed, return.
		 */
		$changed = false;
		if (self::calc($cond->left, $constants, $error)) {
			$changed = true;
		}
		if ($error) return false;
		if (self::calc($cond->right, $constants, $error)) {
			$changed = true;
		}
		if ($error) return false;
		if (!$changed) {
			return false;
		}

		/*
		 * Make sure we know the operator.
		 */
		$ops = array(
			'&&',
			'||',
			'<',
			'>',
			'<=',
			'>=',
			'==',
			'!=',
			'-',
			'+'
		);
		if (!in_array($cond->op, $ops)) {
			$error = "Unknown operator: $cond->op";
			return false;
		}

		/*
		 * One or both operands may be scalar values or not. An operand
		 * will be non-scalar if there was not enough information in
		 * the given constants to reduce its value.
		 */
		$s1 = is_scalar($cond->left);
		$s2 = is_scalar($cond->right);

		/*
		 * If both values are non-scalar, nothing we can do.
		 */
		if (!$s1 && !$s2) return false;

		/*
		 * For boolean operators && and || we need only one of the
		 * operands to be a scalar value and the order of the operands
		 * is not important. To simplify this case, let $v1 be
		 * the scalar value.
		 */
		if ($s1) {
			$v1 = $cond->left;
			$v2 = $cond->right;
		}
		else {
			$v1 = $cond->right;
			$v2 = $cond->left;
		}
		switch ($cond->op) {
		case '&&':
			$cond = ($v1 == '0') ? '0' : $v2;
			return true;
		case '||':
			$cond = ($v1 == '1') ? '1' : $v2;
			return true;
		}

		/*
		 * For all other operators we need both values to be scalar.
		 * If they are not, we leave the condition as is.
		 */
		if (!$s1 || !$s2) {
			return false;
		}

		if (!is_numeric($cond->left) || !is_numeric($cond->right)) {
			//$error = "Can't calculate '$cond->left $cond->op $cond->right'";
			return false;
		}

		$a = $cond->left;
		$b = $cond->right;
		$r = null;
		switch ($cond->op) {
		case '-':
			$r = $a - $b;
			break;
		case '+':
			$r = $a + $b;
			break;
		}

		if ($r !== null) {
			$cond = $r;
			return true;
		}
		switch ($cond->op) {
		case '<':
			$r = $a < $b;
			break;
		case '>':
			$r = $a > $b;
			break;
		case '<=':
			$r = $a <= $b;
			break;
		case '>=':
			$r = $a >= $b;
			break;
		case '==':
			$r = $a == $b;
			break;
		case '!=':
			$r = $a != $b;
			break;
		default:
			$error = "Unhandled operator: $cond->op";
			return false;
		}
		$cond = $r ? '1' : '0';
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
