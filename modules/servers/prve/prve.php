<?php
if (file_exists('../modules/addons/prve/proxmox.php'))
	require_once('../modules/addons/prve/proxmox.php');
else
	require_once('modules/addons/prve/proxmox.php');

use Illuminate\Database\Capsule\Manager as Capsule;

// Check license
$license=Capsule::table('mod_prve')->get()[0] ;
$results=prve_check_license($license->license,$license->localkey) ;

switch ($results['status']) {
    case "Active":
        // get new local key and save it somewhere
        $localkeydata = $results['localkey'];
		Capsule::table('mod_prve')->where('id',1)->update(
			[
				'localkey' => $localkeydata
			]
		);
        break;
    case "Invalid":
        die("<b style='color:red'>PRVE License key is Invalid</b>");
        break;
    case "Expired":
        die("<b style='color:red'>PRVE License key is Expired</b>");
        break;
    case "Suspended":
        die("<b style='color:red'>PRVE License key is Suspended, contact PRVE WHMCS module support</b>");
        break;
    default:
        die("<b style='color:red'>PRVE License server, Invalid Response</b>");
        break;
}

global $guest ;
function prve_ConfigOptions()
{
	// Reterive PRVE Cluster
	$server=Capsule::table('tblservers')->where('type', '=', 'prve')->get()[0] ;
	
	
	// Reterive Plans
	foreach (Capsule::table('mod_prve_plans')->get() as $plan) {
		$plans[$plan->id]=$plan->vmtype.'&nbsp;:&nbsp;'.$plan->title ;
	}
	
	// Reterive IP Pools
	foreach (Capsule::table('mod_prve_ip_pools')->get() as $ippool) {
		$ippools[$ippool->id]=$ippool->title ;
	}
	/*
	$proxmox=new PVE2_API($server->ipaddress, $server->username, "pam", get_server_pass_from_whmcs($server->password));
	if ($proxmox->login()) {
		# Get first node name.
		$nodes = $proxmox->get_node_list();
		$first_node = $nodes[0];
		unset($nodes);
		
		$storage_contents=$proxmox->get('/nodes/'.$first_node.'/storage/local/content') ;
		
		foreach ($storage_contents as $storage_content) {
			if ($storage_content['content']=='vztmpl') {
				$templates[$storage_content['volid']]=explode('.',explode('/',$storage_content['volid'])[1])[0] ;
			}
		}
	}
	*/
	// Option for the product
	$configarray = array(
		"Plan" => array(
			"FriendlyName" => "Plan",
			"Type" => "dropdown",
			'Options' => $plans ,		
			"Description" => "The Virtual Machine Type."
		),
		"IPPool" => array(
			"FriendlyName" => "IP Pool",
			"Type" => "dropdown",
			'Options'=> $ippools,
			"Description" => "IP pool to assign IP to target VM."
		),
	);

	return $configarray;
}
function prve_CreateAccount($params) {
	// Reterive Plan form table
	$plan=Capsule::table('mod_prve_plans')->where('id', '=', $params['configoption1'])->get()[0] ;	
	
	$serverip = $params["serverip"];
	$serverusername = $params["serverusername"];
	$serverpassword = $params["serverpassword"];
	
	$vm_settings=array() ;
	
	
	//$ip=Capsule::select('select * from mod_prve_ip_addresses where pool_id='.$params['configoption2'].' and ipaddress not in(select ipaddress from mod_prve_vms) limit 1')[0] ;
	
	// select a ip address from pool
	$ip=Capsule::select('select ipaddress,mask,gateway from mod_prve_ip_addresses i INNER JOIN mod_prve_ip_pools p on (i.pool_id=p.id and p.id='.$params['configoption2'].') where  i.ipaddress not in(select ipaddress from mod_prve_vms) limit 1')[0] ;
	
	if (!empty($params['customfields']['KVMTemplate'])) {
		file_put_contents('d:\log.txt', $params['customfields']['KVMTemplate']);

		$proxmox=new PVE2_API($serverip, $serverusername, "pam", $serverpassword);
		if ($proxmox->login()) {
			# Get first node name.
			$nodes = $proxmox->get_node_list();
			$first_node = $nodes[0];
			unset($nodes);
			$vm_settings['newid']=($params["serviceid"])+100;
			$vm_settings['name']=($params["serviceid"])+100;
			$vm_settings['full']=true ;
			if ($proxmox->post('/nodes/'.$first_node.'/qemu/'.$params['customfields']['KVMTemplate'].'/clone',$vm_settings)) {
				Capsule::table('mod_prve_vms')->insert(
								[
									'id' => ($params['serviceid']+100),
									'user_id'=>$params['clientsdetails']['userid'],
									'vtype'=>'qemu',
									'ipaddress'=>$ip->ipaddress,
									'subnetmask'=>$ip->mask,
									'gateway'=>$ip->gateway,
									'created'=>date("Y-m-d H:i:s"),
								]
							);				
				return true ;
			}
		}
	
	} else {
		$vm_settings['vmid']=($params["serviceid"])+100;
		if ($plan->vmtype=='openvz') {
			$vm_settings['ostemplate']='local:vztmpl/'.$params['customfields']['Template'] ;
			$vm_settings['swap']=$plan->swap ;
			$vm_settings['rootfs']=$plan->disk ;

			$vm_settings['net0']='bridge='.$plan->bridge.$plan->vmbr.',name=eth0,ip='.$ip->ipaddress.'/'.mask2cidr($ip->mask).',gw='.$ip->gateway;
			$vm_settings['nameserver']='8.8.4.4 4.2.2.2' ;
			$vm_settings['password']=$params['customfields']['Password'] ;
		}
		else {
			$vm_settings['ostype']=$plan->ostype ;
			$vm_settings['sockets']=$plan->cpus ;
			$vm_settings['cores']=$plan->cores ;
			$vm_settings['cpu']=$plan->cpuemu ;
			$vm_settings['kvm']=$plan->kvm ;
			$vm_settings['onboot']=$plan->onboot ;
			
			$vm_settings[$plan->disktype.'0']='local:'.$plan->disk.',format='.$plan->diskformat ;
			if (!empty($plan->diskcache))
				$vm_settings[$plan->disktype.'0'].= ',cache='.$plan->diskcache ;
			
			// Assign ISO File
			if (isset($params['customfields']['ISO']))
				$vm_settings['ide2']='local:iso/'.$params['customfields']['ISO'].',media=cdrom' ;
			
			/* Network s ettings */
			if ($plan->netmode!='none') {
				$vm_settings['net0']=$plan->netmodel;
				if ($plan->netmode=='bridge') {
					$vm_settings['net0'].=',bridge='.$plan->bridge.$plan->vmbr ;
				}
				$vm_settings['net0'].=',firewall='.$plan->firewall ;
				if (!empty($plan->netrate))
					$vm_settings['net0'].=',rate='.$plan->netrate ;
			}
			/* end of network settings */		
		}

		$vm_settings['cpuunits']=$plan->cpuunits ;
		$vm_settings['cpulimit']=$plan->cpulimit ;
		$vm_settings['memory']=$plan->memory ;	

		$proxmox=new PVE2_API($serverip, $serverusername, "pam", $serverpassword);
		
		if ($proxmox->login()) {
			# Get first node name.
			$nodes = $proxmox->get_node_list();
			$first_node = $nodes[0];
			unset($nodes);
			
			if ($plan->vmtype=='kvm') $v='qemu' ; else $v='lxc';
			
			if ($proxmox->post('/nodes/'.$first_node.'/'.$v,$vm_settings)) {
				unset($vm_sttings) ;
				Capsule::table('mod_prve_vms')->insert(
								[
									'id' => ($params['serviceid']+100),
									'user_id'=>$params['clientsdetails']['userid'],
									'vtype'=>$v,
									'ipaddress'=>$ip->ipaddress,
									'subnetmask'=>$ip->mask,
									'gateway'=>$ip->gateway,
									'created'=>date("Y-m-d H:i:s"),
								]
							);
				return true ;
			}
		}else {
			echo 'not';
		}
		unset($vm_sttings);
	}
}


