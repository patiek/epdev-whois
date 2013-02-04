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
//	EP-Dev Whois Domains Class
//	Contains on global domain functions.
//	Date: 4/2/2006
/* ------------------------------------------------------------------ */

class EP_Dev_Whois_Domains
{
	var $CORE;

	var $SERVERS;
	var $SERVERS_ENABLED;
	var $SERVERS_DISABLED;

	var $IDN;

	var $idnExtensions;

	var $allExtensions;

	var $allServers = false;


	function EP_Dev_Whois_Domains(&$global)
	{
		$this->reloadCore($global);

		// load IDN converter if enabled
		if ($this->CORE->CONFIG->SCRIPT['translate_idn'])
		{
			// parse extensions to see which are enable for IDN
			if (!empty($this->CORE->CONFIG->SCRIPT['idn_extensions']))
				$this->idnExtensions = explode(",", str_replace(" ", "", $this->CORE->CONFIG->SCRIPT['idn_extensions']));
			else
				$this->idnExtensions = array(); // enable all extensions

			$this->CORE->includeFile($this->CORE->CONFIG->FILES['file']['idn']);

			$this->IDN = new EP_Dev_Whois_IDNA(array("strict" => true));
		}
		else
		{
			$this->IDN = null;
		}

		$this->fetchAllServers();
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
	//	Validate Domain
	//	
	//	returns true / false on success / failure
	/* ------------------------------------------------------------------ */
	function validateDomain(&$domain, &$extensions, $silent = false)
	{
		// error result
		$validation_result = true;

		if (is_array($extensions))
			$ext = $extensions[0];
		else
			$ext = $extensions;

		// change to lowercase
		$ext = strtolower($ext);
		//$domain = strtolower($domain);

		// remove www
		$domain = preg_replace("/^www\./i", "", $domain);
		
		// remove if contains extension
		if (($end_str = strchr($domain, ".")) !== false)
		{
			if (in_array(substr(strtolower($end_str), 1), $this->getAllExtensions()))
			{
				$domain = str_replace($end_str, "", $domain);
				$extensions = array_merge(array(substr($end_str, 1)), $extensions);
			}
			else
			{
				$domain = substr($end_str, 1);

				// recursive
				$this->validateDomain($domain, $extensions);
			}
		}


		// build array of extension(s) for verification
		if (is_array($extensions))
			$extensions_to_verify = $extensions;
		else
			$extensions_to_verify = array($extensions);


		// get IDN representation
		foreach($extensions_to_verify as $current_extension)
		{
			if (($idn_domain = $this->getPunycode($domain, $current_extension)) !== false)
			{
				// check format
				if (!ereg("^[a-zA-Z0-9]+[a-zA-Z0-9-]*[a-zA-Z0-9]+$", $idn_domain))
				{
					$validation_result = false;

					if (!$silent)
						$this->CORE->ERROR->stop("domain_badformat");
				}
				elseif ($idn_domain == $domain)
				{
					$domain = strtolower($domain);
				}


				// check length
				if ((strlen($idn_domain) > 63) || (strlen($idn_domain) < 2))
				{
					$validation_result = false;

					if (!$silent)
						$this->CORE->ERROR->stop("domain_badlength");
				}
			}

			// error (a false means IDN conversion failed)
			else
			{
				$validation_result = false;

				// bad format
				if (!$silent)
					$this->CORE->ERROR->stop("domain_badformat");
			}
		}

		return $validation_result;
	}


	function getPunycode($domain, $ext)
	{
		// if IDN enabled, use it
		if ($this->CORE->CONFIG->SCRIPT['translate_idn'])
		{
			// if all extensions enable or if extension exists in array, return punycode
			if (empty($this->idnExtensions) || in_array($ext, $this->idnExtensions))
				return $this->IDN->encode($domain);
			else
				return $domain;
		}

		// simply return domain (no conversion done)
		else
		{
			return $domain;
		}
	}


	function getServer($ext, $id)
	{
		// if all servers
		if (isset($this->SERVERS[$ext][$id]) && $this->allServers)
			return $this->SERVERS[$ext][$id];
		else if (isset($this->SERVERS_ENABLED[$ext][$id]) && !$this->allServers) // only enabled servers
			return $this->SERVERS_ENABLED[$ext][$id];
		else // if no more servers
			return false;
	}


	function getPrice($ext)
	{
		return $this->CORE->currencyConvert($this->CORE->CONFIG->BUYMODE['PRICES'][$ext]);
	}


	function setDisabledNameserverUse($use_disabled_nameservers)
	{
		$this->allServers = $use_disabled_nameservers;
	}


	function getAllExtensions($alphabetize = "")
	{
		// detect if need alphabetize
		if (
				(
					(
						$this->CORE->CONFIG->SCRIPT['TLD_DISPLAY']['alphabetize']
						&& empty($this->CORE->CONFIG->SCRIPT['TLD_DISPLAY']['include'])
					)
					|| $alphabetize === true
				)
				&& $alphabetize !== false
			)
		{
			$allExtensionsKey = "alphabetized";
		}
		else
		{
			$allExtensionsKey = "default";
		}

		if (empty($this->allExtensions[$allExtensionsKey]))
		{
			// if pulling enabled and disabled extensions
			if ($this->allServers)
				$this->allExtensions[$allExtensionsKey] = array_keys($this->SERVERS);
			else // if pulling only enabled
				$this->allExtensions[$allExtensionsKey] = array_keys($this->SERVERS_ENABLED);

			// alphabetize results
			if (
					(
						(
							$this->CORE->CONFIG->SCRIPT['TLD_DISPLAY']['alphabetize']
							&& empty($this->CORE->CONFIG->SCRIPT['TLD_DISPLAY']['include'])
						)
						|| $alphabetize === true
					)
					&& $alphabetize !== false
				)
			{
					asort($this->allExtensions[$allExtensionsKey]);
			}
		}

		return $this->allExtensions[$allExtensionsKey];
	}


	function getDisplayExtensions()
	{
		if (empty($this->CORE->CONFIG->SCRIPT['TLD_DISPLAY']['include']))
			return $this->getAllExtensions();
		else
			return explode(",", str_replace(" ", "", $this->CORE->CONFIG->SCRIPT['TLD_DISPLAY']['include']));
	}

	function getPriceTableExtensions()
	{
		if (empty($this->CORE->CONFIG->BUYMODE['PRICETABLE']['include']))
			return $this->getAllExtensions(false);
		else
			return explode(",", str_replace(" ", "", $this->CORE->CONFIG->BUYMODE['PRICETABLE']['include']));
	}


	function getAutoSearchExtensions()
	{
		if (empty($this->CORE->CONFIG->BUYMODE['CONFIG']['tld_search']))
			return array();
		else
			return explode(",", str_replace(" ", "", $this->CORE->CONFIG->BUYMODE['CONFIG']['tld_search']));
	}


	function getDomainSuggestions($domain, $ext)
	{
		if (empty($this->CORE->CONFIG->BUYMODE['CONFIG']['domain_suggestions']))
			return array();
		else
			return str_replace(
						array("[[DOMAIN]]", "[[EXT]]"),
						array($domain, $ext),
						explode(",", str_replace(" ", "", $this->CORE->CONFIG->BUYMODE['CONFIG']['domain_suggestions']))
					);
	}


	function fetchAllServers()
	{
		foreach($this->CORE->CONFIG->NAMESERVERS as $nameserver => $array_val)
		{

			// if nameserver enabled
			if ($array_val['enabled'])
			{
				// fetch enabled extensions
				$extensions = explode(",", str_replace(" ", "", $array_val['extensions']));

				// add extensions to enabled array
				foreach($extensions as $current_ext)
				{
					$this->SERVERS_ENABLED[$current_ext][] = $nameserver;
					$this->SERVERS[$current_ext][] = $nameserver;
				}
			}

			// else assume nameserver is disabled
			else
			{
				// fetch enabled extensions
				$extensions = explode(",", str_replace(" ", "", $array_val['extensions']));

				// add extensions to disabled array
				foreach($extensions as $current_ext)
				{
					$this->SERVERS_DISABLED[$current_ext][] = $nameserver;
					$this->SERVERS[$current_ext][] = $nameserver;
				}
			}

			// fetch disabled extensions
			$disabled_extensions = explode(",", str_replace(" ", "", $array_val['extensions_disabled']));

			// add extensions to disabled array
			foreach($disabled_extensions as $current_ext)
			{
				$this->SERVERS_DISABLED[$current_ext][] = $nameserver;
				$this->SERVERS[$current_ext][] = $nameserver;
			}
		}
	}


}