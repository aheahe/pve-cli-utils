<?php

require_once 'vendor/autoload.php';

class PmUtils {

  private $logintime;
  private $storages;
  private $proxmox;

  public function __construct($auth) {
    $this->login($auth);

    $storages = $this->proxmox->getStorages()['data'];

    $this->storages = array_filter($storages, function($storage) {
      return (strpos($storage['content'], "images") !== false);
    });
  }

  public static function options($opts) {
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

  private function setLoginTime() {
    $this->logintime = microtime(true);
  }

  private function login($auth) {
    try {
      $this->proxmox = new ProxmoxVE\Proxmox([
        'hostname' => $auth['hostname'],
        'username' => $auth['username'],
        'password' => $auth['password'],
        'realm' => $auth['realm'],
        'port' => $auth['port']]
      );
    }
    catch (ProxmoxVE\Exception\AuthenticationException $e) {
      echo "Username, Password or Realm incorrect\n";
      exit();
    }
    catch (Exception $e) {
      echo $e->getMessage()."\n";
      exit();
    }

    $this->setLoginTime();
  }

  private function setCredentials() {
    $this->proxmox->setCredentials($this->proxmox->getCredentials());
    $this->setLoginTime();
  }

  private function getConfig($vm) {
    $config = $this->proxmox->get("/nodes/{$vm['node']}/{$vm['id']}/config")['data'];

    ksort($config, SORT_NATURAL);

    return $config;
  }

  public function storageExists($name) {
    foreach ($this->storages as $storage)
      if ($name === $storage['storage']) return true;
    return false;
  }

  public function virtualMachines() {

    $allVm = $this->proxmox->get("/cluster/resources", array(["type" => "vm"]))['data'];

    $allVm = array_filter($allVm, function($vm) {
      return ($vm['type']==="qemu");
    });

    usort($allVm, function($a, $b) {
      return $a['vmid']-$b['vmid'];
    });

    return $allVm;
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

        if (!preg_match("/^(unused|ide|scsi|virtio|sata)\d{1,2}/", $key)) continue;
        if ($storage && strpos($val, $storage) !== 0) continue;

        $res = [
          "nodename" => $vm['node'],
          "nodeid" => $vm['id'],
          "vmname" => $vm['name'],
          "vmid" => $vm['vmid'],
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
    $tasks =  $this->proxmox->get("/cluster/tasks");

    foreach ($tasks['data'] as $task)
      if (!isset($task['endtime'])) $active++;

    return $active;
  }

  public function delayTasks($procs) {
    while ($this->activeTasks() >= $procs) {
      echo ".";
      sleep(3);

      // Proxmox times out after 2h - relogin every 1h
      if (microtime(true) - $this->logintime > 3600)
        $this->setCredentials();
    }
  }

  public function moveDisk($disk, $dstorage, $format) {

    $args = [
      "disk" => $disk['disk'],
      "storage" => $dstorage
    ];

    if ($format) $args["format"] = $format;

    $this->proxmox->create("/nodes/{$disk['nodename']}/{$disk['nodeid']}/move_disk", $args);
  }

  public function deleteDisk($disk) {
    $this->proxmox->set("/nodes/{$disk['nodename']}/{$disk['nodeid']}/config", array(["delete" => $disk['disk']]));
  }

  public function ejectCdrom($disk) {
    $this->proxmox->set("/nodes/{$disk['nodename']}/{$disk['nodeid']}/config", array([$disk["disk"] => "none,media=cdrom"]));
  }

}

?>
