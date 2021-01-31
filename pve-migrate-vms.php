#!/usr/bin/php
<?php
require_once("pve-utils.php");

$args = PmUtils::options("s:d:lm:R");
$opts = PmUtils::getOpt($args);

if (!$opts || !isset($args['s'])) {
    echo "Migrate VM's from a node to another node - Usage:\n".
PmUtils::usage()."
-s source node (required)
-d dest node
-l auto select dest node with least amount of used memory
-m stop migrating when reaching XX % of memory on target (default 85%)
-R Only migrate running nodes
";
    exit();
}

$running = array_key_exists('R', $args);
$minmem = array_key_exists('l', $args);

$pmutils = new PmUtils($opts);

$snode = $args['s'];
if (!$pmutils->getNode($snode)) {
    echo "Could not find the source node $snode\n";
    exit();
}

$maxMemory = 85;
if (array_key_exists('m', $args)) {
    $maxMemory = $args['m'];
    if (!ctype_digit($maxMemory) || ctype_digit($maxMemory) && ($maxMemory < 1 || $maxMemory > 100))
      exit ("Wrong memory option - should be between 1 and 100 %\n");
}

if (array_key_exists('d', $args)) {
  $dNodeName = $args['d'];
  $dnodeObj = $pmutils->getNode($dNodeName);

  if (!$dnodeObj) echo "Could not find the destination node $dNodeName\n";

  $dnodeObj->config = $pmutils->getStatus($dnodeObj->node);

}

if (!$minmem && !$dnodeObj) exit("-l OR -d should be specified\n");

if (isset($dnodeObj) && $dnodeObj->node===$snode) {
    echo "Destination and source node can't be the same\n";
    exit();
}

function getLeastNode() {
  global $pmutils;
  global $snode;

  $nodes = $pmutils->getNodes();

  $minMem = 100;

  foreach ($nodes as $node) {
    if ($node->node == $snode) continue;

    $node->config = $pmutils->getStatus($node->node);
    $node->memUsed = 100*$node->config->memory->used/$node->config->memory->total;

    if ($node->memUsed < $minMem) {
      $result = $node;
      $minMem = $node->memUsed;
    }
  }

  return $result;
}

foreach ($pmutils->nodeVirtalMachines($snode) as $vm) {

  if ($running && $vm->status !== "running") continue;

  if (!$opts['dryrun']) $pmutils->delayTasks(1);

  if ($minmem)
    $dnodeObj = getLeastNode();
  else {
    $dnodeObj->config = $pmutils->getStatus($dnodeObj->node);
    $dnodeObj->memUsed = 100*$dnodeObj->config->memory->used/$dnodeObj->config->memory->total;
  }

  $vmConfig = $pmutils->getConfig($vm);

  $rMemUsed = round($dnodeObj->memUsed);

  /* if ($vm->vmid == 107)  {
  // echo "CANCEL 107 - FENRIR";
   continue;
 } */

  if ($vmConfig && $dnodeObj->memUsed < $maxMemory && $vmConfig->memory*1024*1024 < $dnodeObj->config->memory->free) {
    echo "\nMigrating {$vm->vmid} - {$vm->name} to {$dnodeObj->node} - used memory $rMemUsed%\n";
    if ($opts['dryrun']) continue;
    $pmutils->migrateVm($vm, $dnodeObj->node);
  }
  else echo "\nNot enough memory on {$dnodeObj->node} to migrate {$vm->vmid} : {$vm->name} - used memory $rMemUsed%\n";

}

?>
