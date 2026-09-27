#!/bin/sh
# Health check for php-fpm: queries the FPM ping endpoint over FastCGI.
set -e
SCRIPT_NAME=/ping SCRIPT_FILENAME=/ping REQUEST_METHOD=GET \
    cgi-fcgi -bind -connect 127.0.0.1:9000 | grep -q pong
