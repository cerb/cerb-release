<?php
/***********************************************************************
| Cerb(tm) developed by Webgroup Media, LLC.
|-----------------------------------------------------------------------
| All source code & content (c) Copyright 2002-2018, Webgroup Media LLC
|   unless specifically noted otherwise.
|
| This source code is released under the Devblocks Public License.
| The latest version of this license can be found here:
| http://cerb.ai/license
|
| By using this software, you acknowledge having read this license
| and agree to be bound thereby.
| ______________________________________________________________________
|	http://cerb.ai	    http://webgroup.media
***********************************************************************/

interface IServiceProvider_HttpRequestSigner {
	function authenticateHttpRequest(Model_ConnectedAccount $account, &$ch, &$verb, &$url, &$body, &$headers);
}

interface IServiceProvider_OAuth {
	function oauthRender();
	function oauthCallback();
}

interface IServiceProvider_OAuthRefresh {
	function oauthRefreshAccessToken(Model_ConnectedAccount $account);
}

class WgmCerb_API {
	private $_base_url = '';
	private $_access_key = '';
	private $_secret_key = '';

	public function __construct($base_url, $access_key, $secret_key) {
		$this->_base_url = $base_url;
		$this->_access_key = $access_key;
		$this->_secret_key = $secret_key;
	}

	private function _getBaseUrl() {
		return rtrim($this->_base_url, '/') . '/rest/';
	}
	
	public function get($path) {
		return $this->_connect('GET', $path);
	}

	public function put($path, $payload=array()) {
		return $this->_connect('PUT', $path, $payload);
	}

	public function post($path, $payload=array()) {
		return $this->_connect('POST', $path, $payload);
	}

	public function delete($path) {
		return $this->_connect('DELETE', $path);
	}

	private function _sortQueryString($query) {
		// Strip the leading ?
		if(substr($query,0,1)=='?') $query = substr($query,1);
		$args = array();
		$parts = explode('&', $query);
		foreach($parts as $part) {
			$pair = explode('=', $part, 2);
			if(is_array($pair) && 2==count($pair))
				$args[$pair[0]] = $part;
		}
		ksort($args);
		return implode("&", $args);
	}
	
	function signHttpRequest($url, $verb, $http_date, $postfields) {
		// Authentication
		$url_parts = parse_url($url);
		$url_path = $url_parts['path'];
		
		$verb = DevblocksPlatform::strUpper($verb);

		$url_query = '';
		if(isset($url_parts['query']) && !empty($url_parts))
			$url_query = $this->_sortQueryString($url_parts['query']);

		$secret = DevblocksPlatform::strLower(md5($this->_secret_key));

		$string_to_sign = "$verb\n$http_date\n$url_path\n$url_query\n$postfields\n$secret\n";
		$hash = md5($string_to_sign);
		return sprintf("%s:%s", $this->_access_key, $hash);
	}

