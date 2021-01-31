#!/usr/bin/php
<?php

require_once("pve-utils.php");

$args = PmUtils::options("");
$opts = PmUtils::getOpt($args);

if (!$opts) {
  echo "List CPU:\n".
  PmUtils::usage()."\n";
  exit();
}

$pmutils = new PmUtils($opts);

$allocatedVCpus = array();

$vms = $pmutils->virtualMachines();
usort($vms, function($a, $b){
  if ($a->node != $b->node) return strcmp($a->node, $b->node);
  return $a->vmid - $b->vmid;
});
echo sprintf("%7s  %4s  %4s   %6s   %5s   %s\n", "Prox", "VMID", "vCPU", "Load", "Usage", "VM name");
foreach ($vms as $vm) {
  if ($vm->status !== "running") continue;
  if (!isset($allocatedVCpus[$vm->node])) $allocatedVCpus[$vm->node] = 0;
  echo sprintf("%7s  %4d   %3d   %5.1f%%   %5.1f   %s\n", $vm->node, $vm->vmid, $vm->maxcpu, $vm->cpu * 100, $vm->maxcpu * $vm->cpu, $vm->name);
  $allocatedVCpus[$vm->node] += $vm->maxcpu;
}
echo "\n";

// Top 10 vCPU load
echo "Top 10 vCPU load:\n";
usort($vms, function($b, $a){
  return $a->cpu * 100 - $b->cpu * 100;
});
foreach ($vms as $index => $vm){
  if ($index > 9) break;
  echo sprintf("%7s  %4d   %3d   %5.1f%%   %5.1f   %s\n", $vm->node, $vm->vmid, $vm->maxcpu, $vm->cpu * 100, $vm->maxcpu * $vm->cpu, $vm->name);
}
echo "\n";

// Top 10 vCPU usage
echo "Top 10 vCPU usage:\n";
usort($vms, function($b, $a){
  return ($a->maxcpu * $a->cpu * 100 - $b->maxcpu * $b->cpu * 100);
});
foreach ($vms as $index => $vm){
  if ($index > 9) break;
  echo sprintf("%7s  %4d   %3d   %5.1f%%   %5.1f   %s\n", $vm->node, $vm->vmid, $vm->maxcpu, $vm->cpu * 100, $vm->maxcpu * $vm->cpu, $vm->name);
}
echo "\n";

// Get node/status - Print KSM
$nodes = $pmutils->getNodes();
usort($nodes, function($a, $b){ return strcmp($a->node, $b->node); });
echo "Total allocated vCPUs on all running machines:\n";
foreach ($nodes as $node){
  echo sprintf("%5s: %3d of %3d physical CPUs (%.1f%% load)\n", $node->node, $allocatedVCpus[$node->node], $node->maxcpu, round($node->cpu * 100, 1));
}

?>
