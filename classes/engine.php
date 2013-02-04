<?php
// --------------------------------------------
// | The EP-Dev Whois script        
// |                                           
// | Copyright (c) 2003-2005 EP-Dev.com :           
// | This program is distributed as free       
// | software under the GNU General Public     
// | License as published by the Free Software 
// | Foundation. You may freely redistribute     
// | and/or modify this program.               
// |                                           
// --------------------------------------------


/* ------------------------------------------------------------------ */
//	EP-Dev Whois Engine Class
//	Contains on functions related to single domain query. Acts as query
//	object for each domain search.
//	Date: 4/2/2006
/* ------------------------------------------------------------------ */

class EP_Dev_Whois_Engine
{
	var $CORE;


	var $domain;
	var $ext;
	var $server;
	var $server_id;

	var $available;
	var $whoisData;

	var $fullWhois;


	function EP_Dev_Whois_Engine($domain, $ext, &$global)
	{
		$this->reloadCore($global);

		$this->domain = $domain;

		if ($ext{0} == ".")
			$this->ext = substr($ext, 1);
		else
			$this->ext = $ext;

		// server id
		$this->server_id = 0;

		// find server
		$this->server = $this->getServer();
	}


	/* ------------------------------------------------------------------ */
	//	Reload Core
	//	Reloads the $global core object with updated links.
	/* ------------------------------------------------------------------ */
	
	function reloadCore(&$global)
	{
		$this->CORE =& $global;
	}


	/* ------------------------------------------------------------------ */
	//	Lookup Domain
	//	Looks up domain by connecting to its server and downloading whois.
	//
	//	Parameters: 
	//		$this->fullWhois = true will force download of whois report.
	//	
	//	return:
	//		true: if successful in getting report
	//		false: unsuccessful (caused by error)
	/* ------------------------------------------------------------------ */
	
