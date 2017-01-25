<?php /* vim: set ts=2 sw=2 tw=0 et :*/

class Ipv4_Address
{
  private $ip_long;
  const ERROR_ADDR_FORMAT = 'IP address string format error';

  /**
   * fromString
   * Creates Ipv4_Address object from a standard dotted-quad IP address
   *
   * @param string $data
   * @static
   * @access public
   * @return Ipv4_Address
   */
  static function fromString($data) {
    if ($long = ip2long($data)) return new self($long);
    throw new Exception(self::ERROR_ADDR_FORMAT);
  }

  /**
   * fromLong
   * Creates Ipv4_Address object from a decimal (long) address
   *
   * @param real $data
   * @static
   * @access public
   * @return Ipv4_Address
   */
  static function fromLong($data) {
    return new self((real)$data);
  }

  /**
   * fromBinary
   * Creates Ipv4_Address object from a binary address
   *
   * @param string $data
   * @static
   * @access public
   * @return Ipv4_Address
   */
  static function fromBinary($data) {
    return new self(bindec($data));
  }

  /**
   * toString
   * Returns value as dotted quad IP address
   *
   * @access public
   * @return string
   */
  public function toString() {
    return long2ip($this->ip_long);
  }

  /**
   * toLong
   * Returns value as decimal (long) address
   *
   * @access public
   * @return real
   */
  public function toLong() {
    return $this->ip_long;
  }

  /**
   * toBinary
   * Returns binary representation of address
   *
   * @access public
   * @return string
   */
  public function toBinary() {
    return str_pad(decbin($this->ip_long),32,0,STR_PAD_LEFT);
  }

  /**
   * __toString
   * Magic method returns dotted quad IP address
   *
   * @access public
   * @return string
   */
  public function __toString() {
    return $this->toString();
  }

  /**
   * __construct
   * Private constructor
   *
   * @param real $long
   * @access private
   * @return void
   */
  private function __construct($long) {
    $this->ip_long = $long;
  }

}
