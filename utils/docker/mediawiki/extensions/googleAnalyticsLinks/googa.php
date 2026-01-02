<?php
 
/*
        Google Analytics Links - v1.2
        (c)2007 Nik Molnar (nik.molnar@gmail.com)
        Distributed UNDER THE TERMS OF GNU GPL Licence.
 
        This extension allows you to easily make use of Google Analytics to track non-HTML files (PDF, AVI, etc.) via JavaScript.
        For more information: http://www.google.com/support/analytics/bin/answer.py?answer=27242
 
        Usage: (third parameter optional)
                <googa>http://yoursite.com/therealfile.pdf|Link Name|/google/friendly/path</googa>
                <googa>/local/path/file.ext|Link Name|/google/friendly/path</googa>
                <googa>http://external.com/whatever.html|Link Name|/google/friendly/path</googa>
        Output: <a href="http://yoursite.com/therealfile.pdf" onClick="javascript:pageTracker._trackPageview('/google/friendly/path')">Link Name</a>
*/
 
$wgExtensionCredits['parserhook'][] = array(
        'name'           => 'Google Analytics Links',
        'version'        => '1.3',
        'author'         => 'Nik Molnar',
        'description'    => 'Allows you to easily make use of Google Analytics to track non-HTML files.',
        'url'            => 'http://www.mediawiki.org/wiki/Extension:Google_Analytics_Links',
        'path'           => __FILE__,
);
 
// Register hook using ParserFirstCallInit (modern MediaWiki 1.35+)
$wgHooks['ParserFirstCallInit'][] = 'wfGAnalyticsExtension';
 
//Register the hook
function wfGAnalyticsExtension( Parser $parser ) {
        $parser->setHook("googa", "renderGAnalytics");
        return true;
}
 
//Render function
function renderGAnalytics($input) {
        //Parse the arguments
        $args = explode("|", $input);
 
        //If 3rd argument is absent, use the 1st argument.
        if(!isset($args[2])) { $args[2] = $args[0]; }
 
        //Return the rendered output
        return
                "<a href=\"" . $args[0] .
                "\" class=\"external text\"" .
                " title=\"" . $args[0] . "\"" .
                " onClick=\"javascript: if (typeof(_gaq) != 'undefined') _gaq.push(['_trackPageview', '" . $args[2] .
                "']);\">" . $args[1] .
                "</a>";
}

