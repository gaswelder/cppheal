<?php
require "lib/buf.php";
require "lib/cli.php";

require "cpp/cpp_cond_calc.php";
require "cpp/cpp_cond_parse.php";
require "cpp/cpp_proc.php";
require "cpp/cpp_proc_reduce.php";

set_error_handler('halt', -1);
function halt($errno, $errstr, $file, $line)
{
	fatal("$errstr at $file:$line");
}

exit(main($argv));

function usage()
{
	fatal("Usage: cppheal [-r] [-U <name>]... [-D <name>=<val>]... <path>...");
}

/*
 * Parses command line and runs the processing.
 */
function main($args)
{
	$flags = parse_args($args, array(
		"D" => "string[]",
		"U" => "string[]",
		"r" => "bool"
	));

	if (!$flags) {
		usage();
	}

	$constants = array();

	foreach ($flags['D'] as $spec) {
		if (strpos($spec, '=')) {
			list($name, $val) = array_map('trim', explode('=', $spec, 2));
			$constants[$name] = $val;
		}
		else {
			$name = $spec;
			$constants[$name] = true;
		}
	}

	foreach ($flags['U'] as $name) {
		$constants[$name] = false;
	}

	$recurse = $flags['r'];

	if (empty($constants)) {
		err("No macros to process");
		return 1;
	}

	if (empty($args)) {
		err("No path arguments");
		return 1;
	}

	foreach ($args as $path) {
		if (!file_exists($path)) {
			err("path doesn't exist: $path");
			continue;
		}

		if (is_dir($path)) {
			if (!$recurse) {
				err("Skipping directory $path");
				continue;
			}
			process_dir($path, $constants, $recurse);
		}
		else {
			process_file($path, $constants);
		}
	}

	return 0;
}

function process_dir($path, $macros, $recurse)
{
	$d = opendir($path);
	while (($name = readdir($d)) !== false) {
		if ($name[0] == '.') continue;

		$p = "$path/$name";
		if (is_dir($p)) {
			if ($recurse) process_dir($p, $macros, $recurse);
		}
		else {
			process_file($p, $macros);
		}
	}
	closedir($d);
}

function process_file($path, $macros)
{
	$ext = pathinfo($path, PATHINFO_EXTENSION);
	if ($ext != "c" && $ext != "h") {
		return;
	}
	//err( "# $path" );
	$orig = file_get_contents($path);
	$buf = new buf($orig, $path);
	$text = cpp_proc::rewrite($buf, $macros, $error);
	if ($error) {
		$where = $buf->loc();
		fwrite(STDERR, "$where: $error\n");
		return;
	}

	if ($text != $orig) {
		err("$path");
		file_put_contents($path, $text);
	}
}

?>
