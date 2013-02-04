// --------------------------------------------
// | The EP-Dev Whois script        
// |                                           
// | Copyright (c) 2003-2009 Patrick Brown as EP-Dev.com           
// | This program is free software; you can redistribute it and/or modify
// | it under the terms of the GNU General Public License as published by
// | the Free Software Foundation; either version 2 of the License, or
// | (at your option) any later version.              
// | 
// | This program is distributed in the hope that it will be useful,
// | but WITHOUT ANY WARRANTY; without even the implied warranty of
// | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// | GNU General Public License for more details.
// | 
// | You should have received a copy of the GNU General Public License
// | along with this program; if not, write to the Free Software
// | Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
// --------------------------------------------

The EP-Dev Whois: version 2.31
Information:
 This whois script will do alot of things. It will act as an ordinary whois script, returning whois information natively on about 200 different extensions with the ability to easily add more. It will also act as a whois script for registrars who plan to pass available domains into a billing system for a customer to order. These two modes contain a multitude of features: price table, automatic alternative domain searches, ability to pass the full domain and/or extension (tld) to another script, backup nameservers, domain search logging, multiple extension (tld) and domain searches at one time, query limit bypass, custom keyword and query formats for each nameserver, enable/disable nameservers and/or extensions, custom currency support, IDN support, contact querying support, image verification, whois contact protection, query caching support, and much more. All of the configuration can be edited from within control panel. Fully template driven, allowing for one to easily edit almost every element of the display to fit any website.

 One of the main goals of this script was to create both a user-friendly and programmer-friendly script. That is to say, create a script that could be used by novice webmasters as well as a script that would provide the customization and power that advanced webmasters require.

// --------------------------------------------
// --------------------------------------------

What's New:
 Version 2.31: Fixed .eu, .info extensions. Added support for .mobi. Fixed foreign language problems with templates.
 
 Version 2.30: Multiple bug fixes, improvements, and general maintenance.

 Version 2.22: A bug has been fixed involving the absolute path not being automatically detected when left blank and the whois script is included within another file.
 
 Version 2.21: eu TLD domain support added.

// --------------------------------------------
// --------------------------------------------

Instructions Install:
1. Upload all files.
2. Visit admin/index.php in your browser and follow the directions.
3. Visit whois.php in your browser.

UPGRADE INSTRUCTIONS:
** YOU SHOULD MAKE A BACKUP OF THE /config/ FOLDER. **

VERSION (2.x -> 2.x)
1. Upload/replace everything except for the /config/ folder.
2. Visit the admin panel /admin/ in your browser and follow the instructions that appear on the screen.

VERSION (1.x -> 2.0)
1. Sorry, there isn't an upgrade path due to the new configuration and structure of version 2.0. You will just need to reconfigure it all (it will likely only take a minute or two).

// --------------------------------------------
// --------------------------------------------

Version History:
2.31 - July 15 2009:
UPDATED - Fixed .eu and .info TLD support.
UPDATED - Added support for .mobi TLD
FIXED BUG - Fixed a bug in admin/display.php preventing UTF-8 strings from being displayed and saved correctly.
FIXED BUG - Fixed a bug in global.php preventing custom currency configurations from working.

2.30 - June 25 2009:
FIXED BUG - Multiple extensions have been fixed (NOTE: You will not see changes if you upgrade).
IMPROVED - Improved validator to display all spaces in whois report.
FIXED BUG - Script no longer uses short tags (<?) which are disabled on default systems.
FIXED BUG - Fixed a bug in documentation that claimed user search extension to be [user-ext] instead of the correct [user-extension].
FIXED BUG - A bug making keyword case insensitive has been fixed. The keywords are now properly case sensitive.
IMPROVED - Added [count] tag for [repeat]...[/repeat] sections in templates to return count of element (starting from 1).
IMPROVED - Exception handling is now the default mechanism for handling errors. The script will throw and catch exceptions instead of exiting.
FIXED BUG - A bug that prevented whole domain queries (domain=somewhere.com) for Whois Reports has been fixed. Full domain names + extensions can now be passed in via domain= instead of separating domain and extension.

2.22 - April 05 2006:
FIXED BUG - Fixed a potential bug when absolute path is left blank and whois script is included within another script.

2.21 - April 03 2006:
UPDATED - Added .eu TLD support. (heh... forgot to include in 2.20).