	function lookup($full_whois = false)
	{
		$this->fullWhois = $full_whois;

		// +------------------------------
		//	Cache Feature
		// +------------------------------

		// detect cache type
		if (
					$this->CORE->CONFIG->SCRIPT['limit_bypass'] 
				&& !$this->fullWhois
				&& !empty($this->CORE->CONFIG->NAMESERVERS[$this->server]['limit_format'])
				&& (
					$this->CORE->CONFIG->NAMESERVERS[$this->server]['limit_format'] != $this->CORE->CONFIG->NAMESERVERS[$this->server]['format']
					||
					$this->CORE->CONFIG->NAMESERVERS[$this->server]['advanced_limit_custom_query'] != $this->CORE->CONFIG->NAMESERVERS[$this->server]['advanced_custom_query']
				   )
			)
		{
			$cache_type = "limit";
		}
		else
		{
			$cache_type = "full";
		}

		// if cached data is still valid
		if (($cache_data = $this->CORE->CACHE->getCache($this->CORE->DOMAINS->getPunycode($this->domain, $this->ext), $this->ext, $cache_type)) !== false)
		{
			$used_limit = ($cache_type == "limit");

			// this should always be an array, but it could technically be just a string
			if (is_array($cache_data))
			{
				$this->whoisData = $cache_data[0];

				// determine availability prior to combining additional data
				$this->processAvailable($used_limit);

				$this->whoisData = trim($cache_data[1]) . "\r\n\r\n" . trim($this->whoisData);
			}
			else
			{
				$this->whoisData = $cache_data;

				// determine availability
				$this->processAvailable($used_limit);
			}

			$status = true;
		}

		// perform normal lookup operations
		else
		{

			// +------------------------------
			//	Main Lookup Operations
			// +------------------------------

			// determine address and port (NOTE c-slashes is used to preserve \r, \n, etc.)
			if (preg_match("#(.*?):([^:/]+):?([^/]*)(.*)#", stripcslashes($this->CORE->CONFIG->NAMESERVERS[$this->server]['advanced_custom_query_address']), $address_matches))
			{
				// detect address and port
				if (!is_numeric($address_matches[2]))
				{
					$port = $address_matches[3];
					$address = $address_matches[1] . ":" . $address_matches[2] . $address_matches[4];
				}
				else
				{
					$port = $address_matches[2];
					$address = $address_matches[1];
				}
			}

			// attempt default settings if we couldn't find a match
			else
			{
				$port = 43;
				$address = $this->server;
			}

			// if port is not a number
			if (!is_numeric($port))
			{
				// hard code an error, as this should probably be found by the webmaster before the client.
				$this->CORE->ERROR->kill("Bad custom address for nameserver {$this->server}. Please contact webmaster.");
			}

			// replace common var [[SERVER]]
			$address = str_replace("[[SERVER]]", $this->server, $address);


			// +------------------------------
			//	Whois Request
			// +------------------------------


			// generate whois request ($request, $used_limit are pass by ref)
			$this->generateWhoisRequest($request, $used_limit);

			// +------------------------------
			//	Send domain data and get report
			// +------------------------------
			
			// connect to server / get request
			$whoisSocket = $this->fetchWhoisData($address, $port, $this->CORE->CONFIG->NAMESERVERS[$this->server]['timeout'], $request, $this->whoisData);

			
			// check if successful
			if ($whoisSocket)
			{
				// +------------------------------
				//	Perform post-whois report operations
				// +------------------------------

				// determine availability
				$this->processAvailable($used_limit);

				$status = true;

				
				// if domain is not available and QUERYWHOIS is enabled, attempt to find / query whois server
				if (!$this->available && $this->CORE->CONFIG->SCRIPT['QUERYWHOIS']['enabled'])
				{
					if (($num_matches = preg_match_all(stripcslashes($this->CORE->CONFIG->SCRIPT['QUERYWHOIS']['regex']), $this->whoisData, $matches)) > 0)
					{

						// if more than one match, try to determine correct one
						if ($num_matches > 1)
						{
							if (preg_match("/((?:Domain|Server)\s*(?:Name)?: " . $this->CORE->DOMAINS->getPunycode($this->domain, $this->ext) . "." . $this->ext . ")\s+/is", $this->whoisData, $more_matches))
							{
								$offset_position = strpos($this->whoisData, $more_matches[1]);

								if (preg_match_all(stripcslashes($this->CORE->CONFIG->SCRIPT['QUERYWHOIS']['regex']), substr($this->whoisData, $offset_position), $new_matches))
								{
									$whoisServer = $new_matches[1][0];
								}
								else
								{
									$whoisServer = $matches[1][0];
								}
							}
							else
							{
								$whoisServer = $matches[1][0];
							}
						}

						// only one match means only one whois server
						else
						{
							$whoisServer = $matches[1][0];
						}


						// +------------------------------
						//	Send domain data and get report
						// +------------------------------

						$whoisSocketDeep = $this->fetchWhoisData($whoisServer, 43, 5, $this->CORE->DOMAINS->getPunycode($this->domain, $this->ext) . "." . $this->ext . "\r\n", $whoisData);

						if (!$whoisSocketDeep)
						{
							$whoisData = "";
						}
					}
				}				

				// store data into cache
				if ($this->whoisData != "")
					$this->CORE->CACHE->putCache($this->CORE->DOMAINS->getPunycode($this->domain, $this->ext), $this->ext, $cache_type, array($this->whoisData, $whoisData));

				// trim and combine whois information
				if ($whoisData != "")
					$this->whoisData = trim($whoisData) . "\r\n\r\n" . trim($this->whoisData);
			}
			
			elseif ($server = $this->getServer())
			{
				// use backup server
				$this->server = $server;
				$status = $this->lookup($this->fullWhois);
			}

			// else error with no server available
			else
			{
				$status = false;
			}
		}

		return $status;
	}


	function processReport()
	{
		$whoisData = $this->whoisData;

		// replace notices, terms of use, etc (if enabled)
		if ($this->CORE->CONFIG->SCRIPT['remove_notices'])
		{
			$removal_strings = array(
				"/(NOTICE|TERMS OF USE|NOTICE AND TERMS OF USE):.*?(\r\n\r\n|\n\n|\r\r)/is", // known
				"/([^\r\n]{45,}\r?\n){6,20}[^\.]*\.?(\r?\n)?/is" // 45+ char, 6+ line
			);

			// replace 5 spaces with tabs (helps prevent incorrect removal)
			$whoisData = str_replace("     ", "\t", $whoisData);

			// remove common notices
			$newData = preg_replace($removal_strings, "", $whoisData);

			// make sure it didn't backfire and we didn't remove everything
			if ($newData != "")
				$whoisData = $newData;
		}

		// convert html entities
		$whoisData = htmlentities(trim($whoisData));

		if ($this->fullWhois)
		{
			// convert images
			if ($this->CORE->EMAILIMAGE->isEnabled())
				$this->CORE->EMAILIMAGE->parseEmails($whoisData);
		}

		// convert new lines to <br>
		$whoisData = nl2br($whoisData);

		return $whoisData;
	}