function prve_TestConnection(array $params)
{
    try {
        // Call the service's connection test function.
		$serverip = $params["serverip"];
		$serverusername = $params["serverusername"];
		$serverpassword = $params["serverpassword"];		
		$proxmox=new PVE2_API($serverip, $serverusername, "pam", $serverpassword);
		if ($proxmox->login())
			$success = true;
        $errorMsg = '';
    } catch (Exception $e) {
        // Record the error in WHMCS's module log.
        logModuleCall(
            'provisioningmodule',
            __FUNCTION__,
            $params,
            $e->getMessage(),
            $e->getTraceAsString()
        );
        $success = false;
        $errorMsg = $e->getMessage();
    }
    return array(
        'success' => $success,
        'error' => $errorMsg,
    );
}
function prve_SuspendAccount(array $params) {
	$serverip = $params["serverip"];	$serverusername = $params["serverusername"];	$serverpassword = $params["serverpassword"];		
	$proxmox=new PVE2_API($serverip, $serverusername, "pam", $serverpassword);
	if ($proxmox->login()){
		# Get first node name.
		$nodes = $proxmox->get_node_list();
		$first_node = $nodes[0];
		unset($nodes);
		// find virtual machine type
		$vm=Capsule::table('mod_prve_vms')->where('id', '=', ($params['serviceid']+100))->get()[0];
		if ($proxmox->post('/nodes/'.$first_node.'/'.$vm->vtype.'/'.($params['serviceid']+100).'/status/suspend')) {
			return true ;
		}
	}
	return false;
}
function prve_UnsuspendAccount(array $params) {
	$serverip = $params["serverip"];	$serverusername = $params["serverusername"];	$serverpassword = $params["serverpassword"];		
	$proxmox=new PVE2_API($serverip, $serverusername, "pam", $serverpassword);
	if ($proxmox->login()){
		# Get first node name.
		$nodes = $proxmox->get_node_list();
		$first_node = $nodes[0];
		unset($nodes);
		// find virtual machine type
		$vm=Capsule::table('mod_prve_vms')->where('id', '=', ($params['serviceid']+100))->get()[0];
		if ($proxmox->post('/nodes/'.$first_node.'/'.$vm->vtype.'/'.($params['serviceid']+100).'/status/resume')) {
			return true ;
		}
	}
	return false;
}


