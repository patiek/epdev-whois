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
//	Image Class
//	General Image Manipulation Class
//	Date: 4/2/2006
/* ------------------------------------------------------------------ */

class EP_Dev_Whois_Image
{
	var $CORE;

	var $IMAGES;

	var $text_color;
	var $bg_color;

	function EP_Dev_Whois_Image(&$global)
	{
		$this->IMAGES = array();
		$this->reloadCore($global);
	}


	/* ------------------------------------------------------------------ */
	//	Reload Core
	//	Reloads the $global core object with updated links.
	/* ------------------------------------------------------------------ */
	
	function reloadCore(&$global)
	{
		$this->CORE =& $global;
	}


	function createImage($string, $imageWidth=null, $imageHeight=null, $font=5, $noise=0)
	{
		$imageFont = $font;
		$fontWidth = imagefontwidth($imageFont);
		$fontHeight = imagefontheight($imageFont);

		$id = count($this->IMAGES);

		if ($imageWidth === null)
		{
			$imageWidth = (strlen($string) * $fontWidth) + 2;
		}

		if ($imageHeight === null)
		{
			$imageHeight = $fontHeight + 2;
		}


		$this->IMAGES[$id] = ImageCreateTrueColor($imageWidth, $imageHeight);
		$this->background = ImageColorAllocate($this->IMAGES[$id], 255, 255, 255);
		ImageFill($this->IMAGES[$id], 0, 0, $this->background);
		$this->text_color = ImageColorAllocate($this->IMAGES[$id], 0, 0, 0);
		ImageString ($this->IMAGES[$id], $imageFont, round(($imageWidth - ($fontWidth*strlen($string)))/2), round(($imageHeight/2) - ($fontHeight/2)), $string, $this->text_color);

		for($i=0; $i<$noise; $i++)
			ImageSetPixel($this->IMAGES[$id], rand(1, $imageWidth-1), rand(1, $imageHeight-1), $this->text_color);


		return $id;
	}


	function saveAllImages($filename_start, $filename_end)
	{
		for($i=0; $i<count($this->IMAGES); $i++)
		{
			$this->saveImage($this->IMAGES[$i], $filename_start . $i . $filename_end);
		}
	}


	function saveImage($id, $filename)
	{
		ImagePNG($this->IMAGES[$id], $filename);
	}


	function destroyAllImages()
	{
		for($i=0; $i<count($this->IMAGES); $i++)
		{
			$this->destroyImage($i, true);
		}

		unset($this->IMAGES);
	}


	function destroyImage($id, $skip=false)
	{
		@ImageDestroy($this->IMAGES[$id]);

		if (!$skip)
			unset($this->IMAGES[$id]);
	}


	function displayImage($id)
	{
		Header("Content-type: image/png");
		ImagePNG($this->IMAGES[$id], '', 75);
		ImageDestroy($this->IMAGES[$id]);

		// a hard die
		die();
	}
}