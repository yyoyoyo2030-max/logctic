<?php
if (function_exists('opcache_reset')) {
    opcache_reset();
    echo "OPCache reset successful.";
} else {
    echo "OPCache is not enabled.";
}
