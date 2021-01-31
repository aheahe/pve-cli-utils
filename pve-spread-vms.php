#!/usr/bin/php
<?php
require_once("pve-utils.php");

$args = PmUtils::options("m:");
$opts = PmUtils::getOpt($args);

if (!$opts) {
    echo "Spread VM's on nodes - Usage:\n".
PmUtils::usage()."
-m stop migrating when reaching XX % of memory on target (default 85%)
";
    exit();
}

$pmutils = new PmUtils($opts);

$maxMemory = 85;
if (array_key_exists('m', $args)) {
    $maxMemory = $args['m'];
    if (!ctype_digit($maxMemory) || ctype_digit($maxMemory) && ($maxMemory < 1 || $maxMemory > 100))
      exit ("Wrong memory option - should be between 1 and 100 %\n");
}

function getNodesMem() {
  global $pmutils;
  global $snode;

  $nodes = $pmutils->getNodes();

  foreach ($nodes as $node) {
    $node->config = $pmutils->getStatus($node->node);
    $node->memUsed = 100*$node->config->memory->used/$node->config->memory->total;
  }

  usort($nodes, function($a, $b) {
    return $a->memUsed-$b->memUsed;
  });

  return $nodes;
}

while (true) {
  $nodes = getNodesMem();

  if (!$opts['dryrun']) $pmutils->delayTasks(1);

  if (count($nodes) === 1) exit("Only one node\n");

  $dnodeObj = $nodes[0];
  $snodeObj = end($nodes);

  $vMachines = $pmutils->nodeVirtalMachines($snodeObj->node, true);

  $vm = $vMachines[array_rand($vMachines)];

  $vmConfig = $pmutils->getConfig($vm);

  $rMemUsed = round($dnodeObj->memUsed);

  if ($vmConfig && $dnodeObj->memUsed < $maxMemory && $vmConfig->memory*1024*1024 < $dnodeObj->config->memory->free) {
    echo "\nMigrating {$vm->vmid} - {$vm->name} from {$snodeObj->node} to {$dnodeObj->node} - used memory $rMemUsed%\n";

    if ($opts['dryrun']) continue;
    $pmutils->migrateVm($vm, $dnodeObj->node);
  }
  else echo "\nNot enough memory on {$dnodeObj->node} to migrate {$vm->name} - used memory $rMemUsed%\n";

}

?>
