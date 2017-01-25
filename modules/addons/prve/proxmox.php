<?php
class PVE2_API {
	protected $constructor_success = null;
	protected $hostname;
	protected $username;
	protected $realm;
	protected $password;
	protected $port;
	protected $verify_ssl;

	protected $login_ticket = null;
	protected $login_ticket_timestamp = null;
	protected $cluster_node_list = null;

	public function __construct ($hostname, $username, $realm, $password, $port = 8006, $verify_ssl = false) {
		if (empty($hostname) || empty($username) || empty($realm) || empty($password) || empty($port)) {
			$this->constructor_success = false;
			return false ;
		}
		// Check hostname resolves.
		if (gethostbyname($hostname) == $hostname && !filter_var($hostname, FILTER_VALIDATE_IP)) {
			$this->constructor_success = false;
			return false ;
		}
		// Check port is between 1 and 65535.
		if (!is_int($port) || $port < 1 || $port > 65535) {
			$this->constructor_success = false;
			return false ;
		}
		// Check that verify_ssl is boolean.
		if (!is_bool($verify_ssl)) {
			$this->constructor_success = false;
			return false ;
		}

		$this->hostname   = $hostname;
		$this->username   = $username;
		$this->realm      = $realm;
		$this->password   = $password;
		$this->port       = $port;
		$this->verify_ssl = $verify_ssl;
	}