	private function _connect($verb, $path, $payload=null) {
		// Prepend the base URL and normalize the given path
		$url = $this->_getBaseUrl() . ltrim($path, '/');
		
		$header = array();
		$ch = DevblocksPlatform::curlInit();

		$verb = DevblocksPlatform::strUpper($verb);
		$http_date = gmdate(DATE_RFC822);

		$header[] = 'Date: '.$http_date;
		$header[] = 'Content-Type: application/x-www-form-urlencoded; charset=utf-8';

		$postfields = '';

		if(!is_null($payload)) {
			if(is_array($payload)) {
				foreach($payload as $pair) {
					if(is_array($pair) && 2==count($pair))
						$postfields .= $pair[0].'='.rawurlencode($pair[1]) . '&';
				}
				rtrim($postfields,'&');

			} elseif (is_string($payload)) {
				$postfields = $payload;
			}
		}

		// HTTP verb-specific options
		switch($verb) {
			case 'DELETE':
				curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
				break;

			case 'GET':
				break;

			case 'PUT':
				$header[] = 'Content-Length: ' .  strlen($postfields);
				curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
				curl_setopt($ch, CURLOPT_POSTFIELDS, $postfields);
				break;

			case 'POST':
				$header[] = 'Content-Length: ' .  strlen($postfields);
				curl_setopt($ch, CURLOPT_POST, 1);
				curl_setopt($ch, CURLOPT_POSTFIELDS, $postfields);
				break;
		}

		// Authentication
		
		if(false == ($signature = $this->signHttpRequest($url, $verb, $http_date, $postfields)))
			return false;
		
		// [TODO] Use Authoriztion w/ bearer
		$header[] = 'Cerb-Auth: ' . $signature;
		
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

		$output = DevblocksPlatform::curlExec($ch);

		$info = curl_getinfo($ch);
		
		// Content-type handling
		@list($content_type,) = explode(';', DevblocksPlatform::strLower($info['content_type']));
		
		curl_close($ch);
		
		switch($content_type) {
			case 'application/json':
			case 'text/javascript':
				return json_decode($output, true);
				break;
				
			default:
				return $output;
				break;
		}
	}
};

class ServiceProvider_Cerb extends Extension_ServiceProvider implements IServiceProvider_HttpRequestSigner {
	const ID = 'core.service.provider.cerb';
	
	function renderConfigForm(Model_ConnectedAccount $account) {
		$tpl = DevblocksPlatform::services()->template();
		$active_worker = CerberusApplication::getActiveWorker();
		
		$params = $account->decryptParams($active_worker);
		$tpl->assign('params', $params);
		
		$tpl->display('devblocks:cerberusweb.core::internal/connected_account/providers/cerb.tpl');
	}
	
	function saveConfigForm(Model_ConnectedAccount $account, array &$params) {
		@$edit_params = DevblocksPlatform::importGPC($_POST['params'], 'array', array());
	
		if(!isset($edit_params['base_url']) || empty($edit_params['base_url']))
			return "The 'Base URL' is required.";
		
		if(!isset($edit_params['access_key']) || empty($edit_params['access_key']))
			return "The 'Access Key' is required.";
		
		if(!isset($edit_params['secret_key']) || empty($edit_params['secret_key']))
			return "The 'Secret Key' is required.";
		
		// Test the credentials
		$cerb = new WgmCerb_API($edit_params['base_url'], $edit_params['access_key'], $edit_params['secret_key']);
		
		$json = $cerb->get('workers/me.json');
		
		if(!is_array($json) || !isset($json['__status']))
			return "Unable to connect to the API. Please check your URL.";
		
		if($json['__status'] == 'error')
			return $json['message'];
		
		foreach($edit_params as $k => $v)
			$params[$k] = $v;
		
		return true;
	}
	
	function authenticateHttpRequest(Model_ConnectedAccount $account, &$ch, &$verb, &$url, &$body, &$headers) {
		$credentials = $account->decryptParams();
		
		if(
			!isset($credentials['base_url'])
			|| !isset($credentials['access_key'])
			|| !isset($credentials['secret_key'])
			|| !is_array($headers)
		)
			return false;
		
		$http_date = gmdate(DATE_RFC822);
		$found_date = false;
		
		foreach($headers as $header) {
			list($k, $v) = explode(':', $header, 2);
			
			if(0 == strcasecmp($k, 'Date')) {
				$http_date = ltrim($v);
				$found_date = true;
				break;
			}
		}
		
		// Add a Date: header if one didn't exist
		if(!$found_date)
			$headers[] = 'Date: ' . $http_date;
		
		$cerb = new WgmCerb_API($credentials['base_url'], $credentials['access_key'], $credentials['secret_key']);
		
		if(false == ($signature = $cerb->signHttpRequest($url, $verb, $http_date, $body)))
			return false;
		
		$headers[] = 'Cerb-Auth: ' . $signature;
		return true;
	}
}

