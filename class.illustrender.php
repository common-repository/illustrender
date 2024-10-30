<?php
/**
 * Illustration Rendering Class
 * Copyright (C) 2004  Alex Gittens <rubberduckie@gmail.com>
 * 
 * based upon the Latex Rendering Class:
 * Copyright (C) 2003  Benjamin Zeiss <zeiss@math.uni-goettingen.de>
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
 */

class IllustRender {
    // ====================================================================================
    // Variable Definitions
    // ====================================================================================
    var $_picture_path = "";
    var $_picture_path_httpd = "";
    var $_tmp_dir = "";

    var $_latex_path = "/usr/bin/latex";
    var $_mpost_path = "/usr/bin/mpost";
    var $_dvips_path = "/usr/bin/dvips";
    var $_convert_path = "/usr/bin/convert";
    var $_identify_path = "/usr/bin/identify";
    var $_gs_path = "/usr/bin/gs";
    var $_psmath_path;
    var $_formula_density = 120;
    var $_illust_density = 120; // smaller = smaller image size and poorer quality
    var $_xsize_limit = 500;
    var $_ysize_limit = 500;
    var $_string_length_limit = 500;
    var $_font_size = 10;
    var $_latexclass = "article"; //install extarticle class if you wish to have smaller font sizes
    var $_tmp_filename;
    var $_image_format = "gif"; //change to png if you prefer
     
    // this most certainly needs to be extended. in the long term it is planned to use
    // a positive list for more security. this is hopefully enough for now. i'd be glad
    // to receive more bad tags !
    var $_latex_tags_blacklist = array("include", "def", "command", "loop", "repeat", "open", "toks", "output", "input",
        "catcode", "name", "^^",
        "\\every", "\\errhelp", "\\errorstopmode", "\\scrollmode", "\\nonstopmode", "\\batchmode",
        "\\read", "\\write", "csname", "\\newhelp", "\\uppercase", "\\lowercase", "\\relax", "\\aftergroup",
        "\\afterassignment", "\\expandafter", "\\noexpand", "\\special"
        ); 
    // this needs to be filled, by someone more familiar with PostScript
    // security issues.
    var $_postscript_tags_blacklist = array();

    var $_errorcode = 0;
    var $_errorextra = ""; 
    // ====================================================================================
    // constructor
    // ====================================================================================
    /**
     * Initializes the class
     * 
     * @param string $ path where the rendered pictures should be stored
     * @param string $ same path, but from the httpd chroot
     */
    function IllustRender($picture_path, $picture_path_httpd, $tmp_dir)
    {
        $this->_picture_path = $picture_path;
        $this->_picture_path_httpd = $picture_path_httpd;
        $this->_tmp_dir = $tmp_dir;
        $this->_psmath_path = $tmp_dir;
        $this->_tmp_filename = md5(rand());
    } 
    // ====================================================================================
    // public functions
    // ====================================================================================
    /**
     * Picture path Mutator function
     * 
     * @param string $ sets the current picture path to a new location
     */
    function setPicturePath($name)
    {
        $this->_picture_path = $name;
    } 

    /**
     * Picture path Mutator function
     * 
     * @returns the current picture path
     */
    function getPicturePath()
    {
        return $this->_picture_path;
    } 

    /**
     * Picture path HTTPD Mutator function
     * 
     * @param string $ sets the current httpd picture path to a new location
     */
    function setPicturePathHTTPD($name)
    {
        $this->_picture_path_httpd = $name;
    } 

    /**
     * Picture path HTTPD Mutator function
     * 
     * @returns the current picture path
     */
    function getPicturePathHTTPD()
    {
        return $this->_picture_path_httpd;
    } 

