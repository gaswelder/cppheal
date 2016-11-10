<?php
class cpp_proc_reduce
{
	static function reduce(&$branches, $constants, &$error)
	{
		$changed = false;

		foreach ($branches as $i => $branch) {
			$cond = $branches[$i][0];
			$orig = $cond;
			if (cpp_cond_calc::calc($cond, $constants, $error)) {
				$changed = true;
			}
			if ($error) return false;
			$branches[$i][0] = $cond;
		}

		if (!$changed) {
			return false;
		}

		/*
		 * Remove branches with false condition.
		 */
		$n = count($branches);
		$i = 0;
		while ($i < $n) {
			$cond = $branches[$i][0];
			if (cpp_cond_calc::is_false($cond)) {
				array_splice($branches, $i, 1);
				$n--;
				continue;
			}
			$i++;
		}

		/*
		 * Remove branches from bottom until there is only one
		 * branch with true condition.
		 *
		 * The "else" condition is always a true branch, but if after
		 * reducing one of the branches above becomes also true, all
		 * other branches below it, including the "else" branch, will
		 * not be taken anymore.
		 */
		$pos = self::true_branch_index($branches);
		if ($pos >= 0) {
			while ($pos < count($branches)-1) {
				array_pop($branches);
			}
		}

		return true;
	}

	private static function true_branch_index($branches)
	{
		$n = count($branches);
		for ($i = 0; $i < $n; $i++) {
			$cond = $branches[$i][0];
			if (cpp_cond_calc::is_true($cond)) {
				return $i;
			}
		}
		return -1;
	}
}

?>
