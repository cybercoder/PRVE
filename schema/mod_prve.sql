-- --------------------------------------------------------
-- Host:                         localhost
-- Server version:               5.6.26 - MySQL Community Server (GPL)
-- Server OS:                    Win32
-- HeidiSQL Version:             9.3.0.4984
-- --------------------------------------------------------

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8mb4 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;

-- Dumping structure for table whmcs.mod_prve_ip_addresses
CREATE TABLE IF NOT EXISTS `mod_prve_ip_addresses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `pool_id` int(11) NOT NULL DEFAULT '0',
  `ipaddress` varchar(255) NOT NULL DEFAULT '0',
  `mask` varchar(255) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `ipaddress` (`ipaddress`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- Data exporting was unselected.


-- Dumping structure for table whmcs.mod_prve_ip_pools
CREATE TABLE IF NOT EXISTS `mod_prve_ip_pools` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- Data exporting was unselected.


-- Dumping structure for table whmcs.mod_prve_plans
CREATE TABLE IF NOT EXISTS `mod_prve_plans` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) CHARACTER SET utf8 NOT NULL COMMENT 'Plan name',
  `vmtype` varchar(8) NOT NULL COMMENT 'Type of Guest e.g KVM or Openvz or others in feuture',
  `ostype` varchar(8) DEFAULT NULL COMMENT 'Operating System Type',
  `cpus` tinyint(2) unsigned DEFAULT NULL,
  `cpuemu` varchar(30) CHARACTER SET utf8 DEFAULT NULL COMMENT 'Emulated CPU Type e.g: 486 | athlon | pentium | pentium2 | pentium3 | coreduo | core2duo | kvm32 | kvm64 | qemu32 | qemu64 | phenom | Conroe | Penryn | Nehalem | Westmere | SandyBridge | IvyBridge | Haswell | Broadwell | Opteron_G1 | Opteron_G2 | Opteron_G3 | Opteron_G4 | Opteron_G5 | host',
  `cores` tinyint(2) unsigned DEFAULT NULL COMMENT 'The number of cores per socket',
  `cpulimit` smallint(5) unsigned DEFAULT NULL COMMENT 'Limit of CPU usage. Note if the computer has 2 CPUs, it has total of ''2'' CPU time. Value ''0'' indicates no CPU limit.',
  `cpuunits` smallint(5) unsigned DEFAULT NULL,
  `memory` smallint(5) unsigned NOT NULL,
  `swap` smallint(5) unsigned DEFAULT NULL,
  `disk` smallint(5) unsigned DEFAULT NULL,
  `iopriority` varchar(10) DEFAULT '4',
  `diskformat` varchar(10) DEFAULT NULL,
  `diskcache` varchar(20) DEFAULT NULL,
  `disktype` varchar(20) DEFAULT NULL,
  `netmode` varchar(10) DEFAULT NULL,
  `bridge` varchar(20) NOT NULL DEFAULT 'vmbr',
  `vmbr` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `netmodel` varchar(10) DEFAULT NULL,
  `netrate` varchar(5) DEFAULT NULL,
  `firewall` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `bw` int(6) unsigned DEFAULT NULL,
  `kvm` tinyint(1) unsigned DEFAULT '0',
  `onboot` tinyint(1) unsigned DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COMMENT='Plans for guests';

-- Data exporting was unselected.


-- Dumping structure for table whmcs.mod_prve_vms
CREATE TABLE IF NOT EXISTS `mod_prve_vms` (
  `id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `ipaddress` varchar(255) NOT NULL,
  `subnetmask` varchar(255) NOT NULL,
  `created` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- Data exporting was unselected.
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IF(@OLD_FOREIGN_KEY_CHECKS IS NULL, 1, @OLD_FOREIGN_KEY_CHECKS) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
