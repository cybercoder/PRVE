<?php /* vim: set ts=2 sw=2 tw=0 et :*/

/**
 * Ipv4_SubnetIterator 
 * An object that implements a subnet iterator
 * 
 * @uses Iterator
 * @package Ipv4
 * @version $id$
 * @copyright 2012 Kelly Hallman
 * @author Kelly Hallman
 * @license MIT
 */
class Ipv4_SubnetIterator implements Iterator
{
  private $position = 0;
  private $low_dec;
  private $hi_dec;

  public function __construct(Ipv4_Subnet $subnet) {
    $this->low_dec = ip2long($subnet->getFirstHostAddr());
    $this->hi_dec = ip2long($subnet->getLastHostAddr());
  }

  function rewind() {
    $this->position = 0;
  }

  function current() {
    return long2ip($this->low_dec + $this->position);
  }

  function key() {
    return $this->position;
  }

  function next() {
    ++$this->position;
  }

  function valid() {
    return (($this->low_dec + $this->position) <= $this->hi_dec);
  }
}