	function processAvailable($used_limit)
	{
		// determine matching type
		if (strpos($this->CORE->CONFIG->NAMESERVERS[$this->server][($used_limit ? "limit_keyword" : "keyword")], "!!!") === 0)
		{
			$opposite = true;
			$keyword = substr($this->CORE->CONFIG->NAMESERVERS[$this->server][($used_limit ? "limit_keyword" : "keyword")], 3);
		}
		else
		{
			$opposite = false;
			$keyword = $this->CORE->CONFIG->NAMESERVERS[$this->server][($used_limit ? "limit_keyword" : "keyword")];
		}


		// determine if available
		if (ereg($keyword, $this->whoisData))
			$this->available = !$opposite;
		else
			$this->available = $opposite;
	}


	
	/* ------------------------------------------------------------------ */
	//	Fetch Whois Data
	//	Fetches the whois data for the current domain / extension using
	//	the $address, $port, $timeout, and $request specified. The whois 
	//	data will be stored into $data.
	//	
	//	return:
	//		true: if successful in connecting / fetching whois
	//		false: unsuccessful in connecting / fetching whois
	/* ------------------------------------------------------------------ */
	function fetchWhoisData($address, $port, $timeout, $request, &$data)
	{
		$whoisSocket = @fsockopen($address, $port, $errno, $errstr, $timeout);

		if (!$whoisSocket)
		{
			return false;
		}
		else
		{
			fputs($whoisSocket, $request);

			while(!feof($whoisSocket))
				$data .= fgets($whoisSocket, 128);

			fclose($whoisSocket);

			return true;
		}
	}


	/* ------------------------------------------------------------------ */
	//	Generate Whois Request
	//	Generates a whois request based on information given.
	//	
	//	parameters:
	//		$force_full_whois : true will force full whois report format
	//		$request:	A pass-by-ref that will hold $request
	//		$used_limit:A pass-by-ref that will hold whether request uses
	//					the limit keyword.
	/* ------------------------------------------------------------------ */
	function generateWhoisRequest(&$request, &$used_limit)
	{
		// if not full whois and limit bypass enabled and limit format is set
		if ($this->CORE->CONFIG->SCRIPT['limit_bypass'] && !$this->fullWhois
			&& !empty($this->CORE->CONFIG->NAMESERVERS[$this->server]['limit_format']))
		{
			$used_limit = true;

			// strip slashes & use custom query
			$request = stripcslashes(
				str_replace("[[QUERY]]", $this->CORE->CONFIG->NAMESERVERS[$this->server]['limit_format'], $this->CORE->CONFIG->NAMESERVERS[$this->server]['advanced_limit_custom_query'])
				);
		}
		
		// use normal format
		else
		{
			$used_limit = false;

			// strip slashes & use custom query
			$request = stripcslashes(
				str_replace("[[QUERY]]", $this->CORE->CONFIG->NAMESERVERS[$this->server]['format'], $this->CORE->CONFIG->NAMESERVERS[$this->server]['advanced_custom_query'])
				);
		}

		// replace templates with actual data
		$request = str_replace("[[DOMAIN]]", $this->CORE->DOMAINS->getPunycode($this->domain, $this->ext), $request);
		$request = str_replace("[[EXT]]", $this->ext, $request);
		$request = str_replace("[[SERVER]]", $this->server, $request);
	}


	function getServerName()
	{
		if (!empty($this->server))
			return $this->server;
		else
			return $this->CORE->DOMAINS->getServer($this->ext, 0);
	}


	function getAllServerNames()
	{
		$i = 0;
		while($this->CORE->DOMAINS->getServer($this->ext, $i) != false)
		{
			$allServers[] = $this->CORE->DOMAINS->getServer($this->ext, $i);
			$i++;
		}

		return $allServers;
	}


	function getServer()
	{

		if ($this->CORE->DOMAINS->getServer($this->ext, $this->server_id) != false)
		{
			$server = $this->CORE->DOMAINS->getServer($this->ext, $this->server_id);
			$this->server_id++;
		}
		
		else
		{
			$server = false;
		}

		return $server;
	}


	function isAvailable()
	{
		return $this->available;
	}


	function getWhoisReport($processing=true)
	{
		// +------------------------------
		//	Display formatting
		// +------------------------------
		// process report
		if ($processing)
			return $this->processReport();
		else
			return $this->whoisData;
	}


	function getDomain()
	{
		return $this->domain;
	}


	function getExt()
	{
		return $this->ext;
	}


	function getPrice()
	{
		return $this->CORE->DOMAINS->getPrice($this->getExt());
	}
}