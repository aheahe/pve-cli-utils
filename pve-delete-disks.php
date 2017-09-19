#!/usr/bin/php
<?php

require_once("pve-utils.php");

$args = PmUtils::options("d:");
$opts = PmUtils::getopt($args);

if (!isset($args['d']) || !$opts) {
  echo "Delete unused disks - Usage:\n".
  PmUtils::usage()."\n-d delete storage (required)\n";
  exit();
}

$deletestorage = $args['d'];

$pmutils = new PmUtils($opts);

if (!$pmutils->storageExists($deletestorage)) {
  echo "Could not find delete storage $deletestorage\n";
  exit();
}

foreach ($pmutils->getAllDisks($deletestorage)['unused'] as $disk) {
  echo "Delete disk {$disk['disk']} - {$disk['content']} on {$disk['vmid']} : {$disk['vmname']}\n";
  if ($opts['dryrun']) continue;

  $pmutils->deleteDisk($disk);
}

?>
