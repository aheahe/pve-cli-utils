#!/usr/bin/php
<?php
require_once("pve-utils.php");

$args = PmUtils::options();
$opts = PmUtils::getOpt($args);

if (!$opts) {
    echo "List storages - Usage:\n".
PmUtils::usage()."
";
    exit();
}

$pmutils = new PmUtils($opts);

echo "Storages which have enabled images:\n";
foreach ($pmutils->getStorages() as $storage) {
  echo "  ".$storage->storage."\n";
}

?>
