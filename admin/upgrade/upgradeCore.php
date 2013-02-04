<?php
// --------------------------------------------
// | EP-Dev Whois        
// |                                           
// | Copyright (c) 2003-2005 Patrick Brown as EP-Dev.com           
// | This program is free software; you can redistribute it and/or modify
// | it under the terms of the GNU General Public License as published by
// | the Free Software Foundation; either version 2 of the License, or
// | (at your option) any later version.              
// | 
// | This program is distributed in the hope that it will be useful,
// | but WITHOUT ANY WARRANTY; without even the implied warranty of
// | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// | GNU General Public License for more details.
// --------------------------------------------


/* ------------------------------------------------------------------ */
//	Upgrade Core Class
//  Contains upgrade functions related to upgrading configuration of 
//	the script.
/* ------------------------------------------------------------------ */

class UpgradeCore
{
	var $adminPanel;

	function UpgradeCore(&$adminPanel)
	{
		$this->adminPanel =& $adminPanel;
	}


	function navigate($old_version, $new_version)
	{
		switch($_REQUEST['page'])
		{
			// +------------------------------
			//	UPGRADE PROCESS
			// +------------------------------

			case "goUpgrade" :
				$this->upgradeProcess($_REQUEST['type'], $new_version);
				$this->adminPanel->DISPLAY->MENU->blank();
				$this->adminPanel->page_Message("Upgrade Complete", $this->adminPanel->DISPLAY->constructOutput("The upgrade has completed. NOTE: Your absolute path has been reset.") . $this->adminPanel->getDonationBox() . $this->adminPanel->DISPLAY->constructOutput("Please <a href='" . basename($_SERVER['PHP_SELF']) . "'> continue to the admin panel</a>."));
			break;

			
			// +------------------------------
			//	Main Upgrader Page
			// +------------------------------
			
			default : 
			switch($old_version)
			{
				case "2.0" :
				case "2.01" :
					$type = "2.01";
					$this->defaultUpgradePage($old_version, $new_version, $type);
				break;

				case "2.1" :
				case "2.10" :
					$type = "2.1";
					$this->defaultUpgradePage($old_version, $new_version, $type);
				break;

				case "2.11" :
					$type = "2.11";
					$this->defaultUpgradePage($old_version, $new_version, $type);
				break;

				case "2.20" :
					$type = "2.20";
					$this->defaultUpgradePage($old_version, $new_version, $type);
				break;


				case "2.21" :
					$type = "2.21";
					$this->defaultUpgradePage($old_version, $new_version, $type);
				break;
				
				case "2.22" :
					$type = "2.22";
					$this->defaultUpgradePage($old_version, $new_version, $type);
				break;
				
				case "2.30" :
					$type = "2.30";
					$this->defaultUpgradePage($old_version, $new_version, $type);
				break;
				


				default : die("No Automatic Upgrade Found. Please install new update by uploading a fresh config/ folder and refreshing this page.");
			}
		}
	}



	/* ------------------------------------------------------------------ */
	//	Default Upgrade Page
	//	Displays generic upgrade page from $old_version to $new_version.
	/* ------------------------------------------------------------------ */
	
