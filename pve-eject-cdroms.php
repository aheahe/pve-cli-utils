#!/usr/bin/php
<?php

require_once("pve-utils.php");

$args = PmUtils::options("d:");
$opts = PmUtils::getOpt($args);

if (!isset($args['d']) || !$opts) {
  echo "Eject cdroms - Usage:\n".
  PmUtils::usage()."\n-d source storage (required)\n";
  exit();
}

$sourcestorage = $args['d'];

$pmutils = new PmUtils($opts);

if (!$pmutils->storageExists($sourcestorage)) {
  echo "Could not find source storage $sourcestorage\n";
  exit();
}

foreach ($pmutils->getAllDisks($sourcestorage)['cdroms'] as $cdrom) {
  echo "Ejecting {$cdrom['disk']} - {$cdrom['content']} on vmid {$cdrom['vmid']} : {$cdrom['vmname']}\n";
  if ($opts['dryrun']) continue;
  $pmutils->ejectCdrom($cdrom);
}

?>
