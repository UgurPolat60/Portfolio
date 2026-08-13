<?php
/* The built-in PHP server does not read .htaccess, so the two rules that file
   carries have to live here instead — otherwise a public demo hands out its
   own database credentials and application log to anyone who guesses the path.
   Everything else is served exactly as before. */

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';

if (preg_match('#^/config\.php$|^/logs(/|$)|\.log$#i', $path)) {
    http_response_code(403);
    exit('Forbidden');
}

return false;
