<?php

require_once 'Proxmox.php';

class PmUtils {

  private $logintime;
  private $storages;
  private $proxmox;
  private $nodes;

  public function __construct($auth) {
    $this->login($auth);

    $this->nodes = $this->proxmox->request("/nodes");
    $storages = $this->proxmox->request("/storage");

    $this->storages = array_filter($storages, function($storage) {
      return (strpos($storage->content, "images") !== false);
    });

  }

  public function getNodes() {
    return $this->nodes;
  }

  public function getStorages() {
    return $this->storages;
  }

  public static function options($opts = "") {
    return getOpt("h:u:p:r:P:n".$opts);
  }

  public static function getOpt($options) {
    if (!isset($options['h']) || !isset($options['u']) || !isset($options['p']))
      return false;

    if (isset($options['P']) && !ctype_digit($options['P']))
      return false;

    return [
      'hostname' => $options['h'],
      'username' => $options['u'],
      'password' => $options['p'],
      'dryrun' => isset($options['n']) ? true : false,
      'realm' => isset($options['r']) ? $options['r'] : "pam",
      'port' => isset($options['P']) ? $options['P'] : 8006
    ];
  }

  public static function usage() {
    return "-h Proxmox hostname (required)
-u Username (required)
-p Password (required)
-r Realm (Default: 'pam')
-P Port (Default: 8006)
-n Dry run";
  }

  private function login($auth) {
    try {
      $this->proxmox = new Proxmox($auth['hostname'], $auth['username'], $auth['password'], $auth['realm'], $auth['port']);
    } catch (Exception $e) {
      //var_dump($e);
      echo $e->getMessage()."\n";
      exit();
    }

  }

  public function getConfig($vm) {
    return $this->proxmox->request("/nodes/{$vm->node}/{$vm->id}/config");
  }

  public function getStatus($node) {
    return $this->proxmox->request("/nodes/$node/status");
  }

  public function storageExists($name) {
    foreach ($this->storages as $storage)
      if ($name === $storage->storage) return true;
    return false;
  }

  public function getNode($name) {
    foreach ($this->nodes as $node)
      if ($node->node === $name) return $node;
    return false;
  }

  public function virtualMachines($running=false) {

    $allVm = $this->proxmox->request("/cluster/resources", "GET", ["type" => "vm"]);

    $result = array();

    foreach ($allVm as $vm) {
      if ($vm->type!=="qemu") continue;
      if ($running && $vm->status!=="running") continue;
      $result[$vm->vmid] = $vm;
    }

    ksort($result);

    return $result;
  }

  public function nodeVirtalMachines($node, $running=false) {
    return array_filter($this->virtualMachines($running), function($vm) use ($node) {
      return ($vm->node === $node);
    });
  }


  public function getAllDisks($storage) {
    $vms = $this->virtualMachines();

    $result = [
      "cdroms" => [],
      "unused" => [],
      "disks" => []
    ];

    foreach ($vms as $vm) {

      $config = $this->getConfig($vm);

      foreach($config as $key => $val) {

        if (!preg_match("/^(efidisk|unused|ide|scsi|virtio|sata)\d{1,2}/", $key)) continue;
        if ($storage && strpos($val, $storage) !== 0) continue;

        $res = [
          "nodename" => $vm->node,
          "nodeid" => $vm->id,
          "vmname" => $vm->name,
          "vmid" => $vm->vmid,
          "vm" => $vm,
          "disk" => $key,
          "content" => $val
        ];

        if (strpos ($val, 'media=cdrom') !== false) {
          $result['cdroms'][] = $res;
          continue;
        }

        if (strpos ($key, "unused") === 0) {
          $result['unused'][] = $res;
          continue;
        }

        $result['disks'][] = $res;
      }

    }
    return $result;


  }

  public function activeTasks() {
    $active = 0;
    $tasks =  $this->proxmox->request("/cluster/tasks");

    foreach ($tasks as $task)
      if (!isset($task->endtime)) $active++;

    return $active;
  }

  public function delayTasks($procs) {
    while ($this->activeTasks() >= $procs) {
      echo ".";
      sleep(3);

      // Proxmox times out after 2h - relogin every 1h
      if (microtime(true) - $this->proxmox->logintime > 3600)
         $this->proxmox->login();
      }
  }

  public function migrateVm($vm, $targetNode) {

    $args = [
      "target" => $targetNode,
      "migration_type" => "insecure",
      "online" => true
    ];

    $this->proxmox->request("/nodes/{$vm->node}/{$vm->id}/migrate", "POST", $args);
  }

  public function moveDisk($disk, $dstorage, $format) {

    $args = [
      "disk" => $disk['disk'],
      "storage" => $dstorage
    ];

    if ($format) $args["format"] = $format;

    $this->proxmox->request("/nodes/{$disk['nodename']}/{$disk['nodeid']}/move_disk", "POST", $args);
  }

  public function deleteDisk($disk) {
    $this->proxmox->request("/nodes/{$disk['nodename']}/{$disk['nodeid']}/config", "PUT", ["delete" => $disk['disk']]);
  }

  public function ejectCdrom($disk) {
    $this->proxmox->request("/nodes/{$disk['nodename']}/{$disk['nodeid']}/config", "PUT", [$disk["disk"] => "none,media=cdrom"]);
  }

  public function setName($vm, $name) {
    $this->proxmox->request("/nodes/{$vm->node}/qemu/{$vm->vmid}/config", "PUT", ["name" => $name]);
  }

  public function disableVm($vm) {
    $this->proxmox->request("/nodes/{$vm->node}/qemu/{$vm->vmid}/config", "PUT", ["args" => "MIGRATED-AND-DISABLED"]);
  }

  public function startVm($vm) {
    $this->proxmox->request("/nodes/{$vm->node}/qemu/{$vm->vmid}/status/start", "POST");
  }



}

?>
