<?php
$root = __DIR__ . DIRECTORY_SEPARATOR;
$versionFile = $root . 'VERSION';
$version = 'dev';
if (is_file($versionFile)) {
    $v = @file_get_contents($versionFile);
    if (is_string($v) && $v !== '') {
        $version = trim($v);
    }
}
return $version;
