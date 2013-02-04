<?php
// --------------------------------------------
// | The EP-Dev Whois script        
// |                                           
// | Copyright (c) 2003-2006 EP-Dev.com :           
// | This program is distributed as free       
// | software under the GNU General Public     
// | License as published by the Free Software 
// | Foundation. You may freely redistribute     
// | and/or modify this program.               
// |                                           
// --------------------------------------------

/* ------------------------------------------------------------------ */
//	Email Image Class
//	Contains all methods relating to parsing text for email addresses and
//	replacing such text with image representations of email addresses.
//	Date: 4/2/2006
/* ------------------------------------------------------------------ */

class EP_Dev_Whois_EmailImage
{
	var $CORE;

	var $purged;
	var $noise;

	function EP_Dev_Whois_EmailImage(&$global)
	{
		$this->reloadCore($global);
		$this->purged = false;

		$this->noise = 50;
	}


	/* ------------------------------------------------------------------ */
	//	Reload Core
	//	Reloads the $global core object with updated links.
	/* ------------------------------------------------------------------ */
	
	function reloadCore(&$global)
	{
		$this->CORE =& $global;
	}


	function isEnabled()
	{
		return ($this->CORE->CONFIG->SCRIPT['email_images'] == "gd");
	}


	function parseEmails(&$text)
	{
		// purge early (will prevent some complications down the road)
		$this->purge();

		$matchnum = preg_match_all("/([^\s\@()]+@{1}[^\s()]+)/i", $text, $matches);

		if ($matchnum)
		{
			// get first (and only) matched results
			$emails = $matches[1];

			foreach($emails as $email)
			{
				// generate image
				$email_image_location = $this->generateEmailImage($email);

				// replace email of whois report with generated image
				$text = str_replace($email, "<img src=\"{$email_image_location}\" alt=\"\" style=\"vertical-align: middle;\"/>", $text);
			}

			// destroy all images in memory (they are generated, no need to keep them around)
			$this->CORE->IMAGE->destroyAllImages();
		}
	}


	function generateEmailImage($email)
	{
		$filename_hash = md5("ep_dev" . $email . $_SERVER['SERVER_NAME']);

		$filename = $this->CORE->CONFIG->SCRIPT['absolute_path'] . $this->CORE->CONFIG->FILES['folder']['images'] . $filename_hash . ".png";

		if (!file_exists($filename))
		{
			$abort_pref = ignore_user_abort(true);
			
			$id = $this->CORE->IMAGE->createImage($email, null, null, 4, $this->noise);

			// save image
			$this->CORE->IMAGE->saveImage($id, $filename);

			ignore_user_abort($abort_pref);
		}

		return $this->CORE->CONFIG->SCRIPT['SITE']['url'] . $this->CORE->CONFIG->FILES['folder']['images'] . $filename_hash . ".png";
	}


	function createTimeFile()
	{
		// purge file
		$filename = $this->CORE->CONFIG->SCRIPT['absolute_path'] . $this->CORE->CONFIG->FILES['folder']['images'] . "purge.epc";

		// simple touch to update modification time
		touch($filename);
	}


	function purge()
	{
		if ($this->purged)
			return;

		// purge file
		$filename = $this->CORE->CONFIG->SCRIPT['absolute_path'] . $this->CORE->CONFIG->FILES['folder']['images'] . "purge.epc";

		// create time file if it doesn't exist
		if (!file_exists($filename))
			$this->createTimeFile();

		// erase images every 10 minutes
		$timeCheck = 60*10;


		if (time() - filemtime($filename) > 60*10)
		{
			// update time file
			$this->createTimeFile();

			// remove all old images (not accessed within 10 mintues if possible)

			// open directory
			$dir_handle = opendir($this->CORE->CONFIG->SCRIPT['absolute_path'] . $this->CORE->CONFIG->FILES['folder']['images']);

			// read files in directory, deleting if old
			while (($file = readdir($dir_handle)) !== false)
			{
				// if not .epc file, skip file
				if (substr($file, -4) != ".png" || substr($file, 0, 8) == "codeBack")
					continue;

				$fileAccessTime = fileatime($this->CORE->CONFIG->SCRIPT['absolute_path'] . $this->CORE->CONFIG->FILES['folder']['images'] . $file);

				// if error in access time or if access time is more than ten minutes old, delete
				if (time() - $fileAccessTime > $timeCheck)
				{
					unlink($this->CORE->CONFIG->SCRIPT['absolute_path'] . $this->CORE->CONFIG->FILES['folder']['images'] . $file);
				}
			}
		}

		$this->purged = true;
	}
}