2.20 - April 03 2006:
IMPROVED - Object / link references have been improved for better memory management on PHP 4.x systems.
ADDED FEATURE - Alternative pre-configured full domains can now be suggested whenever a user domain is unavailable.
ADDED FEATURE - Added ability to display all emails found in a report as images, preventing spam bots from abusing the script.
ADDED FEATURE - Added ability to enforce image verification for whois reports.
FIXED BUG - Fixed a bug in the admin panel's validator that would ignore custom input for secondary servers targeting the same tld.
IMPROVED - Script now parses whois reports for html entities.
ADDED FEATURE - Added ability to remove common whois database notices from whois report output.
FIXED BUG - Fixed missing overall log handling (enable / disable) statements.
ADDED FEATURE - Script can now include and process outside PHP header / footer files from within Edit Templates.
FIXED BUG - Fixed a major bug that could result in corrupt config.php when deleting a nameserver.
FIXED BUG - Fixed a bug that imposed a lower upper-bound for domain length than normal under certain extensions.
IMRPOVED - Updated multiple domain logic to be more lenient of improper user input.
IMPROVED - Cleaned up some display issues in admin panel.
ADDED FEATURE - Script now includes support for Internationalized Domain Names (IDN).
IMPROVED - Improved nameserver removal logic to allow for out of order entries.
IMPROVED - Restructured classes/engine.php to allow for easy modification of connection / request functions.
ADDED FEATURE - Added ability to search whois server for contact or owner information of domain.
IMPROVED - Improved inclusion technique to allow for visible errors and better debugging.
FIXED BUG - Fixed a bug causing initial connect failure that *could* cause a decrease in execution speed of the script in engine.php .
FIXED BUG - Nameserver keyword NOT reference (^) was not working ;). Not sure how this stayed a bug so long, as ^ is going to match string start as opposed to NOT. The new prefix is !!!. When !!! is found, the following text is matched in the manner of NOT text. No more regex conflicts either. Logic improved.
ADDED FEATURE - Added ability to cache whois queries with configuration of time available.
ADDED FEATURE - Added advanced configuration options for nameservers including customizable connections and ports.
IMPROVED - Added [[SERVER]] tag as recognizable in query formats for nameservers. Tag is replaced by server.
FIXED BUG - Fixed a bug that was preventing proper usage of php slashes in query formats. Added proper slash parsing.

2.11 - July 07 2005:
FIXED BUG - Fixed a bug in whois.php that was causing all non buy mode queries to fail no_extension.
FIXED BUG - Fixed a bug in global.php that was causing some error reports to fail in PHP 4.
FIXED BUG - Fixed a bug that caused improper HTML for special currency symbols, including the pound and euro symbol, in global.php (http://www.dev-forums.com/index.php?showtopic=147).

2.1 - May 28 2005:
IMPROVED - Improved upgrade process by creating a new upgrade backend to be used during upgrade. Makes for a very easy upgrade.
ADDED FEATURE - Added ability to accept multiple domains at one time. Also added new multiple domain check page.
FIXED BUG - Fixed bug in admin/display.php that was causing the script's templates to not handle html entities properly.
FIXED BUG - Fixed a bug in the administration panel that was not triggering an error when an attempt to add a duplicate nameserver occurs.
FIXED BUG - Fixed bug in global.php that was not allowing proper TLD error reporting on systems running PHP 4.x.

2.01 - April 17 2005:
IMPROVED - Improved the initial login screen of the administration panel to list the default username and password.
FIXED BUG - Fixed a major bug in the administration panel that prevented proper modification/addition of custom config types (ex: string). The bug affected systems < PHP 5.

2.0 - March 29 2005:
ADDED FEATURE - Administration panel should now be used in editing all aspects of the script.
IMPROVED - Entire code has been rewritten and is now fully object oriented. There are too many new features, etc, to discuss.

1.5 - (Never released):
UPDATED - Updated script's price table to look better.
IMPROVED - Updated script to be more object-oriented.
UPDATED - Updated additional-servers.txt with updated .be whois information (thanks Marc).

1.41 - July 27 2004:
ADDED FEATURE - Added exclusion list to exclude TLDs from the price list that you may not want listed.
FIXED BUG - Fixed typo in search-whois that prevented whois from displaying on some php configs.
IMPROVED - Improved domain submit form to exclude the submit input as a variable.
ADDED FEATURE - Added new nameservers_with_special array to accomodate for servers that require special arguments.
FIXED BUG - Fixed price on Domain Available page not being formatted.
IMPROVED - Improved variable formats being passed into external script. The formats can now be edited from config file.
UPDATED - Updated script to work with default PHP5 install.
UPDATED - Updated additional-servers.txt with .de update (thanks Christian).
ADDED - Added external-script-example.php with explanations & examples of how to use the external_script_url variable in config.php.
UPDATED - Updated script to reflect GNU GPL more effectively.

1.4 - February 14 2004:
ADDED FEATURE - Logs have been incorporated. You can  view them in the /logs folder.
UPDATED - Updated additional-servers.txt with .pl dns updates (thanks WELOO)
ADDED FEATURE - Added support for whois servers that have limits and request entries via "is" command.

1.3 - November 17 2003:
FIXED BUG - Fixed timeout not working with custom setting in whois-class.php .
ADDED FEATURE - Added ability to modify price table in template.php.

1.2 - September 20 2003:
ADDED FEATURE - Multiple & custom currencies. The script can now form currency into any format. values can be set to US, UK, EU, or a custom setting.
ADDED FEATURE - Added template for search TLDs. People with the know-how can edit the search TLD display in the templates.php file.

1.1 - September 16 2003: 
ADDED FEATURE - Added language control. Now messages can be controlled with a language file in the template folder. Other text can be edited in the template.php file.
ADDED FEATURE - Added error checking. Now the domains are checked for length, and invalid characters.

1.0 - September 13 2003:
First release, but can already do alot.

// --------------------------------------------
// --------------------------------------------

TODO:
- Multiple year domain price/registration recognition (such as co.uk).

// --------------------------------------------
// --------------------------------------------

Contact:
 ----> For Support: http://www.dev-forums.com
 I like to hear from people: opinions, comments, suggestions, and anything you may have created as an addition or add-on to the script! Don't hesitate to contact me :)
 To contact us, visit www.ep-dev.com or patiek@ep-dev.com . Thanks for your support!

If you modify this program, you must still include a reference to www.ep-dev.com in the modified script.
