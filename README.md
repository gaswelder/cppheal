# cppheal

Heals `#ifdef` shrapnel wounds in C source files.

	$ cppheal [-r] [-D <name>[=<val>]]... [-U <name>]... <path>...

The following command will rewrite all .c and .h files discarding all conditional
branches assuming that APR_HAVE_CTYPE_H is defined as 42:

	$ cppheal -D APR_HAVE_CTYPE_H=42 -r src

The `-r` flag enables directory recursion.

cppheal doesn't read `#define` and `#undef` directives in the source,
it relies only on those constants that are given on the command line.
If an unknown constant is encountered, all conditions that rely on it are not evaluated and no branches are deleted.

An argument without the equal sign, `-D FOO`, means that `FOO` was just defined without a value.

An argument `-U FOO`, tells cppheal to assume that `FOO` is undefined.
Without the `-U` flag it wouldn't be possible to get rid of conditions like `#ifndef FOO`.


## Limitations

cppheal works only with macro constants and doesn't process functions.
