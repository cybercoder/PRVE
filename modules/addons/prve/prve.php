<?php
	use Illuminate\Database\Capsule\Manager as Capsule;
	define( 'PRVE_BASEURL', 'addonmodules.php?module=prve' );
	require_once('proxmox.php');
	function prve_config() {
		$configarray = array(
			"name" => "ModuleLand PRVE",
			"description" => "Proxmox VE Addon Module for WHMCS 6.x",
			"version" => "1.0",
			"author" => "ModuleLand.com",
			'language' => 'English'
		);
		return $configarray;
	}
	function prve_activate() {
		
		$sql = file_get_contents('../modules/addons/prve/db.sql');
		if (!$sql) {
			return array('status'=>'error','description'=>'The db.sql file not found.');
		}
		$err=false;
		$i=0;
		$query_array=explode(';',$sql) ;
		$query_count=count($query_array) ;
		foreach ( $query_array as $query) {
			if ($i<$query_count-1)
				if (!Capsule::statement($query.';'))
					$err=true;
			$i++ ;
		}
		if (!$err)
			return array('status'=>'success','description'=>'PRVE installed successfuly.');		

		return array('status'=>'error','description'=>'PRVE did not activated.');
	 
	}

	function prve_deactivate() {
		Capsule::statement('drop table mod_prve_ip_addresses,mod_prve_ip_pools,mod_prve_plans,mod_prve_vms,mod_prve');
		# Return Result
		return array('status'=>'success','description'=>'PRVE successfuly deactivated and all related tables deleted.');
		return array('status'=>'error','description'=>'If an error occurs you can return an error
			   message for display here');
		return array('status'=>'info','description'=>'If you want to give an info message to a user
			   you can return it here');
	 
	}
	
	function prve_output($vars) {
		
		$modulelink = $vars['modulelink'];
		
		// Messages			
		if (isset($_SESSION['prve']['infomsg'])) {
			echo '
				<div class="infobox">
					<strong>
						<span class="title">'.$_SESSION['prve']['infomsg']['title'].'</span>
					</strong><br/>
				'.$_SESSION['prve']['infomsg']['message'].'
				</div>		
			' ;
			unset($_SESSION['prve']) ;
		}
			
		echo '
			<div id="clienttabs">
				<ul class="nav nav-tabs admin-tabs">
					<li class="'.($_GET['tab']=="vmplans" ? "active" : "").'"><a id="tabLink1" data-toggle="tab" role="tab" href="#plans">VM Plans</a></li>
					<li class="'.($_GET['tab']=="ippools" ? "active" : "").'"><a id="tabLink2" data-toggle="tab" role="tab" href="#ippools">IP pools</a></li>
					<li class="'.($_GET['tab']=="license" ? "active" : "").'"><a id="tabLink3" data-toggle="tab" role="tab" href="#license">PRVE License</a></li>
				</ul>
			</div>
			<div class="tab-content admin-tabs">
			' ;

			
			if (isset($_POST['addnewkvmplan']))
			{
				save_kvm_plan() ;
			}

			if (isset($_POST['updatekvmplan']))
			{
				update_kvm_plan() ;
			}
			if (isset($_POST['updateopenvzplan']))
			{
				update_openvz_plan() ;
			}
			
			if (isset($_POST['addnewopenvzplan']))
			{
				save_openvz_plan() ;
			}
			
		echo '
				<div id="plans" class="tab-pane '.($_GET['tab']=="vmplans" ? "active" : "").'">
					<div class="btn-group btn-group-lg" role="group" aria-label="...">
						<a class="btn btn-default" href="'. PRVE_BASEURL .'&amp;tab=vmplans&amp;action=planlist">
							<i class="fa fa-list"></i>&nbsp; Plans List
						</a>
						<a class="btn btn-default" href="'. PRVE_BASEURL .'&amp;tab=vmplans&amp;action=add_kvm_plan">
							<i class="fa fa-plus-square"></i>&nbsp; Add new KVM plan
						</a>
						<a class="btn btn-default" href="'. PRVE_BASEURL .'&amp;tab=vmplans&amp;action=add_openvz_plan">
							<i class="fa fa-plus-square"></i>&nbsp; Add new OpenVZ plan
						</a>						
					</div>				
			';
					if ($_GET['action']=='add_kvm_plan') {
						kvm_plan_add() ;
					}
					
					if ($_GET['action']=='editplan') {
						if ($_GET['vmtype']=='kvm')
							kvm_plan_edit($_GET['id']) ;
						else
							openvz_plan_edit($_GET['id']) ;
					}
					
					if($_GET['action']=='removeplan') {
						remove_plan($_GET['id']) ;
					}
					
					
					if ($_GET['action']=='add_openvz_plan') {
						openvz_plan_add() ;
					}
					
					if ($_GET['action']=='planlist') {
						echo '						

									<table class="datatable" border="0" cellpadding="3" cellspacing="1" width="100%">
										<tbody>
											<tr>				
												<th>
													id
												</th>
												<th>
													title
												</th>
												<th>
													vmtype
												</th>
												<th>
													ostype
												</th>
												<th>
													cpus
												</th>
												<th>
													cores
												</th>
												<th>
													memory
												</th>
												<th>
													swap
												</th>
												<th>
													disk
												</th>
												<th>
													disktype
												</th>
												<th>
													netmode
												</th>
												<th>
													Bridge
												</th>
												<th>
													netmodel
												</th>
												<th>
													netrate
												</th>
												<th>
													bw
												</th>
												<th>
													action
												</th>
											</tr>
							';
										foreach (Capsule::table('mod_prve_plans')->get() as $vm) {
											echo '<tr>';
												echo '<td>'.$vm->id . PHP_EOL .'</td>';
												echo '<td>'.$vm->title . PHP_EOL .'</td>';
												echo '<td>'.$vm->vmtype . PHP_EOL .'</td>';
												echo '<td>'.$vm->ostype . PHP_EOL .'</td>';
												echo '<td>'.$vm->cpus . PHP_EOL .'</td>';
												echo '<td>'.$vm->cores . PHP_EOL .'</td>';
												echo '<td>'.$vm->memory . PHP_EOL .'</td>';
												echo '<td>'.$vm->swap . PHP_EOL .'</td>';
												echo '<td>'.$vm->disk . PHP_EOL .'</td>';
												echo '<td>'.$vm->disktype . PHP_EOL .'</td>';
												echo '<td>'.$vm->netmode . PHP_EOL .'</td>';
												echo '<td>'.$vm->bridge.$vm->vmbr . PHP_EOL .'</td>';
												echo '<td>'.$vm->netmodel . PHP_EOL .'</td>';
												echo '<td>'.$vm->netrate . PHP_EOL .'</td>';
												echo '<td>'.$vm->bw . PHP_EOL .'</td>';
												echo '<td>
														<a href="'.PRVE_BASEURL.'&amp;tab=vmplans&amp;action=editplan&amp;id='.$vm->id.'&amp;vmtype='.$vm->vmtype.'"><img height="16" width="16" border="0" alt="Edit" src="images/edit.gif"></a>
														<a href="'.PRVE_BASEURL.'&amp;tab=vmplans&amp;action=removeplan&amp;id='.$vm->id.'" onclick="return confirm(\'Plan will be deleted, continue?\')"><img height="16" width="16" border="0" alt="Edit" src="images/delete.gif"></a>
													  </td>' ;
											echo '</tr>' ;
										}
							echo '			
							';
							echo '
										</tbody>
									</table>
								 ';
					}
			echo '
				</div>
			';
			
			echo '
				<div id="ippools" class="tab-pane '.($_GET['tab']=="ippools" ? "active" : "").'" >
					<div class="btn-group">
						<a class="btn btn-default" href="'. PRVE_BASEURL .'&amp;tab=ippools&amp;action=list_ip_pools">
							<i class="fa fa-list"></i>&nbsp; List IP Pools
						</a>
						<a class="btn btn-default" href="'. PRVE_BASEURL .'&amp;tab=ippools&amp;action=newip">
							<i class="fa fa-plus"></i>&nbsp; Add IP to Pool
						</a>
					</div>
			';
				if ($_GET['action']=='list_ip_pools') {
					list_ip_pools() ;
				}
				if ($_GET['action']=='new_ip_pool') {
					add_ip_pool() ;
				}
				if ($_GET['action']=='newip') {
					add_ip_2_pool() ;
				}
				if (isset($_POST['newIPpool'])) {
					save_ip_pool() ;
				}
				
				if ($_GET['action']=='removeippool') {
					removeIpPool($_GET['id']) ;
				}
				if ($_GET['action']=='list_ips') {
					list_ips();
				}
				
				if ($_GET['action']=='removeip') {
					removeip($_GET['id'],$_GET['pool_id']);
				}
			echo'
				</div>
			';
			// License Tab
			echo '<div id="license" class="tab-pane '.($_GET['tab']=="license" ? "active" : "").'" >' ;
				$license=Capsule::table('mod_prve')->get()[0] ;
				$results=prve_check_license($license->license,$license->localkey);
				switch ($results['status']) {
					case "Active":
						// get new local key and save it somewhere
						$localkeydata = $results['localkey'];
						Capsule::table('mod_prve')->where('id',1)->update(
							[
								'localkey' => $localkeydata
							]
						);
						echo ('<b style="color:green">Valid License key. The key is:&nbsp;'.$license->license.'</b>');
						break;
					case "Invalid":
						echo ('<b style="color:red">License key is Invalid</b>');
						enter_license_key() ;
						break;
					case "Expired":
						echo ('<b style="color:red">License key is Expired, Renew or new license</b>');
						enter_license_key() ;
						break;
					case "Suspended":
						echo ('<b style="color:red">License is Suspended, Contact module customer support</b>');
						break;
					default:
						die("Invalid Response");
						break;
				}
			echo '
				</div>
			';
			
	echo '</div>'; // end of tab-content
	}

	function enter_license_key() {
		if (isset($_POST['saveLicenseKey'])) {
			Capsule::table('mod_prve')->where('id',1)->update(
				[
					'license' => $_POST['licensekey']
				]
			);
			$_SESSION['prve']['infomsg']['title']='PRVE License key updated.' ;
			$_SESSION['prve']['infomsg']['message']='PRVE license key updated successfuly.' ;
			header("Location: ".PRVE_BASEURL."&tab=license");			
		}
		
		echo '<form method="post">
			  <table class="form" border="0" cellpadding="3" cellspacing="1" width="100%">
				<tr>
					<td class="fieldlabel">License Key</td>
					<td class="fieldarea">
						<input type="text" size="100" name="licensekey" id="licensekey" required>
					</td>					
				</tr>
			  </table>
				<div class="btn-container">
					<input type="submit" class="btn btn-primary" value="Save License" name="saveLicenseKey" id="saveLicenseKey">
					<input type="reset" class="btn btn-default" value="Cancel Changes">
				</div>
			  </form>
		';
	}

	
	/* adding a KVM plan */
	function kvm_plan_add() {
		echo '
			<form method="post">
			<table class="form" border="0" cellpadding="3" cellspacing="1" width="100%">
				<tr>
					<td class="fieldlabel">Plan Title</td>
					<td class="fieldarea">
						<input type="text" size="35" name="title" id="title" required>
					</td>
				</tr>
				<tr>
					<td class="fieldlabel">OS Type</td>
					<td class="fieldarea">
						<select class="form-control select-inline" name="ostype">
							<option value="w2k">Windows 2000</option>
							<option value="wxp">Windows XP</option>
							<option value="w2k3">Windows server 2003</option>
							<option value="w2k8">Windows server 2008</option>
							<option value="wvista">Windows Vista</option>
							<option value="win7">Windows 7</option>
							<option value="win8">Windows 8</option>
							<option value="126">Linux 4.X/3.X/2.6 Kernel</option>
							<option value="124">Linux 2.4 Kernel</option>
							<option value="solaris">Solaris Kernel</option>
							<option value="other">Other</option>
						</select>
						Virtual Machine Guest type (OpenVZ or KVM).
					</td>
				</tr>
				<tr>
					<td class="fieldlabel">CPU emulation</td>
					<td class="fieldarea">
						<select class="form-control select-inline" name="cpuemu">
							<option value="486">486</option>
							<option value="athlon">Athlon</option>
							<option value="pentium">Pentium</option>
							<option value="pentium2">Pentium II</option>
							<option value="pentium3">Pentium III</option>
							<option value="coreduo">Core duo</option>
							<option value="core2duo">Core 2 duo</option>
							<option value="kvm32">kvm32</option>
							<option selected="" value="kvm64">kvm64</option>
							<option value="qemu32">qemu32</option>
							<option value="qemu64">qemu64</option>
							<option value="phenom">Phenom</option>
							<option value="Conroe">Conroe</option>
							<option value="Penryn">Penryn</option>
							<option value="Nehalem">Nehalem</option>
							<option value="Westmere">Westmere</option>
							<option value="SandyBridge">SandyBridge</option>
							<option value="IvyBridge">IvyBridge</option>
							<option value="Haswell">Haswell</option>
							<option value="Broadwell">Broadwell</option>
							<option value="Opteron_G1">Opteron G1</option>
							<option value="Opteron_G2">Opteron G2</option>
							<option value="Opteron_G3">Opteron G3</option>
							<option value="Opteron_G4">Opteron G4</option>
							<option value="host">Host</option>
						</select>
						CPU emulation type. Default is KVM64
					</td>
				</tr>
				
				<tr>
					<td class="fieldlabel">CPU</td>
					<td class="fieldarea">
						<input type="text" size="1" name="cpus" id="cpus" value="1" required>
						The number of CPU sockets. 1 - 4.
					</td>
				</tr>
				<tr>
					<td class="fieldlabel">Cores</td>
					<td class="fieldarea">
						<input type="text" size="1" name="cores" id="cores" value="1" required>
						The number of cpu cores per socket. 1 - 32.
					</td>
				</tr>
				<tr>
					<td class="fieldlabel">CPU Limit</td>
					<td class="fieldarea">
						<input type="text" size="8" name="cpulimit" id="cpulimit" value="0" required>
						Limit of CPU usage. Note if the computer has 2 CPUs, it has total of "2" CPU time. Value "0" indicates no CPU limit.
					</td>
				</tr>
				<tr>
					<td class="fieldlabel">CPU Units</td>
					<td class="fieldarea">
						<input type="text" size="8" name="cpuunits" id="cpuunits" value="1024" required>
						Number is relative to weights of all the other running VMs. 8 - 500000 recommended 1024. NOTE: You can disable fair-scheduler configuration by setting this to 0.
					</td>
				</tr>
				<tr>
					<td class="fieldlabel">RAM</td>
					<td class="fieldarea">
						<input type="text" size="8" name="memory" id="memory" required>
						RAM space in MegaByte e.g 1024 = 1GB
					</td>
				</tr>
				<tr>
					<td class="fieldlabel">Disk Space</td>
					<td class="fieldarea">
						<input type="text" size="8" name="disk" id="disk" required>
						Disk space in Gigabayte e.g 1024= 1 Terra Byte
					</td>
				</tr>
				<tr>
					<td class="fieldlabel">IO Priority</td>
					<td class="fieldarea">
						<input type="text" size="2" name="iopriority" id="iopriority" value="4">
					</td>
				</tr>
				<tr>
					<td class="fieldlabel">Disk Format</td>
					<td class="fieldarea">
						<select class="form-control select-inline" name="diskformat">
							<option value="raw">Raw disk image</option>
							<option selected="" value="qcow2">QEMU image format</option>
							<option value="vmdk">VMware image format</option>
						</select>
						Recommended "QEMU image format" (to can make Snapshots)
					</td>
				</tr>
				<tr>
					<td class="fieldlabel">Disk cache</td>
					<td class="fieldarea">
						<select class="form-control select-inline" name="diskcache">
							<option selected="" value="">No Cache (Default)</option>
							<option value="directsync">Direct Sync</option>
							<option value="writethrough">Write Through</option>
							<option value="writeback">Write Back</option>
							<option value="unsafe">Write Back (Unsafe)</option>
							<option value="none">No Cache</option>
						</select>
					</td>
				</tr>
				<tr>
					<td class="fieldlabel">Disk Type</td>
					<td class="fieldarea">
						<select class="form-control select-inline" name="disktype">
							<option selected="" value="ide">IDE</option>
							<option value="sata">SATA</option>
							<option value="scsi">SCSI</option>
							<option value="virtio">VIRTIO</option>
						</select>
					</td>
				</tr>
				<tr>
					<td class="fieldlabel">Network Mode</td>
					<td class="fieldarea">
						<select class="form-control select-inline" name="netmode">
							<option value="bridge">Bridge</option>
							<option value="nat">NAT</option>
							<option value="none">No network</option>
						</select>
					</td>
				</tr>
				<tr>
					<td class="fieldlabel">Bridge Interface</td>
					<td class="fieldarea">
						<input type="text" size="2" name="bridge" id="bridge" value="vmbr">
						Bridge interface name. Proxmox default bridge name is "vmbr".
					</td>
				</tr>
				<tr>
					<td class="fieldlabel">Bridge interface number</td>
					<td class="fieldarea">
						<input type="text" size="2" name="vmbr" id="vmbr" value="0">
						Bridge interface number. Proxmox default bridge (vmbr) number is 0, It means "vmbr0".
					</td>
				</tr>
				<tr>
					<td class="fieldlabel">NIC Model</td>
					<td class="fieldarea">
						<select class="form-control select-inline" name="netmodel">
							<option selected="" value="e1000">Intel E1000</option>
							<option value="virtio">VirtIO (Paravirtualized)</option>
							<option value="rtl8139">Realtek RTL8139</option>
							<option value="vmxnet3">VMware vmxnet3</option>
						</select>
					</td>
				</tr>
				<tr>
					<td class="fieldlabel">Network Rate</td>
					<td class="fieldarea">
						<input type="text" size="5" name="netrate" id="netrate">
						Network Rate Limit in Megabit, Blank means unlimit.
					</td>
				</tr>
				<tr>
					<td class="fieldlabel">Monthly Bandwidth</td>
					<td class="fieldarea">
						<input type="text" size="5" name="bw" id="bw">
						Monthly Bandwidth Limit in GigaByte, Blank means unlimit.
					</td>
				</tr>
				<tr>
					<td class="fieldlabel">
						KVM HV
					</td>
					<td class="fieldarea">
						<label class="checkbox-inline">
							<input type="checkbox" name="kvm" value="1" checked> Enable KVM hardware virtualization. (Recommended)
						</label>
					</td>
				</tr>
				<tr>
					<td class="fieldlabel">
						On boot
					</td>
					<td class="fieldarea">
						<label class="checkbox-inline">
							<input type="checkbox" name="onboot" value="1" checked> Specifies whether a VM will be started during system bootup. ((Recommended))
						</label>
					</td>
				</tr>
			</table>
			
			<div class="btn-container">
				<input type="submit" class="btn btn-primary" value="Save Changes" name="addnewkvmplan" id="addnewkvmplan">
				<input type="reset" class="btn btn-default" value="Cancel Changes">
			</div>
			</form>			
			';
	}

	/* editing a KVM plan */
	function kvm_plan_edit($id) {
		$plan= Capsule::table('mod_prve_plans')->where('id', '=', $id)->get()[0];
		if (empty($plan)) {
			echo 'Plan Not found' ;
			return false ;
		}
		echo '<pre>' ;
		//print_r($plan) ;
		echo '</pre>' ;
		echo '
			<form method="post">
			<table class="form" border="0" cellpadding="3" cellspacing="1" width="100%">
				<tr>
					<td class="fieldlabel">Plan Title</td>
					<td class="fieldarea">
						<input type="text" size="35" name="title" id="title" required value="'.$plan->title.'">
					</td>
				</tr>
				<tr>
					<td class="fieldlabel">OS Type</td>
					<td class="fieldarea">
						<select class="form-control select-inline" name="ostype">
							<option value="w2k" '. ($plan->ostype=="w2k" ? "selected" : "").'>Windows 2000</option>
							<option value="wxp" '. ($plan->ostype=="wxp" ? "selected" : "").'>Windows XP</option>
							<option value="w2k3" '. ($plan->ostype=="w2k3" ? "selected" : "").'>Windows server 2003</option>
							<option value="w2k8" '. ($plan->ostype=="w2k8" ? "selected" : "").'>Windows server 2008</option>
							<option value="wvista" '. ($plan->ostype=="wvista" ? "selected" : "").'>Windows Vista</option>
							<option value="win7" '. ($plan->ostype=="win7" ? "selected" : "").'>Windows 7</option>
							<option value="win8" '. ($plan->ostype=="win8" ? "selected" : "").'>Windows 8</option>
							<option value="126" '. ($plan->ostype=="126" ? "selected" : "").'>Linux 4.X/3.X/2.6 Kernel</option>
							<option value="124" '. ($plan->ostype=="124" ? "selected" : "").'>Linux 2.4 Kernel</option>
							<option value="solaris" '. ($plan->ostype=="solaris" ? "selected" : "").'>Solaris Kernel</option>
							<option value="other" '. ($plan->ostype=="w2k" ? "other" : "").'>Other</option>
						</select>
						Virtual Machine Guest type (OpenVZ or KVM).
					</td>
				</tr>
				<tr>
					<td class="fieldlabel">CPU emulation</td>
					<td class="fieldarea">
						<select class="form-control select-inline" name="cpuemu">
							<option value="486" '. ($plan->cpuemu=="486" ? "selected" : "").'>486</option>
							<option value="athlon" '. ($plan->cpuemu=="athlon" ? "selected" : "").'>Athlon</option>
							<option value="pentium" '. ($plan->cpuemu=="pentium" ? "selected" : "").'>Pentium</option>
							<option value="pentium2" '. ($plan->cpuemu=="pentium2" ? "selected" : "").'>Pentium II</option>
							<option value="pentium3" '. ($plan->cpuemu=="pentium3" ? "selected" : "").'>Pentium III</option>
							<option value="coreduo" '. ($plan->cpuemu=="coreduo" ? "selected" : "").'>Core duo</option>
							<option value="core2duo" '. ($plan->cpuemu=="core2duo" ? "selected" : "").'>Core 2 duo</option>
							<option value="kvm32" '. ($plan->cpuemu=="kvm32" ? "selected" : "").'>kvm32</option>
							<option value="kvm64" '. ($plan->cpuemu=="kvm64" ? "selected" : "").'>kvm64</option>
							<option value="qemu32" '. ($plan->cpuemu=="qemu32" ? "selected" : "").'>qemu32</option>
							<option value="qemu64" '. ($plan->cpuemu=="qemu64" ? "selected" : "").'>qemu64</option>
							<option value="phenom" '. ($plan->cpuemu=="phenom" ? "selected" : "").'>Phenom</option>
							<option value="Conroe" '. ($plan->cpuemu=="Conroe" ? "selected" : "").'>Conroe</option>
							<option value="Penryn" '. ($plan->cpuemu=="Penryn" ? "selected" : "").'>Penryn</option>
							<option value="Nehalem" '. ($plan->cpuemu=="Nehalem" ? "selected" : "").'>Nehalem</option>
							<option value="Westmere" '. ($plan->cpuemu=="Westmere" ? "selected" : "").'>Westmere</option>
							<option value="SandyBridge" '. ($plan->cpuemu=="SandyBridge" ? "selected" : "").'>SandyBridge</option>
							<option value="IvyBridge" '. ($plan->cpuemu=="IvyBridge" ? "selected" : "").'>IvyBridge</option>
							<option value="Haswell" '. ($plan->cpuemu=="Haswell" ? "selected" : "").'>Haswell</option>
							<option value="Broadwell" '. ($plan->cpuemu=="Broadwell" ? "selected" : "").'>Broadwell</option>
							<option value="Opteron_G1" '. ($plan->cpuemu=="Opteron_G1" ? "selected" : "").'>Opteron G1</option>
							<option value="Opteron_G2" '. ($plan->cpuemu=="Opteron_G2" ? "selected" : "").'>Opteron G2</option>
							<option value="Opteron_G3" '. ($plan->cpuemu=="Opteron_G3" ? "selected" : "").'>Opteron G3</option>
							<option value="Opteron_G4" '. ($plan->cpuemu=="Opteron_G4" ? "selected" : "").'>Opteron G4</option>
							<option value="host" '. ($plan->cpuemu=="host" ? "selected" : "").'>Host</option>
						</select>
						CPU emulation type. Default is KVM64
					</td>
				</tr>
				
				<tr>
					<td class="fieldlabel">CPU</td>
					<td class="fieldarea">
						<input type="text" size="1" name="cpus" id="cpus" value="'.$plan->cpus.'" required>
						The number of CPU sockets. 1 - 4.
					</td>
				</tr>
				<tr>
					<td class="fieldlabel">Cores</td>
					<td class="fieldarea">
						<input type="text" size="1" name="cores" id="cores" value="'.$plan->cores.'" required>
						The number of cpu cores per socket. 1 - 32.
					</td>
				</tr>
				<tr>
					<td class="fieldlabel">CPU Limit</td>
					<td class="fieldarea">
						<input type="text" size="8" name="cpulimit" id="cpulimit" value="'.$plan->cpulimit.'" required>
						Limit of CPU usage. Note if the computer has 2 CPUs, it has total of "2" CPU time. Value "0" indicates no CPU limit.
					</td>
				</tr>
				<tr>
					<td class="fieldlabel">CPU Units</td>
					<td class="fieldarea">
						<input type="text" size="8" name="cpuunits" id="cpuunits" value="'.$plan->cpuunits.'" required>
						Number is relative to weights of all the other running VMs. 8 - 500000 recommended 1024. NOTE: You can disable fair-scheduler configuration by setting this to 0.
					</td>
				</tr>
				<tr>
					<td class="fieldlabel">RAM</td>
					<td class="fieldarea">
						<input type="text" size="8" name="memory" id="memory" required value="'.$plan->memory.'">
						RAM space in MegaByte e.g 1024 = 1GB
					</td>
				</tr>
				<tr>
					<td class="fieldlabel">Disk Space</td>
					<td class="fieldarea">
						<input type="text" size="8" name="disk" id="disk" required value="'.$plan->disk.'">
						Disk space in Gigabayte e.g 1024= 1 Terra Byte
					</td>
				</tr>
				<tr>
					<td class="fieldlabel">IO Priority</td>
					<td class="fieldarea">
						<input type="text" size="2" name="iopriority" id="iopriority" value="'.$plan->iopriority.'">
					</td>
				</tr>
				<tr>
					<td class="fieldlabel">Disk Format</td>
					<td class="fieldarea">
						<select class="form-control select-inline" name="diskformat">
							<option value="raw" '. ($plan->diskformat=="raw" ? "selected" : "").'>Raw disk image</option>
							<option value="qcow2" '. ($plan->diskformat=="qcow2" ? "selected" : "").'>QEMU image format</option>
							<option value="vmdk" '. ($plan->diskformat=="vmdk" ? "selected" : "").'>VMware image format</option>
						</select>
						Recommended "QEMU image format" (to can make Snapshots)
					</td>
				</tr>
				<tr>
					<td class="fieldlabel">Disk cache</td>
					<td class="fieldarea">
						<select class="form-control select-inline" name="diskcache">
							<option value="" '. ($plan->diskcache=="" ? "selected" : "").'>No Cache (Default)</option>
							<option value="directsync" '. ($plan->diskcache=="directsync" ? "selected" : "").'>Direct Sync</option>
							<option value="writethrough" '. ($plan->diskcache=="writethrough" ? "selected" : "").'>Write Through</option>
							<option value="writeback" '. ($plan->diskcache=="writeback" ? "selected" : "").'>Write Back</option>
							<option value="unsafe" '. ($plan->diskcache=="unsafe" ? "selected" : "").'>Write Back (Unsafe)</option>
							<option value="none" '. ($plan->diskcache=="none" ? "selected" : "").'>No Cache</option>
						</select>
					</td>
				</tr>
				<tr>
					<td class="fieldlabel">Disk Type</td>
					<td class="fieldarea">
						<select class="form-control select-inline" name="disktype">
							<option value="ide" '. ($plan->disktype=="ide" ? "selected" : "").'>IDE</option>
							<option value="sata" '. ($plan->disktype=="sata" ? "selected" : "").'>SATA</option>
							<option value="scsi" '. ($plan->disktype=="scsi" ? "selected" : "").'>SCSI</option>
							<option value="virtio" '. ($plan->disktype=="virtio" ? "selected" : "").'>VIRTIO</option>
						</select>
					</td>
				</tr>
				<tr>
					<td class="fieldlabel">Network Mode</td>
					<td class="fieldarea">
						<select class="form-control select-inline" name="netmode">
							<option value="bridge" '. ($plan->netmode=="bridge" ? "selected" : "").'>Bridge</option>
							<option value="nat" '. ($plan->netmode=="nat" ? "selected" : "").'>NAT</option>
							<option value="none" '. ($plan->netmode=="none" ? "selected" : "").'>No network</option>
						</select>
					</td>
				</tr>
				<tr>
					<td class="fieldlabel">Bridge Interface</td>
					<td class="fieldarea">
						<input type="text" size="2" name="bridge" id="bridge" value="'.$plan->bridge.'">
						Bridge interface name. Proxmox default bridge name is "vmbr".
					</td>
				</tr>
				<tr>
					<td class="fieldlabel">Bridge interface number</td>
					<td class="fieldarea">
						<input type="text" size="2" name="vmbr" id="vmbr" value="'.$plan->vmbr.'">
						Bridge interface number. Proxmox default bridge (vmbr) number is 0, It means "vmbr0".
					</td>
				</tr>
				<tr>
					<td class="fieldlabel">NIC Model</td>
					<td class="fieldarea">
						<select class="form-control select-inline" name="netmodel">
							<option value="e1000" '. ($plan->netmodel=="e1000" ? "selected" : "").'>Intel E1000</option>
							<option value="virtio" '. ($plan->netmodel=="virtio" ? "selected" : "").'>VirtIO (Paravirtualized)</option>
							<option value="rtl8139" '. ($plan->netmodel=="rtl8139" ? "selected" : "").'>Realtek RTL8139</option>
							<option value="vmxnet3" '. ($plan->netmodel=="vmxnet3" ? "selected" : "").'>VMware vmxnet3</option>
						</select>
					</td>
				</tr>
				<tr>
					<td class="fieldlabel">Network Rate</td>
					<td class="fieldarea">
						<input type="text" size="5" name="netrate" id="netrate" value="'.$plan->netrate.'">
						Network Rate Limit in Megabit, Blank means unlimit.
					</td>
				</tr>
				<tr>
					<td class="fieldlabel">Monthly Bandwidth</td>
					<td class="fieldarea">
						<input type="text" size="5" name="bw" id="bw" value="'.$plan->bw.'">
						Monthly Bandwidth Limit in GigaByte, Blank means unlimit.
					</td>
				</tr>
				<tr>
					<td class="fieldlabel">
						KVM HV
					</td>
					<td class="fieldarea">
						<label class="checkbox-inline">
							<input type="checkbox" name="kvm" value="1" '. ($plan->kvm=="1" ? "checked" : "").'> Enable KVM hardware virtualization. (Recommended)
						</label>
					</td>
				</tr>
				<tr>
					<td class="fieldlabel">
						On boot
					</td>
					<td class="fieldarea">
						<label class="checkbox-inline">
							<input type="checkbox" name="onboot" value="1" '. ($plan->onboot=="1" ? "checked" : "").'> Specifies whether a VM will be started during system bootup. ((Recommended))
						</label>
					</td>
				</tr>
			</table>
			
			<div class="btn-container">
				<input type="submit" class="btn btn-primary" value="Save Changes" name="updatekvmplan" id="saveeditedkvmplan">
				<input type="reset" class="btn btn-default" value="Cancel Changes">
			</div>
			</form>			
			';
	}


	/* adding an OpenVZ plan */
	function openvz_plan_add() {
		echo '
			<form method="post">
			<table class="form" border="0" cellpadding="3" cellspacing="1" width="100%">
				<tr>
					<td class="fieldlabel">Plan Title</td>
					<td class="fieldarea">
						<input type="text" size="35" name="title" id="title" required>
					</td>
				</tr>
				<tr>
					<td class="fieldlabel">CPU Limit</td>
					<td class="fieldarea">
						<input type="text" size="8" name="cpulimit" id="cpulimit" value="1" required>
						Limit of CPU usage. Default is 1. Note: if the computer has 2 CPUs, it has total of "2" CPU time. Value "0" indicates no CPU limit.
					</td>
				</tr>
				<tr>
					<td class="fieldlabel">CPU Units</td>
					<td class="fieldarea">
						<input type="text" size="8" name="cpuunits" id="cpuunits" value="1024" required>
						Number is relative to weights of all the other running VMs. 8 - 500000 recommended 1024.
					</td>
				</tr>
				<tr>
					<td class="fieldlabel">RAM</td>
					<td class="fieldarea">
						<input type="text" size="8" name="memory" id="memory" required>
						RAM space in MegaByte e.g 1024 = 1GB
					</td>
				</tr>
				<tr>
					<td class="fieldlabel">Swap</td>
					<td class="fieldarea">
						<input type="text" size="8" name="swap" id="swap">
						Swap space in MegaByte e.g 1024 = 1GB
					</td>
				</tr>
				<tr>
					<td class="fieldlabel">Disk Space</td>
					<td class="fieldarea">
						<input type="text" size="8" name="disk" id="disk" required>
						Disk space in Gigabayte e.g 1024= 1 Terra Byte
					</td>
				</tr>
				<tr>
					<td class="fieldlabel">Bridge Interface</td>
					<td class="fieldarea">
						<input type="text" size="2" name="bridge" id="bridge" value="vmbr">
						Bridge interface name. Proxmox default bridge name is "vmbr".
					</td>
				</tr>
				<tr>
					<td class="fieldlabel">Bridge interface number</td>
					<td class="fieldarea">
						<input type="text" size="2" name="vmbr" id="vmbr" value="0">
						Bridge interface number. Proxmox default bridge (vmbr) number is 0, It means "vmbr0".
					</td>
				</tr>
				<tr>
					<td class="fieldlabel">Network Rate</td>
					<td class="fieldarea">
						<input type="text" size="5" name="netrate" id="netrate">
						Network Rate Limit in Megabit, Blank means unlimit.
					</td>
				</tr>
				<tr>
					<td class="fieldlabel">Monthly Bandwidth</td>
					<td class="fieldarea">
						<input type="text" size="5" name="bw" id="bw">
						Monthly Bandwidth Limit in GigaByte, Blank means unlimit.
					</td>
				</tr>
				<tr>
					<td class="fieldlabel">
						On boot
					</td>
					<td class="fieldarea">
						<label class="checkbox-inline">
							<input type="checkbox" name="onboot" value="1" checked> Specifies whether a VM will be started during system bootup. ((Recommended))
						</label>
					</td>
				</tr>
			</table>
			
			<div class="btn-container">
				<input type="submit" class="btn btn-primary" value="Save Changes" name="addnewopenvzplan" id="addnewopenvzplan">
				<input type="reset" class="btn btn-default" value="Cancel Changes">
			</div>
			</form>			
			';
	}
	
	/* editing an OpenVZ plan */
	function openvz_plan_edit($id) {
		$plan= Capsule::table('mod_prve_plans')->where('id', '=', $id)->get()[0];
		if (empty($plan)) {
			echo 'Plan Not found' ;
			return false ;
		}
		echo '<pre>' ;
		//print_r($plan) ;
		echo '</pre>' ;
		
		echo '
			<form method="post">
			<table class="form" border="0" cellpadding="3" cellspacing="1" width="100%">
				<tr>
					<td class="fieldlabel">Plan Title</td>
					<td class="fieldarea">
						<input type="text" size="35" name="title" id="title" required value="'.$plan->title.'">
					</td>
				</tr>
				<tr>
					<td class="fieldlabel">CPU Limit</td>
					<td class="fieldarea">
						<input type="text" size="8" name="cpulimit" id="cpulimit" value="'.$plan->cpulimit.'" required>
						Limit of CPU usage. Default is 1. Note: if the computer has 2 CPUs, it has total of "2" CPU time. Value "0" indicates no CPU limit.
					</td>
				</tr>
				<tr>
					<td class="fieldlabel">CPU Units</td>
					<td class="fieldarea">
						<input type="text" size="8" name="cpuunits" id="cpuunits" value="'.$plan->cpuunits.'" required>
						Number is relative to weights of all the other running VMs. 8 - 500000 recommended 1024.
					</td>
				</tr>
				<tr>
					<td class="fieldlabel">RAM</td>
					<td class="fieldarea">
						<input type="text" size="8" name="memory" id="memory" required value="'.$plan->memory.'">
						RAM space in MegaByte e.g 1024 = 1GB
					</td>
				</tr>
				<tr>
					<td class="fieldlabel">Swap</td>
					<td class="fieldarea">
						<input type="text" size="8" name="swap" id="swap" value="'.$plan->swap.'">
						Swap space in MegaByte e.g 1024 = 1GB
					</td>
				</tr>
				<tr>
					<td class="fieldlabel">Disk Space</td>
					<td class="fieldarea">
						<input type="text" size="8" name="disk" id="disk" value="'.$plan->disk.'" required>
						Disk space in Gigabayte e.g 1024= 1 Terra Byte
					</td>
				</tr>
				<tr>
					<td class="fieldlabel">Bridge Interface</td>
					<td class="fieldarea">
						<input type="text" size="2" name="bridge" id="bridge" value="'.$plan->bridge.'">
						Bridge interface name. Proxmox default bridge name is "vmbr".
					</td>
				</tr>
				<tr>
					<td class="fieldlabel">Bridge interface number</td>
					<td class="fieldarea">
						<input type="text" size="2" name="vmbr" id="vmbr" value="'.$plan->vmbr.'">
						Bridge interface number. Proxmox default bridge (vmbr) number is 0, It means "vmbr0".
					</td>
				</tr>
				<tr>
					<td class="fieldlabel">Network Rate</td>
					<td class="fieldarea">
						<input type="text" size="5" name="netrate" id="netrate" value="'.$plan->netrate.'">
						Network Rate Limit in Megabit, Blank means unlimit.
					</td>
				</tr>
				<tr>
					<td class="fieldlabel">Monthly Bandwidth</td>
					<td class="fieldarea">
						<input type="text" size="5" name="bw" id="bw" value="'.$plan->bw.'">
						Monthly Bandwidth Limit in GigaByte, Blank means unlimit.
					</td>
				</tr>
				<tr>
					<td class="fieldlabel">
						On boot
					</td>
					<td class="fieldarea">
						<label class="checkbox-inline">
							<input type="checkbox" value="1" name="onboot" '. ($plan->onboot=="1" ? "checked" : "").'> Specifies whether a VM will be started during system bootup. ((Recommended))
						</label>
					</td>
				</tr>
			</table>
			
			<div class="btn-container">
				<input type="submit" class="btn btn-primary" value="Save Changes" name="updateopenvzplan" id="updateopenvzplan">
				<input type="reset" class="btn btn-default" value="Cancel Changes">
			</div>
			</form>			
			';
	}
	
	function save_kvm_plan() {
		try {
			Capsule::connection()->transaction(
				function ($connectionManager)
				{
					/** @var \Illuminate\Database\Connection $connectionManager */
					$connectionManager->table('mod_prve_plans')->insert(
						[
							'title' => $_POST['title'],
							'vmtype' => 'kvm',
							'ostype' => $_POST['ostype'],
							'cpus' => $_POST['cpus'],
							'cpuemu' => $_POST['cpuemu'],
							'cores' => $_POST['cores'],
							'cpulimit' => $_POST['cpulimit'],
							'cpuunits' => $_POST['cpuunits'],
							'memory' => $_POST['memory'],
							'swap' => $_POST['swap'],
							'disk' => $_POST['disk'],
							'iopriority' => $_POST['iopriority'],
							'diskformat' => $_POST['diskformat'],
							'diskcache' => $_POST['diskcache'],
							'disktype' => $_POST['disktype'],
							'netmode' => $_POST['netmode'],
							'bridge' => $_POST['bridge'],
							'vmbr' => $_POST['vmbr'],
							'netmodel' => $_POST['netmodel'],
							'netrate' => $_POST['netrate'],
							'bw' => $_POST['bw'],
							'kvm' => $_POST['kvm'],
							'onboot' => $_POST['onboot'],
						]
					);
				}
			);
			$_SESSION['prve']['infomsg']['title']='KVM Plan added.' ;
			$_SESSION['prve']['infomsg']['message']='New KVM plan saved successfuly.' ;
			header("Location: ".PRVE_BASEURL."&tab=vmplans&action=planlist");
		} catch (\Exception $e) {
			echo "Uh oh! Inserting didn't work, but I was able to rollback. {$e->getMessage()}";
		}
	}

	function update_kvm_plan() {
		Capsule::table('mod_prve_plans')
					->where('id', $_GET['id'])
					->update(
						[
							'title' => $_POST['title'],
							'vmtype' => 'kvm',
							'ostype' => $_POST['ostype'],
							'cpus' => $_POST['cpus'],
							'cpuemu' => $_POST['cpuemu'],
							'cores' => $_POST['cores'],
							'cpulimit' => $_POST['cpulimit'],
							'cpuunits' => $_POST['cpuunits'],
							'memory' => $_POST['memory'],
							'swap' => $_POST['swap'],
							'disk' => $_POST['disk'],
							'iopriority' => $_POST['iopriority'],
							'diskformat' => $_POST['diskformat'],
							'diskcache' => $_POST['diskcache'],
							'disktype' => $_POST['disktype'],
							'netmode' => $_POST['netmode'],
							'bridge' => $_POST['bridge'],
							'vmbr' => $_POST['vmbr'],
							'netmodel' => $_POST['netmodel'],
							'netrate' => $_POST['netrate'],
							'bw' => $_POST['bw'],
							'kvm' => $_POST['kvm'],
							'onboot' => $_POST['onboot'],
						]
					);
		$_SESSION['prve']['infomsg']['title']='KVM Plan updated.' ;
		$_SESSION['prve']['infomsg']['message']='KVM plan updated successfuly.' ;
		header("Location: ".PRVE_BASEURL."&tab=vmplans&action=planlist");
	}

	
	function remove_plan($id) {
		Capsule::table('mod_prve_plans')->where('id', '=', $id)->delete();
		header("Location: ".PRVE_BASEURL."&tab=vmplans&action=planlist");
		$_SESSION['prve']['infomsg']['title']='Plan Deleted.' ;
		$_SESSION['prve']['infomsg']['message']='Selected Item deleted successfuly.' ;
	}
	function save_openvz_plan() {
		try {
			Capsule::connection()->transaction(
				function ($connectionManager)
				{
					/** @var \Illuminate\Database\Connection $connectionManager */
					$connectionManager->table('mod_prve_plans')->insert(
						[
							'title' => $_POST['title'],
							'vmtype' => 'openvz',
							'cores' => $_POST['cores'],
							'cpulimit' => $_POST['cpulimit'],
							'cpuunits' => $_POST['cpuunits'],
							'memory' => $_POST['memory'],
							'swap' => $_POST['swap'],
							'disk' => $_POST['disk'],
							'bridge' => $_POST['bridge'],
							'vmbr' => $_POST['vmbr'],
							'netmodel' => $_POST['netmodel'],
							'netrate' => $_POST['netrate'],
							'bw' => $_POST['bw'],
							'onboot' => $_POST['onboot'],
						]
					);
				}
			);
			$_SESSION['prve']['infomsg']['title']='New OpenVZ Plan added.' ;
			$_SESSION['prve']['infomsg']['message']='New OpenVZ plan saved successfuly.' ;
			header("Location: ".PRVE_BASEURL."&tab=vmplans&action=planlist");			
		} catch (\Exception $e) {
			echo "Uh oh! Inserting didn't work, but I was able to rollback. {$e->getMessage()}";
		}
	}

	function update_openvz_plan() {
		Capsule::table('mod_prve_plans')
					->where('id', $_GET['id'])
					->update(
						[
							'title' => $_POST['title'],
							'vmtype' => 'openvz',
							'cores' => $_POST['cores'],
							'cpulimit' => $_POST['cpulimit'],
							'cpuunits' => $_POST['cpuunits'],
							'memory' => $_POST['memory'],
							'swap' => $_POST['swap'],
							'disk' => $_POST['disk'],
							'bridge' => $_POST['bridge'],
							'vmbr' => $_POST['vmbr'],
							'netmodel' => $_POST['netmodel'],
							'netrate' => $_POST['netrate'],
							'bw' => $_POST['bw'],
							'onboot' => $_POST['onboot'],
						]
					);
		$_SESSION['prve']['infomsg']['title']='OpenVZ Plan updated.' ;
		$_SESSION['prve']['infomsg']['message']='New KVM plan updated successfuly. (Updating plans will not effect on current Virtual machines.)' ;
		header("Location: ".PRVE_BASEURL."&tab=vmplans&action=planlist");
	}	

	// List IP pools in table
	function list_ip_pools() {
		echo '<a class="btn btn-default" href="'. PRVE_BASEURL .'&amp;tab=ippools&amp;action=new_ip_pool"><i class="fa fa-plus-square"></i>&nbsp; New IP Pool</a>';
		echo '<table class="datatable"><tr><th>Id</th><th>Pool</th><th>Gateway</th><th>Action</th></tr>';
		foreach (Capsule::table('mod_prve_ip_pools')->get() as $pool) {
			echo '<tr>';
				echo '<td>'.$pool->id . PHP_EOL .'</td>';
				echo '<td>'.$pool->title . PHP_EOL .'</td>';
				echo '<td>'.$pool->gateway . PHP_EOL .'</td>';
				echo '<td>
						<a href="'.PRVE_BASEURL.'&amp;tab=ippools&amp;action=list_ips&amp;id='.$pool->id.'"><img height="16" width="16" border="0" alt="Info" src="images/info.gif"></a>
						<a href="'.PRVE_BASEURL.'&amp;tab=ippools&amp;action=removeippool&amp;id='.$pool->id.'" onclick="return confirm(\'Pool and all ip addresses assigned to it will be deleted, are you sure to continue?\')"><img height="16" width="16" border="0" alt="Remove" src="images/delete.gif"></a>
					  </td>' ;
			echo '</tr>' ;
		}
		echo '</table>';
	}
	//create new IP pool
	function add_ip_pool() {
		echo '
		<form method="post">
			<table class="form" border="0" cellpadding="3" cellspacing="1" width="100%">
				<tr>
					<td class="fieldlabel">Pool Title</td>
					<td class="fieldarea">
						<input type="text" size="35" name="title" id="title" required>
					</td>
					<td class="fieldlabel">Gateway</td>
					<td class="fieldarea">
						<input type="text" size="25" name="gateway" id="gateway" required>
						Gateway address of the pool
					</td>
				</tr>
			</table>
			<input type="submit" class="btn btn-primary" name="newIPpool" value="save"/>
		</form>
		';
	}
	
	function save_ip_pool() {
		try {
			Capsule::connection()->transaction(
				function ($connectionManager)
				{
					/** @var \Illuminate\Database\Connection $connectionManager */
					$connectionManager->table('mod_prve_ip_pools')->insert(
						[
							'title' => $_POST['title'],
							'gateway' => $_POST['gateway'],
						]
					);
				}
			);
			$_SESSION['prve']['infomsg']['title']='New IP Pool added.' ;
			$_SESSION['prve']['infomsg']['message']='New IP Pool saved successfuly.' ;
			header("Location: ".PRVE_BASEURL."&tab=ippools&action=list_ip_pools");			
		} catch (\Exception $e) {
			echo "Uh oh! Inserting didn't work, but I was able to rollback. {$e->getMessage()}";
		}		
	}
	
	function removeIpPool($id) {
		Capsule::table('mod_prve_ip_addresses')->where('pool_id', '=', $id)->delete();
		Capsule::table('mod_prve_ip_pools')->where('id', '=', $id)->delete();
		
		header("Location: ".PRVE_BASEURL."&tab=ippools&action=list_ip_pools");
		$_SESSION['prve']['infomsg']['title']='IP Pool Deleted.' ;
		$_SESSION['prve']['infomsg']['message']='Selected IP pool deleted successfuly.' ;		
	}
	
	// add IP address/subnet to Pool
	function add_ip_2_pool() {
		require_once('../modules/addons/prve/Ipv4/Subnet.php');
		echo '<form method="post">
			<table class="form" border="0" cellpadding="3" cellspacing="1" width="100%">
				<tr>
					<td class="fieldlabel">IP Pool</td>
					<td class="fieldarea">
						<select class="form-control select-inline" name="pool_id">';
							foreach (Capsule::table('mod_prve_ip_pools')->get() as $pool) {
								echo '<option value="'.$pool->id.'">'.$pool->title.'</option>';
								$gateways[]=$pool->gateway ;
							}
				   echo '</select>
					</td>
				</tr>
				<tr>
					<td class="fieldlabel">IP Block</td>
					<td class="fieldarea">
						<input type="text" name="ipblock"/>
						IP Block with CIDR e.g. 172.16.255.230/27, for single IP address just don\'t use CIDR
					</td>
				</tr>
			</table>
			<input type="submit" name="assignIP2pool" value="save"/>
			</form>';
		if (isset($_POST['assignIP2pool'])) {
			// check if single IP address
			if ((strpos($_POST['ipblock'],'/'))!=false) {
				$subnet=Ipv4_Subnet::fromString($_POST['ipblock']);
				$ips = $subnet->getIterator();
				foreach($ips as $ip) {
					if (!in_array($ip, $gateways)) {
						Capsule::table('mod_prve_ip_addresses')->insert(
								[
									'pool_id' => $_POST['pool_id'],
									'ipaddress' => $ip,
									'mask' => $subnet->getNetmask(),
								]
							);
					}
				}
			}
			else {
				if (!in_array($_POST['ipblock'], $gateways)) {
					Capsule::table('mod_prve_ip_addresses')->insert(
							[
								'pool_id' => $_POST['pool_id'],
								'ipaddress' => $_POST['ipblock'],
								'mask' => '255.255.255.255',
							]
						);
				}
			}
			header("Location: ".PRVE_BASEURL."&tab=ippools&action=list_ips&id=".$_POST['pool_id']);
			$_SESSION['prve']['infomsg']['title']='IP Address/Blocks added to Pool.' ;
			$_SESSION['prve']['infomsg']['message']='you can remove IP addresses from the pool.' ;			
		}
	}
	// List IP addresses in pool
	function list_ips() {
		//echo '<script>$(function() {$( "#dialog" ).dialog();});</script>' ;
		//echo '<div id="dialog">' ;
		echo '<table class="datatable"><tr><th>IP Address</th><th>Subnet Mask</th><th>Action</th></tr>' ;
		foreach (Capsule::table('mod_prve_ip_addresses')->where('pool_id', '=', $_GET['id'])->get() as $ip) {
			echo '<tr><td>'.$ip->ipaddress.'</td><td>'.$ip->mask.'</td><td>';
			if (count(Capsule::table('mod_prve_vms')->where('ipaddress','=',$ip->ipaddress)->get())>0)
				echo 'is in use' ;
			else
				echo '<a href="'.PRVE_BASEURL.'&amp;tab=ippools&amp;action=removeip&amp;pool_id='.$ip->pool_id.'&amp;id='.$ip->id.'" onclick="return confirm(\'IP address will be deleted from the pool, continue?\')"><img height="16" width="16" border="0" alt="Edit" src="images/delete.gif"></a>';
			echo '</td></tr>';
		}
		echo '</table>' ;
		
	}
	// Remove IP Address
	function removeip($id,$pool_id) {
		Capsule::table('mod_prve_ip_addresses')->where('id', '=', $id)->delete();
		header("Location: ".PRVE_BASEURL."&tab=ippools&action=list_ips&id=".$pool_id);
		$_SESSION['prve']['infomsg']['title']='IP Address Deleted.' ;
		$_SESSION['prve']['infomsg']['message']='Selected Item deleted successfuly.' ;
	}
?>