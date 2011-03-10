<?php

namespace app\controllers;

use lithium\storage\Cache;
use lithium\core\Libraries;
use lithium\action\Dispatcher;
use lithium\storage\cache\adapter\Apc;


class UrlController extends \lithium\action\Controller {

	public function index() {
		// $this->render(array('layout' => false));
	}


	public function resolve() {
		$url = preg_replace('@^url/resolve/https?:/@', 'http://', $this->request->url);

		if (isset($url)) {
			
			$passed_url = filter_var($url, FILTER_VALIDATE_URL);

			$opts = array();

			if (!$passed_url) {
				$opts['error']   = 'invalid url';
				$opts['error_message']   = 'invalid URL passed. Whoops!';
				$this->render(array('json' => $opts));
				return;
			}

			$opts['passed_url'] = $passed_url;
			
			$result = Cache::read('apc', $passed_url);
			if (!$result) {
				$result = $this->resolve_url($opts);
				$result['cached'] = false;
				Cache::write('apc', $passed_url, $result);
			} else {
				$result['cached'] = true;
			}
			
			$this->render(array('json' => $result));
			return;

		}

	}
	
	
	
	
	protected function resolve_url($data) {
		define('MAX_REDIRECTS', 10);
		define('ONE_HOUR', 1000 * 60);

		if (!isset($data['redirects'])) { $data['redirects'] = 0; }

		try {
			$req = new \HttpRequest($data['passed_url'], \HttpRequest::METH_HEAD);
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
	}
}

?>