function prve_TerminateAccount(array $params) {
	$serverip = $params["serverip"];	$serverusername = $params["serverusername"];	$serverpassword = $params["serverpassword"];		
	$proxmox=new PVE2_API($serverip, $serverusername, "pam", $serverpassword);
	if ($proxmox->login()){
		# Get first node name.
		$nodes = $proxmox->get_node_list();
		$first_node = $nodes[0];
		unset($nodes);
		// find virtual machine type
		$vm=Capsule::table('mod_prve_vms')->where('id', '=', ($params['serviceid']+100))->get()[0];
		$proxmox->post('/nodes/'.$first_node.'/'.$vm->vtype.'/'.($params['serviceid']+100).'/status/stop') ;
		sleep(10) ;
		if ($proxmox->delete('/nodes/'.$first_node.'/'.$vm->vtype.'/'.($params['serviceid']+100),array('skiplock'=>1))) {
			Capsule::table('mod_prve_vms')->where('id', '=', ($params['serviceid']+100))->delete();
			return true ;
		}
	}
	return false ;
	
}
// class
	// WHMCS Decrypter
	class hash_encryption {
		/**
		 * Hashed value of the user provided encryption key
		 * @var	string
		 **/
		var $hash_key;
		/**
		 * String length of hashed values using the current algorithm
		 * @var	int
		 **/	
		var $hash_lenth;
		/**
		 * Switch base64 enconding on / off
		 * @var	bool	true = use base64, false = binary output / input
		 **/	
		var $base64;
		/**
		 * Secret value added to randomize output and protect the user provided key
		 * @var	string	Change this value to add more randomness to your encryption
		 **/	
		var $salt = 'Change this to any secret value you like. "d41d8cd98f00b204e9800998ecf8427e" might be a good example.';
		
	
		/**
		 * Constructor method
		 *
		 * Used to set key for encryption and decryption.
		 * @param	string	$key	Your secret key used for encryption and decryption
		 * @param	boold	$base64	Enable base64 en- / decoding
		 * @return mixed
		 */
		function hash_encryption($key, $base64 = true) {
			
			global $cc_encryption_hash;
			
			// Toggle base64 usage on / off
			$this->base64 = $base64;
			
			// Instead of using the key directly we compress it using a hash function
			$this->hash_key = $this->_hash($key);
			
			// Remember length of hashvalues for later use
			$this->hash_length = strlen($this->hash_key);
		}
			
		/**
		 * Method used for encryption
		 * @param	string	$string	Message to be encrypted
		 * @return string	Encrypted message
		 */
		function encrypt($string) {
			$iv = $this->_generate_iv();
			
			// Clear output
			$out = '';
			
			// First block of output is ($this->hash_hey XOR IV)
			for($c=0;$c < $this->hash_length;$c++) {
				$out .= chr(ord($iv[$c]) ^ ord($this->hash_key[$c]));
			}
	
			// Use IV as first key
			$key = $iv;
			$c = 0;
	
			// Go through input string
			while($c < strlen($string)) {
				// If we have used all characters of the current key we switch to a new one
				if(($c != 0) and ($c % $this->hash_length == 0)) {
					// New key is the hash of current key and last block of plaintext
					$key = $this->_hash($key . substr($string,$c - $this->hash_length,$this->hash_length));
				}
				// Generate output by xor-ing input and key character for character
				$out .= chr(ord($key[$c % $this->hash_length]) ^ ord($string[$c]));
				$c++;
			}
			// Apply base64 encoding if necessary
			if($this->base64) $out = base64_encode($out);
			return $out;
		}
		
		/**
		 * Method used for decryption
		 * @param	string	$string	Message to be decrypted
		 * @return string	Decrypted message
		 */
		function decrypt($string) {
			// Apply base64 decoding if necessary
			if($this->base64) $string = base64_decode($string);
			
			// Extract encrypted IV from input
			$tmp_iv = substr($string,0,$this->hash_length);
			
			// Extract encrypted message from input
			$string = substr($string,$this->hash_length,strlen($string) - $this->hash_length);
			$iv = $out = '';
			
			// Regenerate IV by xor-ing encrypted IV from block 1 and $this->hashed_key
			// Mathematics: (IV XOR KeY) XOR Key = IV
			for($c=0;$c < $this->hash_length;$c++) 
			{
				$iv .= chr(ord($tmp_iv[$c]) ^ ord($this->hash_key[$c]));
			}
			// Use IV as key for decrypting the first block cyphertext
			$key = $iv;
			$c = 0;
			
			// Loop through the whole input string
			while($c < strlen($string)) {
				// If we have used all characters of the current key we switch to a new one
				if(($c != 0) and ($c % $this->hash_length == 0)) {
					// New key is the hash of current key and last block of plaintext
					$key = $this->_hash($key . substr($out,$c - $this->hash_length,$this->hash_length));
				}
				// Generate output by xor-ing input and key character for character
				$out .= chr(ord($key[$c % $this->hash_length]) ^ ord($string[$c]));
				$c++;
			}
			return $out;
		}
	
		/**
		 * Hashfunction used for encryption
		 *
		 * This class hashes any given string using the best available hash algorithm.
		 * Currently support for md5 and sha1 is provided. In theory even crc32 could be used
		 * but I don't recommend this.
		 *
		 * @access	private
		 * @param	string	$string	Message to hashed
		 * @return string	Hash value of input message
		 */
		function _hash($string) {
			// Use sha1() if possible, php versions >= 4.3.0 and 5
			if(function_exists('sha1')) {
				$hash = sha1($string);
			} else {
				// Fall back to md5(), php versions 3, 4, 5
				$hash = md5($string);
			}
			$out ='';
			// Convert hexadecimal hash value to binary string
			for($c=0;$c<strlen($hash);$c+=2) {
				$out .= $this->_hex2chr($hash[$c] . $hash[$c+1]);
			}
			return $out;
		}
		
		/**
		 * Generate a random string to initialize encryption
		 *
		 * This method will return a random binary string IV ( = initialization vector).
		 * The randomness of this string is one of the crucial points of this algorithm as it
		 * is the basis of encryption. The encrypted IV will be added to the encrypted message
		 * to make decryption possible. The transmitted IV will be encoded using the user provided key.
		 *
		 * @todo	Add more random sources.
		 * @access	private
		 * @see function	hash_encryption
		 * @return string	Binary pseudo random string
		 **/
		function _generate_iv() {
			// Initialize pseudo random generator
			srand ((double)microtime()*1000000);
			
			// Collect random data.
			// Add as many "pseudo" random sources as you can find.
			// Possible sources: Memory usage, diskusage, file and directory content...
			$iv  = $this->salt;
			$iv .= rand(0,getrandmax());
			// Changed to serialize as the second parameter to print_r is not available in php prior to version 4.4
			$iv .= serialize($GLOBALS);
			return $this->_hash($iv);
		}
		
		/**
		 * Convert hexadecimal value to a binary string
		 *
		 * This method converts any given hexadecimal number between 00 and ff to the corresponding ASCII char
		 *
		 * @access	private
		 * @param	string	Hexadecimal number between 00 and ff
		 * @return	string	Character representation of input value
		 **/
		function _hex2chr($num) {
			return chr(hexdec($num));
		}
	}

