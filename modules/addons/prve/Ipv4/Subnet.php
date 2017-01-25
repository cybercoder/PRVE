<?php /* vim: set ts=2 sw=2 tw=0 et :*/

if (!class_exists('Ipv4_Address'))
  require_once(dirname(__FILE__).'/Address.php');

/**
 * Ipv4_Subnet 
 * Class for identifying and enumerating an IPv4 Subnet
 * 
 * @uses Countable
 * @package Ipv4
 * @license MIT
 */
class Ipv4_Subnet implements Countable
{
  /**
   * Define some error messages as class constants
   */
  const ERROR_NETWORK_FORMAT = 'IP format incorrect';
  const ERROR_CIDR_FORMAT = 'Invalid CIDR format';
  const ERROR_SUBNET_FORMAT = 'Invalid Subnet format';

  /**
   * nw
   * Internal storage of network in long format
   *
   * @var float
   * @access private
   */
  private $nw = 0;

  /**
   * sn
   * Internal storage of subnet in long format
   *
   * @var float
   * @access private
   */
  private $sn = 0;

  /**
   * __construct
   * Public constructor
   *
   * @param mixed $n Network
   * @param mixed $s Subnet
   * @access public
   * @return void
   */
  public function __construct($n=null,$s=null) {
    if ($n instanceof Ipv4_Address) $n = $n->toString();
    if ($s instanceof Ipv4_Address) $s = $s->toString();
    if (is_string($n) and !$s) $this->setFromString($n);
      elseif ($n and $s) $this->setNetwork($n)->setNetmask($s);
  }

  /**
   * fromString
   * Primarily for chaining and consistency
   *
   * @param mixed $data
   * @static
   * @access public
   * @return Ipv4_Subnet
   */
  static function fromString($data) {
    return new Ipv4_Subnet($data);
  }

  /**
   * CIDRtoIP
   * Static method converts CIDR to dotted-quad IP notation
   *
   * @param int $cidr
   * @static
   * @access public
   * @return string
   */
  static function CIDRtoIP($cidr) {
    if (!($cidr >= 0 and $cidr <= 32))
      throw new Exception(self::ERROR_CIDR_FORMAT);

    return long2ip(bindec(str_pad(str_pad('', $cidr, '1'), 32, '0')));
  }

  /**
   * ContainsAddress
   * Static method to determine if an IP is on a subnet
   *
   * @param mixed $sn
   * @param mixed $ip
   * @static
   * @access public
   * @return bool
   */
  static function ContainsAddress($sn,$ip) {
    if (is_string($sn)) $sn = Ipv4_Subnet::fromString($sn);
    if (is_string($ip)) $ip = Ipv4_Address::fromString($ip);
    if (!$sn instanceof Ipv4_Subnet) throw new Exception(self::ERROR_SUBNET_FORMAT);
    if (!$ip instanceof Ipv4_Address) throw new Exception(Ipv4_Address::ERROR_ADDR_FORMAT);
    $sn_dec = ip2long($sn->getNetmask());

    return (($ip->toLong() & $sn_dec) == (ip2long($sn->getNetwork()) & $sn_dec));
  }

  /**
   * setFromString
   * Parse subnet string
   *
   * @param string $data
   * @access public
   * @return self
   */
  public function setFromString($data) {
    // Validate that the input matches an expected pattern
    if (!preg_match('!^([0-9]{1,3}\.){3}[0-9]{1,3}(( ([0-9]{1,3}\.){3}[0-9]{1,3})|(/[0-9]{1,2}))$!',$data))
      throw new Exception(self::ERROR_NETWORK_FORMAT);

    // Parse one of two formats possible, first is /CIDR format
    if (strpos($data,'/')) {
      list($network,$cidr) = explode('/',$data,2);

      $this->setNetwork($network);
      $this->sn = ip2long(self::CIDRtoIP($cidr));
    }
    // Second format is network space subnet
    else {
      list($network,$subnet) = explode(' ',$data,2);
      $this->setNetwork($network);
      $this->setNetmask($subnet);
    }

    return $this;
  }

