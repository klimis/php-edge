<?php
namespace Framework\Core\Cache;
use Framework\Core;

class MemoryCache extends BaseCache {
	private $link;

	public function __construct() {
		$settings = Core\Settings::getInstance();
		$this->link = new \Memcache();
		foreach($settings->memcached_servers as $server){
			list($server, $port, $weight) = explode(':', $server);
			$this->link->addServer($server, (int) $port, 0, (int) $weight);
		}
	}

    /**
     * Override default serialize method of parent since
     * Memcached client library takes care of it
     * @param mixed $data
     * @return mixed
     */
    protected static function serialize($data){
        return $data;
    }

    /**
     * Override default unserialize method of parent since
     * Memcached client library takes care of it
     * @return mixed
     */
    protected static function unserialize($data){
        return $data;
    }

	public function getValue($key) {
		return $this->link->get($key);
	}

	/*public function addIfKeyNotExists($key, $value, $flags=0, $ttl=0) {
		return $this->link->add($key, $value, $flags, $ttl);
	}*/

	public function setValue($key, $value, $flags=0,$ttl=0) {
		$res = $this->link->set($key, $value, $flags, $ttl);
		if(!$res)
			throw new AppException('Error adding to memcache key ' . $key);
		return $res;
	}

	public function replace($key, $value, $flags=0, $ttl=0) {
		$res = $this->link->replace($key, $value, $flags, $ttl);
		if(!$res)
			throw new AppException('Error replacing item to memcache');
		return $res;
 	}

	public function deleteValue($key) {
		return $this->link->delete($key, 0);
	}

	public function __destruct() {
		$this->link->close();
	}
}
?>