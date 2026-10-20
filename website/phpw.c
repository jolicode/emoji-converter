/**
 * Derived from https://github.com/derickr/php-wasm-builder/blob/main/phpw.c
 *
 * Copyright (c) 2025 The PHP Foundation
 * Copyright (c) 2023-2024 Antoine Bluchet
 *
 * Permission is hereby granted, free of charge, to any person obtaining a
 * copy of this software and associated documentation files (the "Software"),
 * to deal in the Software without restriction, including without limitation
 * the rights to use, copy, modify, merge, publish, distribute, sublicense,
 * and/or sell copies of the Software, and to permit persons to whom the
 * Software is furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER
 * DEALINGS IN THE SOFTWARE.
 */
#include "sapi/embed/php_embed.h"
#include <emscripten.h>
#include <stdlib.h>

/*
 * Runs a PHP script in its own request: the engine is started and shut down on
 * each call. Returns the exit status of the script (255 on a fatal error).
 */
int EMSCRIPTEN_KEEPALIVE phpw_run(char *file)
{
	int exit_status;

	/* With the Zend allocator, the memory grows on each call until it is exhausted */
	setenv("USE_ZEND_ALLOC", "0", 1);
	php_embed_init(0, NULL);
	/* Not reset by php_embed_init(), it would keep the status of a previous call */
	EG(exit_status) = 0;

	zend_first_try {
		zend_file_handle file_handle;
		zend_stream_init_filename(&file_handle, file);
		php_execute_script(&file_handle);
		zend_destroy_file_handle(&file_handle);
	} zend_end_try();

	exit_status = EG(exit_status);
	php_embed_shutdown();

	return exit_status;
}
