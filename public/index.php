<?php
require("../db.conf.php");



define('MAX_REDIRECTS', 10);
define('ONE_HOUR', 1000 * 60 * 60);

$cache = new fCache;

if ($_GET['url']) {
	$passed_url = $_GET['url'];
	
	$opts = array();
	$opts['passed_url']   = $passed_url;
	
	if ($cache_obj = $cache->get($opts['passed_url'])) {
		echo "<pre>"; var_dump("CACHED"); echo "</pre>";
		$json_obj = $cache_obj['val'];
	} else {
		echo "<pre>"; var_dump("NOT CACHED"); echo "</pre>";
		$result = resolve($opts);

		$json_obj = json_encode($result);

		$cache_obj->put($opts['passed_url'], $json_obj);
	}
	
	header("Content-Type: text/html");
	echo $json_obj;
}


function resolve($data) {
	
	try {
		$req = new HttpRequest($data['passed_url'], HttpRequest::METH_HEAD);
		$req->setOptions(array('redirect' => MAX_REDIRECTS));
		$req->send();
		
		$resp_code = $req->getResponseCode();
		
		if ($resp_code >= 400 && $resp_code < 600) {
			if ($redirects > MAX_REDIRECTS) {
		
				$data['error'] = 'http-error';
				$data['error_message'] = $resp_code;
				
				return $data;
		
			} else if ($new_location = $req->getResponseHeader('location')) {
		
				$data['redirects']++;
				$opts['passed_url'] = $new_location;
				resolver($data);
		
			} else {

				$data['error'] = 'redirect-error';
				$data['error_message'] = 'couldn\'t find location header';
				
				return $data;
		
			}			
		} elseif ($resp_code >= 200 && $resp_code < 300) {
			
			$data['final_url'] = $req->getResponseInfo('effective_url');
			$data['redirects'] = $req->getResponseInfo('redirect_count');
			return $data;
			
		}
	} catch (HttpException $e) {
		echo $ex;
	}
};


/**
* 
*/
class fCache {
	
	public function __construct() {
		$this->mysqli_link = new mysqli(MYSQLI_HOSTNAME, MYSQLI_USERNAME, MYSQLI_PASSWORD, MYSQLI_DBNAME);
	}
	
	public function put($key, $val, $expire=null) {
		$sql = "INSERT INTO link_cache (key, val) VALUES (?, ?)";
		$stmt = $this->mysqli_link->prepare($sql);
		$stmt->bind_param("ss", md5($key), $val);
		$rs = $stmt->execute();
		$rows = $rs->affected_rows();
		$rs->close();
		return $rows;
	}
	
	public function get($key) {
		$sql = "SELECT val FROM link_cache WHERE key = '?'";
		
		$stmt = $this->mysqli_link->prepare($sql);
		$stmt->bind_param("s", md5($key));
		$rs = $stmt->execute();
		
		if ($rs-num_rows() > 0) {
			$row = $rs->fetch_assoc();
			$rs->close();
			return $row;
		}
		$rs->close();
		return false;
	}

}


