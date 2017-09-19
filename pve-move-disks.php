#!/usr/bin/php
<?php
require_once("pve-utils.php");

$args = PmUtils::options("d:s:t:");
$opts = PmUtils::getOpt($args);

if (!$opts || !isset($args['d'])) {
    echo "Move disk to another storage or format - Usage:\n".
PmUtils::usage()."
-d dest storage (required)
-s source storage
-t target format (raw, qcow2, vmdk)
";
    exit();
}

$dstorage = $args['d'];

$sstorage = "";
if (array_key_exists('s', $args)) {
    $sstorage = $args['s'];
}

$format = "";
if (array_key_exists('t', $args)) {
    $format = strtolower($args['t']);
}

if ($format && !in_array($format, ["raw", "qcow2", "vmdk"])) {
    echo 'Format not "raw", "qcow2" or "vmdk"'."\n";
    exit();
}

$pmutils = new PmUtils($opts);

if (!$pmutils->storageExists($dstorage)) {
    echo "Could not find destination storage $dstorage or the storage does not contain images\n";
    exit();
}

if ($sstorage && !$pmutils->storageExists($sstorage)) {
    echo "Could not find source storage $sstorage or the storage does not contain images\n";
    exit();
}

foreach ($pmutils->getAllDisks($sstorage)['disks'] as $disk) {

  $regex = "";
  if ($format) $regex ="\.$format.*";

  if (preg_match("/^$dstorage.*$regex$/", $disk['content']) === 1) continue;

  if (!$opts['dryrun']) $pmutils->delayTasks(1);

  echo "\nMoving {$disk['disk']} - {$disk['content']} to $dstorage $format on {$disk['vmid']} : {$disk['vmname']}\n";

  if ($opts['dryrun']) continue;

  $pmutils->moveDisk($disk, $dstorage, $format);

}

?>
