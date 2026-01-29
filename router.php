<?php
if ($_SERVER['REQUEST_URI'] === '/.well-known/appspecific/com.chrome.devtools.json') {
    http_response_code(204);
    exit;
}
return false;