    /**
     * Tries to match the illustration code given as argument against the
     * cache. If the picture has not been rendered before, it'll
     * try to render the illustration and drop it in the picture cache directory.
     * 
     * @param string $ illustration code in a supported format (PS, MP)
     * @param string $ the format code
     * @returns the webserver based URL to a picture which contains the
     * requested illustration. If anything fails, the result value is false.
     */
    function getIllustURL($illustcode, $format)
    { 
        // circumvent certain security functions of web-software which
        // is pretty pointless right here
        $illustcode = preg_replace("/&gt;/i", ">", $illustcode);
        $illustcode = preg_replace("/&lt;/i", "<", $illustcode);

        $illust_hash = md5($illustcode);

        $filename = $illust_hash . "." . $this->_image_format;
        $full_path_filename = $this->getPicturePath() . "/" . $filename;

        if (is_file($full_path_filename)) {
            return $this->getPicturePathHTTPD() . "/" . $filename;
        } else {
            switch ($format) {
                case 'MP': return $this->handle_mp($illustcode, $filename);
                case 'PS': return $this->handle_ps($illustcode, $filename);
                default: $this->_errorcode = 3; // unsupported illustration format
                    return false;
            } 
        } 
    } 
    // ====================================================================================
    // private functions
    // ====================================================================================
    /**
     * handles the rendering of MetaPost illustrations.
     * 
     * @param string $ MetaPost code
     * @param string $ filename the illustration will be stored under
     * @returns the url of the illustration, or false if an error occurred.
     */
    function handle_mp($illustcode, $filename)
    { 
        // check for blacklisted LaTeX tags
        for ($i = 0;$i < sizeof($this->_latex_tags_blacklist);$i++) {
            if (stristr($illustcode, $this->_latex_tags_blacklist[$i])) {
                $this->_errorcode = 2;
                return false;
            } 
        } 
        // security checks assume safe illustration, let's render it
        if ($this->renderMP($illustcode)) {
            return $this->getPicturePathHTTPD() . "/" . $filename;
        } else {
            return false;
        } 
    } 

    /**
     * handles the rendering of PostScript illustrations.
     * 
     * @param string $ Postscript code
     * @param string $ filename the illustration will be stored under
     * @returns the url of the illustration, or false if an error occurred.
     */

    function handle_ps($illustcode, $filename)
    { 
        // check for blacklisted PostScript
        for ($i = 0;$i < sizeof($this->_postscript_tags_blacklist);$i++) {
            if (stristr($illustcode, $this->_postscript_tags_blacklist[$i])) {
                $this->_errorcode = 2;
                return false;
            } 
        } 
        // security checks assume safe illustration, let's render it
        if ($this->renderPS($illustcode)) {
            return $this->getPicturePathHTTPD() . "/" . $filename;
        } else {
            return false;
        } 
    } 
    /**
     * converts the basic MetaPost illustration code passed in as a string into
     * a fully compliant MetaPost program, and returns as a string. Customize
     * if you want to change the font style, for example.
     * 
     * @param string $ MetaPost illustration code
     * @returns a complete MetaPost program as a string
     */

    function wrap_mpcode($mpcode)
    {
        $string = "beginfig(1);\n";
        $string .= "verbatimtex\n";
        $string .= "\documentclass{article}\n";
        $string .= "\usepackage{times,amsmath}\n";
        $string .= "\begin{document}\n";
        $string .= "etex;\n";
        $string .= $mpcode . "\n";
        $string .= "endfig;\n";
        $string .= "end;\n";

        return $string;
    } 

    /**
     * wraps a minimalistic LaTeX document around the illustration and returns a string
     * containing the whole document as string.
     * 
     * @param string $ filename of illustration
     * @returns minimalistic LaTeX document containing the given illustration
     */

    function wrap_latex($illustfile)
    {
        $string = "\documentclass[" . $this->_font_size . "pt]{" . $this->_latexclass . "}\n";
        $string .= "\usepackage[latin1]{inputenc}\n";
        $string .= "\usepackage{amsmath}\n";
        $string .= "\usepackage{amsfonts}\n";
        $string .= "\usepackage{amssymb}\n";
        $string .= "\usepackage{graphicx}\n";
        $string .= "\pagestyle{empty}\n";
        $string .= "\begin{document}\n";
        $string .= "\includegraphics{" . $illustfile . "}\n";
        $string .= "\end{document}\n";

        return $string;
    } 

