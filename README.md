# ProxmoxVE Command Line Interface Utilities

This **PHP 5.4+** library allows you to mass move and delete disks in Proxmox VE.

> If you find any errors, typos or you detect that something is not working as expected please open an [issue](https://github.com/aheahe/pve-cli-utils/issues/new) or email me helms.adam@gmail.com. I'll try to release a fix as soon as possible.

## Installation

You do not need to install any packages on your hypervisor. You can use any machine with the PHP Cli and PHP Curl package.

For Debian 9+

```sh
$ apt install php-cli php-curl
```
## Usage
Currently available commands
* pve-move-disks.php - Move disk to another storage or format
* pve-delete-disks.php - Delete unused disks
* pve-eject-cdroms.php - Eject cdroms
* pve-list-storages - List storages with the disk image option enabled

### Move disks
```sh
$ ./pve-move-disks.php
Move disk to another storage or format - Usage:
-h Proxmox hostname (required)
-u Username (required)
-p Password (required)
-r Realm (Default: 'pam')
-P Port (Default: 8006)
-n Dry run
-d dest storage (required)
-s source storage
-t target format (raw, qcow2, vmdk)
```

## Delete unused disks
```sh
$ ./pve-delete-disks.php
Delete unused disks - Usage:
-h Proxmox hostname (required)
-u Username (required)
-p Password (required)
-r Realm (Default: 'pam')
-P Port (Default: 8006)
-n Dry run
-d delete storage (required)
```
## Eject cdroms
```sh
$ ./pve-eject-cdroms.php
Eject cdroms - Usage:
-h Proxmox hostname (required)
-u Username (required)
-p Password (required)
-r Realm (Default: 'pam')
-P Port (Default: 8006)
-n Dry run
-d source storage (required)
```

## Example Usage

Move all disks from "oldstorage" to "newstorage" - using format raw on "newstorage"
```
$ ./pve-move-disks.php -h 192.168.0.10 -u root -p mypassword -s oldstorage -d newstorage -t raw
Moving virtio1 - oldstorage:100/vm-100-disk-1.qcow2,size=107374182 to newstorage raw on 100 : mymachine
Moving virtio2 - oldstorage:100/vm-100-disk-2.raw,size=214748364 to newstorage raw on 100 : mymachine
```

After all disks are moved, delete the old disks - start with a "dry run" (-n) to show which disk which would be deleted.
```sh
$ ./pve-delete-disks.php -h 192.168.0.10 -u root -p mypassword -d oldstorage -n
Delete disk unused0 - oldstorage:100/vm-100-disk-1.qcow2 on 100 : mymachine
Delete disk unused1 - oldstorage:100/vm-100-disk-2.raw on 100 : mymachine
```

Actually delete the disks
```sh
$ ./pve-delete-disks.php -h 192.168.0.10 -u root -p mypassword -d oldstorage
Delete disk unused0 - oldstorage:100/vm-100-disk-1.qcow2 on 100 : mymachine
Delete disk unused1 - oldstorage:100/vm-100-disk-2.raw on 100 : mymachine
```

Eject the cdrom on the oldstorage
```sh
$ ./pve-eject-cdroms.php -h 192.168.0.10 -u root -p mypassword -d oldstorage
Ejecting ide2 - oldstorage:iso/debian-9.1.0-amd64-netinst.iso,media=cdrom,size=290M on vmid 100 : mymachine
```
# License

This project is released under the GPL V3 License. See the bundled [LICENSE] file for details.

[LICENSE]:./LICENSE