// end class


function get_server_pass_from_whmcs($enc_pass){
	
	global $cc_encryption_hash;
	// Include WHMCS database configuration file
	include_once(dirname(dirname(dirname(dirname(__FILE__)))).'/configuration.php');
	$key1 = md5 (md5 ($cc_encryption_hash));
	$key2 = md5 ($cc_encryption_hash);
	$key = $key1.$key2;
	$hasher = new hash_encryption($key);
	return $hasher->decrypt($enc_pass);
}

function prve_ClientAreaCustomButtonArray() {
    $buttonarray = array(
	 "<img src='./modules/servers/prve/img/tigervnc.png'/> Tiger VNC (JAVA)" => "javaVNC",
	 "<img src='./modules/servers/prve/img/novnc.png'/> NoVNC" => "noVNC",
	 "<i class='fa fa-2x fa-plug'></i> Start" => "vmStart",
	 "<i class='fa fa-2x fa-power-off'></i> Shutdown" => "vmShutdown",
	 "<i class='fa fa-2x fa-stop'></i>  Stop" => "vmStop",
	 "<i class='fa fa-2x fa fa-line-chart'></i>  Statistics" => "vmStat",
	);
	return $buttonarray;
}
	
function prve_ClientArea($params) {
	//reterive virtual machine info from table mod_prve_vms
	$guest=Capsule::table('mod_prve_vms')->where('id','=',($params['serviceid']+100))->get()[0] ;
	$serverip = $params["serverip"];
	$serverusername = $params["serverusername"];
	$serverpassword = $params["serverpassword"];
	$proxmox=new PVE2_API($serverip, $serverusername, "pam", $serverpassword);
	if ($proxmox->login()) {
		//$proxmox->setCookie();
		# Get first node name.
		$nodes = $proxmox->get_node_list();
		$first_node = $nodes[0];
		unset($nodes);
		
		$vm_config=$proxmox->get('/nodes/'.$first_node.'/'.$guest->vtype.'/'.($params['serviceid']+100) .'/config') ;
		$vm_status=$proxmox->get('/nodes/'.$first_node.'/'.$guest->vtype.'/'.($params['serviceid']+100) .'/status/current') ;
		
		$vm_status['uptime']=time2format($vm_status['uptime']) ;
		$vm_status['cpu']=round( $vm_status['cpu'] * 100, 2 ) ;

		$vm_status['diskusepercent'] = intval( $vm_status['disk'] * 100 / $vm_status['maxdisk'] ); 
		$vm_status['memusepercent']=intval( $vm_status['mem'] * 100 / $vm_status['maxmem']);

		if ($guest->vtype=='lxc') $vm_status['swapusepercent']=intval( $vm_status['swap'] * 100 / $vm_status['maxswap']);
		
		// Max CPU usage Yearly
		$rrd_params=array('timeframe'=>'year','ds'=>'cpu','cf'=>'AVERAGE') ;
		$vm_rrd=$proxmox->get('/nodes/'.$first_node.'/'.$guest->vtype.'/'.($params['serviceid']+100) .'/rrd',$rrd_params) ;
		$vm_rrd['image']=utf8_decode($vm_rrd['image']) ;
		$vm_statistics['cpu']['year']=base64_encode($vm_rrd['image']);
		
		// Max CPU usage monthly
		$rrd_params=array('timeframe'=>'month','ds'=>'cpu','cf'=>'AVERAGE') ;
		$vm_rrd=$proxmox->get('/nodes/'.$first_node.'/'.$guest->vtype.'/'.($params['serviceid']+100) .'/rrd',$rrd_params) ;
		$vm_rrd['image']=utf8_decode($vm_rrd['image']) ;
		$vm_statistics['cpu']['month']=base64_encode($vm_rrd['image']);
		
		// Max CPU usage weekly
		$rrd_params=array('timeframe'=>'week','ds'=>'cpu','cf'=>'AVERAGE') ;
		$vm_rrd=$proxmox->get('/nodes/'.$first_node.'/'.$guest->vtype.'/'.($params['serviceid']+100) .'/rrd',$rrd_params) ;
		$vm_rrd['image']=utf8_decode($vm_rrd['image']) ;
		$vm_statistics['cpu']['week']=base64_encode($vm_rrd['image']);
		
		// Max CPU usage daily
		$rrd_params=array('timeframe'=>'day','ds'=>'cpu','cf'=>'AVERAGE') ;
		$vm_rrd=$proxmox->get('/nodes/'.$first_node.'/'.$guest->vtype.'/'.($params['serviceid']+100) .'/rrd',$rrd_params) ;
		$vm_rrd['image']=utf8_decode($vm_rrd['image']) ;
		$vm_statistics['cpu']['day']=base64_encode($vm_rrd['image']);
		
		// Max memory Yearly
		$rrd_params=array('timeframe'=>'year','ds'=>'maxmem','cf'=>'AVERAGE') ;
		$vm_rrd=$proxmox->get('/nodes/'.$first_node.'/'.$guest->vtype.'/'.($params['serviceid']+100) .'/rrd',$rrd_params) ;
		$vm_rrd['image']=utf8_decode($vm_rrd['image']) ;
		$vm_statistics['maxmem']['year']=base64_encode($vm_rrd['image']);
		
		// Max memory monthly
		$rrd_params=array('timeframe'=>'month','ds'=>'maxmem','cf'=>'AVERAGE') ;
		$vm_rrd=$proxmox->get('/nodes/'.$first_node.'/'.$guest->vtype.'/'.($params['serviceid']+100) .'/rrd',$rrd_params) ;
		$vm_rrd['image']=utf8_decode($vm_rrd['image']) ;
		$vm_statistics['maxmem']['month']=base64_encode($vm_rrd['image']);
		
		// Max memory weekly
		$rrd_params=array('timeframe'=>'week','ds'=>'maxmem','cf'=>'AVERAGE') ;
		$vm_rrd=$proxmox->get('/nodes/'.$first_node.'/'.$guest->vtype.'/'.($params['serviceid']+100) .'/rrd',$rrd_params) ;
		$vm_rrd['image']=utf8_decode($vm_rrd['image']) ;
		$vm_statistics['maxmem']['week']=base64_encode($vm_rrd['image']);
		
		// Max memory daily
		$rrd_params=array('timeframe'=>'day','ds'=>'maxmem','cf'=>'AVERAGE') ;
		$vm_rrd=$proxmox->get('/nodes/'.$first_node.'/'.$guest->vtype.'/'.($params['serviceid']+100) .'/rrd',$rrd_params) ;
		$vm_rrd['image']=utf8_decode($vm_rrd['image']) ;
		$vm_statistics['maxmem']['day']=base64_encode($vm_rrd['image']);

		// Network rate Yearly
		$rrd_params=array('timeframe'=>'year','ds'=>'netin,netout','cf'=>'AVERAGE') ;
		$vm_rrd=$proxmox->get('/nodes/'.$first_node.'/'.$guest->vtype.'/'.($params['serviceid']+100) .'/rrd',$rrd_params) ;
		$vm_rrd['image']=utf8_decode($vm_rrd['image']) ;
		$vm_statistics['netinout']['year']=base64_encode($vm_rrd['image']);
		
		// Network rate monthly
		$rrd_params=array('timeframe'=>'month','ds'=>'netin,netout','cf'=>'AVERAGE') ;
		$vm_rrd=$proxmox->get('/nodes/'.$first_node.'/'.$guest->vtype.'/'.($params['serviceid']+100) .'/rrd',$rrd_params) ;
		$vm_rrd['image']=utf8_decode($vm_rrd['image']) ;
		$vm_statistics['netinout']['month']=base64_encode($vm_rrd['image']);
		
		// Network rate weekly
		$rrd_params=array('timeframe'=>'week','ds'=>'netin,netout','cf'=>'AVERAGE') ;
		$vm_rrd=$proxmox->get('/nodes/'.$first_node.'/'.$guest->vtype.'/'.($params['serviceid']+100) .'/rrd',$rrd_params) ;
		$vm_rrd['image']=utf8_decode($vm_rrd['image']) ;
		$vm_statistics['netinout']['week']=base64_encode($vm_rrd['image']);
		
		// Network rate daily
		$rrd_params=array('timeframe'=>'day','ds'=>'netin,netout','cf'=>'AVERAGE') ;
		$vm_rrd=$proxmox->get('/nodes/'.$first_node.'/'.$guest->vtype.'/'.($params['serviceid']+100) .'/rrd',$rrd_params) ;
		$vm_rrd['image']=utf8_decode($vm_rrd['image']) ;
		$vm_statistics['netinout']['day']=base64_encode($vm_rrd['image']);
		
		// Max IO Yearly
		$rrd_params=array('timeframe'=>'year','ds'=>'diskread,diskwrite','cf'=>'AVERAGE') ;
		$vm_rrd=$proxmox->get('/nodes/'.$first_node.'/'.$guest->vtype.'/'.($params['serviceid']+100) .'/rrd',$rrd_params) ;
		$vm_rrd['image']=utf8_decode($vm_rrd['image']) ;
		$vm_statistics['diskrw']['year']=base64_encode($vm_rrd['image']);
		
		// Max IO monthly
		$rrd_params=array('timeframe'=>'month','ds'=>'diskread,diskwrite','cf'=>'AVERAGE') ;
		$vm_rrd=$proxmox->get('/nodes/'.$first_node.'/'.$guest->vtype.'/'.($params['serviceid']+100) .'/rrd',$rrd_params) ;
		$vm_rrd['image']=utf8_decode($vm_rrd['image']) ;
		$vm_statistics['diskrw']['month']=base64_encode($vm_rrd['image']);
		
		// Max IO weekly
		$rrd_params=array('timeframe'=>'week','ds'=>'diskread,diskwrite','cf'=>'AVERAGE') ;
		$vm_rrd=$proxmox->get('/nodes/'.$first_node.'/'.$guest->vtype.'/'.($params['serviceid']+100) .'/rrd',$rrd_params) ;
		$vm_rrd['image']=utf8_decode($vm_rrd['image']) ;
		$vm_statistics['diskrw']['week']=base64_encode($vm_rrd['image']);
		
		// Max IO daily
		$rrd_params=array('timeframe'=>'day','ds'=>'diskread,diskwrite','cf'=>'AVERAGE') ;
		$vm_rrd=$proxmox->get('/nodes/'.$first_node.'/'.$guest->vtype.'/'.($params['serviceid']+100) .'/rrd',$rrd_params) ;
		$vm_rrd['image']=utf8_decode($vm_rrd['image']) ;
		$vm_statistics['diskrw']['day']=base64_encode($vm_rrd['image']);
		
		unset($vm_rrd) ;
		
		$vm_config['vtype']=$guest->vtype ;
		$vm_config['ipv4']=$guest->ipaddress ;
		$vm_config['netmask4']=$guest->subnetmask ;
		$vm_config['gateway4']=$guest->gateway ;
		$vm_config['created']=$guest->created ;
		
	}
	else echo '<script>alert("no proxmox") ;</script>' ;
	
    return array(
        'templatefile' => 'clientarea',
		'templateVariables' =>array(
			'params' => $params,
			'vm_config'=>$vm_config,
			'vm_status'=>$vm_status,
			'vm_statistics'=>$vm_statistics,
			'vm_vncproxy'=>$vm_vncproxy,
		)
    );
}

