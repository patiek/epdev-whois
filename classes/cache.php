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
//	EP-Dev Whois Cache Class
//	Contains functions used for Caching queries.
//	Date: 4/2/2006
/* ------------------------------------------------------------------ */

class EP_Dev_Whois_Cache
{
	var $CORE;

	var $cacheFolder;

	function EP_Dev_Whois_Cache(&$global)
	{
		$this->reloadCore($global);
		$this->cacheFolder = $this->CORE->CONFIG->SCRIPT['absolute_path'] . $this->CORE->CONFIG->SCRIPT['CACHE']['folder'];
	}


	/* ------------------------------------------------------------------ */
	//	Reload Core
	//	Reloads the $global core object with updated links.
	/* ------------------------------------------------------------------ */
	
	function reloadCore(&$global)
	{
		$this->CORE =& $global;
	}


	function createTimeFile()
	{
		// create purge file
		$this->writeCache("", $this->cacheFolder . "purge.epc");
	}


	// return cache length in seconds
	function getCacheLength()
	{
		return $this->CORE->CONFIG->SCRIPT['CACHE']['time'] * 60;
	}


	
	/* ------------------------------------------------------------------ */
	//	Get Purge Time
	//	Get the last purge time.
	/* ------------------------------------------------------------------ */
	function getPurgeTime()
	{
		// retrieve purge data
		$purge_data = @include($this->cacheFolder . "purge.epc");

		// if purge time file is missing
		if ($purge_data === false)
		{
			$this->createTimeFile();

			// return current time
			return time();
		}

		// else return purge time
		{
			return $purge_data[0];
		}
	}



	/* ------------------------------------------------------------------ */
	//	Purge old data
	//	Removes all files determined to be older than purge time.
	/* ------------------------------------------------------------------ */
	function purge()
	{
		// get last purge time
		$purgeTime = $this->getPurgeTime();

		// check if it is time to purge again
		// (every 15 minutes or cache time ~ whichever is greatest)
		if (time() - $purgeTime > max((60*15), $this->CORE->CONFIG->SCRIPT['CACHE']['time']))
		{
			// open directory
			$dir_handle = opendir($this->cacheFolder);

			// read files in directory, deleting if old
			while (($file = readdir($dir_handle)) !== false)
			{
				// if not .epc file, skip file
				if (substr($file, -4) != ".epc")
					continue;

				// get file's contents
				$current_data = @include($this->cacheFolder . $file);

				if ($current_data !== false)
				{
					// if file time is older than cache time, remove file
					if ($current_data[0] < (time() - ($this->CORE->CONFIG->SCRIPT['CACHE']['time'] * 60)))
						unlink($this->cacheFolder . $file);
				}
			}

			// update purge file
			$this->createTimeFile();
		}
	}


	
	/* ------------------------------------------------------------------ */
	//	Put Cache
	//	Store the cache $data for the specified $domain and $tld.
	/* ------------------------------------------------------------------ */
	function putCache($domain, $tld, $type, $data)
	{
		// if cache is disabled, return
		if ($this->CORE->CONFIG->SCRIPT['CACHE']['time'] <= 0)
		{
			return;
		}

		// generate filename
		$filename = $this->createFilename($domain, $tld, $type);

		// write to cache file
		$this->writeCache($data, $filename) ;
	}


	/* ------------------------------------------------------------------ */
	//	Get Cache
	//	Get the cache for the specified $domain and $tld, if available.
	//
	//	RETURN:
	//		returns cached whois data on success (string or array)
	//		returns false if no cache is available or too old.
	/* ------------------------------------------------------------------ */
	function getCache($domain, $tld, $type)
	{
		/*

		FILE:

		$time = TIME;
		$data = "":
		return array($time, $data);

		*/

		// if cache is disabled, return false
		if ($this->CORE->CONFIG->SCRIPT['CACHE']['time'] <= 0)
		{
			return false;
		}


		// purge old files (call function that attempts to)
		$this->purge();


		$filename = $this->createFilename($domain, $tld, $type);

		// retrieve cached data
		$cached_data = @include($filename);

		// NOTE: Two return statements, one if data retrieved, the other if error / not up-to-date

		if ($cached_data !== false)
		{
			if ($cached_data[0] > (time() - $this->CORE->CONFIG->SCRIPT['CACHE']['time'] * 60))
				return $cached_data[1];
		}

		return false;
	}


	/* ------------------------------------------------------------------ */
	//	Create Filename
	//	Generate a filename based on domain, tld.
	/* ------------------------------------------------------------------ */
	function createFilename($domain, $tld, $type)
	{
		return $this->cacheFolder . md5("eP_dEv{$type}__{$domain}__{$tld}" . $_SERVER['SERVER_NAME']) . ".epc" ;
	}


	/* ------------------------------------------------------------------ */
	//	Write cache
	//	Writes $data to $filename, if possible.
	//
	//	$data can be string or array of strings
	//
	//	NOTE: This function is silent on failure.
	/* ------------------------------------------------------------------ */
	function writeCache($data, $filename)
	{
		if (is_array($data))
		{
			for($i=0; $i<count($data); $i++)
				$data[$i] = str_replace("\'", "'", addslashes($data[$i]));

			$data_output = "array(\"" . implode("\",\"", $data) . "\")";
		}
		else
		{
			$data_output = "\"" . str_replace("\'", "'", addslashes($data)) . "\"";
		}

		$abort_pref = ignore_user_abort(true);

		$handle = @fopen($filename, "wb");

		if ($handle)
		{
			$file_data = "<?php\n\$time = " . time() . ";"
						. "\n\$data=" . $data_output . ";"
						. "\nreturn array(\$time, \$data);";

			// lock file
			flock($handle, LOCK_EX);
			
			// write to file
			@fwrite($handle, $file_data);

			// unlock file
			flock($handle, LOCK_UN);

			// close
			@fclose($file_data);
		}

		ignore_user_abort($abort_pref);
	}



}




