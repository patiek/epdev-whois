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


// deprecated introduced in PHP 5.3
if (!defined('E_DEPRECATED')) {
	define('E_DEPRECATED', 8192);
}

// set error reporting level (ignore deprecated for ereg)
// @TODO: transition ereg use to preg
error_reporting(E_ALL & ~(E_NOTICE | E_DEPRECATED));
		
ini_set("memory_limit", "128M");

// +------------------------------
//	initialize administration panel and navigate
// +------------------------------
$adminPanel = new EP_Dev_Whois_Admin();
$adminPanel->navigate($_REQUEST['page']);

/* ------------------------------------------------------------------ */
//	Administration Panel Class
//  Contains / Operates all of the functions of the admin panel through
//	its own functions or other included classes.
/* ------------------------------------------------------------------ */

class EP_Dev_Whois_Admin
{
	// internal version
	var $internal_version = "2.31";

	// configs
	var $CORE;
	var $TEMPLATE;

	// error handle
	var $ERROR;

	// panel display
	var $DISPLAY;

	// variable format
	var $FORMAT;

	// user login / logout functions
	var $USER;

	function EP_Dev_Whois_Admin()
	{
		// We will be using sessions
		session_start();

		// +------------------------------
		//	Remove effects of magic_quotes
		// +------------------------------
		if (get_magic_quotes_gpc())
		{
			$_POST = array_map(array(&$this, 'stripslashes_deep'), $_POST);
			$_GET = array_map(array(&$this, 'stripslashes_deep'), $_GET);
			$_COOKIE = array_map(array(&$this, 'stripslashes_deep'), $_COOKIE);
		}


		// Load config file
		require_once("../config/config.php");

		// initialize configuration
		$this->CONFIG = new EP_Dev_Whois_Config();


		// +------------------------------
		//	Load up common required files
		// +------------------------------
		require_once("../config/template.php");
		require_once("display.php");
		require_once("fileio.php");
		require_once("file_format.php");


		// +------------------------------
		//	Initialize variables
		// +------------------------------
		$template_temp = new EP_Dev_Whois_Templates();
		$this->TEMPLATE = $template_temp;

		$this->ERROR = new EP_Dev_Whois_Admin_Error_Handle();
		$this->DISPLAY = new EP_Dev_Whois_Admin_Display("EP-Dev Whois Administration Panel");
		$this->FORMAT = new EP_Dev_Whois_Admin_Variable_Format($this->ERROR);
		$this->USER = new EP_Dev_Whois_Admin_UserControl($this->CONFIG->ADMIN['username'], $this->CONFIG->ADMIN['password']);


		// +------------------------------
		//	Check if this panel is enabled
		// +------------------------------
		if (!$this->CONFIG->ADMIN['enabled'])
		{
			// error if we are disabled
			$this->ERROR->stop("panel_disabled");
		}


		// +------------------------------
		//	Ensure that files are writable
		// +------------------------------ 
		if (!is_writable("../config/config.php")
			|| !is_writable("../config/template.php")
			|| !is_writable("../whois.php")
			|| !is_writable("../logs/")
			|| !is_writable("../cache/")
			|| !is_writable("../images/"))
		{
			$this->DISPLAY->MENU->blank();
			$message = $this->DISPLAY->constructOutput("It has been detected that not all of the files that need to be writable are writable.<br><br>
			Please ensure the following files are chmod 0666 (read & write all):<br>
			whois/whois.php<br>
			whois/config/config.php<br>
			whois/config/template.php<br><br>
			In addition, the logs and cache folders need to be chmod 0777 (read/write/execute all):<br>
			whois/logs/<br>
			whois/cache/<br>
			whois/images/<br><br>
			NOTE: You can usually chmod files by right clicking on the file in your ftp program and selecting \"Change file permissions\" or \"CHMOD\".<br><br>
			Once you have changed these files to be writable, please refresh this page.
			");

			$this->page_Message("ERROR: NOT ALL REQUIRED FILES WRITABLE", "<div style=\"text-align: left;\">{$message}</div>");
			die();
		}


		// +------------------------------
		//	Check if in process of upgrading and load upgrader if so.
		// +------------------------------
		if ($this->CONFIG->SCRIPT['version'] != $this->internal_version)
		{
			require_once("upgrade/upgradeCore.php");
			$UPGRADER = new UpgradeCore($this);
			$UPGRADER->navigate($this->CONFIG->SCRIPT['version'], $this->internal_version);
			die();
		}
	}



	/* ------------------------------------------------------------------ */
	//	Strip slashes from $value
	//	Equivilent to stripslashes(), but it operates recursively
	/* ------------------------------------------------------------------ */
	
	function stripslashes_deep($value)
	{
       $value = is_array($value) ?
                   array_map(array(&$this, 'stripslashes_deep'), $value) :
                   stripslashes($value);

       return $value;
	}



	/* ------------------------------------------------------------------ */
	//	Navigate to $page
	//  Calls, based on $page, the correct page method.
	/* ------------------------------------------------------------------ */
	
	function navigate($page = null)
	{
		// +------------------------------
		//	Call method based on $page
		// +------------------------------
		switch($page)
		{
			// +------------------------------
			//	Non-restricted (Public) Pages
			// +------------------------------

			case "FAQ" :
				if (!$this->USER->check())
					$this->DISPLAY->MENU->blank();
				$this->page_FAQ();
			break;

			case "goLogin" :
				$this->USER->login($_POST['username'], $_POST['password']);
				$this->navigate();
			break;

			default: 
				
				// +------------------------------
				//	User Authentication
				// +------------------------------
				if(!$this->USER->check()) // if not valid user
				{
					// show login page
					$this->DISPLAY->MENU->blank();

					if ($this->USER->defaultConfig())
						$this->page_Login($this->CONFIG->ADMIN['username'], $this->CONFIG->ADMIN['password']);
					else
						$this->page_Login();
				}


				// +------------------------------
				//	Restricted (Requires authentication) Pages
				// +------------------------------

				else
				{
					// +------------------------------
					//	Auto Check for update (if enabled)
					// +------------------------------
					if ($this->CONFIG->ADMIN['update_check'] && !$this->USER->getValue("checked_for_update"))
					{
						$update_info = $this->CheckUpdate();
						if ($update_info['version_available'])
						{
							$this->USER->setValue("checked_for_update", true);

							$this->page_CheckForUpdate();

							break;
						}
					}


					// +------------------------------
					//	Force Username & Password Change if still default
					// +------------------------------
					if ($this->USER->defaultConfig() && $page != "goModifyConfig")
						$page = "AdminSettings";


					/* A fancy (or sloppy, depending on how you look at it)
					embedded switch statement */

					switch($page)
					{
						case "goLogout" :
							$this->USER->logout();
							$this->navigate();
						break;

						case "goModifyConfig" :
							$this->ModifyConfig($_POST);
							$this->page_Message("Settings Updated", "Your settings have been updated.");
						break;

						case "goRemoveConfig" :
							$this->RemoveConfig($_POST);
							$this->page_Message("Configuration Removed", "Your settings have been updated.");
						break;

						case "goAddNameserver" :
							$this->AddNameserver($_POST);
							$this->page_Message("Nameserver Added", "Your settings have been updated.");
						break;

						case "goModifyTLDPrices" :
							$this->ModifyTLDPrices($_POST);
							$this->page_Message("TLD Prices Updated", "Your settings have been updated.");
						break;

						case "AdminSettings" :
							$this->page_AdminSettings();
						break;

						case "ScriptSettings" :
							$this->page_ScriptSettings();
						break;

						case "WhoisSettings" :
							$this->page_WhoisSettings();
						break;

						case "BuyModeSettings" :
							$this->page_BuyModeSettings();
						break;

						case "TLDSettings" : 
							$this->page_TLDSettings();
						break;

						case "AddNameserver" :
							$this->page_AddNameserver();
						break;

						case "NameserverSettings" :
							$this->page_NameserverSettings();
						break;

						case "DeleteNameserver" :
							$this->page_DeleteNameserver();
						break;

						case "TemplateSettings" :
							$this->page_TemplateSettings();
						break;

						case "ErrorSettings" :
							$this->page_ErrorSettings();
						break;

						case "CheckForUpdate" :
							$this->page_CheckForUpdate();
						break;

						case "NSValidator" :
							$this->page_Nameserver_Validator();
						break;

						case "goValidateNameserver" :
							$this->ValidateNameserver($_REQUEST);
						break;

						default :
							$this->page_Main();
					}
				}
		}
	}



	/* ------------------------------------------------------------------ */
	//	Login Page
	//	If specified, will fill in username and password input with given
	//	parameters.
	/* ------------------------------------------------------------------ */
	
	function page_Login($default_username="", $default_password="")
	{
		// start form
		$content .= $this->DISPLAY->constructStartForm("goLogin", "ADMIN_login_form");

		if (!empty($default_username))
			$content .= "<div align='center'>" . $this->DISPLAY->constructOutput("Click Login to begin!") . "</div>";

		$content .= "<br>";

		$content .= "<div align='center'><table>\n";
		
		$content .= "<tr>\n<td align='center'>" . $this->DISPLAY->constructOutput("Username: <input type='text' name='username' value='{$default_username}'>") . "</td>\n</tr>\n";
		$content .= "<tr>\n<td align='center'>" . $this->DISPLAY->constructOutput("Password: <input type='password' name='password' value='{$default_password}'>") . "</td>\n</tr>\n";

		$content .= "</table><div>\n";

		$content .= $this->DISPLAY->constructOutput("<a href='#' onClick=\"window.open('?page=FAQ&amp;topic=1&amp;small=1', 'feature_window', 'toolbar=no,status=no,height=300,width=475,resizable=yes,scrollbars=yes,top=100,left=100');\">Forgot your password?</a>");

		$content .= "<br>";

		// end form
		$content .= "<div align='center'>" . $this->DISPLAY->constructEndForm("Login") . "</div>";

		$content .= "<br>";

		if (!empty($default_username))
			$content .= "<div align='center'>" . $this->DISPLAY->constructOutput("NOTE: The default username is admin and the default password is blank (empty).") . "</div>";

		$this->DISPLAY->displayPage($content, "EP-Dev Whois Login");
	}



	/* ------------------------------------------------------------------ */
	//	Script Update Page
	//	Checks to see if script is up-to-date.
	/* ------------------------------------------------------------------ */
	
	function page_CheckForUpdate()
	{
		$update_info = $this->CheckUpdate();
		if ($update_info['version_available'])
		{
			$message = $this->DISPLAY->constructOutput("A new update, version {$update_info['version_current']}, is available for download from <a href='{$update_info['download_url']}' target='_blank'>EP-Dev.com</a>. Details can be found below:<br>");
			
			if ($update_info['recommend'])
			{
				$message .= $this->DISPLAY->constructOutput("<font color='red'>Warning: The version of the script you are running, {$update_info['version_user']}, is at least two versions old. Please update as soon as possible.</font>");
			}

			if (!empty($update_info['download_url']))
			{
				$message .= "<br>";

				$message .= $this->DISPLAY->constructOutput("You can download the new version from <a href='{$update_info['download_url']}' target='_blank'>EP-Dev.com</a>.");
			}

			if (count($update_info['version_recent']) != 0)
			{
				$message .= "<br><strong>Bug Fixes and Features in new version:</strong>";
			}

			for($i=0; $i<count($update_info['version_recent']); $i++)
			{
				$message .= $this->DISPLAY->constructOutput("<i>Version {$update_info['version_recent'][$i]['number']}</i><br>{$update_info['version_recent'][$i]['description']}") . "<br>";
			}
		}
		else
		{
			$message = $this->DISPLAY->constructOutput("No new updates are available. Your script is up-to-date.");
		}

		$message .= "<br>\n" . $this->DISPLAY->constructOutput("Your current script version is " . $this->CONFIG->SCRIPT['version']);

		$this->DISPLAY->displayPage($message, ($update_info['version_available'] ? "<font color='red'>Update Available!</font>" : "No Update Available"));
	}



	/* ------------------------------------------------------------------ */
	//	Check Update
	//	Returns script info pulled from EP-Dev.com server.
	//	returns array:	string ['ver'] = version number from server.
	//					boolean ['new'] = script status (true = up-to-date)
	/* ------------------------------------------------------------------ */
	
	function CheckUpdate()
	{
		// +------------------------------
		//	Connect to EP-Dev and check for Update.
		// +------------------------------

		$referrer = "http://" . $_SERVER['HTTP_HOST'] . str_replace("whois/admin/index.php" , "", $_SERVER['PHP_SELF']);
		
		$cur_version = $this->CONFIG->SCRIPT['version'];
		$file = @file_get_contents("http://www.ep-dev.com/update/check.php?name=ep-dev-whois&version={$cur_version}&referrer={$referrer}");
		if ($file === false)
		{
			$version_info['success'] = false;
			$version_info['version_available'] = false;
		}
		else
		{
			$version_info = unserialize($file);
			$version_info['success'] = true;
			$version_info['version_available'] = ($version_info['version_current'] != $cur_version);
		}

		return $version_info;
	}



	/* ------------------------------------------------------------------ */
	//	FAQ Page
	//	If set, $_GET['topic'] will force single topic display.
	//	Else, all will be displayed in FAQ manner.
	/* ------------------------------------------------------------------ */
	
	function page_FAQ()
	{
		// get proper FAQ code, 0 = display all (default)
		$faq_code = (isset($_GET['topic']) ? $_GET['topic'] : 0);
		$small_display = ($_GET['small'] == "1");

		// +------------------------------
		//	Format questions / answers (General)
		// +------------------------------

		$questions[] = "I forgot my username and password to the admin section. How can I change them manually?";
		$answers[] = "Open up config/config.php and edit the <font color='#0000FF'>\$this->ADMIN['username']</font> and <font color='#0000FF'>\$this->ADMIN['password']</font> values.";


		$questions[] = "What tags can be used for logging?";
		$answers[] = "The following tags can be used in the log filename and logging formats:<br>
		<font color='#0000FF'>[[MONTH]]</font> - 3 letter month<br>
		<font color='#0000FF'>[[DAY]]</font> - Day number of month.<br>
		<font color='#0000FF'>[[YEAR]]</font> - four number year (ex: 2005).<br>
		<font color='#0000FF'>[[TIME]]</font> - Number of seconds since Unix equinox.<br>
		<font color='#0000FF'>[[DOMAIN]]</font> - The domain of the query being logged.<br>
		<font color='#0000FF'>[[DOMAIN-PUNY]]</font> - The domain of the query being logged in the Punycode (ACE) format.<br>
		<font color='#0000FF'>[[EXT]]</font> - The extension of the query being logged.<br>
		<font color='#0000FF'>[[IP]]</font> - The IP of the query being logged.";


		$questions[] = "How do I configure the price format option?";
		$answers[] = "The price format of <font color='#0000FF'>Buy Mode Settings</font> can accept many different values.<br>
		Acceptable formats are:<br>
		<font color='#0000FF'>us</font> - Will display 9.95 as \$9.95<br>
		<font color='#0000FF'>uk</font> - Will display 9.95 as &euro;9.95<br>
		<font color='#0000FF'>eu</font> - Will display 9.95 as &pound;9,95<br>
		<br>
		<font color='#0000FF'>custom:sign:decimals:decimal sign:thousands sign</font> - Will display a custom format. For example, if we decided to custom set UK currency (this is just example, please use <font color='#0000FF'>uk</font>) then it would look like <font color='#0000FF'>custom:&pound;:2:.:,</font> as a custom setting. This allows for one to setup the whois script to use any currency.";


		$questions[] = "Where can I find additional whois or nameserver information?";
		$answers[] = "You can find additional whois information on the IANA home page, located at <a href=\"http://www.iana.org/\" taget=\"_blank\">http://www.iana.org/</a>. There are also several <a href=\"http://www.domaininformation.de/whoisserver_list.html\" target=\"_blank\">unofficial pages</a> out there.";


		$questions[] = "What are suggested domains?";
		$answers[] = "Suggested domains are alternative domains and/or extensions that are queried and displayed to the user when the user's original domain is unavailable AND the suggested domain is available.<br><br>
		<font color='#0000FF'>[[DOMAIN]]</font> - The domain of the original query.<br>
		<font color='#0000FF'>[[EXT]]</font> - The extension(s) of the original query.<br><br>
		If multiple extensions were submitted by the user and any of those domains.ext were found to be unavailable, suggested alternatives will be found for the unavailable extension. For example, if the user searched for domain.com, domain.net, and domain.org simultaneously and only domain.org was available, the user would get a display of suggested alternatives for domain.com and domain.net.<br><br>
		Example: suggested domains are:<br>
		<span style=\"font-size: 10px; color: #0000FF\">my-[[DOMAIN]].[[EXT]],ilove[[DOMAIN]].[[EXT]],[[DOMAIN]].net</span><br> and the user searches for &quot;music.com&quot; (which returns unavailable), the suggested alternatives would be (if they are available) &quot;my-music.com&quot;, &quot;ilovemusic.com&quot;, and &quot;music.net&quot;.";


		$questions[] = "What is IDN Support?";
		$answers[] = "Support internationalized domain names (IDN) will allow foreign character domains. Such domains include specialty characters, such as &copy;.com , as well as other foreign character domains. In order to support IDNs properly, you will need to have the proper encoding for your whois script. If you are visiting the EP-Dev Whois Script directly in the browser then this is done automatically. If you are including the script into another page, you will need to ensure that your other page is sending itself in the correct UTF-8 charset. For php scripts, this can be accomplished by adding the following to your php page(s) before ANY output is sent to the browser:<br><span style=\"font-size: 12px;\">"
		. highlight_string("<?php header(\"Content-Type: text/html; charset=UTF-8\"); ?\>", true) . "</span>";

		
		// +------------------------------
		//	Display all or single answers / questions
		// +------------------------------
		// If no FAQ code, display all
		if (is_numeric($faq_code) && $faq_code == 0)
		{
			$content .= "<ol>\n";
			
			for($i=0; $i<count($questions); $i++)
				$content .= "<li>" . $this->DISPLAY->constructOutput("<a href='#FAQ{$i}'>{$questions[$i]}</a>") ."</li>\n";

			$content .= "</ol>\n<hr>\n";

			for($i=0; $i<count($questions); $i++)
			{
				$content .= $this->DISPLAY->constructOutput("<strong><a name='FAQ{$i}'>" . ($i+1) . "</a>. {$questions[$i]}</strong>");
				$content .= $this->DISPLAY->constructOutput($answers[$i], 15);
				$content .= "<br>\n<br>\n";
			}
		}

		// Display single if FAQ code specified
		elseif(is_numeric($faq_code))
		{
			$content .= $this->DISPLAY->constructOutput("<strong><a name='FAQ{$i}'>&nbsp;</a> {$questions[$faq_code-1]}</strong>");
			$content .= $this->DISPLAY->constructOutput($answers[$faq_code-1], 15);
			$content .= "<br>\n<br>\n";
		}

		// Display non-default FAQ
		else
		{
			unset($questions);
			unset($answers);

			$template_global_tags_no_header_footer = "
				<font color='#0000FF'>[[site-url]]</font> - Site URL listed in Script Settings.<br>
				<font color='#0000FF'>[[site-title]]</font> - Site Title listed in Script Settings.<br>
				<font color='#0000FF'>[[user-domain]]</font> - Domain of last query (if available).<br>
				<font color='#0000FF'>[[user-extension]]</font> - Extension of last query (if available).<br>
				<font color='#0000FF'>[[copyright]]</font> - EP-Dev \"Generated by...\" Copyright.<br>
					";

			$template_global_tags = "
				{$template_global_tags_no_header_footer}
				<font color='#0000FF'>[[header]]</font> - Site header template.<br>
				<font color='#0000FF'>[[footer]]</font> - Site footer template.<br>
					";

			// +------------------------------
			//	Format questions / answers (templates)
			// +------------------------------
			$questions['templatesSearchBar'] = "Search Bar Tags";
			$answers['templatesSearchBar'] = "The following tags can be used in the search bar template:<br>
				<font color='#0000FF'>[[tld-checkboxes]]</font> - Display extensions as a grid of checkboxes.<br>
				<font color='#0000FF'>[[tld-dropbox]]</font> - Display extensions in a dropdown box.<br>
				<br>
				Alternatively, you can leave out the checkboxes or dropbox altogether. The script has smart recognition that will
				detect various domain and subdomain inputs that automatically detect the extension of the input text (such as detecting the input text 'somedomain.com' is made up of the domain 'somedomain' and the extension 'com').<br>
				<br>
				{$template_global_tags}";


			$questions['templatesDomains'] = "Domain Tags";
			$answers['templatesDomains'] = "The following tags can be used in the domain availability templates:<br>
				<font color='#0000FF'>[[domain]]</font> - The domain part of the domain name.<br>
				<font color='#0000FF'>[[domain-puny]]</font> - The domain part of the domain name in Punycode (ACE) format.<br>
				<font color='#0000FF'>[[ext]]</font> - The extension part of the domain name.<br>
				<font color='#0000FF'>[[price]]</font> - The price of this domain name.<br>
				<font color='#0000FF'>[[whois-reporturl]]</font> - The url to the whois report page for this domain.<br>
				<font color='#0000FF'>[repeat]CONTENT[/repeat]</font> - Place the repeat tags around text that will be repeated for each domain. This allows one to utlize tables. Only text within the repeat tags will be parsed for each domain.<br>
				<font color='#0000FF'>[[count]]</font> - The count of the current repeat loop iteration starting from 1.<br>
				{$template_global_tags}";


			$questions['templatesErrorPage'] = "Error Page Tags";
			$answers['templatesErrorPage'] = "The following tags can be used in the error page template:<br>
				<font color='#0000FF'>[[error]]</font> - The error of the request.<br>
				{$template_global_tags}";


			$questions['templatesResultsPage'] = "Buy Mode Results Page Tags";
			$answers['templatesResultsPage'] = "The following tags can be used in the Buy Mode Results template:<br>
				<font color='#0000FF'>[[availabledomains]]</font> - Displays the parsed template of available domain names.<br>
				<font color='#0000FF'>[[unavailabledomains]]</font> - Displays the parsed template of unavailable domain names.<br>
				{$template_global_tags}";


			$questions['templatesReportPage'] = "Whois Report Page Tags";
			$answers['templatesReportPage'] = "The following tags can be used in the Whois Report Page template:<br>
				<font color='#0000FF'>[[whoisreport]]</font> - Displays the whois report.<br>
				<font color='#0000FF'>[[pricetable]]</font> - Displays the price table.<br>
				<font color='#0000FF'>[[searchbar]]</font> - Displays the search bar.<br>
				{$template_global_tags}";


			$questions['templatesImageVerificationPage'] = "Whois Image Verification Page Tags";
			$answers['templatesImageVerificationPage'] = "The following tags can be used in the Image Verification Page template:<br>
				<font color='#0000FF'>[[domain]]</font> - The domain of the report to be fetched.<br>
				<font color='#0000FF'>[[ext]]</font> - The extension of the report to be fetched.<br>
				<font color='#0000FF'>[[image-code]]</font> - The random image number used in verification.<br>
				{$template_global_tags}";


			$questions['templatesMainPage'] = "Main Page Tags";
			$answers['templatesMainPage'] = "The following tags can be used in the Main (Front) Page template:<br>
				<font color='#0000FF'>[[pricetable]]</font> - Displays the price table.<br>
				<font color='#0000FF'>[[searchbar]]</font> - Displays the search bar.<br>
				{$template_global_tags}";


			$questions['templatesFooter'] = "Footer Tags";
			$answers['templatesFooter'] = "The following tags can be used in the footer template:<br>
				<font color='#0000FF'>[[pricetable]]</font> - Displays the price table.<br>
				<font color='#0000FF'>[[searchbar]]</font> - Displays the search bar.<br>
				{$template_global_tags_no_header_footer}";


			$questions['templatesHeader'] = "Header Tags";
			$answers['templatesHeader'] = "The following tags can be used in the header template:<br>
				<font color='#0000FF'>[[pricetable]]</font> - Displays the price table.<br>
				<font color='#0000FF'>[[searchbar]]</font> - Displays the search bar.<br>
				{$template_global_tags_no_header_footer}";


			// +------------------------------
			//	Display questions / answers (templates)
			// +------------------------------

			$content .= $this->DISPLAY->constructOutput("<strong>{$questions[$faq_code]}</strong>", 5);
			$content .= $this->DISPLAY->constructOutput($answers[$faq_code], 20);
			//$content .= "<br>\n<br>\n";
		}

		
		// detect if small display
		if ($small_display)
		{
			// manually add HTML
			$content = "<html>\r\n<head>\r\n" . $this->DISPLAY->get_Style(2) . "</head>\r\n<body>\r\n" . $content . "</body>\r\n</html>";

			// remove menu
			$this->DISPLAY->MENU->blank();

			// manual call to show content
			$this->DISPLAY->show_Content($content);
		}

		// display full page
		else
		{
			// display page
			$this->DISPLAY->displayPage($content, "Troubleshooting");
		}
	}



	/* ------------------------------------------------------------------ */
	//	Message Page
	//	Displays generic page with $title as title and $message as content
	/* ------------------------------------------------------------------ */
	
	function page_Message($title, $message)
	{
		$content = $this->DISPLAY->constructOutput("<div style=\"margin: 15px auto 15px auto; text-align: center;\">" . $message . "</div>");

		// display page
		$this->DISPLAY->displayPage($content, $title);
	}



	/* ------------------------------------------------------------------ */
	//	Welcome Page
	//	Contains Welcome Page displaying tips, helpful links, etc.
	/* ------------------------------------------------------------------ */
	
	function page_Main()
	{
		$content .= $this->DISPLAY->constructOutput("Welcome to the administration panel of the EP-Dev Whois script. The links below should be used as a guide as you configure this script:");

		$content .= "<br>";

		$message = "<a href='index.php?page=AdminSettings'>Admin Settings</a>";
		$message2 = "Manage this script's preferences such as username and password access.";
		$content .= $this->DISPLAY->constructOutput($message);
		$content .= $this->DISPLAY->constructOutput($message2, 15);
		
		$content .= "<br>";
		$message = "<a href='index.php?page=ScriptSettings'>Script Settings</a>";
		$message2 = "The Script Settings page allows you to modify specific global settings of the script. The settings include the script's absolute path as well as other general settings.";
		$content .= $this->DISPLAY->constructOutput($message);
		$content .= $this->DISPLAY->constructOutput($message2, 15);

		$content .= "<br>";
		$message = "<a href='index.php?page=WhoisSettings'>Whois Settings</a>";
		$message2 = "The Whois Settings page allows you to modify specific whois engine related options including the general manipulation of queries, IDN support, and whois result image verification.";
		$content .= $this->DISPLAY->constructOutput($message);
		$content .= $this->DISPLAY->constructOutput($message2, 15);

		$content .= "<br>";
		$message = "<a href='index.php?page=TemplateSettings'>Edit Templates</a>";
		$message2 = "The Edit Templates page allows you to modify the appearance of the whois script using the EP-Dev Whois template system.";
		$content .= $this->DISPLAY->constructOutput($message);
		$content .= $this->DISPLAY->constructOutput($message2, 15);

		$content .= "<br>";
		$message = "<a href='index.php?page=NameserverSettings'>Edit Nameservers</a>";
		$message2 = "Quickly manage all of the nameservers of the script. You can enable and disabled entire nameservers or just certain extensions. Each nameserver has a variety of custom settings.";
		$content .= $this->DISPLAY->constructOutput($message);
		$content .= $this->DISPLAY->constructOutput($message2, 15);

		$content .= "<br>";
		$message = "<a href='index.php?page=BuyModeSettings'>Buy Mode Settings</a>";
		$message2 = "Turn the Whois Script into a preface for an order process through the Buy Mode settings. Manage automatic TLD recommendations, currency formats, and a TLD price table!";
		$content .= $this->DISPLAY->constructOutput($message);
		$content .= $this->DISPLAY->constructOutput($message2, 15);

		$content .= "<br>";
		$message = "<a href='index.php?page=TLDSettings'>Edit TLD Prices</a>";
		$message2 = "Quickly edit the prices of all of the TLDs currently configured to be recognized by the script.";
		$content .= $this->DISPLAY->constructOutput($message);
		$content .= $this->DISPLAY->constructOutput($message2, 15);


		$this->DISPLAY->displayPage($this->DISPLAY->constructOutput("<br>".$content, 10));
	}



	/* ------------------------------------------------------------------ */
	//	Admin Settings Page
	//	Contains all administration panel related settings.
	/* ------------------------------------------------------------------ */
	
	function page_AdminSettings()
	{
		// If still default config, notify that config must be changed
		if ($this->USER->defaultConfig())
		{
			$content .= $this->getDonationBox();

			$content .= $this->DISPLAY->constructOutput("<font color='red'>You must change your username and password before you are allowed to continue.</font>");
		}


		$content .= $this->DISPLAY->constructOutput("The settings below concern the administration panel.");

		// +------------------------------
		//	Construct form with table of inputs
		// +------------------------------
		
		// start form
		$content .= $this->DISPLAY->constructStartForm("goModifyConfig", "configADMIN_form");

		$content .= "<input type='hidden' name='adminpanel_filename' value='../config/config.php:::ADMIN__username,ADMIN__password,ADMIN__update_check'>\n";

		$content .=  "<input type='hidden' name='adminpanel_class' value='CONFIG'>\n";


		$content .= "<table>";

		// Username input
		$name = "Username";
		$description = "Your desired username to this script's administration panel.";
		$varType = "text";
		$varName = "ADMIN__username";
		$varValue = $this->CONFIG->ADMIN['username'];
		$content .= $this->DISPLAY->constructTableVariable($name, $description, $varType, $varName, $varValue);

		// Password input
		$name = "Password";
		$description = "Your desired password to this script's administration panel.";
		$varType = "text";
		$varName = "ADMIN__password";
		$varValue = $this->CONFIG->ADMIN['password'];
		$content .= $this->DISPLAY->constructTableVariable($name, $description, $varType, $varName, $varValue);


		// Automatically check for updates?
		$name = "Check for Updates on Login";
		$description = "If enabled, the script will automatically check EP-Dev.com for important updates and notify you when a new version of this script is released. It is recommended that you leave this enabled.";
		$varType = "select";
		$varName = "ADMIN__update_check";
		$varOptions = array(
						"Enabled" => "true",
						"Disabled" => "false"
						);
		$varSelected = ($this->CONFIG->ADMIN['update_check'] ? "Enabled" : "Disabled");
		$varValue = array(
						"options" => $varOptions,
						"selected" => $varSelected
						);
		$content .= $this->DISPLAY->constructTableVariable($name, $description, $varType, $varName, $varValue);

		$content .= "</table>";


		// end form
		$content .= "<div align='center'>" . $this->DISPLAY->constructEndForm("Save Config") . "</div>";

		// display page
		$this->DISPLAY->displayPage($content, "Admin Panel Settings");
	}



	/* ------------------------------------------------------------------ */
	//	Script Settings Page
	//	Contains all script related settings.
	/* ------------------------------------------------------------------ */
	
	function page_ScriptSettings()
	{

		// attempt to get absolute path
		$abs_path = ereg_replace("[\\/]+admin[\\/]+$", "/", dirname($_SERVER['SCRIPT_FILENAME']) . "/");

		// +------------------------------
		//	Javascript: Ensure trailing slash on abs path
		// +------------------------------
		$javascript .= "<script LANGUAGE=\"Javascript\" type=\"text/javascript\">
			function check_AbsPath() {
				if (
						(
							document.getElementById('SCRIPT__absolute_path').value.charAt (
							document.getElementById('SCRIPT__absolute_path').value.length -1
								) != '/'
						)
						&&
						(document.getElementById('SCRIPT__absolute_path').value.substr (
							document.getElementById('SCRIPT__absolute_path').value.length -2, document.getElementById('SCRIPT__absolute_path').value.length
							) != \"\\\\\\\\\"
						)
						&&
						(document.getElementById('SCRIPT__absolute_path').value != \"\")
					)
				{
					window.alert(\"Last character of absolute path MUST BE a '/' or '\\\\\\\\'\");
					document.getElementById('SCRIPT__absolute_path').focus();
					document.getElementById('SCRIPT__absolute_path').select();
					return false;
				}

				return true;
			}

			function load_AbsPath() {
				document.getElementById('SCRIPT__absolute_path').value = \"{$abs_path}\";
			}
		</script>
		";


		$content .= $this->DISPLAY->constructOutput("The settings below concern the settings of this script.");

		// +------------------------------
		//	Construct form with table of inputs
		// +------------------------------
		
		// start form
		$content .= $this->DISPLAY->constructStartForm("goModifyConfig", "configSCRIPT_form", "POST", null, "return check_AbsPath();");

		$content .= "<input type='hidden' name='adminpanel_filename' value='../config/config.php:::SCRIPT__SITE__title,SCRIPT__SITE__url,SCRIPT__absolute_path,SCRIPT__copyright,SCRIPT__LOGS__enable,SCRIPT__LOGS__all,SCRIPT__LOGS__file,SCRIPT__LOGS__type,SCRIPT__LOGS__short_format,SCRIPT__LOGS__long_format:::../whois.php:::absolute_path'>\n";

		// we need to copy the value of absolute path so that it can also be written for whois.php (under a different variable)
		$content .= "<input type='hidden' name='adminpanel_copy' value='SCRIPT__absolute_path:::absolute_path'>\n";

		$content .=  "<input type='hidden' name='adminpanel_class' value='CONFIG'>\n";


		$content .= "<table>";

		// Site title
		$name = "Site Title";
		$description = "The default title for your site.";
		$varType = "text";
		$varName = "SCRIPT__SITE__title";
		$varValue = $this->CONFIG->SCRIPT['SITE']['title'];
		$content .= $this->DISPLAY->constructTableVariable($name, $description, $varType, $varName, $varValue, 30);

		// Script URL
		$name = "Script URL";
		$description = "The default URL to this script. Must end in a trailing slash.<br>ex: http://www.mysite.com/whois/";
		$varType = "text";
		$varName = "SCRIPT__SITE__url";
		$varValue = $this->CONFIG->SCRIPT['SITE']['url'];
		$content .= $this->DISPLAY->constructTableVariable($name, $description, $varType, $varName, $varValue, 40);
		
		// Absolute Path
		$name = "Absolute Path";
		$description = "The absolute path to this script. If you leave this blank the script will try to auto-detect your absolute path. (<a href=\"javascript:load_AbsPath();\">auto-detect</a>)<br>ex: /abs/path/to/whois/";
		$varType = "text";
		$varName = "SCRIPT__absolute_path";
		$varValue = $this->CONFIG->SCRIPT['absolute_path'];
		$content .= $this->DISPLAY->constructTableVariable($name, $description, $varType, $varName, $varValue, 40);


		// Display Copyright
		$name = "Display Generated By";
		$description = "Display \"Generated By\" text in footer (would be nice, but you don't have to).";
		$varType = "select";
		$varName = "SCRIPT__copyright";
		$varOptions = array(
						"Enabled" => "true",
						"Disabled" => "false"
						);
		$varSelected = ($this->CONFIG->SCRIPT['copyright'] ? "Enabled" : "Disabled");
		$varValue = array(
						"options" => $varOptions,
						"selected" => $varSelected
						);
		$content .= $this->DISPLAY->constructTableVariable($name, $description, $varType, $varName, $varValue);


		// horizontal rule
		$content .= "<tr><td><hr></td></tr>\n";


		// TLD Logging
		$name = "TLD Logging";
		$description = "If enabled, the script will log all queries for TLDs by the user.";
		$varType = "select";
		$varName = "SCRIPT__LOGS__enable";
		$varOptions = array(
						"Enabled" => "true",
						"Disabled" => "false"
						);
		$varSelected = ($this->CONFIG->SCRIPT['LOGS']['enable'] ? "Enabled" : "Disabled");
		$varValue = array(
						"options" => $varOptions,
						"selected" => $varSelected
						);
		$content .= $this->DISPLAY->constructTableVariable($name, $description, $varType, $varName, $varValue);


		// Log all queries
		$name = "Log All Queries";
		$description = "If enabled, the script will log all TLD requests, including all auto-search requests. If disabled, the script will only log TLD queries that were specified by the user and it will ignore any queries that were automatically processed by the script (such as auto-search queries).";
		$varType = "select";
		$varName = "SCRIPT__LOGS__all";
		$varOptions = array(
						"Enabled" => "true",
						"Disabled" => "false"
						);
		$varSelected = ($this->CONFIG->SCRIPT['LOGS']['all'] ? "Enabled" : "Disabled");
		$varValue = array(
						"options" => $varOptions,
						"selected" => $varSelected
						);
		$content .= $this->DISPLAY->constructTableVariable($name, $description, $varType, $varName, $varValue);


		// Log filename
		$name = "Log Filename / Path";
		$description = "The path and filename to the log.";
		$varType = "text";
		$helpcode = 2;
		$varName = "SCRIPT__LOGS__file";
		$varValue = $this->CONFIG->SCRIPT['LOGS']['file'];
		$content .= $this->DISPLAY->constructTableVariable($name, $description, $varType, $varName, $varValue, 40, $helpcode);


		// Log type
		$name = "Log Type";
		$description = "The log type specifies whether you want to use the long format or the short format of logging.";
		$varType = "select";
		$varName = "SCRIPT__LOGS__type";
		$varOptions = array(
						"Short" => "short",
						"Long" => "long"
						);
		$varSelected = ($this->CONFIG->SCRIPT['LOGS']['type'] == "long" ? "Long" : "Short");
		$varValue = array(
						"options" => $varOptions,
						"selected" => $varSelected
						);
		$content .= $this->DISPLAY->constructTableVariable($name, $description, $varType, $varName, $varValue);


		// Log short format
		$name = "Short Log Format";
		$description = "The format for short logging.";
		$varType = "textarea";
		$varSize = array(
					"rows" => 4, 
					"cols" => 60
					);
		$helpcode = 2;
		$varName = "SCRIPT__LOGS__short_format";
		$varValue = $this->CONFIG->SCRIPT['LOGS']['short_format'];
		$content .= $this->DISPLAY->constructTableVariable($name, $description, $varType, $varName, $varValue, $varSize, $helpcode);


		// Log long format
		$name = "Long Log Format";
		$description = "The format for long logging.";
		$varType = "textarea";
		$varSize = array(
					"rows" => 4, 
					"cols" => 60
					);
		$helpcode = 2;
		$varName = "SCRIPT__LOGS__long_format";
		$varValue = $this->CONFIG->SCRIPT['LOGS']['long_format'];
		$content .= $this->DISPLAY->constructTableVariable($name, $description, $varType, $varName, $varValue, $varSize, $helpcode);


		$content .= "</table>";


		// end form
		$content .= "<div align='center'>" . $this->DISPLAY->constructEndForm("Save Config") . "</div>";

		// display page
		$this->DISPLAY->displayPage($content, "Script Settings", null, $javascript);
	}


	/* ------------------------------------------------------------------ */
	//	Whois Settings Page
	//	Contains all whois/query related settings.
	/* ------------------------------------------------------------------ */
	
	function page_WhoisSettings()
	{

		// +------------------------------
		//	Javascript: Ensure trailing slash on abs path
		// +------------------------------
		$javascript .= "<script LANGUAGE=\"Javascript\" type=\"text/javascript\">
			function check_AbsPath() {
				if (
						(
							document.getElementById('SCRIPT__absolute_path').value.charAt (
							document.getElementById('SCRIPT__absolute_path').value.length -1
								) != '/'
						)
						&&
						(document.getElementById('SCRIPT__absolute_path').value.substr (
							document.getElementById('SCRIPT__absolute_path').value.length -2, document.getElementById('SCRIPT__absolute_path').value.length
							) != \"\\\\\\\\\"
						)
						&&
						(document.getElementById('SCRIPT__absolute_path').value != \"\")
					)
				{
					window.alert(\"Last character of absolute path MUST BE a '/' or '\\\\\\\\'\");
					document.getElementById('SCRIPT__absolute_path').focus();
					document.getElementById('SCRIPT__absolute_path').select();
					return false;
				}

				return true;
			}

			function load_AbsPath() {
				document.getElementById('SCRIPT__absolute_path').value = \"{$abs_path}\";
			}
		</script>
		";


		$content .= $this->DISPLAY->constructOutput("The settings below concern the manipulation of whois queries and actions.");

		// +------------------------------
		//	Construct form with table of inputs
		// +------------------------------
		
		// start form
		$content .= $this->DISPLAY->constructStartForm("goModifyConfig", "configSCRIPT_form", "POST");

		$content .= "<input type='hidden' name='adminpanel_filename' value='../config/config.php:::SCRIPT__limit_bypass,SCRIPT__translate_idn,SCRIPT__idn_extensions,SCRIPT__CACHE__time,SCRIPT__QUERYWHOIS__enabled,SCRIPT__QUERYWHOIS__regex,SCRIPT__remove_notices,SCRIPT__image_verification,SCRIPT__email_images'>\n";

		// we need to copy the value of absolute path so that it can also be written for whois.php (under a different variable)
		$content .= "<input type='hidden' name='adminpanel_copy' value='SCRIPT__absolute_path:::absolute_path'>\n";

		$content .=  "<input type='hidden' name='adminpanel_class' value='CONFIG'>\n";


		$content .= "<table>";


		// Query Limit Bypass
		$name = "Query Limit Bypass";
		$description = "If enabled, the script will automatically attempt to use a query that prevents going over the query limit of the nameserver that it is searching.";
		$varType = "select";
		$varName = "SCRIPT__limit_bypass";
		$varOptions = array(
						"Enabled" => "true",
						"Disabled" => "false"
						);
		$varSelected = ($this->CONFIG->SCRIPT['limit_bypass'] ? "Enabled" : "Disabled");
		$varValue = array(
						"options" => $varOptions,
						"selected" => $varSelected
						);
		$content .= $this->DISPLAY->constructTableVariable($name, $description, $varType, $varName, $varValue);


		// horizontal rule
		$content .= "<tr><td><hr></td></tr>\n";


		// IDN support
		$name = "IDN Support";
		$description = "If enabled, the script will support internationalized domain names (IDN) that will allow foreign character domains.";
		$varType = "select";
		$varName = "SCRIPT__translate_idn";
		$varOptions = array(
						"Enabled" => "true",
						"Disabled" => "false"
						);
		$varSelected = ($this->CONFIG->SCRIPT['translate_idn'] ? "Enabled" : "Disabled");
		$varValue = array(
						"options" => $varOptions,
						"selected" => $varSelected
						);
		$helpCode = 6;
		$content .= $this->DISPLAY->constructTableVariable($name, $description, $varType, $varName, $varValue, null, $helpCode);


		// IDN Supported Extensions
		$name = "IDN Extensions";
		$description = "The extensions listed here will have IDN support. If you want all extensions to support IDN, then leave this blank.<br>Example: <span style=\"color: blue\">com, net, org</span>";
		$varType = "text";
		$varName = "SCRIPT__idn_extensions";
		$varValue = $this->CONFIG->SCRIPT['idn_extensions'];
		$content .= $this->DISPLAY->constructTableVariable($name, $description, $varType, $varName, $varValue);


		// horizontal rule
		$content .= "<tr><td><hr></td></tr>\n";


		// Query Cache Time
		$name = "Query Cache Time";
		$description = "The number of minutes to cache the whois results of a query. By caching results, you will be using fewer queries, your script will be faster, and there is less a chance of a nameserver rejecting you due to too many queries.<br>Set to <span style=\"color: blue\">0</span> to disable.";
		$varType = "text";
		$varName = "SCRIPT__CACHE__time";
		$varValue = $this->CONFIG->SCRIPT['CACHE']['time'];
		$content .= $this->DISPLAY->constructTableVariable($name, $description, $varType, $varName, $varValue, 4);


		// Query Whois server
		$name = "Query Whois Server";
		$description = "If enabled, the script will attempt to locate a whois server for this domain and retrieve further whois contact details.";
		$varType = "select";
		$varName = "SCRIPT__QUERYWHOIS__enabled";
		$varOptions = array(
						"Enabled" => "true",
						"Disabled" => "false"
						);
		$varSelected = ($this->CONFIG->SCRIPT['QUERYWHOIS']['enabled'] ? "Enabled" : "Disabled");
		$varValue = array(
						"options" => $varOptions,
						"selected" => $varSelected
						);
		$content .= $this->DISPLAY->constructTableVariable($name, $description, $varType, $varName, $varValue);


		// Query Whois Server String
		$name = "Query Whois Server Regex (advanced)";
		$description = "The perl regular expression that is used to locate the whois server. DO NOT MODIFY THIS VALUE UNLESS YOU KNOW WHAT YOU ARE DOING.<br>Default: <span style=\"color: blue\">/(?:Whois Server|Registrar Whois): (.+)/i</span>";
		$varType = "text";
		$varName = "SCRIPT__QUERYWHOIS__regex";
		$varValue = $this->CONFIG->SCRIPT['QUERYWHOIS']['regex'];
		$content .= $this->DISPLAY->constructTableVariable($name, $description, $varType, $varName, $varValue);


		// Query Formatting
		$name = "Remove Common Query Notices";
		$description = "If enabled, the script will try to remove common query notices from the whois server, including NOTICE and TERMS OF USE statements.";
		$varType = "select";
		$varName = "SCRIPT__remove_notices";
		$varOptions = array(
						"Enabled" => "true",
						"Disabled" => "false"
						);
		$varSelected = ($this->CONFIG->SCRIPT['remove_notices'] ? "Enabled" : "Disabled");
		$varValue = array(
						"options" => $varOptions,
						"selected" => $varSelected
						);
		$content .= $this->DISPLAY->constructTableVariable($name, $description, $varType, $varName, $varValue);



		// horizontal rule
		$content .= "<tr><td><hr></td></tr>\n";



		// Image Verification
		$name = "Whois Report Image Verification";
		$description = "If enabled, the script will display a verification image that requires correct user input in order to view whois reports. In order to use this feature, the server must have GD installed.";
		$varType = "select";
		$varName = "SCRIPT__image_verification";
		$varOptions = array(
						"Enabled" => "gd",
						"Disabled" => ""
						);
		$varSelected = ($this->CONFIG->SCRIPT['image_verification'] == "gd" ? "Enabled" : "Disabled");
		$varValue = array(
						"options" => $varOptions,
						"selected" => $varSelected
						);
		$content .= $this->DISPLAY->constructTableVariable($name, $description, $varType, $varName, $varValue);



		// Email Images
		$name = "Whois Report Email Protection";
		$description = "If enabled, the script will display an image for each email address contained within the report. This helps to stop spam bots from abusing the script by collecting contact information of registered domain names. In order to use this feature, the server must have GD installed.";
		$varType = "select";
		$varName = "SCRIPT__email_images";
		$varOptions = array(
						"Enabled" => "gd",
						"Disabled" => ""
						);
		$varSelected = ($this->CONFIG->SCRIPT['email_images'] == "gd" ? "Enabled" : "Disabled");
		$varValue = array(
						"options" => $varOptions,
						"selected" => $varSelected
						);
		$content .= $this->DISPLAY->constructTableVariable($name, $description, $varType, $varName, $varValue);



		$content .= "</table>";


		// end form
		$content .= "<div align='center'>" . $this->DISPLAY->constructEndForm("Save Config") . "</div>";

		// display page
		$this->DISPLAY->displayPage($content, "Whois Settings", null, $javascript);
	}



	/* ------------------------------------------------------------------ */
	//	Buy Mode Settings Page
	//	Contains all buy mode related settings.
	/* ------------------------------------------------------------------ */
	
	function page_BuyModeSettings()
	{

		$content .= $this->DISPLAY->constructOutput("The settings below concern the settings of the script's Buy Mode feature.");

		// +------------------------------
		//	Construct form with table of inputs
		// +------------------------------
		
		// start form
		$content .= $this->DISPLAY->constructStartForm("goModifyConfig", "configSCRIPT_form");

		$content .= "<input type='hidden' name='adminpanel_filename' value='../config/config.php:::BUYMODE__CONFIG__enable,BUYMODE__CONFIG__tld_search,BUYMODE__CONFIG__domain_suggestions,BUYMODE__CONFIG__currency_format,BUYMODE__CONFIG__currency_string,BUYMODE__PRICETABLE__enable,BUYMODE__PRICETABLE__num_rows,BUYMODE__PRICETABLE__num_columns,BUYMODE__PRICETABLE__include'>\n";

		$content .=  "<input type='hidden' name='adminpanel_class' value='CONFIG'>\n";


		$content .= "<table>";

		// Enable Buy Mode
		$name = "Buy Mode";
		$description = "Buy mode affects the entire script. Enabling Buy Mode will result in the script assuming that it will eventually pass the domain to a script that will handle the purchase of an	available domain. Buy mode will also give alternative TLDs to domains that are not available. If buymode is disabled, then the script will act as a normal whois script... displaying the whois information on every request, as opposed to just saying if the domain is available or not.";
		$varType = "select";
		$varName = "BUYMODE__CONFIG__enable";
		$varOptions = array(
						"Enabled" => "true",
						"Disabled" => "false"
						);
		$varSelected = ($this->CONFIG->BUYMODE['CONFIG']['enable'] ? "Enabled" : "Disabled");
		$varValue = array(
						"options" => $varOptions,
						"selected" => $varSelected
						);
		$content .= $this->DISPLAY->constructTableVariable($name, $description, $varType, $varName, $varValue);


		// horizontal rule
		$content .= "<tr><td><hr></td></tr>\n";


		// Automatic TLD search
		$name = "Automatic TLD Search";
		$description = "The script can automatically check if certain alternative extensions for a given domain are available. You can specify those here. Leave blank to disable.<br>ex: com, net, org";
		$varType = "text";
		$varName = "BUYMODE__CONFIG__tld_search";
		$varValue = $this->CONFIG->BUYMODE['CONFIG']['tld_search'];
		$content .= $this->DISPLAY->constructTableVariable($name, $description, $varType, $varName, $varValue);


		// Suggested Domains search
		$name = "Suggested Domains";
		$description = "The script can automatically check if certain alternative domains exist if the domain provided by the user is unavailable. Unlike automatic TLD search, suggested domains are only displayed when the original domain is unavailable and the suggested domain is available. Leave blank to disable.";
		$varType = "text";
		$helpcode = 5;
		$varName = "BUYMODE__CONFIG__domain_suggestions";
		$varValue = $this->CONFIG->BUYMODE['CONFIG']['domain_suggestions'];
		$content .= $this->DISPLAY->constructTableVariable($name, $description, $varType, $varName, $varValue, 40, $helpcode);


		// Currency Format
		$name = "Currency Format";
		$description = "The format of the currency that you want to display the cost of the domains with.";
		$varType = "text";
		$varName = "BUYMODE__CONFIG__currency_format";
		$helpcode = 3;
		$varValue = $this->CONFIG->BUYMODE['CONFIG']['currency_format'];
		$content .= $this->DISPLAY->constructTableVariable($name, $description, $varType, $varName, $varValue, null, $helpcode);


		// Currency String
		$name = "Currency String";
		$description = "Sometimes the same symbol is used for currency in different countries and it can be confusing as to which currency your site is using. The currency string will be prepended to the final format price of a domain. This can be left blank.<br>Ex: USD (US Dollar) will display prices as USD $9.95.";
		$varType = "text";
		$varName = "BUYMODE__CONFIG__currency_string";
		$varValue = $this->CONFIG->BUYMODE['CONFIG']['currency_string'];
		$content .= $this->DISPLAY->constructTableVariable($name, $description, $varType, $varName, $varValue);


		// horizontal rule
		$content .= "<tr><td><hr></td></tr>\n";


		// Price Table
		$name = "Price Table";
		$description = "The price table automatically creates a table of prices for specific domains.";
		$varType = "select";
		$varName = "BUYMODE__PRICETABLE__enable";
		$varOptions = array(
						"Enabled" => "true",
						"Disabled" => "false"
						);
		$varSelected = ($this->CONFIG->BUYMODE['PRICETABLE']['enable'] ? "Enabled" : "Disabled");
		$varValue = array(
						"options" => $varOptions,
						"selected" => $varSelected
						);
		$content .= $this->DISPLAY->constructTableVariable($name, $description, $varType, $varName, $varValue);


		// Price Table row number
		$name = "Price Table Rows";
		$description = "The maximum number of rows that will be displayed in the price table.";
		$varType = "text";
		$varName = "BUYMODE__PRICETABLE__num_rows";
		$varValue = $this->CONFIG->BUYMODE['PRICETABLE']['num_rows'];
		$content .= $this->DISPLAY->constructTableVariable($name, $description, $varType, $varName, $varValue, 3);


		// Price Table column number
		$name = "Price Table Columns";
		$description = "The maximum number of columns that will be displayed in the price table.";
		$varType = "text";
		$varName = "BUYMODE__PRICETABLE__num_columns";
		$varValue = $this->CONFIG->BUYMODE['PRICETABLE']['num_columns'];
		$content .= $this->DISPLAY->constructTableVariable($name, $description, $varType, $varName, $varValue, 3);


		// Price Table extensions
		$name = "Price Table TLDs";
		$description = "These TLDs will be shown in the price table in the order you list here. You can leave this blank to display the default TLDs.<br>ex: com, net, org";
		$varType = "text";
		$varName = "BUYMODE__PRICETABLE__include";
		$varValue = $this->CONFIG->BUYMODE['PRICETABLE']['include'];
		$content .= $this->DISPLAY->constructTableVariable($name, $description, $varType, $varName, $varValue, 40);

		$content .= "</table>";


		// end form
		$content .= "<div align='center'>" . $this->DISPLAY->constructEndForm("Save Config") . "</div>";

		// display page
		$this->DISPLAY->displayPage($content, "Buy Mode Settings");
	}



	/* ------------------------------------------------------------------ */
	//	TLD Settings Page
	//	Contains all TLD price settings.
	/* ------------------------------------------------------------------ */
	
	function page_TLDSettings()
	{
		$content .= $this->DISPLAY->constructOutput("Below you can set the prices of the extensions currently recognized by the script. If you do not see an extension here, then you likely do not have a nameserver configured for it. Enabled extensions are in <span style=\"color: green;\">green</span>.");

		// +------------------------------
		//	Construct form with table of inputs
		// +------------------------------
		
		// start form
		$content .= $this->DISPLAY->constructStartForm("goModifyTLDPrices", "configTLDPrices_form");

		$content .=  "<input type='hidden' name='adminpanel_class' value='CONFIG'>\n";


		$content .= "<table style=\"width: 100%\">";


		// +------------------------------
		//	Get All Servers & TLDs
		// +------------------------------
		$SERVERS = $this->getAllServers();
		$TLDS = array_keys($SERVERS);


		// assume all servers disabled until proven otherwise
		$disabledServers = $SERVERS;

		// initialize empty enabled array
		$enabledExtensions = array();

		// cycle through all extensions
		foreach($TLDS as $extension)
		{
			// cycle through all servers for extension
			foreach($SERVERS[$extension] as $server)
			{
				// if server enabled
				if ($this->CONFIG->NAMESERVERS[$server]['enabled'])
				{
					// format enabled extensions into array
					$serverEnabledExtensions = explode(",", str_replace(" ", "", $this->CONFIG->NAMESERVERS[$server]['extensions']));

					// check if extension listed in enabled extensions array
					if (in_array($extension, $serverEnabledExtensions))
					{
						// delete from disabled extensions
						unset($disabledServers[$extension]);

						// add to enabled extensions
						$enabledExtensions[] = $extension;

						// skip rest of servers for this extension
						break;
					}
				}
			}
		}


		unset($TLDS);

		// get extensions that are disabled
		$disabledExtensions = array_keys($disabledServers);

		// sort all extensions alphabetically
		sort($disabledExtensions);
		sort($enabledExtensions);

		// combine extensions with enabled extensions appearing first
		$TLDS = array_merge($enabledExtensions, $disabledExtensions);


		// extensions that must be created
		$addExtensions = "";

		// extensions that already exist
		$modifyExtensions = "";

		
		$content .= "<tr>
						<td><div style=\"font-weight: bold;\">" . $this->DISPLAY->constructOutput("Extension") . "</div></td>
						<td><div style=\"font-weight: bold;\">" . $this->DISPLAY->constructOutput("Price") . "</div></td>
						<td><div style=\"font-weight: bold;\">" . $this->DISPLAY->constructOutput("Extension") . "</div></td>
						<td><div style=\"font-weight: bold;\">" . $this->DISPLAY->constructOutput("Price") . "</div></td>
						<td><div style=\"font-weight: bold;\">" . $this->DISPLAY->constructOutput("Extension") . "</div></td>
						<td><div style=\"font-weight: bold;\">" . $this->DISPLAY->constructOutput("Price") . "</div></td>
					</tr>";


		// table row count
		$count = 0;

		foreach ($TLDS as $extension)
		{
			// ensure extension not empty
			if (!empty($extension))
			{
				// insert new row if more than 3 columns
				if ($count > 2)
				{
					$count = 0;
					$content .= "</tr>\n<tr>";
				}


				// extension display
				$name = $extension;
				
				// extension variable (with '___' instead of '.')
				$varName = "BUYMODE__PRICES__" . str_replace(".", "___", $extension);

				// if variable exists in config
				if (isset($this->CONFIG->BUYMODE['PRICES'][$extension]))
				{
					// process variable as needing to be modified
					$varValue = $this->CONFIG->BUYMODE['PRICES'][$extension];
					$modifyExtensions .= ",{$extension}";
				}

				// if variable does not exist in config
				else
				{
					// process variable as needing to be created
					$varValue = "0.00";
					$addExtensions .= ",{$extension}";
				}

				// actual display
				$content .= "<td>" . (in_array($extension, $enabledExtensions) ? "<span style=\"color: green;\">" . $this->DISPLAY->constructOutput($name, 20) . "</span>" : "<span style=\"color: red;\">" . $this->DISPLAY->constructOutput($name, 20) . "</span>") . "</td>
								<td><input type=\"text\" name=\"{$varName}\" value=\"{$varValue}\" size=\"5\"></td>\n";

				$count++;
			}
		}


		$content .= "</tr>\n</table><br>";


		// hidden var: variables that will be created
		$content .= "<input type='hidden' name='adminpanel_addExtensions' value='" . substr($addExtensions, 1) . "'>\n";

		// hidden var: variables that will be modified
		$content .= "<input type='hidden' name='adminpanel_modifyExtensions' value='" . substr($modifyExtensions, 1) . "'>\n";

		// end form
		$content .= "<div align='center'>" . $this->DISPLAY->constructEndForm("Save Config") . "</div>";

		// display page
		$this->DISPLAY->displayPage($content, "TLD Price Settings");
	}



	/* ------------------------------------------------------------------ */
	//	Nameserver Settings Page
	//	Contains all nameserver related settings.
	/* ------------------------------------------------------------------ */
	
	function page_NameserverSettings()
	{
		// boolean if java enabled
		$enableJava = ($_GET['displayall'] != "1");

		// special javascript code for page
		$javascript = <<< JAVSCRIPT_CODE
		<SCRIPT LANGUAGE="JavaScript" TYPE="text/javascript">
			function showDiv(id)
			{
				divObject = document.getElementById(id);

				divObject.style.display = "block";
			}

			function hideDiv(id)
			{
				divObject = document.getElementById(id);

				//divObject.style.visibility = "hidden";
				divObject.style.display = "none";
			}


			function showhideDiv(id)
			{
				if (document.getElementById(id).style.display == "none")
					showDiv(id);
				else
					hideDiv(id);
			}


			function updateTextExtensions(checkedID, checkedExtension, enabledTextExtensionsID, disabledTextExtensionsID)
			{
				checkbox = document.getElementById(checkedID);
				enabledText = document.getElementById(enabledTextExtensionsID);
				disabledText = document.getElementById(disabledTextExtensionsID);

				enabledText.value = stripChar(enabledText.value, ' ');
				disabledText.value = stripChar(disabledText.value, ' ');

				var regExpReplace = new RegExp ('(,' + checkedExtension + ')|(' + checkedExtension + ',)|(' + checkedExtension + ')', 'ig') ;

				disabledText.value = disabledText.value.replace(regExpReplace, '') ;
				enabledText.value = enabledText.value.replace(regExpReplace, '') ;

				if (checkbox.checked)
				{
					if (enabledText.value != "")
						enabledText.value += ',';

					enabledText.value += checkedExtension;
				}
				else
				{
					if (disabledText.value != "")
						disabledText.value += ',';

					disabledText.value += checkedExtension;
				}
			}


			function updateCheckedExtensions(enabledTextExtensionsID, disabledTextExtensionsID, baseID, extensionID)
			{
				enabledText = document.getElementById(enabledTextExtensionsID);
				disabledText = document.getElementById(disabledTextExtensionsID);
				checkExtensionDiv = document.getElementById(baseID + "_checkExtensions");

				enabledText.value = stripChar(enabledText.value, ' ');
				disabledText.value = stripChar(disabledText.value, ' ');

				var enabledExtensions = enabledText.value.split(",");
				var disabledExtensions = disabledText.value.split(",");

				var newExtensionData = "<table><tr>";

				var i, totalCount=0;

				if (enabledExtensions[0] != "")
				{
					for(i=0; i < enabledExtensions.length; i++, totalCount++)
					{
						if (totalCount % 5 == 0)
							newExtensionData += "</tr>\\r\\n<tr>";

						newExtensionData += "<td><input type=\"checkbox\" ID=\"" + baseID + "_ext_" + enabledExtensions[i] + "\" onClick=\"javascript:updateTextExtensions('" + baseID + "_ext_" + enabledExtensions[i] + "', '" + enabledExtensions[i] + "', '" + extensionID + "__extensions', '" + extensionID + "__extensions_disabled');\" checked>." + enabledExtensions[i] + "</td>";
					}
				}

				if (disabledExtensions[0] != "")
				{
					for(i=0; i < disabledExtensions.length; i++, totalCount++)
					{
						if (totalCount % 5 == 0)
							newExtensionData += "</tr>\\r\\n<tr>";

						newExtensionData += "<td><input type=\"checkbox\" ID=\"" + baseID + "_ext_" + disabledExtensions[i] + "\" onClick=\"javascript:updateTextExtensions('" + baseID + "_ext_" + disabledExtensions[i] + "', '" + disabledExtensions[i] + "', '" + extensionID + "__extensions', '" + extensionID + "__extensions_disabled');\">." + disabledExtensions[i] + "</td>";
					}
				}

				newExtensionData += "</tr></table>";

				checkExtensionDiv.innerHTML = '';
				checkExtensionDiv.innerHTML = newExtensionData;

				showDiv(baseID + "_checkExtensions");
				hideDiv(baseID + "_textExtensions");
			}


			function stripChar(text, charToStrip)
			{
				var regExpReplace = new RegExp (charToStrip, 'i') ;

				return text.replace(regExpReplace, '');
			}
		</SCRIPT>
JAVSCRIPT_CODE;


		// start nameserver count for javascript at 1
		$nameserver_count = 1;

		// table heading
		$nameserverOutput .= "<tr><td><table style=\"width: 100%\">
										<tr>
										<td style=\"width: 300px\">" . $this->DISPLAY->constructOutput("<strong>Nameserver</strong>") . "</td>
										<td style=\"width: 100px\">" . $this->DISPLAY->constructOutput("<strong>Status</strong>") . "</td>
										<td>" . $this->DISPLAY->constructOutput("<strong>Settings</strong>") . "</td>
										</tr>
							  </table></td></tr>";

		// only output if at least one nameserver exists
		if (!empty($this->CONFIG->NAMESERVERS))
		{
			// alphabetical sort by nameserver address (sort by keys)
			ksort($this->CONFIG->NAMESERVERS);

			
			// create default enabled servers array
			$enabledServers = array();

			// create default disabled servers array
			$disabledServers = array();


			// sort by nameserver status
			foreach($this->CONFIG->NAMESERVERS as $nameserver => $nameserver_data)
			{
				if ($nameserver_data['enabled'])
					$enabledServers[$nameserver] = $nameserver_data;
				else
					$disabledServers[$nameserver] = $nameserver_data;
			}


			// combine sorted arrays with enabled appearing first
			$sortedServers = $enabledServers + $disabledServers;

			// cycle through all servers
			foreach($sortedServers as $nameserver => $nameserver_data)
			{
				// convert nameserver's name for variable name
				$nameserver_id = str_replace(".", "___", $nameserver);

				// being nameserver output
				$nameserverOutput .= "
									<tr>
									<td>
										<div>
										<table style=\"width: 100%\">
											<tr>
											<td style=\"width: 300px\">" . $this->DISPLAY->constructOutput("{$nameserver}<span style=\"font-size: 8pt; margin-left: 10px;\">(<a href=\"?page=DeleteNameserver&amp;nameserver={$nameserver}\">delete</a>)</span>") . "</td>
											<td style=\"width: 100px\">
												<select name=\"NAMESERVERS__{$nameserver_id}__enabled\">
													<option value=\"true\"" . ($nameserver_data['enabled'] ? "selected" : "") . ">Enabled</option>
													<option value=\"false\"" . (!$nameserver_data['enabled'] ? "selected" : "") . ">Disabled</option>
												</select>
											</td>";

												// add to var save list
												$allSaveVars .= str_replace("___", ".", ",NAMESERVERS__{$nameserver_id}__enabled");

											$nameserverOutput .= "
											<td>" . $this->DISPLAY->constructOutput("[<a href=\"javascript:showhideDiv('nameserver{$nameserver_count}');\">Show/Hide Settings</a>]") . "</td>
											</tr>
										</table>
										</div>
										<div style=\"font-size: 8pt; margin-left: 10px;\">";

											// display enabled extensions if they exist
											if (!empty($nameserver_data['extensions']))
											{
												$nameserverOutput .= "<span style=\"color: green;\">" . str_replace(",", " ", $nameserver_data['extensions']) . "</span>";
											}

											// display disabled extensions if they exist
											if (!empty($nameserver_data['extensions_disabled']))
											{
												// insert extra space if enabled extensions exist
												$nameserverOutput .= "<span style=\"color: red;\">" . (!empty($nameserver_data['extensions']) ? 
												" " . str_replace(",", " ", $nameserver_data['extensions_disabled']) 
												: str_replace(",", " ", $nameserver_data['extensions_disabled'])) . "</span>";
											}

									// resume output
									$nameserverOutput .= "</div></td>
									</tr>
									</tr>
									<tr>
									<td>
									<div id=\"nameserver{$nameserver_count}\" style=\"display: " . ($enableJava ? "none" : "block") . "; z-index:1;\">

										<table>";

										// Timeout Time
										$name = "Timeout Time";
										$description = "The maximum amount of time to spend trying to get a response from the nameserver (in seconds).";
										$varType = "text";
										$varName = "NAMESERVERS__{$nameserver_id}__timeout";
										$varValue = $nameserver_data['timeout'];
										$nameserverOutput .= $this->DISPLAY->constructTableVariable($name, $description, $varType, $varName, $varValue);

										// add variable to final save string
										$allSaveVars .= str_replace("___", ".", ",{$varName}");


										// Normal Keword
										$name = "Keyword";
										$description = "The keyword(s) to look for when querying a server. If this is found, then the script will assume the domain is available. If you want to make the script assume the domain is unavailable unless the keyword is found, place <span style=\"color: blue\">!!!</span> in front of the keyword. Regular expressions are acceptable. This is CaSe SeNsiTive.";
										$varType = "text";
										$varName = "NAMESERVERS__{$nameserver_id}__keyword";
										$varValue = $nameserver_data['keyword'];
										$nameserverOutput .= $this->DISPLAY->constructTableVariable($name, $description, $varType, $varName, $varValue);

										// add variable to final save string
										$allSaveVars .= str_replace("___", ".", ",{$varName}");

										
										// Normal Format
										$name = "Query Format";
										$description = "The format of the query to send to the server. The tags <span style=\"color: blue\">[[DOMAIN]]</span> and <span style=\"color: blue\">[[EXT]]</span> will be replaced by the domain and extension of the query.<br>ex: <span style=\"color: blue\">[[DOMAIN]].[[EXT]]</span>";
										$varType = "text";
										$varName = "NAMESERVERS__{$nameserver_id}__format";
										$varValue = $nameserver_data['format'];
										$nameserverOutput .= $this->DISPLAY->constructTableVariable($name, $description, $varType, $varName, $varValue);

										// add variable to final save string
										$allSaveVars .= str_replace("___", ".", ",{$varName}");


										// Limit Keword
										$name = "Limit Keyword";
										$description = "The limit keyword will be used when the script is trying to only check if the domain is available. Not all servers support this format, in which case you should just make it the same as the regular keyword. This is CaSe SeNsiTive.";
										$varType = "text";
										$varName = "NAMESERVERS__{$nameserver_id}__limit_keyword";
										$varValue = $nameserver_data['limit_keyword'];
										$nameserverOutput .= $this->DISPLAY->constructTableVariable($name, $description, $varType, $varName, $varValue);

										// add variable to final save string
										$allSaveVars .= str_replace("___", ".", ",{$varName}");

										
										// Limit Format
										$name = "Limit Query Format";
										$description = "The limit format will be used when the script is trying to only check if the domain is available. Not all servers support this format, in which case you should just make it the same as the regular query format. The tags <span style=\"color: blue\">[[DOMAIN]]</span> and <span style=\"color: blue\">[[EXT]]</span> will be replaced by the domain and extension of the query.<br>ex: <span style=\"color: blue\">[[DOMAIN]].[[EXT]]</span>";
										$varType = "text";
										$varName = "NAMESERVERS__{$nameserver_id}__limit_format";
										$varValue = $nameserver_data['limit_format'];
										$nameserverOutput .= $this->DISPLAY->constructTableVariable($name, $description, $varType, $varName, $varValue);

										// add variable to final save string
										$allSaveVars .= str_replace("___", ".", ",{$varName}");

										// resume nameserver output
										$nameserverOutput .="
										<tr>
											<td><strong>Advanced Settings:</strong> &nbsp;&nbsp; [<a href=\"javascript:showhideDiv('nameserver{$nameserver_count}_advancedSettings');\">Show / Hide Advanced Settings</a>]</td>
										</tr>
										<tr>
											<td>Many of the above settings can be customized even further. However, this is usually only required by those running advanced configurations or needing to configure the script to interact with an irregular nameserver.</td>
										</tr>
										<tr>
										<td>

										<div id=\"nameserver{$nameserver_count}_advancedSettings\" style=\"display: " . ($enableJava ? "none" : "block") . ";\">
											<table>";

										// Custom Query (advanced)
										$name = "Custom Query (advanced)";
										$description = "Here you can set a completely custom query that does not conform to any traditional standards. Unless you know what you are doing, it is <strong>highly recommended that you leave this as default</strong>. The tag <span style=\"color: blue\">[[QUERY]]</span> will be replaced by the query format above.<br>ex Default: <span style=\"color: blue\">[[QUERY]]\\r\\n/span>";
										$varType = "text";
										$varName = "NAMESERVERS__{$nameserver_id}__advanced_custom_query";
										$varValue = $nameserver_data['advanced_custom_query'];
										$nameserverOutput .= $this->DISPLAY->constructTableVariable($name, $description, $varType, $varName, $varValue);

										// add variable to final save string
										$allSaveVars .= str_replace("___", ".", ",{$varName}");


										// Advanced Limit Custom Query
										$name = "Limit Custom Query (advanced)";
										$description = "This setting is similar to the setting above, except that this will be used in limit situations (see above). The tag <span style=\"color: blue\">[[QUERY]]</span> will be replaced by the limit query format above.<br>ex Default: <span style=\"color: blue\">[[QUERY]]\\r\\n</span>";
										$varType = "text";
										$varName = "NAMESERVERS__{$nameserver_id}__advanced_limit_custom_query";
										$varValue = $nameserver_data['advanced_limit_custom_query'];
										$nameserverOutput .= $this->DISPLAY->constructTableVariable($name, $description, $varType, $varName, $varValue);

										// add variable to final save string
										$allSaveVars .= str_replace("___", ".", ",{$varName}");


										// Advanced Custom Query Address & Port
										$name = "Custom Query Address & Port (advanced)";
										$description = "Here you can set a non-standard address & port to connect to. Unless you know what you are doing, it is <strong>highly recommended that you leave this as default</strong>. Please note: You MUST specify the port. The tag <span style=\"color: blue\">[[SERVER]]</span> will be replaced by the server name above.<br>ex Default: <span style=\"color: blue\">[[SERVER]]:43</span><br>non-standard: <span style=\"color: blue\">www.[[SERVER]]:80/whoislookup.cgi</span>";
										$varType = "text";
										$varName = "NAMESERVERS__{$nameserver_id}__advanced_custom_query_address";
										$varValue = $nameserver_data['advanced_custom_query_address'];
										$nameserverOutput .= $this->DISPLAY->constructTableVariable($name, $description, $varType, $varName, $varValue);

										// add variable to final save string
										$allSaveVars .= str_replace("___", ".", ",{$varName}");


										// resume output
										$nameserverOutput .= "
											</table>
										</div>
										</td>
										</tr>";



										// resume nameserver output
										$nameserverOutput .="
										<tr>
											<td><strong>Recognized Extensions:</strong> &nbsp;&nbsp; [<a href=\"javascript:showDiv('nameserver{$nameserver_count}_textExtensions'); hideDiv('nameserver{$nameserver_count}_checkExtensions');\">Add / Remove Extensions</a>]</td>
										</tr>
										<tr>
											<td>You can select or deselect the extensions below in order to enable them or disable them.</td>
										</tr>
										<tr>
										<td>

										<div id=\"nameserver{$nameserver_count}_checkExtensions\" style=\"display: " . (!$enableJava ? "none" : "block") . ";\">
											<table>
											<tr>";

										// initialize extension row count
										$ext_count = 0;
										
										// display enabled extensions if available
										if (!empty($nameserver_data['extensions']))
										{
											foreach(explode(",", str_replace(" ", "", $nameserver_data['extensions'])) as $current_ext)
											{
												// insert new row if column count more than 5
												if ($ext_count % 5 == 0)
													$nameserverOutput .= "</tr><tr>";

												// display extension with all the complicated javascript
												$nameserverOutput .= "<td><input type=\"checkbox\" ID=\"nameserver{$nameserver_count}_ext_{$current_ext}\" onClick=\"javascript:updateTextExtensions('nameserver{$nameserver_count}_ext_{$current_ext}', '{$current_ext}', 'NAMESERVERS__{$nameserver_id}__extensions', 'NAMESERVERS__{$nameserver_id}__extensions_disabled');\" checked>.{$current_ext}</td>";

												$ext_count++;

											}
										}

										// display disabled extensions if available
										if (!empty($nameserver_data['extensions_disabled']))
										{
											foreach(explode(",", str_replace(" ", "", $nameserver_data['extensions_disabled'])) as $current_ext)
											{
												// insert new row if column count more than 5 
												if ($ext_count % 5 == 0)
													$nameserverOutput .= "</tr><tr>";

												// display extension with all the complicated javascript
												$nameserverOutput .= "<td><input type=\"checkbox\" ID=\"nameserver{$nameserver_count}_ext_{$current_ext}\" onClick=\"javascript:updateTextExtensions('nameserver{$nameserver_count}_ext_{$current_ext}', '{$current_ext}', 'NAMESERVERS__{$nameserver_id}__extensions', 'NAMESERVERS__{$nameserver_id}__extensions_disabled');\">.{$current_ext}</td>";

												$ext_count++;

											}
										}


										// resume output
										$nameserverOutput .= "
											</tr>
											</table>
										</div>

										<div id=\"nameserver{$nameserver_count}_textExtensions\" style=\"display: " . ($enableJava ? "none" : "block") . ";\">
											<table>";


										// Enabled Extensions
										$name = "Enabled Extensions";
										$description = "These extensions are enabled.<br>ex: com, net, org";
										$varType = "text";
										$varName = "NAMESERVERS__{$nameserver_id}__extensions";
										$varValue = str_replace(" ", "", $nameserver_data['extensions']);
										$nameserverOutput .= $this->DISPLAY->constructTableVariable($name, $description, $varType, $varName, $varValue, 50);

										// add variable to final save string
										$allSaveVars .= str_replace("___", ".", ",{$varName}");


										// Disabled Extensions
										$name = "Disabled Extensions";
										$description = "These extensions are disabled.<br>ex: com, net, org";
										$varType = "text";
										$varName = "NAMESERVERS__{$nameserver_id}__extensions_disabled";
										$varValue = str_replace(" ", "", $nameserver_data['extensions_disabled']);
										$nameserverOutput .= $this->DISPLAY->constructTableVariable($name, $description, $varType, $varName, $varValue, 50);

										// add variable to final save string
										$allSaveVars .= str_replace("___", ".", ",{$varName}");


										$nameserverOutput .= "
											</table>";

										$nameserverOutput .= $this->DISPLAY->constructOutput("<a href=\"javascript:updateCheckedExtensions('NAMESERVERS__{$nameserver_id}__extensions', 'NAMESERVERS__{$nameserver_id}__extensions_disabled', 'nameserver{$nameserver_count}', 'NAMESERVERS__{$nameserver_id}');\">Save Extensions</a>");

										$nameserverOutput .="
										</td>
										</tr>
										</table>
									<hr>
									</div>
									<br>
									</td>
									</tr>\n\n\n";

					$nameserver_count++;
			}
		}


		$content .= $this->DISPLAY->constructOutput("The settings below concern the nameserver settings of your script.");

		// +------------------------------
		//	Construct form with table of inputs
		// +------------------------------
		
		// start form
		$content .= $this->DISPLAY->constructStartForm("goModifyConfig", "configNAMESERVER_form");

		$content .= "<input type='hidden' name='adminpanel_filename' value='../config/config.php:::" . substr($allSaveVars, 1) . "'>\n";

		$content .=  "<input type='hidden' name='adminpanel_class' value='CONFIG'>\n";

		$content .= "<br>";
		
		$content .= $this->DISPLAY->constructOutput("If you are having trouble with javascript, you can <a href='?page=NameserverSettings&amp;displayall=1'>Disable Javascript</a> and instead display all data on all nameservers.");


		$content .= "<table>";

		// display all nameserver output that was generated above
		$content .= $nameserverOutput;

		$content .= "</table>";

		// end form
		$content .= "<div align='center'>" . $this->DISPLAY->constructEndForm("Save Config") . "</div>";

		// display page
		$this->DISPLAY->displayPage($content, "Nameserver Settings", null, $javascript);
	}



	/* ------------------------------------------------------------------ */
	//	Template Settings Page
	//	Contains all template related settings.
	/* ------------------------------------------------------------------ */
	
	function page_TemplateSettings()
	{
		$content .= $this->DISPLAY->constructOutput("The settings below concern the display of the whois script.");

		// +------------------------------
		//	Construct form with table of inputs
		// +------------------------------
		
		// start form
		$content .= $this->DISPLAY->constructStartForm("goModifyConfig", "configADMIN_form");

		$content .= "<input type='hidden' name='adminpanel_filename' value='../config/config.php:::SCRIPT__TLD_DISPLAY__num_rows,SCRIPT__TLD_DISPLAY__num_columns,SCRIPT__TLD_DISPLAY__include,SCRIPT__TLD_DISPLAY__alphabetize,SCRIPT__custom_header,SCRIPT__custom_footer:::../config/template.php:::TEMPLATES__searchbar,TEMPLATES__availabledomains,TEMPLATES__unavailabledomains,TEMPLATES__header,TEMPLATES__footer,TEMPLATES__main,TEMPLATES__multiple_tlds,TEMPLATES__results,TEMPLATES__report,TEMPLATES__image_verification,TEMPLATES__error'>\n";

		$content .=  "<input type='hidden' name='adminpanel_class' value='TEMPLATE'>\n";
		$content .=  "<input type='hidden' name='adminpanel_class2' value='CONFIG:SCRIPT__TLD_DISPLAY__num_rows,SCRIPT__TLD_DISPLAY__num_columns,SCRIPT__TLD_DISPLAY__include,SCRIPT__TLD_DISPLAY__alphabetize,SCRIPT__custom_header,SCRIPT__custom_footer'>\n";


		$content .= "<table>";


		// TLD num rows
		$name = "TLD Search row number";
		$description = "The number of rows of extensions to display in the search bar (if using check boxes).";
		$varType = "text";
		$varName = "SCRIPT__TLD_DISPLAY__num_rows";
		$varValue = $this->CONFIG->SCRIPT['TLD_DISPLAY']['num_rows'];
		$content .= $this->DISPLAY->constructTableVariable($name, $description, $varType, $varName, $varValue, 3);


		// TLD num columns
		$name = "TLD Search column number";
		$description = "The number of columns of extensions to display in the search bar (if using check boxes).";
		$varType = "text";
		$varName = "SCRIPT__TLD_DISPLAY__num_columns";
		$varValue = $this->CONFIG->SCRIPT['TLD_DISPLAY']['num_columns'];
		$content .= $this->DISPLAY->constructTableVariable($name, $description, $varType, $varName, $varValue, 3);


		// TLD default extensions
		$name = "Default Display Extensions";
		$description = "The extensions that you want to display in the search box. You can leave this blank to display all extensions.<br>ex: com, net, org";
		$varType = "text";
		$varName = "SCRIPT__TLD_DISPLAY__include";
		$varValue = $this->CONFIG->SCRIPT['TLD_DISPLAY']['include'];
		$content .= $this->DISPLAY->constructTableVariable($name, $description, $varType, $varName, $varValue, 40);


		// Alphabetize TLD Search
		$name = "Alphabetize TLD Search";
		$description = "If enabled and default tld search display is blank, the script will automatically alphabetize all the TLDs. This is useful if you are using the drop down box for TLDs in your display.";
		$varType = "select";
		$varName = "SCRIPT__TLD_DISPLAY__alphabetize";
		$varOptions = array(
						"Enabled" => "true",
						"Disabled" => "false"
						);
		$varSelected = ($this->CONFIG->SCRIPT['TLD_DISPLAY']['alphabetize'] ? "Enabled" : "Disabled");
		$varValue = array(
						"options" => $varOptions,
						"selected" => $varSelected
						);
		$content .= $this->DISPLAY->constructTableVariable($name, $description, $varType, $varName, $varValue);


		// search bar template
		$name = "Search Bar";
		$description = "The search bar will be displayed whereever [[searchbar]] is found within your templates.";
		$varType = "textarea";
		$varName = "TEMPLATES__searchbar";
		$varValue = $this->TEMPLATE->TEMPLATES['searchbar'];
		$varSize = array(
					"rows" => 8, 
					"cols" => 65
					);
		$helpcode = "templatesSearchBar";
		$content .= $this->DISPLAY->constructTableVariable($name, $description, $varType, $varName, $varValue, $varSize, $helpcode);


		// horizontal rule
		$content .= "<tr><td><hr></td></tr>\n";


		// Domains available template
		$name = "Available Domains";
		$description = "The available domains template will be used in Buy Mode to display the results of one or multiple queries on domains. This template will be used for all queries that resulted in the domain being available for registration.";
		$varType = "textarea";
		$varName = "TEMPLATES__availabledomains";
		$varValue = $this->TEMPLATE->TEMPLATES['availabledomains'];
		$varSize = array(
					"rows" => 8, 
					"cols" => 65
					);
		$helpcode = "templatesDomains";
		$content .= $this->DISPLAY->constructTableVariable($name, $description, $varType, $varName, $varValue, $varSize, $helpcode);


		// Domains unavailable template
		$name = "Unavailable Domains";
		$description = "The unavailable domains template will be used in Buy Mode to display the results of one or multiple queries on domains. This template will be used for all queries that resulted in the domain being unavailable for registration (already registered).";
		$varType = "textarea";
		$varName = "TEMPLATES__unavailabledomains";
		$varValue = $this->TEMPLATE->TEMPLATES['unavailabledomains'];
		$varSize = array(
					"rows" => 8, 
					"cols" => 65
					);
		$helpcode = "templatesDomains";
		$content .= $this->DISPLAY->constructTableVariable($name, $description, $varType, $varName, $varValue, $varSize, $helpcode);


		// horizontal rule
		$content .= "<tr><td><hr></td></tr>\n";


		// Header template
		$name = "Default Header";
		$description = "This header will be displayed whenever [[header]] is found within your templates.";
		$varType = "textarea";
		$varName = "TEMPLATES__header";
		$varValue = $this->TEMPLATE->TEMPLATES['header'];
		$varSize = array(
					"rows" => 8, 
					"cols" => 65
					);
		$helpcode = "templatesHeader";
		$content .= $this->DISPLAY->constructTableVariable($name, $description, $varType, $varName, $varValue, $varSize, $helpcode);


		// Custom Header File
		$name = "Custom Header File";
		$description = "The file (if any) that you want to include as a header. This file will be included before the <font style=\"color: red\">Default Header</font> template found above and before all other output of the script. You can leave this blank to not include any external header file.<br>Example: <font style=\"color: blue\">/abs/path/to/header.php</font>";
		$varType = "text";
		$varName = "SCRIPT__custom_header";
		$varValue = $this->CONFIG->SCRIPT['custom_header'];
		$content .= $this->DISPLAY->constructTableVariable($name, $description, $varType, $varName, $varValue, 40);


		// Footer template
		$name = "Default Footer";
		$description = "This footer will be displayed whenever [[footer]] is found within your templates.";
		$varType = "textarea";
		$varName = "TEMPLATES__footer";
		$varValue = $this->TEMPLATE->TEMPLATES['footer'];
		$varSize = array(
					"rows" => 8, 
					"cols" => 65
					);
		$helpcode = "templatesFooter";
		$content .= $this->DISPLAY->constructTableVariable($name, $description, $varType, $varName, $varValue, $varSize, $helpcode);


		// Custom Footer File
		$name = "Custom Footer File";
		$description = "The file (if any) that you want to include as a footer. This file will be included after the <font style=\"color: red\">Default Footer</font> template found above and after all other output of the script. You can leave this blank to not include any external footer file.<br>Example: <font style=\"color: blue\">/abs/path/to/footer.php</font>";
		$varType = "text";
		$varName = "SCRIPT__custom_footer";
		$varValue = $this->CONFIG->SCRIPT['custom_footer'];
		$content .= $this->DISPLAY->constructTableVariable($name, $description, $varType, $varName, $varValue, 40);


		// horizontal rule
		$content .= "<tr><td><hr></td></tr>\n";


		// main page template
		$name = "Main Page";
		$description = "This template will be used for the main / front page of the script.";
		$varType = "textarea";
		$varName = "TEMPLATES__main";
		$varValue = $this->TEMPLATE->TEMPLATES['main'];
		$varSize = array(
					"rows" => 8, 
					"cols" => 65
					);
		$helpcode = "templatesMainPage";
		$content .= $this->DISPLAY->constructTableVariable($name, $description, $varType, $varName, $varValue, $varSize, $helpcode);


		// multiple TLDs page template
		$name = "Multiple TLD Page";
		$description = "This template will be used for the multiple TLD page of the script, the page that allows one to check multiple root domains at one time (ex: mydomain.com, anotherdomain.com, thirddomain.net).";
		$varType = "textarea";
		$varName = "TEMPLATES__multiple_tlds";
		$varValue = $this->TEMPLATE->TEMPLATES['multiple_tlds'];
		$varSize = array(
					"rows" => 8, 
					"cols" => 65
					);
		$helpcode = "templatesMainPage";
		$content .= $this->DISPLAY->constructTableVariable($name, $description, $varType, $varName, $varValue, $varSize, $helpcode);


		// results page template
		$name = "Buy Mode Results Page";
		$description = "This template will be used to display the results of the queries of domains in buy mode, displaying id certain domains are available or not.";
		$varType = "textarea";
		$varName = "TEMPLATES__results";
		$varValue = $this->TEMPLATE->TEMPLATES['results'];
		$varSize = array(
					"rows" => 8, 
					"cols" => 65
					);
		$helpcode = "templatesResultsPage";
		$content .= $this->DISPLAY->constructTableVariable($name, $description, $varType, $varName, $varValue, $varSize, $helpcode);


		// whois report template
		$name = "Whois Report Page";
		$description = "This template will be used for displaying the page that contains the full whois report of a domain.";
		$varType = "textarea";
		$varName = "TEMPLATES__report";
		$varValue = $this->TEMPLATE->TEMPLATES['report'];
		$varSize = array(
					"rows" => 8, 
					"cols" => 65
					);
		$helpcode = "templatesReportPage";
		$content .= $this->DISPLAY->constructTableVariable($name, $description, $varType, $varName, $varValue, $varSize, $helpcode);


		// image verification template
		$name = "Image Verification Page";
		$description = "This template will be used for displaying the security image and verification form when image verification is enabled.";
		$varType = "textarea";
		$varName = "TEMPLATES__image_verification";
		$varValue = $this->TEMPLATE->TEMPLATES['image_verification'];
		$varSize = array(
					"rows" => 8, 
					"cols" => 65
					);
		$helpcode = "templatesImageVerificationPage";
		$content .= $this->DISPLAY->constructTableVariable($name, $description, $varType, $varName, $varValue, $varSize, $helpcode);


		// error page template
		$name = "Error Page";
		$description = "This template will be used to display errors, such as when a user enters an invalid domain.";
		$varType = "textarea";
		$varName = "TEMPLATES__error";
		$varValue = $this->TEMPLATE->TEMPLATES['error'];
		$varSize = array(
					"rows" => 8, 
					"cols" => 65
					);
		$helpcode = "templatesErrorPage";
		$content .= $this->DISPLAY->constructTableVariable($name, $description, $varType, $varName, $varValue, $varSize, $helpcode);


		$content .= "</table>";


		// end form
		$content .= "<div align='center'>" . $this->DISPLAY->constructEndForm("Save Config") . "</div>";

		// display page
		$this->DISPLAY->displayPage($content, "Template Settings");
	}



	/* ------------------------------------------------------------------ */
	//	Error Code Settings
	//	Contains all error related settings.
	/* ------------------------------------------------------------------ */
	
	function page_ErrorSettings()
	{
		$content .= $this->DISPLAY->constructOutput("The settings below concern the display errors of the script.");

		// +------------------------------
		//	Construct form with table of inputs
		// +------------------------------
		
		// start form
		$content .= $this->DISPLAY->constructStartForm("goModifyConfig", "configERROR_form");

		$content .= "<input type='hidden' name='adminpanel_filename' value='../config/config.php:::SCRIPT__ERRORS__domain_badlength,SCRIPT__ERRORS__domain_badformat,SCRIPT__ERRORS__bad_extension,SCRIPT__ERRORS__no_extension,SCRIPT__ERRORS__default'>\n";

		$content .=  "<input type='hidden' name='adminpanel_class' value='CONFIG'>\n";


		$content .= "<table>";

		// Incorrect Domain Length
		$name = "Incorrect Domain Length";
		$description = "The error that is displayed when the domain length is incorrect.";
		$varType = "text";
		$varName = "SCRIPT__ERRORS__domain_badlength";
		$varValue = $this->CONFIG->SCRIPT['ERRORS']['domain_badlength'];
		$content .= $this->DISPLAY->constructTableVariable($name, $description, $varType, $varName, $varValue, 50);

		// Incorrect Domain Format
		$name = "Incorrect Domain Format";
		$description = "The error that is displayed when the domain format is incorrect.";
		$varType = "text";
		$varName = "SCRIPT__ERRORS__domain_badformat";
		$varValue = $this->CONFIG->SCRIPT['ERRORS']['domain_badformat'];
		$content .= $this->DISPLAY->constructTableVariable($name, $description, $varType, $varName, $varValue, 50);

		// Invalid Extension
		$name = "Invalid Extension";
		$description = "The error that is displayed when an unsupported extension is used.<br>
		<span style=\"color: blue;\">[[DATA-1]]</span> - Unknown TLD.";
		$varType = "text";
		$varName = "SCRIPT__ERRORS__bad_extension";
		$varValue = $this->CONFIG->SCRIPT['ERRORS']['bad_extension'];
		$content .= $this->DISPLAY->constructTableVariable($name, $description, $varType, $varName, $varValue, 50);

		// Invalid Extension
		$name = "No Extension";
		$description = "The error that is displayed when no extension is specified and auto-search is turned off.";
		$varType = "text";
		$varName = "SCRIPT__ERRORS__no_extension";
		$varValue = $this->CONFIG->SCRIPT['ERRORS']['no_extension'];
		$content .= $this->DISPLAY->constructTableVariable($name, $description, $varType, $varName, $varValue, 50);

		// Default Error
		$name = "Unknown Error";
		$description = "The error that is displayed when an unknown error code is called.<br>
		<span style=\"color: blue;\">[[DATA-0]]</span> - Unknown error.";
		$varType = "text";
		$varName = "SCRIPT__ERRORS__default";
		$varValue = $this->CONFIG->SCRIPT['ERRORS']['default'];
		$content .= $this->DISPLAY->constructTableVariable($name, $description, $varType, $varName, $varValue, 50);

		$content .= "</table>";


		// end form
		$content .= "<div align='center'>" . $this->DISPLAY->constructEndForm("Save Config") . "</div>";

		// display page
		$this->DISPLAY->displayPage($content, "Error Settings");
	}


	
	/* ------------------------------------------------------------------ */
	//	Nameserver Validator Page
	//	A tool used to validate the keywords of nameservers
	/* ------------------------------------------------------------------ */
	
	function page_Nameserver_Validator()
	{
		$content .= $this->DISPLAY->constructOutput("The nameserver validator allows you to quickly check one or multiple nameservers against its current keyword setting in the whois script. This allows you to quickly review nameservers and find those which are configured incorrectly.");

		// +------------------------------
		//	Construct form with table of inputs
		// +------------------------------
		
		// start form
		$content .= $this->DISPLAY->constructStartForm("goValidateNameserver", "configNSVALIDATE_form");


		$content .= "<table>";

		// display nameservers & extensions limits if available
		if (!empty($this->CONFIG->NAMESERVERS))
		{

			// Server selection
			$name = "Server";
			$description = "You can select a specific nameserver to check or you can select \"All\" to check all nameservers.";
			$varType = "select";
			$varName = "nsvalidator_server";


			// construct servers
			// +------------------------------
			//	Create nameserver array based on TLDs
			// +------------------------------
			
			$SERVERS = $this->getAllServers();

			foreach($SERVERS as $current_TLD_data)
			{
				foreach($current_TLD_data as $current_servername)
				{
					$SERVER_NAMES[] = $current_servername;
				}
			}

			// remove duplicate servers
			$SERVER_NAMES = array_unique($SERVER_NAMES);

			// sort by name
			sort($SERVER_NAMES);

			// add in all option
			$varOptions['All Servers'] = 'all';

			// construct into options
			foreach($SERVER_NAMES as $current_name)
			{
				$varOptions[$current_name] = $current_name;
			}

			$varSelected = "All Servers";
			$varValue = array(
							"options" => $varOptions,
							"selected" => $varSelected
							);
			$content .= $this->DISPLAY->constructTableVariable($name, $description, $varType, $varName, $varValue);
			unset($varOptions);


			// Extension
			$name = "Extension";
			$description = "You can select to check only a specific extension or to check them all.";
			$varType = "select";
			$varName = "nsvalidator_extension";
			// construct servers
			// +------------------------------
			//	Create nameserver array based on TLDs
			// +------------------------------

			$EXTENSIONS = array_keys($SERVERS);

			// add in all option
			$varOptions['All Extensions'] = 'all';

			// sort by name
			sort($EXTENSIONS);

			// construct into options
			foreach($EXTENSIONS as $current_name)
			{
				if (!empty($current_name))
					$varOptions[$current_name] = $current_name;
			}

			$varSelected = "All Extensions";
			$varValue = array(
							"options" => $varOptions,
							"selected" => $varSelected
							);
			$content .= $this->DISPLAY->constructTableVariable($name, $description, $varType, $varName, $varValue);
			unset($varOptions);
		}


		// Maximum queries per page
		$name = "Maximum Queries / Page";
		$description = "If you have a large amount of nameservers being checked, you may want to only check so many at a time, preventing your server from being bogged down.";
		$varType = "text";
		$varName = "nsvalidator_maximum";
		$varValue = "50";
		$content .= $this->DISPLAY->constructTableVariable($name, $description, $varType, $varName, $varValue, 5);
		
		
		// Custom query
		$name = "Custom Query";
		$description = "You can set a custom query to be used or leave this blank to use the default query for this nameserver.<br>Valid Tags:<br><span style=\"color: blue\">[[DOMAIN]]</span> - domain that is being queried.<br><span style=\"color: blue\">[[EXT]]</span> - Extension that is being queried.";
		$varType = "text";
		$varName = "nsvalidator_query";
		$varValue = "";
		$content .= $this->DISPLAY->constructTableVariable($name, $description, $varType, $varName, $varValue);


		// Custom keywork matcher
		$name = "Custom Keyword Match";
		$description = "You can set a custom keyword match or leave this blank to use the default keyword for this nameserver.<br>Valid Tags:<br><span style=\"color: blue\">[[KEYWORD]]</span> - default keyword of this nameserver.";
		$varType = "text";
		$varName = "nsvalidator_keyword";
		$varValue = "";
		$content .= $this->DISPLAY->constructTableVariable($name, $description, $varType, $varName, $varValue);


		// Custom domain
		$name = "Domain";
		$description = "You can specifiy a specific domain to be checked. Please not that this domain should be AVAILABLE on all nameservers, otherwise the script will give incorrect reports. It is HIGHLY recommended that you leave this field blank (so that a random domain will be generated).";
		$varType = "text";
		$varName = "nsvalidator_domain";
		$varValue = "";
		$content .= $this->DISPLAY->constructTableVariable($name, $description, $varType, $varName, $varValue);


		// Custom domain
		$name = "Server Delay";
		$description = "When querying the same server it is best to insert an intentional delay to avoid flooding the server. If you set it too low the whois server will return incorrect data. If you set it too high the script will take too long to execute. The recommended setting is 1 (second).";
		$varType = "text";
		$varName = "nsvalidator_delay";
		$varValue = "1";
		$content .= $this->DISPLAY->constructTableVariable($name, $description, $varType, $varName, $varValue);


		$content .= "</table>";

		$content .= $this->DISPLAY->constructOutput("<div style=\"color: blue\">NOTE: Depending on your settings above, the script may take several minutes to fetch and display the results. Please be patient after clicking the Start Validator button.</div>");

		// end form
		$content .= "<div align='center'>" . $this->DISPLAY->constructEndForm("Start Validator") . "</div>";

		// display page
		$this->DISPLAY->displayPage($content, "Nameserver Validator");
	}



	/* ------------------------------------------------------------------ */
	//	Validate Nameserver
	//	Check specified servers with specified arguments for validity
	/* ------------------------------------------------------------------ */
	
	function ValidateNameserver($data)
	{
		// set max execution time to above normal at 8 minutes
		set_time_limit(60*8);


		// +------------------------------
		//	Store various settings
		// +------------------------------
		$server = $data['nsvalidator_server'];
		$extension = $data['nsvalidator_extension'];
		$requests_per_page = (!empty($data['nsvalidator_maximum']) ? intval($data['nsvalidator_maximum']) : 100000);
		$custom_query = $data['nsvalidator_query'];
		$custom_keyword = $data['nsvalidator_keyword'];
		$domain = $data['nsvalidator_domain'];
		$start_number = (!empty($data['nsvalidator_start_number']) ? intval($data['nsvalidator_start_number']) : 0);
		$server_delay = ($data['nsvalidator_delay'] != "" ? intval($data['nsvalidator_delay']) : 1);


		// if domain empty generate a random one
		if (empty($domain))
		{
			// +------------------------------
			//	Generate random domain string
			//	credit: can't remember where this came from.
			//	(it is simple anyway)
			// +------------------------------
			for(
				$length = 10, $domain="";
				strlen($domain) < $length;
				$domain .= chr( 
							!mt_rand(0,2) ? mt_rand(48,57) : 
								(!mt_rand(0,1) ? mt_rand (65,90) : mt_rand (97,122))
					)
			); // everything happens above
		}


		// make domain lowercase
		$domain = strtolower($domain);

		// start validator output
		$validator_output .= $this->DISPLAY->constructOutput("Checking for the domain {$domain} ... ");
		$validator_output .= "<table style=\"width: 100%\">
							<tr>
								<td>
									<table style=\"width: 100%\">
										<tr>
										<td style=\"width: 300px;\"><div style=\"font-weight: bold;\">Extension</div></td>
										<td style=\"width: 80px;\"><div style=\"font-weight: bold;\">Status</div></td>
										<td><div style=\"font-weight: bold;\">Whois Report</div></td>
										</tr>
									</table>
								</td>
							</tr>
							";


		// +------------------------------
		//	Require Whois Script's core
		// +------------------------------
		if (file_exists("../global.php"))
			include_once("../global.php");
		else
			$this->ERROR->stop("global_missing");

		$CORE = new EP_Dev_Whois_Global("../");

		$CORE->DOMAINS->setDisabledNameserverUse(true);

		// turn off cache feature
		$CORE->CONFIG->SCRIPT['CACHE']['time'] = 0;

		// fetch all extensions if necessary
		if ($extension == "all")
		{
			$all_extensions = array_keys($this->getAllServers());
			$all_extensions = array_unique($all_extensions);
			sort($all_extensions);
		}

		// fetch only specified extension
		else
		{
			$all_extensions = array($extension);
		}


		// initialize extension count & internal count
		$extension_count = 0;
		$internal_count = 0;
		
		
		// cycle throw extensions
		foreach($all_extensions as $current_extension)
		{
			// if starting after this extension, do not process it
			if ($start_number > $internal_count)
			{
				// increment
				$internal_count++;

				// continue to next extension
				continue;
			}

			// if query count is less than max count
			if ($extension_count < $requests_per_page) // ($start_number+$requests_per_page)
			{
				// create new query
				$whois_query = $CORE->whoisRequest($domain, $current_extension);

				// process all servers for specified extension
				foreach($whois_query->getAllServerNames() as $current_server)
				{
					if (($server != "all" && $server == $current_server) || ($server == "all"))
					{
						// if same as last server, slow down to prevent flooding
						if ($current_server == $last_server)
							sleep($server_delay);

						// set server for query
						$whois_query->server = $current_server;

						// if not custom query, load default
						if (!empty($custom_query))
							$whois_query->CORE->CONFIG->NAMESERVERS[$current_server]['format'] = $custom_query;

						//die($whois_query->server);

						// if no custom keyword, load default
						if (!empty($custom_keyword))
						{
							$whois_query->CORE->CONFIG->NAMESERVERS[$current_server]['keyword'] = str_replace(
								"[[KEYWORD]]",
								$whois_query->CORE->CONFIG->NAMESERVERS[$current_server]['keyword'],
								$custom_keyword
								);
						}

						// perform query
						$whois_query->lookup(true);

						// display output
						$validator_output .= "<tr>\r\n<td>\r\n<table style=\"width: 100%\">";
						$validator_output .= "<tr>\r\n<td style=\"width: 300px;\">" . $this->DISPLAY->constructOutput("." . $current_extension) . "</td>\r\n";
						$validator_output .= "<td style=\"width: 80px;\">" . ($whois_query->isAvailable() ? "<div style=\"color: green;\">PASSED</div>" : "<div style=\"color: red;\">FAILED</div>") . "</td>\r\n";
						$validator_output .= "<td><a href=\"javascript:showhideDiv('" . str_replace(".", "__", $current_server . "___" . $current_extension) . "');\">Show / Hide Report</a></td\r\n>";

						$validator_output .= "</table>\r\n</td>\r\n</tr>";

						$validator_output .= "<tr>
						<td>
							<div style=\"border: 2px solid #268CFE; margin: 20px; display: none;\" id=\"" . str_replace(".", "__", $current_server . "___" . $current_extension) . "\">
								<div style=\"border-right: 2px solid #268CFE; height: 10px; width: 75%;\">&nbsp;</div>
								<div style=\"border-bottom: 2px solid #268CFE; border-right: 2px solid #268CFE; font-weight: bold; height: 25px; width: 75%;\">
									<div style=\"margin-left: 10px;\">SERVER: " . $current_server . "</div>
								</div>
						<div style=\"margin: 15px;\"><pre>" . htmlentities($whois_query->getWhoisReport(false), ENT_COMPAT, 'UTF-8') . "</pre></div></div>\r\n</td>\r\n</tr>\r\n";

						// log last server for future checks on flooding
						$last_server = $current_server;

						$extension_count++;
					}
				}

				$internal_count++;
			}

		}


		$validator_output .= "</table>\r\n";

		// display total extension count
		$validator_output .= $this->DISPLAY->constructOutput("Total Count: " . $extension_count);

		$validator_output .= "<div style=\"margin-left: 10px;\"><table><tr>";

		// if start number is more than zero display Previous link
		if ($start_number > 0)
		{
			$validator_output .= "<td>
			<form action=\"index.php\" method=\"POST\">
				<input type='hidden' name='page' value=\"goValidateNameserver\">
				<input type='hidden' name='nsvalidator_server' value='{$server}'>
				<input type='hidden' name='nsvalidator_extension' value='{$extension}'>
				<input type='hidden' name='nsvalidator_maximum' value='{$requests_per_page}'>
				<input type='hidden' name='nsvalidator_query' value='{$custom_query}'>
				<input type='hidden' name='nsvalidator_keyword' value='{$custom_keyword}'>
				<input type='hidden' name='nsvalidator_domain' value='{$domain}'>
				<input type='hidden' name='nsvalidator_delay' value='{$server_delay}'>
				<input type='hidden' name='nsvalidator_start_number' value='" . ($start_number + $requests_per_page) . "'>
				<input type='submit' value='<< View " . ($start_number - $requests_per_page + 1) . " - " . $start_number . "'>
			</form></td>";
		}


		// if count is less than total extension count display Next link
		if ($internal_count < count($all_extensions))
		{
			$validator_output .= "<td>
			<form action=\"index.php\" method=\"POST\">
				<input type='hidden' name='page' value=\"goValidateNameserver\">
				<input type='hidden' name='nsvalidator_server' value=\"{$server}\">
				<input type='hidden' name='nsvalidator_extension' value=\"{$extension}\">
				<input type='hidden' name='nsvalidator_maximum' value=\"{$requests_per_page}\">
				<input type='hidden' name='nsvalidator_query' value=\"{$custom_query}\">
				<input type='hidden' name='nsvalidator_keyword' value=\"{$custom_keyword}\">
				<input type='hidden' name='nsvalidator_domain' value=\"{$domain}\">
				<input type='hidden' name='nsvalidator_delay' value='{$server_delay}'>
				<input type='hidden' name='nsvalidator_start_number' value=\"" . ($start_number + $requests_per_page) . "\">
				<input type='submit' value=\"View " . ($start_number + $requests_per_page + 1) . " - " . ($start_number + ($requests_per_page*2)) . " >>\">
			</form></td>";
		}

		$validator_output .= "</tr></table></div>";


		// javascript
		$javascript = <<< JAVASCRIPT_CODE
		<SCRIPT LANGUAGE="JavaScript" TYPE="text/javascript">
			function showDiv(id)
			{
				divObject = document.getElementById(id);

				divObject.style.display = "block";
			}

			function hideDiv(id)
			{
				divObject = document.getElementById(id);

				//divObject.style.visibility = "hidden";
				divObject.style.display = "none";
			}


			function showhideDiv(id)
			{
				if (document.getElementById(id).style.display == "none")
					showDiv(id);
				else
					hideDiv(id);
			}
		</SCRIPT>
JAVASCRIPT_CODE;

		// display page
		$this->DISPLAY->displayPage($validator_output, "Validating Nameservers", null, $javascript);
	}



	/* ------------------------------------------------------------------ */
	//	Delete Nameserver Page
	//	Confirms the deletion of the nameserver
	/* ------------------------------------------------------------------ */
	
	function page_DeleteNameserver()
	{
		// fetch nameserver to be deleted
		$nameserver = $_REQUEST['nameserver'];

		// format nameserver key with underscores instead of periods
		$nameserver_key = str_replace(".", "___", $nameserver);


		// +------------------------------
		//	Construct form with table of inputs
		// +------------------------------
		
		// start form
		$content .= $this->DISPLAY->constructStartForm("goRemoveConfig", "configNSREMOVE_form");


		// get all extensions
		$all_extensions = array_merge(  
			explode(  ",", str_replace(" ", "", $this->CONFIG->NAMESERVERS[$nameserver]['extensions'] )  ) ,
			explode(  ",", str_replace(" ", "", $this->CONFIG->NAMESERVERS[$nameserver]['extensions_disabled'] )  )  
			);


		// fetch all servers
		$allServers = $this->getAllServers();

		// initialize extension blocks
		$extensionBlocks = "";

		// cycle through extensions of nameserver
		foreach($all_extensions as $extension)
		{
			// if extension is only assigned to this nameserver
			if (count($allServers[$extension]) == 1)
			{
				// create deletion rules

				$extensionBlocks .= ":::../config/config.php:::BUYMODE__PRICES__{$extension}";
				$extensionInputs .= "<input type='hidden' name='BUYMODE__PRICES__" . str_replace(".", "___", $extension) . "'>\n";
				$ruleSet .= ":::BUYMODE__PRICES__{$extension},string";
			}
		}

		$content .= "<input type='hidden' name='adminpanel_rules' value='" . substr($ruleSet, 3) . "'>\n";


		$content .= "<input type='hidden' name='adminpanel_filename' value='../config/config.php:::";

		$hiddenInputs = "";
		
		// cycle through array keys and add them to removal string
		$keys_of_nameserver = array_keys($this->CONFIG->NAMESERVERS[$nameserver]);

		for($i=0; $i<count($keys_of_nameserver); $i++)
		{
			if ($i != 0)
				$content .= ",";

			$content .= "NAMESERVERS__{$nameserver}__{$keys_of_nameserver[$i]}";

			$hiddenInputs .= "<input type='hidden' name='NAMESERVERS__{$nameserver_key}__{$keys_of_nameserver[$i]}'>\n";
		}

		$content .= $extensionBlocks . "'>\n";


		$content .= $hiddenInputs;

		$content .= $extensionInputs;

		$content .=  "<input type='hidden' name='adminpanel_class' value='CONFIG'>\n";

		$content .= $this->DISPLAY->constructOutput("<div style=\"color: red; font-weight: bold;\">The following nameserver and all of its related settings will be deleted:</div>");

		$content .= $this->DISPLAY->constructOutput("<br><div style=\"font-weight: bold;\">{$nameserver}</div>" . $this->DISPLAY->constructOutput( str_replace(",", " ", $this->CONFIG->NAMESERVERS[$nameserver]['extensions']) ."<br>" . str_replace(",", " ", $this->CONFIG->NAMESERVERS[$nameserver]['extensions_disabled']), 15), 15);

		$content .= "<br>";

		// end form
		$content .= "<div align='center'>" . $this->DISPLAY->constructEndForm("Delete Nameserver") . "</div>";

		// display page
		$this->DISPLAY->displayPage($content, "Delete Nameserver");
	}



	/* ------------------------------------------------------------------ */
	//	Add Nameserver
	//	Allows for adding of new nameservers
	/* ------------------------------------------------------------------ */
	
	function page_AddNameserver()
	{
		$content .= $this->DISPLAY->constructOutput("Below new nameservers can be added.");

		// +------------------------------
		//	Construct form with table of inputs
		// +------------------------------
		
		// start form
		$content .= $this->DISPLAY->constructStartForm("goAddNameserver", "configADDNAMESERVER_form");


		$content .= "<table>";

		// Nameserver Address
		$name = "Nameserver Address";
		$description = "The address for this nameserver.<br>ex: whois.someserver.com";
		$varType = "text";
		$varName = "NAMESERVERS__address";
		$varValue = "";
		$varSize = 40;
		$content .= $this->DISPLAY->constructTableVariable($name, $description, $varType, $varName, $varValue, $varSize);


		// Enable Nameserver
		$name = "Enabled";
		$description = "Select to enable or disable this nameserver.";
		$varType = "select";
		$varName = "NAMESERVERS__enabled";
		$varOptions = array(
						"Enabled" => "true",
						"Disabled" => "false"
						);
		$varSelected = "Enabled";
		$varValue = array(
						"options" => $varOptions,
						"selected" => $varSelected
						);
		$content .= $this->DISPLAY->constructTableVariable($name, $description, $varType, $varName, $varValue);


		// Normal Keword
		$name = "Keyword";
		$description = "The keyword(s) to look for when querying a server. If this is found, then the script will assume the domain is available. If you want to make the script assume the domain is unavailable unless the keyword is found, place <span style=\"color: blue\">!!!</span> in front of the keyword. Regular expressions are acceptable. This is CaSe SeNsiTive.";
		$varType = "text";
		$varName = "NAMESERVERS__keyword";
		$varValue = "not found";
		$content .= $this->DISPLAY->constructTableVariable($name, $description, $varType, $varName, $varValue);


		// Normal Format
		$name = "Query Format";
		$description = "The format of the query to send to the server. The tags <span style=\"color: blue\">[[DOMAIN]]</span> and <span style=\"color: blue\">[[EXT]]</span> will be replaced by the domain and extension of the query.<br>ex: <span style=\"color: blue\">[[DOMAIN]].[[EXT]]</span>";
		$varType = "text";
		$varName = "NAMESERVERS__format";
		$varValue = "[[DOMAIN]].[[EXT]]";
		$content .= $this->DISPLAY->constructTableVariable($name, $description, $varType, $varName, $varValue);

		// Advanced Custom Query
		$name = "Custom Query (advanced)";
		$description = "Here you can set a completely custom query that does not conform to any traditional standards. Unless you know what you are doing, it is [b]highly recommended that you leave this as default[/b]. The tag <span style=\"color: blue\">[[QUERY]]</span> will be replaced by the query format above.<br>ex Default: <span style=\"color: blue\">[[QUERY]]\\r\\n</span>";
		$varType = "text";
		$varName = "NAMESERVERS__advanced_custom_query";
		$varValue = "[[QUERY]]\\r\\n";
		$content .= $this->DISPLAY->constructTableVariable($name, $description, $varType, $varName, $varValue);


		// Limit Keword
		$name = "Limit Keyword";
		$description = "The limit keyword will be used when the script is trying to only check if the domain is available. Not all servers support this format, in which case you should just make it the same as the regular keyword. This is CaSe SeNsiTive.";
		$varType = "text";
		$varName = "NAMESERVERS__limit_keyword";
		$varValue = "not found";
		$content .= $this->DISPLAY->constructTableVariable($name, $description, $varType, $varName, $varValue);


		// Limit Format
		$name = "Limit Query Format";
		$description = "The limit format will be used when the script is trying to only check if the domain is available. Not all servers support this format, in which case you should just make it the same as the regular query format. The tags <span style=\"color: blue\">[[DOMAIN]]</span> and <span style=\"color: blue\">[[EXT]]</span> will be replaced by the domain and extension of the query.<br>ex: <span style=\"color: blue\">[[DOMAIN]].[[EXT]]</span>";
		$varType = "text";
		$varName = "NAMESERVERS__limit_format";
		$varValue = "[[DOMAIN]].[[EXT]]";
		$content .= $this->DISPLAY->constructTableVariable($name, $description, $varType, $varName, $varValue);


		// Advanced Limit Custom Query
		$name = "Limit Custom Query (advanced)";
		$description = "Here you can set a completely custom query that does not conform to any traditional standards. Unless you know what you are doing, it is [b]highly recommended that you leave this as default[/b]. This will be used in limit situations (see above). The tag <span style=\"color: blue\">[[QUERY]]</span> will be replaced by the limit query format above.<br>ex Default: <span style=\"color: blue\">[[QUERY]]\\r\\n</span>";
		$varType = "text";
		$varName = "NAMESERVERS__advanced_limit_custom_query";
		$varValue = "[[QUERY]]\\r\\n";
		$content .= $this->DISPLAY->constructTableVariable($name, $description, $varType, $varName, $varValue);


		// Advanced Custom Query Address & Port
		$name = "Custom Query Address & Port (advanced)";
		$description = "Here you can set a non-standard address & port to connect to. Unless you know what you are doing, it is [b]highly recommended that you leave this as default[/b]. Please note: You MUST specify the port. The tag <span style=\"color: blue\">[[SERVER]]</span> will be replaced by the server name above.<br>ex Default: <span style=\"color: blue\">[[SERVER]]:43</span><br>non-standard: <span style=\"color: blue\">www.[[SERVER]]:80/whoislookup.cgi</span>";
		$varType = "text";
		$varName = "NAMESERVERS__advanced_custom_query_address";
		$varValue = "[[SERVER]]:43";
		$content .= $this->DISPLAY->constructTableVariable($name, $description, $varType, $varName, $varValue);


		// Timeout Time
		$name = "Timeout Time";
		$description = "The maximum amount of time to spend trying to get a response from the nameserver (in seconds).";
		$varType = "text";
		$varName = "NAMESERVERS__timeout";
		$varValue = "30";
		$content .= $this->DISPLAY->constructTableVariable($name, $description, $varType, $varName, $varValue);


		// Enabled Extensions
		$name = "Enabled Extensions";
		$description = "These extensions are enabled.<br>ex: com, net, org";
		$varType = "text";
		$varName = "NAMESERVERS__extensions";
		$varValue = "";
		$content .= $this->DISPLAY->constructTableVariable($name, $description, $varType, $varName, $varValue, 50);


		// Disabled Extensions
		$name = "Disabled Extensions";
		$description = "These extensions are disabled.<br>ex: com, net, org";
		$varType = "text";
		$varName = "NAMESERVERS__extensions_disabled";
		$varValue = "";
		$content .= $this->DISPLAY->constructTableVariable($name, $description, $varType, $varName, $varValue, 50);


		$content .= "</table>";


		// end form
		$content .= "<div align='center'>" . $this->DISPLAY->constructEndForm("Add Nameserver") . "</div>";

		// display page
		$this->DISPLAY->displayPage($content, "Create New Nameserver");
	}



	function getDonationBox()
	{
		$content .= "<div style=\"border: 3px solid #268CFE; background-color: #F0F3FE; margin: 10px; padding: 5px;\">
			<div style=\"font-size: 12pt; font-weight: bold;\">Support EP-Dev Whois!</div>
			<div>Thank you for supporting this script! If you have found this script somewhere for free, please help support this script by making a donation. Many hours of development have went into the making of this script over the past few years. By supporting this script, you ensure that the script will continue to be improved. If you cannot donate now, please still feel free to use the script. You can find the link for donation or purchase in the lower left-hand menu of this page.</div><br>
			<div style=\"text-align: center; font-weight: bold; font-size: 11pt;\"><a href=\"http://www.ep-dev.com/donate.php?name=EP-Dev Whois Script\" target=\"_blank\">Donate Now</a></div>
			<div style=\"text-align: center; font-size: 8pt;\">(opens a new window)</div>
		</div>";

		return $content;
	}



	/* ------------------------------------------------------------------ */
	//	Modify TLD Prices with $data
	//	A function that is used to sort through extension data and decide:
	//		a) Extensions that must be created.
	//		b) Extensions that must be modified.
	//	The function sends the resulting data to be modified / created
	/* ------------------------------------------------------------------ */
	
	function ModifyTLDPrices($data)
	{
		// +------------------------------
		//	Pull out special data
		//	Store special (not-to-be-posted) data into is own array
		// +------------------------------

		foreach ($data as $key => $value)
		{
			if (ereg("^adminpanel_(.*)", $key, $args))
			{
				$data_extra[$args[1]] = $value;
				unset($data[$key]);
			}
		}


		//empty price config
		$priceConfig = array();


		// format all extensions that need to be added into own array
		$addExtensions = explode(",", $data_extra['addExtensions']);

		// cycle through extensions to be added
		foreach($addExtensions as $extension)
		{
			// ensure extension does not exist
			if (!isset($this->CONFIG->BUYMODE['PRICES'][$extension]) && !empty($extension))
			{
				// format extension key
				$extension_key = str_replace(".", "___", $extension);

				// add creation rules
				$ruleSets .= ":::BUYMODE__PRICES__{$extension},string";
				$priceKeys .= ",BUYMODE__PRICES__{$extension}";
				$priceConfig["BUYMODE__PRICES__{$extension}"] = $data["BUYMODE__PRICES__{$extension_key}"];
			}
		}


		// if extensions need to be added
		if (!empty($priceConfig))
		{
			$priceConfig['adminpanel_filename'] = "../config/config.php:::" . substr($priceKeys, 1);

			$priceConfig['adminpanel_rules'] = substr($ruleSets, 3);

			$priceConfig['adminpanel_replace_string'] = "// ---- SPECIAL PRICE TARGET LINE USED BY ADMIN PANEL -- DO NOT REMOVE ---- //";

			$priceConfig['adminpanel_class'] = "CONFIG";

			// send data to be created
			$this->AddConfig($priceConfig);
		}


		// destroy data from above for use below
		unset($priceConfig);
		unset($ruleSets);
		unset($priceKeys);

		//create empty price config
		$priceConfig = array();


		// format all extensions that need to be modified into own array
		$modifyExtensions = explode(",", $data_extra['modifyExtensions']);

		// cycle through extensions to be modified
		foreach($modifyExtensions as $extension)
		{
			// ensure extension already exists
			if (isset($this->CONFIG->BUYMODE['PRICES'][$extension]) && !empty($extension))
			{
				$extension_key = str_replace(".", "___", $extension);
				$ruleSets .= ":::BUYMODE__PRICES__{$extension},string";
				$priceKeys .= ",BUYMODE__PRICES__{$extension}";
				$priceConfig["BUYMODE__PRICES__{$extension}"] = $data["BUYMODE__PRICES__{$extension_key}"];
			}
		}

		// if extensions need to be modified
		if (!empty($priceConfig))
		{

			$priceConfig['adminpanel_filename'] = "../config/config.php:::" . substr($priceKeys, 1);

			$priceConfig['adminpanel_rules'] = substr($ruleSets, 3);

			$priceConfig['adminpanel_class'] = "CONFIG";

			// send data to be modified
			$this->ModifyConfig($priceConfig);
		}
	}



	/* ------------------------------------------------------------------ */
	//	Add Nameserver with $data
	//	A function that is used to sort new nameserver data and decide:
	//		a) Extensions that must be created.
	//		b) Validity of new nameserver.
	//	The function sends the resulting data to be created.
	/* ------------------------------------------------------------------ */
	
	function AddNameserver($data)
	{
		// +------------------------------
		//	Pull out special data
		//	Store nameserver data into array
		// +------------------------------

		foreach ($data as $key => $value)
		{
			if (ereg("^NAMESERVERS__(.*)", $key, $args))
			{
				$data_extra[$args[1]] = $value;
				unset($data[$key]);
			}
		}


		// get nameserver address from special array
		$nameserver_address = trim($data_extra['address']);

		// destroy address from special array
		unset($data_extra['address']);

		// if nameserver exists, error
		if (isset($this->CONFIG->NAMESERVERS[$nameserver_address]))
		{
			$this->ERROR->stop("nameserver_exists");
		}

		// if empty nameserver address, error
		else if (empty($nameserver_address))
		{
			$this->ERROR->stop("bad_nameserver");
		}


		// create empty add array
		$addConfigArray = array();

		// for each nameserver key
		foreach ($data_extra as $key => $value)
		{
			$addConfigArray["NAMESERVERS__{$nameserver_address}__{$key}"] = $value;
		}

		// remove spaces
		$addConfigArray["NAMESERVERS__{$nameserver_address}__extensions"] = str_replace(" ", "", $addConfigArray["NAMESERVERS__{$nameserver_address}__extensions"]);

		// remove spaces
		$addConfigArray["NAMESERVERS__{$nameserver_address}__extensions_disabled"] = str_replace(" ", "", $addConfigArray["NAMESERVERS__{$nameserver_address}__extensions_disabled"]);

		// format into expected output order
		$addConfigArray['adminpanel_filename'] = "../config/config.php:::"
												. "NAMESERVERS__{$nameserver_address}__enabled"
												. ",NAMESERVERS__{$nameserver_address}__keyword"
												. ",NAMESERVERS__{$nameserver_address}__format"
												. ",NAMESERVERS__{$nameserver_address}__limit_format"
												. ",NAMESERVERS__{$nameserver_address}__limit_keyword"
												. ",NAMESERVERS__{$nameserver_address}__timeout"
												. ",NAMESERVERS__{$nameserver_address}__extensions"
												. ",NAMESERVERS__{$nameserver_address}__extensions_disabled"
												. ",NAMESERVERS__{$nameserver_address}__advanced_custom_query"
												. ",NAMESERVERS__{$nameserver_address}__advanced_limit_custom_query"
												. ",NAMESERVERS__{$nameserver_address}__advanced_custom_query_address";

		// designate special search string
		$addConfigArray['adminpanel_replace_string'] = "// ---- SPECIAL NAMESERVER TARGET LINE USED BY ADMIN PANEL -- DO NOT REMOVE ---- //";


		// merge generated nameserver data with other data passed in from add nameserver page
		$final_array = array_merge($addConfigArray, $data);

		// add to config
		$this->AddConfig($final_array);


		// +------------------------------
		//	TLD Price handling
		// +------------------------------

		// get all extensions
		$all_extensions = array_merge(  
			explode(  ",", str_replace(" ", "", $data_extra['extensions'] )  ) ,
			explode(  ",", str_replace(" ", "", $data_extra['extensions_disabled'] )  )  
			);

		// cycle through extensions of new nameserver
		foreach($all_extensions as $extension)
		{
			// only if extension is not already set & extension not empty
			if (!isset($this->CONFIG->BUYMODE['PRICES'][$extension]) && !empty($extension))
			{
				// add extension creation rules

				$priceConfig["BUYMODE__PRICES__{$extension}"] = "0.00";
				$priceKeys .= ",BUYMODE__PRICES__{$extension}";

				$ruleSets .= ":::BUYMODE__PRICES__{$extension},string";
			}
		}

		
		// only process if there are extensions to add
		if (!empty($priceConfig))
		{
			$priceConfig['adminpanel_replace_string'] = "// ---- SPECIAL PRICE TARGET LINE USED BY ADMIN PANEL -- DO NOT REMOVE ---- //";

			$priceConfig['adminpanel_filename'] = "../config/config.php:::" . substr($priceKeys, 1);

			$priceConfig['adminpanel_rules'] = substr($ruleSets, 3);
			
			// add new extension data
			$this->AddConfig($priceConfig);
		}

	}



	/* ------------------------------------------------------------------ */
	//	Add Configuration of $data
	//	A function that is used to add $data to configuration files.
	//	Fairly complex as it must do quite a bit.
	/* ------------------------------------------------------------------ */
	
	function AddConfig($data)
	{
		// +------------------------------
		//	Convert triple underscores to singular periods
		// +------------------------------
		foreach($data as $key => $value)
		{
			$data_new[str_replace("___", ".", $key)] = $value;
		}

		// assign new data to old variable
		$data = $data_new;

		// remove temp variable
		unset($data_new);


		// +------------------------------
		//	Pull out special data
		//	Store special (not-to-be-posted) data into is own array
		// +------------------------------

		foreach ($data as $key => $value)
		{
			if (ereg("^adminpanel_(.*)", $key, $args))
			{
				$data_extra[$args[1]] = $value;
				unset($data[$key]);
			}
		}

		// also get rid of page
		unset($data['page']);


		// +------------------------------
		//	Custom Rule Sets
		//	Get Custom Rule Sets
		// +------------------------------
		$customRules = array();
		if (isset($data_extra['rules']))
		{
			$ruleset = explode(":::", $data_extra['rules']);

			foreach($ruleset as $current_rule)
			{
				$current_set = explode(",", $current_rule);
				$customRules[$current_set[0]] = $current_set[1];
			}
		}


		// +------------------------------
		//	Custom Newline Count
		//	Get the number of newlines to end a variable with.
		//	If none specified, set a default number.
		// +------------------------------
		if (isset($data_extra['newline_count']))
			$newLineCount = $data_extra['newline_count'];
		else
			$newLineCount = 2;


		// +------------------------------
		//	Grab file names
		//
		//	Filenames are in adminpanel_filename in the format of:
		//	filename:::var1,var2,var3:::filename2:::var4,var5,var6
		//	where var1, 2, and 3 will be written to filename
		//	and var4, 5, and 6 will be written to filename2
		// +------------------------------

		// adminpanel_FILENAME
		$filenames_array = explode(":::", $data_extra['filename']);
		

		// Store filenames into array such as $array['filename'] = array(var1, var2, ...);
		for($i=0; $i<count($filenames_array); $i+=2)
			$filenames[$filenames_array[$i]] = explode(",", $filenames_array[$i+1]);


		
		// +------------------------------
		//	Build search / replace arrays
		//
		//	Cycle through the data and build the search and replace arrays.
		// +------------------------------
		
		foreach($data as $key => $value)
		{
			// make sure these are empty
			unset($key_data);
			unset($keys);
			unset($keys_string);

			/* keys are stored as ARRAY_TYPE__KEY ...
				Example: $data['ADMIN__username'] = "value";
				-or-
				$data['BUYMODE__CONFIG__currency_string'] = "value"; where ARRAY_TYPE__KEY1__KEY2
			*/
			$key_data = explode("__", $key);

			// Store keys into an array
			$keys = array();
			for($i=1; $i<count($key_data); $i++)
			{
				$keys[] = $key_data[$i];

				// keep keys string as well for search array
				// note: We detect if numeric key or not
				$keys_string .= (!is_numeric($key_data[$i]) ? "['".$key_data[$i]."']" : "[".$key_data[$i]."]");
			}


			if (!isset($customRules[$key]))
			{
				// store formatted replace value into replace array.
				$replace[$key] = $this->FORMAT->convertDataToString($value, $keys, $key_data[0]);
			}
			else
			{
				// store formatted replace value into replace array.
				$replace[$key] = $this->FORMAT->convertDataToString($value, $keys, $key_data[0], $customRules[$key]);
			}
		}


		// detect newline type
		$newLine = "
		";
		$newLine = str_replace("\t", "", $newLine);


		// +------------------------------
		//	Perform Search & Replace
		// +------------------------------
		foreach($filenames as $file => $vars)
		{
			// create new fileObj
			$fileObj = new EP_Dev_Whois_Admin_File_IO($file, $this->ERROR);

			// Open file
			$fileObj->open();

			// read contents
			$fileContent = $fileObj->read();

			// perform search & replace
			foreach($vars as $cur_var)
			{
				$newConfig .= "\t\t" . $replace[$cur_var] . $newLine;
			}

			$newConfig .= str_repeat($newLine, $newLineCount) . "\t\t{$data_extra['replace_string']}";

			$fileContent = str_replace("\t\t{$data_extra['replace_string']}", $newConfig, $fileContent);

			// write data back to file
			$fileObj->writeNew($fileContent);

			// close file object
			$fileObj->close();

			// get rid of variable
			unset($fileObj);
		}
	}



	/* ------------------------------------------------------------------ */
	//	Remove Configuration of $data
	//	A function that is used to remove configuration from files.
	/* ------------------------------------------------------------------ */
	
	function RemoveConfig($data)
	{
		// +------------------------------
		//	Convert triple underscores to singular periods
		// +------------------------------
		foreach($data as $key => $value)
		{
			$data_new[str_replace("___", ".", $key)] = $value;
		}

		// assign new data to old variable
		$data = $data_new;

		// remove temp variable
		unset($data_new);


		// +------------------------------
		//	Pull out special data
		//	Store special (not-to-be-posted) data into is own array
		// +------------------------------

		foreach ($data as $key => $value)
		{
			if (ereg("^adminpanel_(.*)", $key, $args))
			{
				$data_extra[$args[1]] = $value;
				unset($data[$key]);
			}
		}

		// also get rid of page
		unset($data['page']);



		// +------------------------------
		//	Custom Rule Sets
		//	Get Custom Rule Sets
		// +------------------------------
		$customRules = array();
		if (isset($data_extra['rules']))
		{
			$ruleset = explode(":::", $data_extra['rules']);

			foreach($ruleset as $current_rule)
			{
				$current_set = explode(",", $current_rule);
				$customRules[$current_set[0]] = $current_set[1];
			}
		}



		// +------------------------------
		//	Grab file names
		//
		//	Filenames are in adminpanel_filename in the format of:
		//	filename:::var1,var2,var3:::filename2:::var4,var5,var6
		//	where var1, 2, and 3 will be written to filename
		//	and var4, 5, and 6 will be written to filename2
		// +------------------------------

		// adminpanel_FILENAME
		$filenames_array = explode(":::", $data_extra['filename']);
		

		// Store filenames into array such as $array['filename'][block_number] = array(var1, var2, ...);
		for($i=0; $i<count($filenames_array); $i+=2)
			$filenames[$filenames_array[$i]][] = explode(",", $filenames_array[$i+1]);

		//var_dump($filenames);
		//die();


		foreach($data as $key => $value)
		{
			// make sure these are empty
			unset($key_data);
			unset($keys);
			unset($keys_string);

			/* keys are stored as ARRAY_TYPE__KEY ...
				Example: $data['ADMIN__username'] = "value";
				-or-
				$data['BUYMODE__CONFIG__currency_string'] = "value"; where ARRAY_TYPE__KEY1__KEY2
			*/
			$key_data = explode("__", $key);


			// Store keys into an array
			$keys = array();
			for($i=1; $i<count($key_data); $i++)
			{
				$keys[] = $key_data[$i];

				// keep keys string as well for search array
				// note: We detect if numeric key or not
				$keys_string .= (!is_numeric($key_data[$i]) ? "['".$key_data[$i]."']" : "[".$key_data[$i]."]");
			}

			$current_mainclass = $data_extra['class']; 

			eval("\$removeValues[\$key] = \$this->{$current_mainclass}->{$key_data[0]}{$keys_string};");

			if (!isset($customRules[$key]))
			{
				// store formatted search value into remove array.
				$remove_data[$key] .= quotemeta($this->FORMAT->convertVarToString($removeValues[$key], $keys, $key_data[0]));
			}
			else
			{
				// store formatted search value into remove array.
				$remove_data[$key] .= quotemeta($this->FORMAT->convertVarToString($removeValues[$key], $keys, $key_data[0], $customRules[$key]));
			}
		}

		
		// cycle through filenames
		foreach($filenames as $filename => $block_data)
		{
			// +------------------------------
			//	Perform Search & Replace
			// +------------------------------
			// create new fileObj
			$fileObj = new EP_Dev_Whois_Admin_File_IO($filename, $this->ERROR);

			// Open file
			$fileObj->open();

			// read contents
			$fileContent = $fileObj->read();

			// cycle through keys of current removal block
			foreach($block_data as $remove_data_keys)
			{
				unset($remove_string);

				foreach($remove_data_keys as $current_key)
				{
					$remove_string .= $remove_data[$current_key] . "\s*";
				}

				// skip if this string isn't empty... that would be very bad ;)
				if ($remove_string == "\s*")
					continue;

				// perform removal
				$fileContent = preg_replace("/" . $remove_string . "/", $data_extra['replace_string'], $fileContent);
			}

			// write data back to file
			$fileObj->writeNew($fileContent);

			// close file object
			$fileObj->close();

			// get rid of variable
			unset($fileObj);
		}
	}



	/* ------------------------------------------------------------------ */
	//	Modify config files with new $data
	//	A relatively complex function that makes specific calls to classes
	//	in order to eventually modify filenames (specified within $data)
	//	with the new information (also specified within $data).
	/* ------------------------------------------------------------------ */
	
	function ModifyConfig($data)
	{
		// +------------------------------
		//	Convert triple underscores to singular periods
		// +------------------------------
		foreach($data as $key => $value)
		{
			$data_new[str_replace("___", ".", $key)] = $value;
		}

		// assign new data to old variable
		$data = $data_new;

		// remove temp variable
		unset($data_new);


		// +------------------------------
		//	Pull out special data
		//	Store special (not-to-be-posted) data into is own array
		// +------------------------------

		foreach ($data as $key => $value)
		{
			if (ereg("^adminpanel_(.*)", $key, $args))
			{
				$data_extra[$args[1]] = $value;
				unset($data[$key]);
			}
		}

		// also get rid of page
		unset($data['page']);


		// +------------------------------
		//	Custom Rule Sets
		//	Get Custom Rule Sets
		// +------------------------------
		$customRules = array();
		if (isset($data_extra['rules']))
		{
			$ruleset = explode(":::", $data_extra['rules']);

			foreach($ruleset as $current_rule)
			{
				$current_set = explode(",", $current_rule);
				$customRules[$current_set[0]] = $current_set[1];
			}
		}


		// +------------------------------
		//	Grab file names
		//
		//	Filenames are in adminpanel_filename in the format of:
		//	filename:::var1,var2,var3:::filename2:::var4,var5,var6
		//	where var1, 2, and 3 will be written to filename
		//	and var4, 5, and 6 will be written to filename2
		// +------------------------------

		// adminpanel_FILENAME
		$filenames_array = explode(":::", $data_extra['filename']);
		

		// Store filenames into array such as $array['filename'] = array(var1, var2, ...);
		for($i=0; $i<count($filenames_array); $i+=2)
			$filenames[$filenames_array[$i]] = explode(",", $filenames_array[$i+1]);


		// store special keys of arrays
		if (isset($data_extra['arrays']))
			$varArrayKeys = implode(",", $data_extra['arrays']);
		else
			$varArrayKeys = array();


		// perform the copy action
		// ORIGINAL_NAME:NEW_NAME,ORIGINAL_NAME2:NEW_NAME2
		if (isset($data_extra['copy']))
		{
			$need_renamed = explode(",", $data_extra['copy']);

			foreach($need_renamed as $rename)
			{
				$rename_arry = explode(":::", $rename);

				// create new entry in data with old data
				$data[$rename_arry[1]] = $data[$rename_arry[0]];

				// place into array of new_key => old_key;
				// This will be recognized later so that old_key's value is used.
				$varCopy[$rename_arry[1]] = $rename_arry[0];
			}
		}


		
		// +------------------------------
		//	Build search / replace arrays
		//
		//	Cycle through the data and build the search and replace arrays.
		// +------------------------------
		
		foreach($data as $key => $value)
		{
			// make sure these are empty
			unset($key_data);
			unset($keys);
			unset($keys_string);

			/* keys are stored as ARRAY_TYPE__KEY ...
				Example: $data['ADMIN__username'] = "value";
				-or-
				$data['BUYMODE__CONFIG__currency_string'] = "value"; where ARRAY_TYPE__KEY1__KEY2
			*/
			$key_data = explode("__", $key);

			// Store keys into an array
			$keys = array();
			for($i=1; $i<count($key_data); $i++)
			{
				$keys[] = $key_data[$i];

				// keep keys string as well for search array
				// note: We detect if numeric key or not
				$keys_string .= (!is_numeric($key_data[$i]) ? "['".$key_data[$i]."']" : "[".$key_data[$i]."]");
			}

			// Take care of array variables
			if (in_array($key, $varArrayKeys))
			{
				// strip out all but numbers and commas
				$new_value = ereg_replace("[^0-9,]", "", $value);

				// turn into array
				unset($value);

				// turn into array
				$value = explode(",", $value);
			}

			// store formatted replace value into replace array.
			if (!isset($customRules[$key]))
			{
				$replace[$key] = $this->FORMAT->convertDataToString($value, $keys, $key_data[0]);
			}
			else
			{
				$replace[$key] = $this->FORMAT->convertDataToString($value, $keys, $key_data[0], $customRules[$key]);
			}

			// Detect if copy is in place
			if (isset($varCopy[$key]))
			{
				// store earlier found data of key in varCopy[] for $key
				eval("\$searchValues[\$key] = \$searchValues[\$varCopy[\$key]];");
			}
			else
			{
				// Allow for multiple main class setups
				if (!empty($data_extra['class2']))
				{
					$mainkeyclass = explode(":", $data_extra['class2']);

					if (strstr($mainkeyclass[1], $key) !== false)
						$current_mainclass = $mainkeyclass[0];
					else
						$current_mainclass = $data_extra['class'];
				}
				else
				{
					$current_mainclass = $data_extra['class']; 
				}

				// store current data for variable into $searchValues
				eval("\$searchValues[\$key] = \$this->{$current_mainclass}->{$key_data[0]}{$keys_string};");
			}

			// store formatted search value into search array
			// detect custom ruleset
			if (!isset($customRules[$key]))
			{
				$search[$key] = $this->FORMAT->convertVarToString($searchValues[$key], $keys, $key_data[0]);
			}
			else
			{
				$search[$key] = $this->FORMAT->convertVarToString($searchValues[$key], $keys, $key_data[0], $customRules[$key]);
			}
		}

		// +------------------------------
		//	Perform Search & Replace
		// +------------------------------
		foreach($filenames as $file => $vars)
		{
			// create new fileObj
			$fileObj = new EP_Dev_Whois_Admin_File_IO($file, $this->ERROR);

			// Open file
			$fileObj->open();

			// read contents
			$fileContent = $fileObj->read();

			// perform search & replace
			foreach($vars as $cur_var)
			{
				//echo "SEARCH: <pre>" . $search[$cur_var] . "</pre><br><br>" . "REPLACE: <pre>" . $replace[$cur_var] . "</pre><br><br><br><br><br>";
				$fileContent = str_replace($search[$cur_var], $replace[$cur_var], $fileContent);
			}

			// write data back to file
			$fileObj->writeNew($fileContent);

			// close file object
			$fileObj->close();

			// get rid of variable
			unset($fileObj);
		}
	}


	/* ------------------------------------------------------------------ */
	//	Fetch all servers of script (both enabled and disabled)
	//	RETURNS: array of servers with extensions as keys
	/* ------------------------------------------------------------------ */
	
	function getAllServers()
	{
		// +------------------------------
		//	Create nameserver array based on TLDs
		// +------------------------------
		
		if (!empty($this->CONFIG->NAMESERVERS))
		{
			foreach($this->CONFIG->NAMESERVERS as $nameserver => $array_val)
			{
				if (true==true)//$array_val['enabled'])
				{
					$extensions = array_merge(
							explode(",", str_replace(" ", "", $array_val['extensions'])),
							explode(",", str_replace(" ", "", $array_val['extensions_disabled']))
						);

					foreach($extensions as $current_ext)
					{
						if (!empty($current_ext))
							$SERVERS[$current_ext][] = $nameserver;
					}
				}
			}
		}
		else
		{
			$SERVERS = array();
		}

		return $SERVERS;
	}

}



/* ------------------------------------------------------------------ */
//	User Interaction Class
//	Contains all functions that interact with specific user.
/* ------------------------------------------------------------------ */

class EP_Dev_Whois_Admin_UserControl
{
	var $username;
	var $password;
	var $key_prefix;

	function EP_Dev_Whois_Admin_UserControl($config_username, $config_password)
	{
		$this->username = $config_username;
		$this->password = $config_password;

		$this->key_prefix = "Whois_Admin_";
	}


	/* ------------------------------------------------------------------ */
	//	Login based on $username and $password
	//	Sets new session based on $username and $password
	/* ------------------------------------------------------------------ */
	
	function login($username, $password)
	{
		if ($this->check($username, $password))
		{
			$this->setValue("username", $username);
			$this->setValue("password", $password);
		}
	}


	/* ------------------------------------------------------------------ */
	//	Logout
	//	Destroys all session data relating to admin panel.
	/* ------------------------------------------------------------------ */
	
	function logout()
	{
		foreach(array_keys($_SESSION) as $key)
		{
			if (substr($key, 0, strlen($this->key_prefix)) == $this->key_prefix)
				unset($_SESSION[$key]);
		}
	}


	/* ------------------------------------------------------------------ */
	//	check
	//	boolean return of correct username & password.
	//	If parameters specified, they are used. Otherwise, session values
	//	are used.
	//	return true = good login, false = bad login
	/* ------------------------------------------------------------------ */
	
	function check($username = null, $password = null)
	{
		if ($username===null)
			$username = $this->getValue("username");

		if ($password===null)
			$password = $this->getValue("password");
		
		return ($username == $this->username && $password == $this->password);
	}


	/* ------------------------------------------------------------------ */
	//	Get session value of $key
	//	Returns session value of $key
	/* ------------------------------------------------------------------ */
	
	function getValue($key)
	{
		return $_SESSION[$this->key_prefix . $key];
	}


	/* ------------------------------------------------------------------ */
	//	Set session value of $key to $value
	/* ------------------------------------------------------------------ */
	
	function setValue($key, $value)
	{
		$_SESSION[$this->key_prefix . $key] = $value;
	}


	// Checks if username and password are still default
	function defaultConfig()
	{
		return ($this->username == "admin" && $this->password == "");
	}

}