function prve_vmStat($params) {
	return true ;
}

function prve_noVNC($params) {
	$serverip = $params["serverip"];
	$serverusername = $params["serverusername"];
	$serverpassword = $params["serverpassword"];
	$proxmox=new PVE2_API($serverip, $serverusername, "pam", $serverpassword);
	if ($proxmox->login()) {
		//$proxmox->setCookie() ;
		# Get first node name.
		$nodes = $proxmox->get_node_list();
		$first_node = $nodes[0];
		unset($nodes);
		$guest=Capsule::table('mod_prve_vms')->where('id','=',($params['serviceid']+100))->get()[0] ;
		$vm_vncproxy=$proxmox->post('/nodes/'.$first_node.'/'.$guest->vtype.'/'.($params['serviceid']+100) .'/vncproxy', array( 'websocket' => '1' )) ;	
		
		$path = 'api2/json/websocket?port=' . $vm_vncproxy['port'] . '&user=' . $serverusername . '@pam' . '&vmid=' . ($params['serviceid']+100) . '&vncticket=' . urlencode($vm_vncproxy['ticket']);
		
		
		$url='./modules/servers/prve/novnc/novnc_pve.php?host='.$serverip.'&port=8006&ticket='.$vm_vncproxy['ticket'].'&path='.urlencode($path) ;
		echo '<script>window.open("'.$url.'")</script>';
		
		//echo '<script>window.open("./modules/servers/prve/noVNC/vnc.php?node=pve&console=lxc&vmid=136&port='.$vm_vncwebsocket['port'].'&ticket='.$vm_vncproxy['ticket'].'")</script>';
	}
}

