<?php
	function parseHeaders($headers)
	{
		$head = array();
		foreach($headers as $k=>$v)
		{
			$t = explode(':', $v, 2);
			if(isset($t[1]))
				$head[trim($t[0])] = trim($t[1]);
			else
			{
				$head[] = $v;
				if(preg_match( "#HTTP/[0-9\.]+\s+([0-9]+)#", $v, $out))
					$head['response_code'] = intval($out[1]);
			}
		}
		return $head;
	}
	
	/** 
	* Send a POST requst using cURL 
	* @param string $url to request 
	* @param array $post values to send 
	* @param array $options for cURL 
	* @return string 
	*/
	function curl_post($url, array $post = NULL, array $options = array()) 
	{ 
		$defaults = array( 
			CURLOPT_POST => 1, 
			CURLOPT_HEADER => 0, 
			CURLOPT_URL => $url, 
			CURLOPT_FRESH_CONNECT => 1, 
			CURLOPT_RETURNTRANSFER => 1, 
			CURLOPT_FORBID_REUSE => 1, 
			CURLOPT_TIMEOUT => 4, 
			CURLOPT_POSTFIELDS => http_build_query($post) 
		); 

		$ch = curl_init(); 
		curl_setopt_array($ch, ($options + $defaults)); 
		if( ! $result = curl_exec($ch)) 
		{ 
			trigger_error(curl_error($ch)); 
		} 
		curl_close($ch); 
		return $result; 
	} 

	/** 
	* Send a GET requst using cURL 
	* @param string $url to request 
	* @param array $get values to send 
	* @param array $options for cURL 
	* @return string 
	*/
	function curl_get($url, array $get = NULL, array $options = array()) 
	{    
		$defaults = array( 
			CURLOPT_URL => $url. (strpos($url, '?') === FALSE ? '?' : ''). http_build_query($get), 
			CURLOPT_HEADER => 0, 
			CURLOPT_RETURNTRANSFER => TRUE, 
			CURLOPT_TIMEOUT => 10
		); 
		
		$ch = curl_init(); 
		curl_setopt_array($ch, ($options + $defaults)); 
		if( ! $result = curl_exec($ch)) 
		{ 
			trigger_error(curl_error($ch)); 
		} 
		curl_close($ch); 
		return $result; 
	}
?>