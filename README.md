# cppheal

Utility to heal #ifdef shrapnel wounds on C source files.

	$ cppheal [-r] [-D <name>[=<val>]]... [-U <name>]... <path>...


Suppose we have a large C code tree with all kinds of `#if` and `#ifdef`
conditions which involve some predefined constants, like `use_foo`. If
we decide that `use_foo` will be always 1, then we can substitute this
value to the macros and clean the code a little:

	$ cppheal -D use_foo=1 -r src

Only `"*.c"` and `"*.h"` files are processed. The `-r` flag allows
directory recursion.

cppheal doesn't read `#define` and `#undef` directives in the source,
it relies only on those constants that are given on the command line.
If an unknown constant is encountered, it is left as is.

An argument `-D FOO=42` tells cppheal to assume that `FOO` was defined
to `42`. An argument `-D FOO` means that `FOO` was just defined without
a value. An argument `-U FOO`, on the other hand, tells cppheal to
assume that `FOO` is undefined. Without `-U` flag it wouldn't be
possible to get rid of conditions like `#ifndef FOO`.


## Limitations

cppheal works only with macro constants and doesn't process functions.