function prve_javaVNC($params){
	$serverip = $params["serverip"];
	$serverusername = $params["serverusername"];
	$serverpassword = $params["serverpassword"];
	$proxmox=new PVE2_API($serverip, $serverusername, "pam", $serverpassword);
	if ($proxmox->login()) {
		//$proxmox->setCookie();
		# Get first node name.
		$nodes = $proxmox->get_node_list();
		$first_node = $nodes[0];
		unset($nodes);
		
		$guest=Capsule::table('mod_prve_vms')->where('id','=',($params['serviceid']+100))->get()[0] ;
		
		$vm_vncproxy=$proxmox->post('/nodes/'.$first_node.'/'.$guest->vtype.'/'.($params['serviceid']+100) .'/vncproxy') ;

		$javaVNCparams=array() ;
		$javaVNCparams[0]=$serverip ;
		$javaVNCparams[1]=str_replace("\n","|",$vm_vncproxy['cert']) ;
		$javaVNCparams[2]=$vm_vncproxy['port'] ;
		$javaVNCparams[3]=$vm_vncproxy['user'] ;
		$javaVNCparams[4]=$vm_vncproxy['ticket'] ;

		echo '<script>window.open("modules/servers/prve/tigervnc.php?'.http_build_query($javaVNCparams).'","VNC","location=0,toolbar=0,menubar=0,scrollbars=1,resizable=1,width=802,height=624")</script>';
		return true ;
	}
	return false;
}