    /**
     * returns the dimensions of a picture file using 'identify' of the
     * imagemagick tools. The resulting array can be adressed with either
     * $dim[0] / $dim[1] or $dim["x"] / $dim["y"]
     * 
     * @param string $ path to a picture
     * @returns array containing the picture dimensions
     */
    function getDimensions($filename)
    {
        $output = exec($this->_identify_path . " " . $filename);
        $result = explode(" ", $output);
        $dim = explode("x", $result[2]);
        $dim["x"] = $dim[0];
        $dim["y"] = $dim[1];

        return $dim;
    } 

    /**
     * Renders a MetaPost illustration by the using the following method:
     *   - write the MetaPost code into a wrapped .mp file in a temporary directory
     *     and change to it
     *   - run mpost on the mp file to generate the .1 illustration file
     *   - create a temporary tex file containing a reference to the .1
     *     illustration
     *   - Create a DVI file using latex (tetex)
     *   - Convert DVI file to Postscript (PS) using dvips (tetex)
     *   - convert the ps file to tiff using GS 
     *   - convert to desired format, trim and add transparency by using 'convert' from the
     *     imagemagick package.
     *   - Save the resulting image to the picture cache directory using an
     *     md5 hash as filename. Already rendered illustrations can be found directly
     *     this way.
     * 
     * @param string $ Metapost code
     * @returns true if the picture has been successfully saved to the picture
     *           cache directory
     */
    function renderMP($illustcode)
    {
        $mp_document = $this->wrap_mpcode($illustcode);

        $current_dir = getcwd();

        chdir($this->_tmp_dir); 
        // create temporary mpost file
        $fp = fopen($this->_tmp_dir . "/" . $this->_tmp_filename . ".mp", "w");
        fputs($fp, $mp_document);
        fclose($fp); 
        // create temporary mpost output file
        $command = "TEX=" . $this->_latex_path . " " . $this->_mpost_path . " --interaction=nonstopmode " . $this->_tmp_filename . ".mp";
        $status_code = exec($command);

        if (!$status_code) {
            $this->cleanTemporaryDirectory();
            chdir($current_dir);
            $this->_errorcode = 4;
            return false;
        } 
        // create temporary latex file
        $latex_document = $this->wrap_latex($this->_tmp_filename . ".1");

        $fp = fopen($this->_tmp_dir . "/" . $this->_tmp_filename . ".tex", "a+");
        fputs($fp, $latex_document);
        fclose($fp); 
        // create temporary dvi file
        $command = $this->_latex_path . " --interaction=nonstopmode " . $this->_tmp_filename . ".tex";
        $status_code = exec($command);

        if (!$status_code) {
            $this->cleanTemporaryDirectory();
            chdir($current_dir);
            $this->_errorcode = 4;
            return false;
        } 
        // convert dvi file to postscript using dvips
        $command = $this->_dvips_path . " -E " . $this->_tmp_filename . ".dvi" . " -o " . $this->_tmp_filename . ".ps";
        $status_code = exec($command); 
        // ghostscript convert ps to image and trim picture using imagemagick
        $command = "echo quit | " . $this->_gs_path . " -sDEVICE=tiffcrle -sOutputFile=" . $this->_tmp_filename . ".tiff" . " -r" . $this->_illust_density . " " . $this->_tmp_filename . ".ps";
        error_log($command, 0);

        $status_code = exec($command);

        if (!$status_code) {
            chdir($current_dir);
            $this->_errorcode = 6;
            return false;
        } 

        $command = $this->_convert_path . " -antialias -trim " . $this->_tmp_filename . ".tiff " . $this->_tmp_filename . "." . $this->_image_format;

        $status_code = exec($command);

        return $this->cache_illust($illustcode, $current_dir);
    } 

    /**
     * converts the minimalistic postscript code passed in into a complete
     * Postscript program, and returns it in a string
     * 
     * @param string $ Postscript code
     * @returns complete Postscript program
     */

    function wrap_pscode($illustcode)
    {
        $string = "%!PS-Adobe-3.0 \n";
        $string .= "($this->_psmath_path/psm.pro) run\n";
        $string .= "PSMinit\n";
        $string .= $illustcode . "\n";
        $string .= "PSMclose\n";
        $string .= "showpage";

        return $string;
    } 