	/*
	 * bool login ()
	 * Performs login to PVE Server using JSON API, and obtains Access Ticket.
	 */
	public function login () {
		// Prepare login variables.
		$login_postfields = array();
		$login_postfields['username'] = $this->username;
		$login_postfields['password'] = $this->password;
		$login_postfields['realm'] = $this->realm;

		$login_postfields_string = http_build_query($login_postfields);
		unset($login_postfields);

		// Perform login request.
		$prox_ch = curl_init();
		curl_setopt($prox_ch, CURLOPT_URL, "https://{$this->hostname}:{$this->port}/api2/json/access/ticket");
		curl_setopt($prox_ch, CURLOPT_POST, true);
		curl_setopt($prox_ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($prox_ch, CURLOPT_POSTFIELDS, $login_postfields_string);
		curl_setopt($prox_ch, CURLOPT_SSL_VERIFYPEER, $this->verify_ssl);

		$login_ticket = curl_exec($prox_ch);
		$login_request_info = curl_getinfo($prox_ch);

		curl_close($prox_ch);
		unset($prox_ch);
		unset($login_postfields_string);

		if (!$login_ticket) {
			// SSL negotiation failed or connection timed out
			$this->login_ticket_timestamp = null;
			return false;
		}

		$login_ticket_data = json_decode($login_ticket, true);
		if ($login_ticket_data == null || $login_ticket_data['data'] == null) {
			// Login failed.
			// Just to be safe, set this to null again.
			$this->login_ticket_timestamp = null;
			if ($login_request_info['ssl_verify_result'] == 1) {
				$this->constructor_success = false;
				return false ;				
			}
			return false;
		} else {
			// Login success.
			$this->login_ticket = $login_ticket_data['data'];
			// We store a UNIX timestamp of when the ticket was generated here,
			// so we can identify when we need a new one expiration-wise later
			// on...
			$this->login_ticket_timestamp = time();
			$this->reload_node_list();
			return true;
		}
	}

	# Sets the PVEAuthCookie
	# Attetion, after using this the user is logged into the web interface aswell!
	# Use with care, and DO NOT use with root, it may harm your system
	public function setCookie() {
		if (!$this->check_login_ticket()) {
			$this->constructor_success = false;
			return false ;
		}

		setrawcookie("PVEAuthCookie", $this->login_ticket['ticket'], 0, "/");
	}

	/*
	 * bool check_login_ticket ()
	 * Checks if the login ticket is valid still, returns false if not.
	 * Method of checking is purely by age of ticket right now...
	 */
	protected function check_login_ticket () {
		if ($this->login_ticket == null) {
			// Just to be safe, set this to null again.
			$this->login_ticket_timestamp = null;
			return false;
		}
		if ($this->login_ticket_timestamp >= (time() + 7200)) {
			// Reset login ticket object values.
			$this->login_ticket = null;
			$this->login_ticket_timestamp = null;
			return false;
		} else {
			return true;
		}
	}

	/*
	 * object action (string action_path, string http_method[, array put_post_parameters])
	 * This method is responsible for the general cURL requests to the JSON API,
	 * and sits behind the abstraction layer methods get/put/post/delete etc.
	 */
	private function action ($action_path, $http_method, $put_post_parameters = null) {
		// Check if we have a prefixed / on the path, if not add one.
		if (substr($action_path, 0, 1) != "/") {
			$action_path = "/".$action_path;
		}

		if (!$this->check_login_ticket()) {
			$this->constructor_success = false;
			return false ;
		}

		// Prepare cURL resource.
		$prox_ch = curl_init();
		curl_setopt($prox_ch, CURLOPT_URL, "https://{$this->hostname}:{$this->port}/api2/json{$action_path}");

		$put_post_http_headers = array();
		$put_post_http_headers[] = "CSRFPreventionToken: {$this->login_ticket['CSRFPreventionToken']}";
		// Lets decide what type of action we are taking...
		switch ($http_method) {
			case "GET":
				// Nothing extra to do.
				curl_setopt($prox_ch, CURLOPT_URL, "https://{$this->hostname}:{$this->port}/api2/json{$action_path}?".http_build_query($put_post_parameters));
				break;
			case "PUT":
				curl_setopt($prox_ch, CURLOPT_CUSTOMREQUEST, "PUT");

				// Set "POST" data.
				$action_postfields_string = http_build_query($put_post_parameters);
				curl_setopt($prox_ch, CURLOPT_POSTFIELDS, $action_postfields_string);
				unset($action_postfields_string);

				// Add required HTTP headers.
				curl_setopt($prox_ch, CURLOPT_HTTPHEADER, $put_post_http_headers);
				break;
			case "POST":
				curl_setopt($prox_ch, CURLOPT_POST, true);

				// Set POST data.
				$action_postfields_string = http_build_query($put_post_parameters);
				curl_setopt($prox_ch, CURLOPT_POSTFIELDS, $action_postfields_string);
				unset($action_postfields_string);

				// Add required HTTP headers.
				curl_setopt($prox_ch, CURLOPT_HTTPHEADER, $put_post_http_headers);
				break;
			case "DELETE":
				curl_setopt($prox_ch, CURLOPT_CUSTOMREQUEST, "DELETE");
				// No "POST" data required, the delete destination is specified in the URL.

				// Add required HTTP headers.
				curl_setopt($prox_ch, CURLOPT_HTTPHEADER, $put_post_http_headers);
				break;
			default:
				//throw new PVE2_Exception("Error - Invalid HTTP Method specified.", 5);	
				return false;
		}

		curl_setopt($prox_ch, CURLOPT_HEADER, true);
		curl_setopt($prox_ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($prox_ch, CURLOPT_COOKIE, "PVEAuthCookie=".$this->login_ticket['ticket']);
		curl_setopt($prox_ch, CURLOPT_SSL_VERIFYPEER, false);

		$action_response = curl_exec($prox_ch);

		curl_close($prox_ch);
		unset($prox_ch);

		$split_action_response = explode("\r\n\r\n", $action_response, 2);
		$header_response = $split_action_response[0];
		$body_response = $split_action_response[1];
		$action_response_array = json_decode($body_response, true);

		$action_response_export = var_export($action_response_array, true);
		error_log("----------------------------------------------\n" .
			"FULL RESPONSE:\n\n{$action_response}\n\nEND FULL RESPONSE\n\n" .
			"Headers:\n\n{$header_response}\n\nEnd Headers\n\n" .
			"Data:\n\n{$body_response}\n\nEnd Data\n\n" .
			"RESPONSE ARRAY:\n\n{$action_response_export}\n\nEND RESPONSE ARRAY\n" .
			"----------------------------------------------");

		unset($action_response);
		unset($action_response_export);

		// Parse response, confirm HTTP response code etc.
		$split_headers = explode("\r\n", $header_response);
		if (substr($split_headers[0], 0, 9) == "HTTP/1.1 ") {
			$split_http_response_line = explode(" ", $split_headers[0]);
			if ($split_http_response_line[1] == "200") {
				if ($http_method == "PUT") {
					return true;
				} else {
					return $action_response_array['data'];
				}
			} else {
				error_log("This API Request Failed.\n" . 
					"HTTP Response - {$split_http_response_line[1]}\n" . 
					"HTTP Error - {$split_headers[0]}");
				return false;
			}
		} else {
			error_log("Error - Invalid HTTP Response.\n" . var_export($split_headers, true));
			return false;
		}

		if (!empty($action_response_array['data'])) {
			return $action_response_array['data'];
		} else {
			error_log("\$action_response_array['data'] is empty. Returning false.\n" . 
				var_export($action_response_array['data'], true));
			return false;
		}
	}

	/*
	 * array reload_node_list ()
	 * Returns the list of node names as provided by /api2/json/nodes.
	 * We need this for future get/post/put/delete calls.
	 * ie. $this->get("nodes/XXX/status"); where XXX is one of the values from this return array.
	 */
	public function reload_node_list () {
		$node_list = $this->get("/nodes");
		if (count($node_list) > 0) {
			$nodes_array = array();
			foreach ($node_list as $node) {
				$nodes_array[] = $node['node'];
			}
			$this->cluster_node_list = $nodes_array;
			return true;
		} else {
			error_log(" Empty list of nodes returned in this cluster.");
			return false;
		}
	}

	/*
	 * array get_node_list ()
	 *
	 */
	public function get_node_list () {
		// We run this if we haven't queried for cluster nodes as yet, and cache it in the object.
		if ($this->cluster_node_list == null) {
			if ($this->reload_node_list() === false) {
				return false;
			}
		}

		return $this->cluster_node_list;
	}
	
	/*
	 * bool|int get_next_vmid ()
	 * Get Last VMID from a Cluster or a Node
	 * returns a VMID, or false if not found.
	 */
	public function get_next_vmid () {
		$vmid = $this->get("/cluster/nextid");
		if ($vmid == null) {
			return false;
		} else {
			return $vmid;
		}
	}

	/*
	 * bool|string get_version ()
	 * Return the version and minor revision of Proxmox Server
	 */
	public function get_version () {
		$version = $this->get("/version");
		if ($version == null) {
			return false;
		} else {
			return $version['version'];
		}
	}

	/*
	 * object/array? get (string action_path)
	 */
	public function get ($action_path,$parameters='null') {
		return $this->action($action_path, "GET",$parameters);
	}

	/*
	 * bool put (string action_path, array parameters)
	 */
	public function put ($action_path, $parameters) {
		return $this->action($action_path, "PUT", $parameters);
	}

	/*
	 * bool post (string action_path, array parameters)
	 */
	public function post ($action_path, $parameters) {
		return $this->action($action_path, "POST", $parameters);
	}

	/*
	 * bool delete (string action_path)
	 */
	public function delete ($action_path) {
		return $this->action($action_path, "DELETE");
	}

	// Logout not required, PVEAuthCookie tokens have a 2 hour lifetime.
}


function prve_check_license($licensekey, $localkey='') {

    // -----------------------------------
    //  -- Configuration Values --
    // -----------------------------------

    // Enter the url to your WHMCS installation here
    $whmcsurl = 'http://www.moduleland.com/';
    // Must match what is specified in the MD5 Hash Verification field
    // of the licensing product that will be used with this check.
    $licensing_secret_key = 'q8e1BNyxo7HEo7wGDoyX3Bp5wno2s4HC';
    // The number of days to wait between performing remote license checks
    $localkeydays = 15;
    // The number of days to allow failover for after local key expiry
    $allowcheckfaildays = 5;

    // -----------------------------------
    //  -- Do not edit below this line --
    // -----------------------------------

    $check_token = time() . md5(mt_rand(1000000000, 9999999999) . $licensekey);
    $checkdate = date("Ymd");
    $domain = $_SERVER['SERVER_NAME'];
    $usersip = isset($_SERVER['SERVER_ADDR']) ? $_SERVER['SERVER_ADDR'] : $_SERVER['LOCAL_ADDR'];
    $dirpath = dirname(__FILE__);
    $verifyfilepath = 'modules/servers/licensing/verify.php';
    $localkeyvalid = false;
    if ($localkey) {
        $localkey = str_replace("\n", '', $localkey); # Remove the line breaks
        $localdata = substr($localkey, 0, strlen($localkey) - 32); # Extract License Data
        $md5hash = substr($localkey, strlen($localkey) - 32); # Extract MD5 Hash
        if ($md5hash == md5($localdata . $licensing_secret_key)) {
            $localdata = strrev($localdata); # Reverse the string
            $md5hash = substr($localdata, 0, 32); # Extract MD5 Hash
            $localdata = substr($localdata, 32); # Extract License Data
            $localdata = base64_decode($localdata);
            $localkeyresults = unserialize($localdata);
            $originalcheckdate = $localkeyresults['checkdate'];
            if ($md5hash == md5($originalcheckdate . $licensing_secret_key)) {
                $localexpiry = date("Ymd", mktime(0, 0, 0, date("m"), date("d") - $localkeydays, date("Y")));
                if ($originalcheckdate > $localexpiry) {
                    $localkeyvalid = true;
                    $results = $localkeyresults;
                    $validdomains = explode(',', $results['validdomain']);
                    if (!in_array($_SERVER['SERVER_NAME'], $validdomains)) {
                        $localkeyvalid = false;
                        $localkeyresults['status'] = "Invalid";
                        $results = array();
                    }
                    $validips = explode(',', $results['validip']);
                    if (!in_array($usersip, $validips)) {
                        $localkeyvalid = false;
                        $localkeyresults['status'] = "Invalid";
                        $results = array();
                    }
                    $validdirs = explode(',', $results['validdirectory']);
                    if (!in_array($dirpath, $validdirs)) {
                        $localkeyvalid = false;
                        $localkeyresults['status'] = "Invalid";
                        $results = array();
                    }
                }
            }
        }
    }
    if (!$localkeyvalid) {
        $responseCode = 0;
        $postfields = array(
            'licensekey' => $licensekey,
            'domain' => $domain,
            'ip' => $usersip,
            'dir' => $dirpath,
        );
        if ($check_token) $postfields['check_token'] = $check_token;
        $query_string = '';
        foreach ($postfields AS $k=>$v) {
            $query_string .= $k.'='.urlencode($v).'&';
        }
        if (function_exists('curl_exec')) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $whmcsurl . $verifyfilepath);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $query_string);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            $data = curl_exec($ch);
            $responseCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
        } else {
            $responseCodePattern = '/^HTTP\/\d+\.\d+\s+(\d+)/';
            $fp = @fsockopen($whmcsurl, 80, $errno, $errstr, 5);
            if ($fp) {
                $newlinefeed = "\r\n";
                $header = "POST ".$whmcsurl . $verifyfilepath . " HTTP/1.0" . $newlinefeed;
                $header .= "Host: ".$whmcsurl . $newlinefeed;
                $header .= "Content-type: application/x-www-form-urlencoded" . $newlinefeed;
                $header .= "Content-length: ".@strlen($query_string) . $newlinefeed;
                $header .= "Connection: close" . $newlinefeed . $newlinefeed;
                $header .= $query_string;
                $data = $line = '';
                @stream_set_timeout($fp, 20);
                @fputs($fp, $header);
                $status = @socket_get_status($fp);
                while (!@feof($fp)&&$status) {
                    $line = @fgets($fp, 1024);
                    $patternMatches = array();
                    if (!$responseCode
                        && preg_match($responseCodePattern, trim($line), $patternMatches)
                    ) {
                        $responseCode = (empty($patternMatches[1])) ? 0 : $patternMatches[1];
                    }
                    $data .= $line;
                    $status = @socket_get_status($fp);
                }
                @fclose ($fp);
            }
        }
        if ($responseCode != 200) {
            $localexpiry = date("Ymd", mktime(0, 0, 0, date("m"), date("d") - ($localkeydays + $allowcheckfaildays), date("Y")));
            if ($originalcheckdate > $localexpiry) {
                $results = $localkeyresults;
            } else {
                $results = array();
                $results['status'] = "Invalid";
                $results['description'] = "Remote Check Failed";
                return $results;
            }
        } else {
            preg_match_all('/<(.*?)>([^<]+)<\/\\1>/i', $data, $matches);
            $results = array();
            foreach ($matches[1] AS $k=>$v) {
                $results[$v] = $matches[2][$k];
            }
        }
        if (!is_array($results)) {
            die("Invalid License Server Response");
        }
        if ($results['md5hash']) {
            if ($results['md5hash'] != md5($licensing_secret_key . $check_token)) {
                $results['status'] = "Invalid";
                $results['description'] = "MD5 Checksum Verification Failed";
                return $results;
            }
        }
        if ($results['status'] == "Active") {
            $results['checkdate'] = $checkdate;
            $data_encoded = serialize($results);
            $data_encoded = base64_encode($data_encoded);
            $data_encoded = md5($checkdate . $licensing_secret_key) . $data_encoded;
            $data_encoded = strrev($data_encoded);
            $data_encoded = $data_encoded . md5($data_encoded . $licensing_secret_key);
            $data_encoded = wordwrap($data_encoded, 80, "\n", true);
            $results['localkey'] = $data_encoded;
        }
        $results['remotecheck'] = true;
    }
    unset($postfields,$data,$matches,$whmcsurl,$licensing_secret_key,$checkdate,$usersip,$localkeydays,$allowcheckfaildays,$md5hash);
    return $results;
}


?>