function prve_vmStart($params) {
	$serverip = $params["serverip"];
	$serverusername = $params["serverusername"];
	$serverpassword = $params["serverpassword"];
	$proxmox=new PVE2_API($serverip, $serverusername, "pam", $serverpassword);
	if ($proxmox->login()) {
		//$proxmox->setCookie();
		# Get first node name.
		$nodes = $proxmox->get_node_list();
		$first_node = $nodes[0];
		unset($nodes);
		$guest=Capsule::table('mod_prve_vms')->where('id','=',($params['serviceid']+100))->get()[0] ;

		if ($proxmox->post('/nodes/'.$first_node.'/'.$guest->vtype.'/'.($params['serviceid']+100).'/status/start'))
			return true ;
	}
	return false;
}
function prve_vmShutdown($params) {
	$serverip = $params["serverip"];
	$serverusername = $params["serverusername"];
	$serverpassword = $params["serverpassword"];
	$proxmox=new PVE2_API($serverip, $serverusername, "pam", $serverpassword);
	if ($proxmox->login()) {
		//$proxmox->setCookie();
		# Get first node name.
		$nodes = $proxmox->get_node_list();
		$first_node = $nodes[0];
		unset($nodes);
		$guest=Capsule::table('mod_prve_vms')->where('id','=',($params['serviceid']+100))->get()[0] ;

		if ($proxmox->post('/nodes/'.$first_node.'/'.$guest->vtype.'/'.($params['serviceid']+100).'/status/shutdown'))
			return true ;
	}
	return false;
}
function prve_vmStop($params) {
	$serverip = $params["serverip"];
	$serverusername = $params["serverusername"];
	$serverpassword = $params["serverpassword"];
	$proxmox=new PVE2_API($serverip, $serverusername, "pam", $serverpassword);
	if ($proxmox->login()) {
		//$proxmox->setCookie();
		# Get first node name.
		$nodes = $proxmox->get_node_list();
		$first_node = $nodes[0];
		unset($nodes);
		$guest=Capsule::table('mod_prve_vms')->where('id','=',($params['serviceid']+100))->get()[0] ;
		if ($proxmox->post('/nodes/'.$first_node.'/'.$guest->vtype.'/'.($params['serviceid']+100).'/status/stop'))
			return true ;
	}
	return false;
}

