<?php
# This file was automatically generated by the MediaWiki 1.20.0
# installer. If you make manual changes, please keep track in case you
# need to recreate them later.
#
# See includes/DefaultSettings.php for all configurable settings
# and their default values, but don't forget to make changes in _this_
# file, not there.
#
# Further documentation for configuration settings may be found at:
# http://www.mediawiki.org/wiki/Manual:Configuration_settings

# Protect against web entry
if ( !defined( 'MEDIAWIKI' ) ) {
	exit;
}

## Uncomment this to disable output compression
# $wgDisableOutputCompression = true;

$wgSitename      = "CUETools";
$wgMetaNamespace = "CUETools_wiki";

## The URL base path to the directory containing the wiki;
## defaults for all runtime URL paths are based off of this.
## For more information on customizing the URLs
## (like /w/index.php/Page_title to /wiki/Page_title) please see:
## http://www.mediawiki.org/wiki/Manual:Short_URL
$wgScriptPath       = "/w";
#$wgScript           = "/w/index.php";
$wgArticlePath      = "/wiki/$1";
#$wgScriptExtension  = ".php";

## The protocol and server name to use in fully-qualified URLs
$wgServer           = "http://cue.tools";

## The relative URL path to the skins directory
#$wgResourceBasePath = "http://s3.cuetools.net/mediawiki27.0";
#$wgStylePath = "$wgResourceBasePath/skins";

## The relative URL path to the logo.  Make sure you change this from the default,
## or else you'll overwrite your logo when you upgrade!
$wgLogo             = "http://s3.cuetools.net/ctdb64.png";

## UPO means: this is also a user preference option

$wgSMTP = array(
 'host'     => "sendmail",         // could also be an IP address. Where the SMTP server is located
 'IDHost'   => "cue.tools",        // Generally this will be the domain name of your website (aka mywiki.org)
 'port'     => 25,                 // Port to use when connecting to the SMTP server
 'auth'     => false,              // Should we use SMTP authentication (true or false)
# 'username' => "my_user_name",     // Username to use for SMTP authentication (if being used)
# 'password' => "my_password"       // Password to use for SMTP authentication (if being used)
);

$wgEnableEmail      = true;
$wgEnableUserEmail  = true; # UPO

$wgEmergencyContact = "admin@cuetools.net";
$wgNoReplyAddress = "CUETools Wiki <noreply@cuetools.net>";
$wgPasswordSenderName = "CUETools Wiki";
$wgPasswordSender   = "admin@cuetools.net";

$wgEnotifUserTalk      = false; # UPO
$wgEnotifWatchlist     = true; # UPO
$wgEmailAuthentication = true;
$wgEmailConfirmToEdit = true;

## Database settings
$wgDBtype           = "postgres";
$wgDBserver         = "pgbouncer";
$wgDBname           = "ctwiki";
$wgDBuser           = "ctwiki";
$wgDBpassword       = "";
$wgDBmwschema       = "mediawiki";
$wgDBprefix         = "";

# Postgres specific settings
$wgDBport           = "6432";
$wgDBmwschema       = "mediawiki";

## Shared memory settings
$wgMainCacheType    = CACHE_ACCEL;
$wgMemCachedServers = array();

## To enable image uploads, make sure the 'images' directory
## is writable, then set this to true:
$wgEnableUploads  = true;
$wgAllowCopyUploads    = true;
#$wgUseImageMagick = true;
#$wgImageMagickConvertCommand = "/usr/bin/convert";

# InstantCommons allows wiki to use images from http://commons.wikimedia.org
$wgUseInstantCommons  = false;

## If you use ImageMagick (or any other shell command) on a
## Linux server, this will need to be set to the name of an
## available UTF-8 locale
$wgShellLocale = "en_US.utf8";

## If you want to use image uploads under safe mode,
## create the directories images/archive, images/thumb and
## images/temp, and make them all writable. Then uncomment
## this, if it's not already uncommented:
#$wgHashedUploadDirectory = false;

## Set $wgCacheDirectory to a writable directory on the web server
## to make your wiki go slightly faster. The directory should not
## be publically accessible from the web.
#$wgCacheDirectory = "$IP/cache";

# Site language code, should be one of the list in ./languages/Names.php
$wgLanguageCode = "en";

$wgSecretKey = "203a643b4112d98bff3cf6d32e3cdd4f9ba366bc72fd47d60aa66c53fdc87aa5";

