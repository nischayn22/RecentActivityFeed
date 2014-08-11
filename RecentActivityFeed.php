<?php

# Alert the user that this is not a valid access point to MediaWiki if they try to access the special pages file directly.
if ( !defined( 'MEDIAWIKI' ) ) {
   echo <<<EOT
To install my extension, put the following line in LocalSettings.php:
require_once( "\$IP/extensions/MyExtension/Recentactivityfeed.php" );
EOT;
	exit( 1 );
}
 
$wgExtensionCredits[ 'specialpage' ][] = array(
		     'path' => __FILE__,
		     'name' => 'RecentActivityFeed',
		     'author' => 'Nischay Nahata',
		     'url' => 'https://www.mediawiki.org/wiki/Extension:RecentActivityFeed',
		     'descriptionmsg' => 'recentactivityfeed-desc',
		     'version' => '0.0.0',
);

$wgAutoloadClasses[ 'SpecialRecentActivityFeed' ] = __DIR__ . '/SpecialRecentActivityFeed.php'; # Location of the SpecialRecentActivityFeed class (Tell MediaWiki to load this file)
$wgMessagesDirs[ 'RecentActivityFeed' ] = __DIR__ . "/i18n"; # Location of localisation files (Tell MediaWiki to load them)
$wgSpecialPages[ 'RecentActivityFeed' ] = 'SpecialRecentActivityFeed'; # Tell MediaWiki about the new special page and its class name