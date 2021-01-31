#!/usr/bin/php
<?php

require_once("pve-utils.php");

$args = PmUtils::options("");
$opts = PmUtils::getOpt($args);

if (!$opts) {
  echo "List Memory:\n".
  PmUtils::usage()."\n";
  exit();
}

$pmutils = new PmUtils($opts);

$nodemem = $vmmem = 0;

function formatBytes($size, $precision = 2) {
  return round($size/1024**3,2);
}

foreach ($pmutils->virtualMachines() as $vm) {
  if ($vm->status !== "running") continue;
  $vmmem+=$vm->maxmem;
}

foreach ($pmutils->getNodes() as $node) {
  $nodemem+=$node->maxmem;
}

echo "Total allocated memory on all running machines: ".formatBytes($vmmem)." GB
Total memory on all nodes: ".formatBytes($nodemem)." GB
Provisioned: ".round(100*$vmmem/$nodemem,2)." %\n";

?>
