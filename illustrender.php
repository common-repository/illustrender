<?php
/**
 * Illustration Rendering Class - Calling function
 * Copyright (C) 2004  Alex Gittens <rubberduckie@gmail.com>
 * 
 * based upon the Latex Rendering Class:
 * Copyright (C) 2003  Benjamin Zeiss <zeiss@math.uni-goettingen.de>
 * as revised by Steve Mayer
 * 
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation; either
 * version 2.1 of the License, or (at your option) any later version.
 * 
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 * Lesser General Public License for more details.
 * 
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 * --------------------------------------------------------------------
 * 
 * @author Alex Gittens <rubberduckie@gmail.com> 
 * @version v0.1
 * @package illustrender
 * 
 * This file can be included in many PHP programs by using something like 
 * 		include_once('/full_path_here_to/illustrender/illustrender.php');
 * 		$text_to_be_converted=illust_content($illust_to_be_drawn);
 * $illust_to_be_drawn will then contain the link to the appropriate image
 * or an error code as follows (the 500 values can be altered in class.illustrender.php):
 * 	0 OK
 * 	1 Formula longer than 500 characters
 * 	2 Includes a blacklisted command 
 * 	3 Unsupported illustration format
 * 	4 Cannot create DVI file
 * 	5 Picture larger than 500 x 500 followed by x x y dimensions
 * 	6 Cannot copy image to pictures directory
 */

function illust_content($text)
{ 
    // --------------------------------------------------------------------------------------------------
    // adjust this to match your system configuration
    $illustrender_path = "/public_html/wordpress/illustrender";
    $illustrender_path_http = "/wordpress/illustrender"; 
    // --------------------------------------------------------------------------------------------------
    include_once($illustrender_path . "/class.illustrender.php");

    preg_match_all("#\[illust\](.*?)\[/illust\]#si", $text, $illust_matches);

    $illust = new IllustRender($illustrender_path . "/pictures", $illustrender_path_http . "/pictures", $illustrender_path . "/tmp");

    for ($i = 0; $i < count($illust_matches[0]); $i++) {
        $pos = strpos($text, $illust_matches[0][$i]);
        $illustcode = $illust_matches[1][$i]; 
        // if you use htmlArea to input the text then uncomment the next 6 lines
        $illustcode = str_replace("&amp;", "&", $illustcode);
        $illustcode = str_replace("&#38;", "&", $illustcode);
        $illustcode = str_replace("&nbsp;", " ", $illustcode);
        $illustcode = str_replace("<br />", "", $illustcode);
        $illustcode = str_replace("<p>", "", $illustcode);
        $illustcode = str_replace("</p>", "", $illustcode);

        if (stristr($illustcode, "%PS")) {
            $url = $illust->getIllustURL($illustcode, "PS");
        } else {
            $url = $illust->getIllustURL($illustcode, "MP");
        } 

        $alt_illustcode = htmlentities($illustcode, ENT_QUOTES);
        $alt_illustcode = str_replace("\r", "&#13;", $alt_illustcode);
        $alt_illustcode = str_replace("\n", "&#10;", $alt_illustcode);

        if ($url != false) {
            $text = substr_replace($text, "<img src='" . $url . "' title='" . $alt_illustcode . "' alt='" . $alt_illustcode . "' align=absmiddle>", $pos, strlen($illust_matches[0][$i]));
        } else {
            $text = substr_replace($text, "[Unparseable, unsupported, or potentially dangerous illustration code. Error $illust->_errorcode $illust->_errorextra]", $pos, strlen($illust_matches[0][$i]));
        } 
    } 
    return $text;
} 

?>