	function defaultUpgradePage($old_version, $new_version, $type)
	{
		$formURL = basename($_SERVER['PHP_SELF']);

		// default upgrade page.
			$this->adminPanel->DISPLAY->MENU->blank();
			$message = $this->adminPanel->DISPLAY->constructOutput("You are about to begin the process of upgrading from version {$old_version}
			to version {$new_version}. Please follow any on-screen instructions.<br><br>
			<span style=\"color: red\">IT IS HIGHLY RECOMMENDED THAT YOU BACKUP YOUR <span style=\"color: blue\">config/</span> FOLDER BEFORE PROCEEDING! Failed upgrades cannot be reversed without a backup of the folder. Do not continue until you have backed up this folder.</span><br><br>
			<form name='upgradeForm' action='{$formURL}' method='post'>
				<input type='hidden' name='page' value='goUpgrade'>
				<input type='hidden' name='type' value='{$type}'>
				<div align='center'><input type='submit' value='Continue Upgrade'></div>
			</form>
			");

			$this->adminPanel->page_Message("UPGRADE :: From version {$old_version} to version {$new_version}", $message);
	}


	/* ------------------------------------------------------------------ */
	//	Upgrade Process
	//	The part of this script that actually modifies the files (upgrades).
	/* ------------------------------------------------------------------ */
	
	function upgradeProcess($old_version, $new_version)
	{
		ignore_user_abort(true);

		$current = $old_version;

		$this->clearAbsolutePath();

		while ($current != $new_version)
		{
			switch($current)
			{



				// +------------------------------
				//	Version 2.0x -> 2.1
				// +------------------------------

				case "2.01" :
				// +------------------------------
				//	Add new configuration
				// +------------------------------

				$addConfigArray = array();
				// begin new data info
				$addConfigArray["TEMPLATES__multiple_tlds"] = "[[header]]
<div align=\"center\">Please enter the full domains that you want to check, one per line.</div>
<div align=\"center\"><form name='whois_search' method='POST' action='[[site-url]]whois.php'>
			<input type='hidden' name='page' value='WhoisSearch'>
			<input type='hidden' name='skip_additional' value='1'>
				<table>
					<tr>
					<td>http://www.</td><td><textarea rows='10' cols='40' name='domain'>[[user-domain]]</textarea></td>
					</tr>
				</table>
			<input type='submit' id='Submit' value='Check Availability'> 
</form></div>
[[footer]]";

				// format into expected output
				$addConfigArray['adminpanel_filename'] = "../config/template.php:::"
												. "TEMPLATES__multiple_tlds";

				// designate special search string (we will use nameserver string)
				$addConfigArray['adminpanel_replace_string'] = "\$this->TEMPLATES['header'] = ";

				$this->adminPanel->AddConfig($addConfigArray);

				// +------------------------------
				//	Modify Version Number to reflect new version
				// +------------------------------
				$this->modifyVersion("2.1");
				$current = "2.1";

				break;



				// +------------------------------
				//	Version 2.1 -> 2.11
				// +------------------------------

				case "2.1" :
					// +------------------------------
					//	Modify Version Number to reflect new version
					// +------------------------------
					$this->modifyVersion("2.11");
					$current = "2.11";
				break;



				// +------------------------------
				//	Version 2.11 -> 2.20
				// +------------------------------

				case "2.11" :
				// +------------------------------
				//	Add new configuration
				// +------------------------------

				
				
				// +------------------------------
				//	New Advanced Query Options
				// +------------------------------
				foreach($this->adminPanel->CONFIG->NAMESERVERS as $nameserver => $array_val)
				{

					$addConfigArray = array();


					// begin new data info
					$addConfigArray["NAMESERVERS__{$nameserver}__advanced_custom_query"] = "[[QUERY]]\\r\\n";
					$addConfigArray["NAMESERVERS__{$nameserver}__advanced_limit_custom_query"] = "[[QUERY]]\\r\\n";
					$addConfigArray["NAMESERVERS__{$nameserver}__advanced_custom_query_address"] = "[[SERVER]]:43";

					// format into expected output
					$addConfigArray['adminpanel_filename'] = "../config/config.php:::"
													. "NAMESERVERS__{$nameserver}__advanced_custom_query"
													. ",NAMESERVERS__{$nameserver}__advanced_limit_custom_query"
													. ",NAMESERVERS__{$nameserver}__advanced_custom_query_address";


					// designate special search string (we will use nameserver string)
					$addConfigArray['adminpanel_replace_string'] = "\$this->NAMESERVERS['{$nameserver}']['timeout']";

					// set number of newlines to 1
					$addConfigArray['adminpanel_newline_count'] = 0;

					$this->adminPanel->AddConfig($addConfigArray);
				}


					// +------------------------------
					//	Add new files
					// +------------------------------

					// wipe clean
					$addConfigArray = array();

					// begin new data info
					$addConfigArray["FILES__file__cache"] = "\$this->FILES['folder']['classes'] . \"cache.php\"";

					$addConfigArray["FILES__file__idn"] = "\$this->FILES['folder']['classes'] . \"IDN/idna_convert.class.php\"";

					$addConfigArray["FILES__file__securityimage"] = "\$this->FILES['folder']['classes'] . \"securityImage.php\"";

					$addConfigArray["FILES__file__image"] = "\$this->FILES['folder']['classes'] . \"image.php\"";

					$addConfigArray["FILES__file__emailimage"] = "\$this->FILES['folder']['classes'] . \"emailImage.php\"";

					// ensure that configuration is added as raw configuration (and not formatted as a string)
					$addConfigArray['adminpanel_rules'] = "FILES__file__cache,raw:::FILES__file__idn,raw:::FILES__file__securityimage,raw:::FILES__file__image,raw:::FILES__file__emailimage,raw";

					// format into expected output
					$addConfigArray['adminpanel_filename'] = "../config/config.php:::"
													. "FILES__file__cache,FILES__file__idn,FILES__file__securityimage,FILES__file__image,FILES__file__emailimage";

					// designate special search string (will be used to place new data)
					$addConfigArray['adminpanel_replace_string'] = "\$this->FILES['file']['domains']";

					// set number of newlines to 1
					$addConfigArray['adminpanel_newline_count'] = 0;

					$this->adminPanel->AddConfig($addConfigArray);



					// +------------------------------
					//	Add new folder
					// +------------------------------

					// wipe clean
					$addConfigArray = array();

					// begin new data info
					$addConfigArray["FILES__folder__images"] = "images/";

					// format into expected output
					$addConfigArray['adminpanel_filename'] = "../config/config.php:::"
													. "FILES__folder__images";

					// designate special search string (will be used to place new data)
					$addConfigArray['adminpanel_replace_string'] = "\$this->FILES['folder']['classes']";

					// set number of newlines to 1
					$addConfigArray['adminpanel_newline_count'] = 0;

					$this->adminPanel->AddConfig($addConfigArray);



					// +------------------------------
					//	Add new cache options
					// +------------------------------

					// wipe clean
					$addConfigArray = array();

					// begin new data info
					$addConfigArray["SCRIPT__CACHE__time"] = 5;
					$addConfigArray["SCRIPT__CACHE__folder"] = "cache/";

					// format into expected output
					$addConfigArray['adminpanel_filename'] = "../config/config.php:::"
													. "SCRIPT__CACHE__time,SCRIPT__CACHE__folder";

					// designate special search string (will be used to place new data)
					$addConfigArray['adminpanel_replace_string'] = "\$this->SCRIPT['ERRORS']['domain_badlength']";

					$this->adminPanel->AddConfig($addConfigArray);



					// +------------------------------
					//	Add Whois Server Query Options
					// +------------------------------

					// wipe clean
					$addConfigArray = array();

					// begin new data info
					$addConfigArray["SCRIPT__QUERYWHOIS__enabled"] = "true";
					$addConfigArray["SCRIPT__QUERYWHOIS__regex"] = "/(?:Whois Server|Registrar Whois): (.+)/i";

					// format into expected output
					$addConfigArray['adminpanel_filename'] = "../config/config.php:::"
													. "SCRIPT__QUERYWHOIS__enabled,SCRIPT__QUERYWHOIS__regex";

					// designate special search string (will be used to place new data)
					$addConfigArray['adminpanel_replace_string'] = "\$this->SCRIPT['ERRORS']['domain_badlength']";

					$this->adminPanel->AddConfig($addConfigArray);



					// +------------------------------
					//	Add Whois Query Manipulation Options
					// +------------------------------

					// wipe clean
					$addConfigArray = array();

					// begin new data info
					$addConfigArray["SCRIPT__remove_notices"] = "true";
					$addConfigArray["SCRIPT__translate_idn"] = "true";
					$addConfigArray["SCRIPT__idn_extensions"] = "";
					$addConfigArray["SCRIPT__image_verification"] = "";
					$addConfigArray["SCRIPT__custom_header"] = "";
					$addConfigArray["SCRIPT__custom_footer"] = "";

					// enable email images by default if gd is installed
					if (extension_loaded('gd'))
						$addConfigArray["SCRIPT__email_images"] = "gd";
					else
						$addConfigArray["SCRIPT__email_images"] = "";

					// format into expected output
					$addConfigArray['adminpanel_filename'] = "../config/config.php:::"
													. "SCRIPT__remove_notices,SCRIPT__translate_idn,SCRIPT__idn_extensions,SCRIPT__image_verification,SCRIPT__custom_header,SCRIPT__custom_footer,SCRIPT__email_images";

					// designate special search string (will be used to place new data)
					$addConfigArray['adminpanel_replace_string'] = "\$this->SCRIPT['limit_bypass']";

					// set number of newlines to 1
					$addConfigArray['adminpanel_newline_count'] = 0;

					$this->adminPanel->AddConfig($addConfigArray);



					// +------------------------------
					//	Add Buy Mode suggestion option
					// +------------------------------

					// wipe clean
					$addConfigArray = array();

					// begin new data info
					$addConfigArray["BUYMODE__CONFIG__domain_suggestions"] = "";

					// format into expected output
					$addConfigArray['adminpanel_filename'] = "../config/config.php:::"
													. "BUYMODE__CONFIG__domain_suggestions";

					// designate special search string (will be used to place new data)
					$addConfigArray['adminpanel_replace_string'] = "\$this->BUYMODE['CONFIG']['enable']";

					// set number of newlines to 1
					$addConfigArray['adminpanel_newline_count'] = 0;

					$this->adminPanel->AddConfig($addConfigArray);



					// +------------------------------
					//	Modify [[DOMAIN]] in logging to reflect new [[DOMAIN-PUNY]]
					// +------------------------------

					$modifyConfigArray = array();
					$modifyConfigArray['adminpanel_filename'] = "../config/config.php:::SCRIPT__LOGS__long_format,SCRIPT__LOGS__short_format";
					$modifyConfigArray['adminpanel_class'] = "CONFIG";
					$modifyConfigArray['SCRIPT__LOGS__long_format'] = str_replace("[[DOMAIN]]", "[[DOMAIN-PUNY]]", $this->adminPanel->CONFIG->SCRIPT['LOGS']['long_format']);
					$modifyConfigArray['SCRIPT__LOGS__short_format'] = str_replace("[[DOMAIN]]", "[[DOMAIN-PUNY]]", $this->adminPanel->CONFIG->SCRIPT['LOGS']['short_format']);
					$this->adminPanel->ModifyConfig($modifyConfigArray);


					// +------------------------------
					//	New Nameserver Details
					// +------------------------------

					// disable the crsnic by default
					$modifyConfigArray = array();
					$modifyConfigArray['adminpanel_filename'] = "../config/config.php:::NAMESERVERS__whois.crsnic.net__enabled";
					$modifyConfigArray['adminpanel_class'] = "CONFIG";
					$modifyConfigArray['NAMESERVERS__whois.crsnic.net__enabled'] = "false";
					$this->adminPanel->ModifyConfig($modifyConfigArray);


					// add new nameservers (replacing the old one above basically) if they do not exist

					// wipe clean
					$addConfigArray = array();

					// wipe clean
					$addConfigNameservers = array();
					
					// whois.verisign-grs.com (.com, .net)
					if (!isset($this->adminPanel->CONFIG->NAMESERVERS['whois.verisign-grs.com']))
					{
						$addNameserverArray = array();

						$addNameserverArray["NAMESERVERS__whois.verisign-grs.com__enabled"] = "true";
						$addNameserverArray["NAMESERVERS__whois.verisign-grs.com__keyword"] = "No match";
						$addNameserverArray["NAMESERVERS__whois.verisign-grs.com__format"] = "=[[DOMAIN]].[[EXT]]";
						$addNameserverArray["NAMESERVERS__whois.verisign-grs.com__limit_format"] = "=[[DOMAIN]].[[EXT]]";
						$addNameserverArray["NAMESERVERS__whois.verisign-grs.com__limit_keyword"] = "No match";
						$addNameserverArray["NAMESERVERS__whois.verisign-grs.com__advanced_custom_query"] = "[[QUERY]]\\r\\n";
						$addNameserverArray["NAMESERVERS__whois.verisign-grs.com__advanced_limit_custom_query"] = "[[QUERY]]\\r\\n";
						$addNameserverArray["NAMESERVERS__whois.verisign-grs.com__advanced_custom_query_address"] = "[[SERVER]]:43";
						$addNameserverArray["NAMESERVERS__whois.verisign-grs.com__timeout"] = 30;
						$addNameserverArray["NAMESERVERS__whois.verisign-grs.com__extensions"] = "com,net";
						$addNameserverArray["NAMESERVERS__whois.verisign-grs.com__extensions_disabled"] = "";

						$addConfigNameservers[] = $addNameserverArray;
					}


					// whois.educause.edu (.edu)
					if (!isset($this->adminPanel->CONFIG->NAMESERVERS['whois.educause.edu']))
					{
						$addNameserverArray = array();

						$addNameserverArray["NAMESERVERS__whois.educause.edu__enabled"] = "true";
						$addNameserverArray["NAMESERVERS__whois.educause.edu__keyword"] = "No match";
						$addNameserverArray["NAMESERVERS__whois.educause.edu__format"] = "=[[DOMAIN]].[[EXT]]";
						$addNameserverArray["NAMESERVERS__whois.educause.edu__limit_format"] = "=[[DOMAIN]].[[EXT]]";
						$addNameserverArray["NAMESERVERS__whois.educause.edu__limit_keyword"] = "No match";
						$addNameserverArray["NAMESERVERS__whois.educause.edu__advanced_custom_query"] = "[[QUERY]]\\r\\n";
						$addNameserverArray["NAMESERVERS__whois.educause.edu__advanced_limit_custom_query"] = "[[QUERY]]\\r\\n";
						$addNameserverArray["NAMESERVERS__whois.educause.edu__advanced_custom_query_address"] = "[[SERVER]]:43";
						$addNameserverArray["NAMESERVERS__whois.educause.edu__timeout"] = 30;
						$addNameserverArray["NAMESERVERS__whois.educause.edu__extensions"] = "edu";
						$addNameserverArray["NAMESERVERS__whois.educause.edu__extensions_disabled"] = "";

						$addConfigNameservers[] = $addNameserverArray;
					}

					// if nameservers need to be added
					if (!empty($addNameserverArray))
					{
						foreach($addConfigNameservers as $nameserverArray)
						{
							// format into expected output
							$addConfigArray['adminpanel_filename'] = "../config/config.php:::" . implode(",", array_keys($nameserverArray));


							// designate special search string (will be used to place new data)
							$addConfigArray['adminpanel_replace_string'] = "// ---- SPECIAL NAMESERVER TARGET LINE USED BY ADMIN PANEL -- DO NOT REMOVE ---- //";

							// add nameservers array
							$nameserverArray = array_merge($nameserverArray, $addConfigArray);

							$this->adminPanel->AddConfig($nameserverArray);
						}
					}



					// check for ^KEYWORD bug (new format for NOT is !!!)
					/*
					if ($this->adminPanel->CONFIG->NAMESERVERS['whois-check.ausregistry.net.au']['keyword'] == "^Available")
					{
						// modify the variable

						$modifyConfigArray = array();

						$modifyConfigArray['adminpanel_filename'] = "../config/config.php:::NAMESERVERS__whois-check.ausregistry.net.au__keyword";
						$modifyConfigArray['adminpanel_class'] = "CONFIG";
						$modifyConfigArray['NAMESERVERS__whois-check.ausregistry.net.au__keyword'] = "!!!Available";
						$modifyConfigArray['adminpanel_rules'] = "NAMESERVERS__whois-check.ausregistry.net.au__keyword,string";

						$this->adminPanel->ModifyConfig($modifyConfigArray);
					}*/


					// +------------------------------
					//	Add new image verification template
					// +------------------------------

					$addConfigArray = array();
					// begin new data info
					$addConfigArray["TEMPLATES__image_verification"] = "[[header]]
<div align='center'>
	<div style=\"width: 75%;\">In an effort to prevent the abuse of contact information found within whois databases, we enforce image validation to prevent computer-automated data mining machines from abusing this service. Below you will find an image with 5 random letters and numbers that can only be read by humans. Please type those five characters into the box and click \"View Report\".</div>
	<form name='whois_validate' method='POST' action='[[site-url]]whois.php'>
		<div>
			<table>
				<tr>
					<td><img src='[[site-url]]whois.php?page=SecurityImage&amp;code=[[image-code]]'></td>
					<td style=\"padding-left: 20px;\">
						<input type='text' name='vcode'>
						<input type='hidden' name='code' value='[[image-code]]'>
						<input type='hidden' name='domain' value='[[domain]]'>
						<input type='hidden' name='ext' value='[[ext]]'>
						<input type='hidden' name='page' value='WhoisReport'>
					</td>
				</tr>
			</table>
			<br />
			<input type='submit' value='View Report'>
		</div>
	</form>
</div>
[[footer]]";

					// format into expected output
					$addConfigArray['adminpanel_filename'] = "../config/template.php:::"
													. "TEMPLATES__image_verification";

					// designate special search string (we will use header)
					$addConfigArray['adminpanel_replace_string'] = "\$this->TEMPLATES['header'] = ";

					$this->adminPanel->AddConfig($addConfigArray);



					// +------------------------------
					//	Modify Version Number to reflect new version
					// +------------------------------
					$this->modifyVersion("2.20");
					$current = "2.20";
				break;




				// +------------------------------
				//	Version 2.20 -> 2.21
				// +------------------------------

				case "2.20" :

					// +------------------------------
					//	Add new .eu nameserver support
					// +------------------------------

					// wipe clean
					$addConfigArray = array();

					// wipe clean
					$addConfigNameservers = array();

					// das.eu (.eu)
					if (!isset($this->adminPanel->CONFIG->NAMESERVERS['das.eu']))
					{
						$addNameserverArray = array();
						$addNameserverArray["NAMESERVERS__das.eu__enabled"] = "false";
						$addNameserverArray["NAMESERVERS__das.eu__keyword"] = "Status: FREE";
						$addNameserverArray["NAMESERVERS__das.eu__format"] = "get 1.0 [[DOMAIN]]";
						$addNameserverArray["NAMESERVERS__das.eu__limit_format"] = "get 1.0 [[DOMAIN]]";
						$addNameserverArray["NAMESERVERS__das.eu__limit_keyword"] = "Status: FREE";
						$addNameserverArray["NAMESERVERS__das.eu__advanced_custom_query"] = "[[QUERY]]\\r\\n";
						$addNameserverArray["NAMESERVERS__das.eu__advanced_limit_custom_query"] = "[[QUERY]]\\r\\n";
						$addNameserverArray["NAMESERVERS__das.eu__advanced_custom_query_address"] = "[[SERVER]]:4343";
						$addNameserverArray["NAMESERVERS__das.eu__timeout"] = 30;
						$addNameserverArray["NAMESERVERS__das.eu__extensions"] = "eu";
						$addNameserverArray["NAMESERVERS__das.eu__extensions_disabled"] = "";
						$addConfigNameservers[] = $addNameserverArray;
					}


					// if nameservers need to be added
					if (!empty($addNameserverArray))
					{
						foreach($addConfigNameservers as $nameserverArray)
						{
							// format into expected output
							$addConfigArray['adminpanel_filename'] = "../config/config.php:::" . implode(",", array_keys($nameserverArray));


							// designate special search string (will be used to place new data)
							$addConfigArray['adminpanel_replace_string'] = "// ---- SPECIAL NAMESERVER TARGET LINE USED BY ADMIN PANEL -- DO NOT REMOVE ---- //";

							// add nameservers array
							$nameserverArray = array_merge($nameserverArray, $addConfigArray);

							$this->adminPanel->AddConfig($nameserverArray);
						}
					}


					// +------------------------------
					//	Modify Version Number to reflect new version
					// +------------------------------
					$this->modifyVersion("2.21");
					$current = "2.21";
				break;



				// +------------------------------
				//	Version 2.21 -> 2.22
				// +------------------------------

				case "2.21" :
					// +------------------------------
					//	Modify Version Number to reflect new version
					// +------------------------------
					$this->modifyVersion("2.22");
					$current = "2.22";
				break;
				
				
				
				// +------------------------------
				//	Version 2.22 -> 2.30
				// +------------------------------

				case "2.22" :
					// +------------------------------
					//	Modify Version Number to reflect new version
					// +------------------------------
					$this->modifyVersion("2.30");
					$current = "2.30";
				break;
				
				
				
				// +------------------------------
				//	Version 2.30 -> 2.31
				// +------------------------------

				case "2.30" :
					
					// +------------------------------
					//	Modify .eu and .info Nameserver Details
					// +------------------------------

					// modify .eu nameserver
					$modifyConfigArray = array();
					$modifyConfigArray['adminpanel_filename'] = "../config/config.php:::NAMESERVERS__whois.eu__keyword,NAMESERVERS__whois.eu__format,NAMESERVERS__whois.eu__limit_format,NAMESERVERS__whois.eu__limit_keyword,NAMESERVERS__whois.eu__advanced_custom_query_address";
					$modifyConfigArray['adminpanel_class'] = "CONFIG";
					$modifyConfigArray['NAMESERVERS__whois.eu__keyword'] = "Status:	AVAILABLE";
					$modifyConfigArray['NAMESERVERS__whois.eu__format'] = "[[DOMAIN]].[[EXT]]";
					$modifyConfigArray['NAMESERVERS__whois.eu__limit_format'] = "[[DOMAIN]].[[EXT]]";
					$modifyConfigArray['NAMESERVERS__whois.eu__limit_keyword'] = "Status:	AVAILABLE";
					$modifyConfigArray['NAMESERVERS__whois.eu__advanced_custom_query_address'] = "[[SERVER]]:43";
					$this->adminPanel->ModifyConfig($modifyConfigArray);
					
					// modify .info nameserver
					$modifyConfigArray = array();
					$modifyConfigArray['adminpanel_filename'] = "../config/config.php:::NAMESERVERS__whois.afilias.info__keyword,NAMESERVERS__whois.afilias.info__limit_keyword";
					$modifyConfigArray['adminpanel_class'] = "CONFIG";
					$modifyConfigArray['NAMESERVERS__whois.afilias.info__keyword'] = "NOT FOUND";
					$modifyConfigArray['NAMESERVERS__whois.afilias.info__limit_keyword'] = "NOT FOUND";
					$this->adminPanel->ModifyConfig($modifyConfigArray);
					
					
					
					// +------------------------------
					//	Add new .mobi nameserver support
					// +------------------------------

					// wipe clean
					$addConfigArray = array();

					// wipe clean
					$addConfigNameservers = array();

					// das.eu (.eu)
					if (!isset($this->adminPanel->CONFIG->NAMESERVERS['whois.dotmobiregistry.net']))
					{
						$addNameserverArray = array();
						$addNameserverArray["NAMESERVERS__whois.dotmobiregistry.net__enabled"] = "true";
						$addNameserverArray["NAMESERVERS__whois.dotmobiregistry.net__keyword"] = "NOT FOUND";
						$addNameserverArray["NAMESERVERS__whois.dotmobiregistry.net__format"] = "[[DOMAIN]].[[EXT]]";
						$addNameserverArray["NAMESERVERS__whois.dotmobiregistry.net__limit_format"] = "[[DOMAIN]].[[EXT]]";
						$addNameserverArray["NAMESERVERS__whois.dotmobiregistry.net__limit_keyword"] = "NOT FOUND";
						$addNameserverArray["NAMESERVERS__whois.dotmobiregistry.net__advanced_custom_query"] = "[[QUERY]]\\r\\n";
						$addNameserverArray["NAMESERVERS__whois.dotmobiregistry.net__advanced_limit_custom_query"] = "[[QUERY]]\\r\\n";
						$addNameserverArray["NAMESERVERS__whois.dotmobiregistry.net__advanced_custom_query_address"] = "[[SERVER]]:43";
						$addNameserverArray["NAMESERVERS__whois.dotmobiregistry.net__timeout"] = 30;
						$addNameserverArray["NAMESERVERS__whois.dotmobiregistry.net__extensions"] = "mobi";
						$addNameserverArray["NAMESERVERS__whois.dotmobiregistry.net__extensions_disabled"] = "";
						$addConfigNameservers[] = $addNameserverArray;
					}


					// if nameservers need to be added
					if (!empty($addNameserverArray))
					{
						foreach($addConfigNameservers as $nameserverArray)
						{
							// format into expected output
							$addConfigArray['adminpanel_filename'] = "../config/config.php:::" . implode(",", array_keys($nameserverArray));


							// designate special search string (will be used to place new data)
							$addConfigArray['adminpanel_replace_string'] = "// ---- SPECIAL NAMESERVER TARGET LINE USED BY ADMIN PANEL -- DO NOT REMOVE ---- //";

							// add nameservers array
							$nameserverArray = array_merge($nameserverArray, $addConfigArray);

							$this->adminPanel->AddConfig($nameserverArray);
						}
					}
					
					
					
					// +------------------------------
					//	Modify Version Number to reflect new version
					// +------------------------------
					$this->modifyVersion("2.31");
					$current = "2.31";
				break;



			}
		}
	}


	/* ------------------------------------------------------------------ */
	//	Modify Version
	//	Updates configuration file's version to $new_version
	/* ------------------------------------------------------------------ */

	function modifyVersion($new_version)
	{
		$modifyConfigArray = array();

		$modifyConfigArray['adminpanel_filename'] = "../config/config.php:::SCRIPT__version";
		$modifyConfigArray['adminpanel_class'] = "CONFIG";
		$modifyConfigArray['SCRIPT__version'] = $new_version;
		$modifyConfigArray['adminpanel_rules'] = "SCRIPT__version,string";

		$this->adminPanel->ModifyConfig($modifyConfigArray);

		$this->adminPanel->CONFIG->SCRIPT['version'] = $new_version;
	}


	function clearAbsolutePath()
	{
		$modifyConfigArray = array();

		$modifyConfigArray['adminpanel_filename'] = "../config/config.php:::SCRIPT__absolute_path";
		$modifyConfigArray['adminpanel_class'] = "CONFIG";
		$modifyConfigArray['SCRIPT__absolute_path'] = "";
		$modifyConfigArray['adminpanel_rules'] = "SCRIPT__absolute_path,string";

		$this->adminPanel->ModifyConfig($modifyConfigArray);
	}
}