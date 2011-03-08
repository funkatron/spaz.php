<?php
define('MAX_REDIRECTS', 10);
define('ONE_HOUR', 1000 * 60);

$cache = new fCache;

if (isset($_GET['url'])) {
	$passed_url = htmlspecialchars($_GET['url'], ENT_QUOTES, 'UTF-8');
	
	$opts = array();
	$opts['passed_url']   = $passed_url;
	
	if ($json_obj = $cache->get($opts['passed_url'])) {
		// yay
	} else {
		$result = resolve($opts);

		$json_obj = json_encode($result);

		$rs = $cache->put($opts['passed_url'], $json_obj);
	}
	
	header("Content-Type: application/json");
	echo $json_obj;
}


function resolve($data) {
	
	if (!isset($data['redirects'])) { $data['redirects'] = 0; }
	
	try {
		$req = new HttpRequest($data['passed_url'], HttpRequest::METH_HEAD);
		$req->setOptions(array('redirect' => MAX_REDIRECTS));
		$req->send();
		
		$resp_code = $req->getResponseCode();
		
		
		if ($resp_code >= 400 && $resp_code < 600) {

			$data['error'] = 'http-error';
			$data['error_message'] = $resp_code;
			return $data;

		} elseif ($resp_code >= 200 && $resp_code < 300) {
			
			$data['final_url'] = $req->getResponseInfo('effective_url');
			$data['redirects'] = $req->getResponseInfo('redirect_count');
			return $data;
			
		} else {
			
			$data['error'] = 'Unknown';
			$data['error_message'] = 'Something didn\'t work, bro';
			return $data;
			
		}
		
	} catch (HttpException $e) {
		
		$data['error'] = $e->getCode();
		$data['error_message'] = $e->getMessage();
		return $data;
		
	}
};


/**
* a little wrapper for apc caching
*/
class fCache {
	
	public function __construct() {
	}
	
	public function put($key, $val, $expire=null) {
		if (!isset($expire)) {
			$expire = time()+(ONE_HOUR); // 1 hour from now
		} else {
			$expire = time()+($expire);
		}
		return apc_store($key, $val, $expire);
	}
	
	public function get($key) {
		return apc_fetch($key);
	}

	public function del($key) {
		return apc_delete($key);
	}

}


