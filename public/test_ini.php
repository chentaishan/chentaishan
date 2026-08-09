<?php
echo "Loaded php.ini: " . php_ini_loaded_file() . "\n";
echo "open_basedir: " . ini_get('open_basedir') . "\n";
echo "PHP Version: " . phpversion() . "\n";
?>