    /**
     * Renders a Postscript illustration by the using the following method:
     *   - write the Postscript into a wrapped .ps file in a temporary directory
     *     and change to it
     *   - convert the ps file to tiff using gs in safe mode
     *   - convert to desired format, trim and add transparency by using 'convert' from the
     *     imagemagick package.
     *   - Save the resulting image to the picture cache directory using an
     *     md5 hash as filename. Already rendered illustrations can be found directly
     *     this way.
     * 
     * @param string $ Postscript code
     * @returns true if the picture has been successfully saved to the picture
     *           cache directory
     */
    function renderPS($illustcode)
    {
        $ps_document = $this->wrap_pscode($illustcode);

        $current_dir = getcwd();

        chdir($this->_tmp_dir); 
        // create temporary ps file
        $fp = fopen($this->_tmp_dir . "/" . $this->_tmp_filename . ".ps", "w");
        fputs($fp, $ps_document);
        fclose($fp); 
        // ghostscript convert ps to image and trim picture using imagemagick
        $command = "echo quit | " . $this->_gs_path . " -sDEVICE=tiffcrle -dSAFER -sOutputFile=" . $this->_tmp_filename . ".tiff" . " -r" . $this->_illust_density . " " . $this->_tmp_filename . ".ps";
        error_log($command, 0);

        $status_code = exec($command);

        if (!$status_code) {
            chdir($current_dir);
            $this->_errorcode = 6;
            return false;
        } 

        $command = $this->_convert_path . " -antialias -trim " . $this->_tmp_filename . ".tiff " . $this->_tmp_filename . "." . $this->_image_format;

        $status_code = exec($command);

        return $this->cache_illust($illustcode, $current_dir);
    } 

    /**
     * Checks that illustration is right size and copies it to the cached 
     * illustration directory,
     * 
     * @param string $ illustration code
     * @param string $ directory illustrender started in
     * @returns true if illustration is correct size and was copied successfully,
     * false otherwise
     */
    function cache_illust($illustcode, $current_dir)
    { 
        // test picture for correct dimensions
        $dim = $this->getDimensions($this->_tmp_filename . "." . $this->_image_format);

        if (($dim["x"] > $this->_xsize_limit) or ($dim["y"] > $this->_ysize_limit)) {
            $this->cleanTemporaryDirectory();
            chdir($current_dir);
            $this->_errorcode = 5;
            $this->_errorextra = ": " . $dim["x"] . "x" . number_format($dim["y"], 0, "", "");
            return false;
        } 
        // copy temporary illustration file to cached illustration directory
        $illusthash = md5($illustcode);
        $filename = $this->getPicturePath() . "/" . $illusthash . "." . $this->_image_format;

        $status_code = copy($this->_tmp_filename . "." . $this->_image_format, $filename);

        $this->cleanTemporaryDirectory();

        if (!$status_code) {
            chdir($current_dir);
            $this->_errorcode = 6;
            return false;
        } 
        chdir($current_dir);

        return true;
    } 

    /**
     * Cleans the temporary directory
     */
    function cleanTemporaryDirectory()
    {
        $current_dir = getcwd();
        chdir($this->_tmp_dir);

        unlink($this->_tmp_dir . "/" . $this->_tmp_filename . ".tex");
        unlink($this->_tmp_dir . "/" . $this->_tmp_filename . ".aux");
        unlink($this->_tmp_dir . "/" . $this->_tmp_filename . ".log");
        unlink($this->_tmp_dir . "/" . $this->_tmp_filename . ".dvi");
        unlink($this->_tmp_dir . "/" . $this->_tmp_filename . ".ps");
        unlink($this->_tmp_dir . "/" . $this->_tmp_filename . "." . $this->_image_format);

        unlink($this->_tmp_dir . "/" . $this->_tmp_filename . ".mpx");
        unlink($this->_tmp_dir . "/" . $this->_tmp_filename . ".mp");
        unlink($this->_tmp_dir . "/" . $this->_tmp_filename . ".1");
        unlink($this->_tmp_dir . "/" . $this->_tmp_filename . ".tiff");

        chdir($current_dir);
    } 
} 

?>