// convert subnet mask to CIDR
function mask2cidr($mask){
  $long = ip2long($mask);
  $base = ip2long('255.255.255.255');
  return 32-log(($long ^ $base)+1,2);      
}

function bytes2format($bytes, $precision = 2, $_1024 = true) {
	$units = array( 'B', 'KB', 'MB', 'GB', 'TB' );
	$bytes = max( $bytes, 0 );
	$pow = floor( ($bytes ? log( $bytes ) : 0) / log( ($_1024 ? 1024 : 1000) ) );
	$pow = min( $pow, count( $units ) - 1 );
	$bytes /= pow( ($_1024 ? 1024 : 1000), $pow );
	return round( $bytes, $precision ) . ' ' . $units[$pow];
}

function time2format($s) {
	$d = intval( $s / 86400 );
	if ($d < '10') {
		$d = '0' . $d;
	}
	$s -= $d * 86400;
	$h = intval( $s / 3600 );
	if ($h < '10') {
		$h = '0' . $h;
	}
	$s -= $h * 3600;
	$m = intval( $s / 60 );
	if ($m < '10') {
		$m = '0' . $m;
	}
	$s -= $m * 60;
	if ($s < '10') {
		$s = '0' . $s;
	}
	if ($d) {
		$str = $d . ' days ';
	}
	if ($h) {
		$str .= $h . ':';
	}
	if ($m) {
		$str .= $m . ':';
	}
	if ($s) {
		$str .= $s . '';
	}
	return $str;
}	
?>