  /**
   * contains
   * Method to check if an IP is on this network
   *
   * @param mixed $ip
   * @access public
   * @return bool
   */
  public function contains($ip) {
    return self::ContainsAddress($this,$ip);
  }

  /**
   * setNetwork
   * Sets the network on the object, from dotted-quad notation
   *
   * @param string $data
   * @access public
   * @return self
   */
  public function setNetwork($data) {
    $this->nw = Ipv4_Address::fromString($data)->toLong();
    return $this;
  }

  /**
   * setNetmask
   * Sets the netmask on the object, from dotted-quad notation
   *
   * @param string $data
   * @access public
   * @return self
   */
  public function setNetmask($data) {
    $data = Ipv4_Address::fromString($data);

    if (!preg_match('/^1*0*$/',$data->toBinary()))
      throw new Exception(self::ERROR_SUBNET_FORMAT);

    $this->sn = $data->toLong();
    return $this;
  }

  /**
   * getNetmask
   * Returns the netmask as dotted-quad string
   *
   * @access public
   * @return string
   */
  public function getNetmask() {
    return long2ip($this->sn);
  }

  /**
   * getNetmaskCidr
   * Returns the CIDR value representing the netmask
   *
   * @access public
   * @return int
   */
  public function getNetmaskCidr() {
    return strlen(rtrim(decbin($this->sn),'0'));
  }

  /**
   * getNetwork
   * Returns the network address in dotted-quad notation
   *
   * @access public
   * @return string
   */
  public function getNetwork() {
    $nw_bin = Ipv4_Address::fromLong($this->nw)->toBinary();
    $nw_bin = (str_pad(substr($nw_bin,0,$this->getNetmaskCidr()),32,0));
    return Ipv4_Address::fromBinary($nw_bin)->toString();
  }

  /**
   * getFirstHostAddr
   * Returns the first address of this network
   *
   * @access public
   * @return string
   */
  public function getFirstHostAddr() {
    $bin_net = Ipv4_Address::fromString($this->getNetwork())->toBinary();
    $bin_first = (str_pad(substr($bin_net,0,31),32,1));
    return Ipv4_Address::fromBinary($bin_first)->toString();
  }

  /**
   * getLastHostAddr
   * Returns last host of this network
   *
   * @access public
   * @return string
   */
  public function getLastHostAddr() {
    $bin_bcast = Ipv4_Address::fromString($this->getBroadcastAddr())->toBinary();
    $bin_last = (str_pad(substr($bin_bcast,0,31),32,0));
    return Ipv4_Address::fromBinary($bin_last)->toString();
  }

  /**
   * getBroadcastAddr
   * Returns the broadcast address for this network
   *
   * @access public
   * @return string
   */
  public function getBroadcastAddr() {
    $bin_host = Ipv4_Address::fromLong($this->nw)->toBinary();
    $bin_bcast = str_pad(substr($bin_host,0,$this->getNetmaskCidr()),32,1);
    return Ipv4_Address::fromBinary($bin_bcast)->toString();
  }

  /**
   * getTotalHosts
   * Returns a count of the total number of hosts on this network
   *
   * @access public
   * @return int
   */
  public function getTotalHosts() {
    return (bindec(str_pad('',(32-$this->getNetmaskCidr()),1)) - 1);
  }

  /**
   * getIterator
   * Returns an iterator for addresses in this subnet
   *
   * @access public
   * @return Ipv4_SubnetIterator
   */
  public function getIterator() {

    if (!class_exists('Ipv4_SubnetIterator'))
      require_once(dirname(__FILE__).'/SubnetIterator.php');

    return new Ipv4_SubnetIterator($this);
  }

  /**
   * __toString
   * Magic method prints subnet in IP/cidr format
   *
   * @access public
   * @return string
   */
  public function __toString() {
    return sprintf(
      '%s/%s',
      $this->getNetwork(),
      $this->getNetmaskCidr()
    );
  }

  /**
   * count 
   * Implements Countable interface
   * 
   * @access public
   * @return void
   */
  public function count() {
    return $this->getTotalHosts();
  } 
}
