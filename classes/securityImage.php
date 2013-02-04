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
//	Security Image Class
//	Contains all methods relating to security image verification.
//	Date: 4/2/2006
/* ------------------------------------------------------------------ */

class EP_Dev_Whois_SecurityImage
{
	var $CORE;

	var $noise;

	function EP_Dev_Whois_SecurityImage(&$global)
	{
		$this->reloadCore($global);

		$this->noise = 200;
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
	//	Display Image
	//	Display Security Image with code based on $random_number.
	/* ------------------------------------------------------------------ */

	function displayImage($random_number)
	{
		$imageFont = 5;
		$imageWidth = 90;
		$imageHeight = 40;
		$fontWidth = imagefontwidth($imageFont);
		$fontHeight = imagefontheight($imageFont);

		// +------------------------------
		//	Generate Image & Display
		// +------------------------------

		$code = $this->getSecurityString($random_number);
		
		/*
		// insert spaces (makes it look a bit better)
		for($i=0; $i<strlen($code); $i++)
			$codeFormatted .= " " . $code[$i];
		*/

		//$code = trim($codeFormatted);

		$image = ImageCreateFromPNG($this->CORE->CONFIG->SCRIPT['absolute_path'] . $this->CORE->CONFIG->FILES['folder']['images'] . "codeBack-" . rand(1, 5) . ".png");
		$text_color = ImageColorAllocate($image, 80, 80, 80);
		header("Content-type: image/png");

		// random noise
		for($i=0; $i<$this->noise; $i++)
			ImageSetPixel($image, rand(1, $imageWidth-1), rand(1, $imageHeight-1), $text_color);

		$posX = round(($imageWidth - ($fontWidth*((strlen($code)*2)-1)))/2);

		for($i=0; $i<strlen($code); $i++)
		{
			ImageString($image, $imageFont, $posX+($fontWidth*2*$i), rand(2, $imageHeight-$fontHeight-2), $code[$i], $text_color);
		}

		ImagePNG($image);
		ImageDestroy($image);

		// a hard die
		die();
	}


	/* ------------------------------------------------------------------ */
	//	Get Security String
	//	Generate a security string based on $random_number
	/* ------------------------------------------------------------------ */
	
	function getSecurityString($random_number, $length=5)
	{
		// base string on server_name (unique to site), random number (unique to id), user ip (unique to user)
		$string = strtoupper(base_convert(md5 ( md5 ($_SERVER['SERVER_NAME'] . $random_number . $_SERVER['REMOTE_ADDR']) ), 16, 36));

		// replace any conflicting characters:
		$searches = array ("0", "O", "I", "1", "S", "5", "B", "8", "V", "U", "Q", "7", "Z", "G", "6");
		$replacements = array ("A", "F", "C", "D", "T", "T", "N", "N", "K", "K", "J", "X", "P", "F", "L");
		$string = str_replace($searches, $replacements, $string);

		return substr($string, 2, $length);
	}


	/* ------------------------------------------------------------------ */
	//	Get Random Number
	//	Generates a number specific to server, user, and current time.
	/* ------------------------------------------------------------------ */
	
	function getRandomNumber($unique="")
	{
		// number is not actually random:
		// number is generated based on time and environment
		return (time() + floatval(
									base_convert(
												substr(
														md5($_SERVER['SERVER_NAME'] . "ep_dev" . $_SERVER['REMOTE_ADDR'] . $unique),
														10,
														10
													   ),
												16,
												10
											)
									)
							);
	}


	/* ------------------------------------------------------------------ */
	//	Check Random Number
	//	Checks a random number to ensure that it is valid for the server,
	//	the user, and is not too old (based on $minutes).
	/* ------------------------------------------------------------------ */
	
	function checkRandomNumber($number, $minutes=10, $unique="")
	{
		// get current number
		$current = $this->getRandomNumber($unique);

		// return true if random number is less than 10 minutes old
		return ($current - $number < 60*$minutes);
	}


	
	/* ------------------------------------------------------------------ */
	//	Check Security String
	//	Checks a random number and user input for a match.
	//
	//	RETURNS: true / false on success / failure
	/* ------------------------------------------------------------------ */

	function checkSecurityString($securityNumber, $userInput, $unique="")
	{
		return ($this->checkRandomNumber($securityNumber, 10, $unique) && $this->getSecurityString($securityNumber) == str_replace(" ", "", strtoupper($userInput)));
	}
}