# Site upgrade key. Must be set to a string (default provided) to turn on the
# web installer while LocalSettings.php is in place
$wgUpgradeKey = "4f8247c8b2c1d3c4";

## Default skin: you can change the default skin. Use the internal symbolic
## names, ie 'standard', 'nostalgia', 'cologneblue', 'monobook', 'vector':
$wgDefaultSkin = "vector";

## For attaching licensing metadata to pages, and displaying an
## appropriate copyright notice / icon. GNU Free Documentation
## License and Creative Commons licenses are supported so far.
#$wgEnableCreativeCommonsRdf = true;
$wgRightsPage = ""; # Set to the title of a wiki page that describes your license/copyright
$wgRightsUrl  = "";
$wgRightsText = "Public Domain";
$wgRightsIcon = "$wgScriptPath/resources/assets/licenses/public-domain.png";

# Path to the GNU diff3 utility. Used for conflict resolution.
$wgDiff3 = "/usr/bin/diff3";

# Query string length limit for ResourceLoader. You should only set this if
# your web server has a query string length limit (then set it to that limit),
# or if you have suhosin.get.max_value_length set in php.ini (then set it to
# that value)
$wgResourceLoaderMaxQueryLength = -1;

# The following permissions were set based on your choice in the installer
$wgGroupPermissions['*']['edit'] = false;
$wgGroupPermissions['sysop']['checkuser'] = true;
$wgGroupPermissions['sysop']['checkuser-log'] = true;

# End of automatically generated settings.
# Add more configuration options below.

wfLoadExtension( 'Interwiki' );
wfLoadSkin( 'Timeless' );
wfLoadSkin( 'Vector' );
$wgVectorUseSimpleSearch = true;
$wgDefaultUserOptions['vector-collapsiblenav'] = 1;
#$wgDefaultUserOptions['vector-simplesearch'] = 1;

wfLoadExtension( 'WikiEditor' );
$wgDefaultUserOptions['usebetatoolbar'] = 1;
$wgDefaultUserOptions['usebetatoolbar-cgd'] = 1;
$wgDefaultUserOptions['wikieditor-preview'] = 1;

wfLoadExtensions( array( 'ConfirmEdit', 'ConfirmEdit/ReCaptchaNoCaptcha' ) );
$wgCaptchaClass = 'ReCaptchaNoCaptcha';
$wgReCaptchaSiteKey = '6Ld6BsgSAAAAAIv4joGO60OjtfxxU6FNAoj9nCra';
$wgReCaptchaSecretKey = '6Ld6BsgSAAAAAArzGB8Te-fGh3lj92L-W2WvXu42';
$wgReCaptchaSendRemoteIP = true;

#wfLoadExtensions( array( 'ConfirmEdit', 'ConfirmEdit/QuestyCaptcha' ) );
#$arr = array (
#        "What is the most popular lossless audio codec?" => "flac",
#        'What file extension is used for CD table of contents?' => 'cue',
#);
#foreach ( $arr as $key => $value ) {
#        $wgCaptchaQuestions[] = array( 'question' => $key, 'answer' => $value );
#}

wfLoadExtension('StopForumSpam');
$wgSFSIPListLocation = '/var/www/blacklist/listed_ip_30_all.zip';

wfLoadExtension('SpamBlacklist');

$wgSpamBlacklistFiles = array(
   "[[m:Spam blacklist]]",
   "http://meta.wikimedia.org/wiki/Spam_blacklist",
   "http://en.wikipedia.org/wiki/MediaWiki:Spam-blacklist"
);

#wfLoadExtension('googleAnalytics');
require_once( "$IP/extensions/googleAnalytics/googleAnalytics.php" );
$wgGoogleAnalyticsAccount = "UA-25790916-1";
require_once( "$IP/extensions/googleAnalyticsLinks/googa.php" );

$wgEnableDnsBlacklist = true;
$wgDnsBlacklistUrls = array( 'xbl.spamhaus.org', 'dnsbl.tornevall.org' );

wfLoadExtension( 'Nuke' );
wfLoadExtension( 'MobileFrontend' );

$wgMFDefaultSkinClass = 'SkinVector';
$wgMFAutodetectMobileView = true;

$wgShowExceptionDetails = true;

$wgEnableCanonicalServerLink = true;

wfLoadExtension( 'ParserFunctions' );
wfLoadExtension( 'CheckUser' );

$wgUsePrivateIPs = true;
$wgSquidServersNoPurge = array( '172.18.0.1/16' );
