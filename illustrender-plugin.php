<?php
/*
Plugin Name: IllustRender
Plugin URI: http://www.tangentspace.net/cz/archives/2005/01/illustrender
Description: Add support for Postscript and MetaPost illustrations
Version: 1.0
Author: Alex Gittens
Author URI: http://www.tangentspace.net/cz
*/

function addillust($illusttxt)
{
    include_once('/public_html/wordpress/illustrender/illustrender.php');
    return illust_content($illusttxt);
} 
// And now for the filters
add_filter('the_title', 'addillust');
add_filter('the_content', 'addillust');

/*
Add a TeX button
Adapted from:
Plugin Name: Edit Button Framework
Plugin URI: http://www.asymptomatic.net/wp-hacks
Description: A Plugin template for adding new buttons to the post editor.
Version: 1.0
Author: Owen Winkler
Author URI: http://www.asymptomatic.net
*/

add_filter('admin_footer', 'alexg_function_name');

function alexg_function_name()
{
    if (strpos($_SERVER['REQUEST_URI'], 'post.php')) {

        ?>
<script language="JavaScript" type="text/javascript"><!--
var toolbar = document.getElementById("ed_toolbar");
<?php
        edit_insert_illust_button("illust", "illust_button_handler", "Add Illust tag");

        ?>

function illust_button_handler() {
	var j=edButtons.length - 1;
	for (i = 0; i < edButtons.length; i++) {
		if (edButtons[i].id == 'ed_illust') {
			j=i;
		}
	}
	edInsertTag(edCanvas, j);
}
//--></script>

<?php
    } 
} 

if (!function_exists('edit_insert_illust_button')) {
    // edit_insert_button: Inserts a button into the editor
    function edit_insert_illust_button($caption, $js_onclick, $title = '')
    {

        ?>
		if(toolbar)
		{
			edButtons[edButtons.length] =
			new edButton('ed_illust'
			,'illust'
			,'[illust]'
			,'[/illust]'
			,'x'
			);

			var theButton = document.createElement('input');
			theButton.type = 'button';
			theButton.value = '<?php echo $caption;
        ?>';
			theButton.onclick = <?php echo $js_onclick;
        ?>;
			theButton.className = 'ed_button';
			theButton.title = "<?php echo $title;
        ?>";
			theButton.id = "<?php echo "ed_{$caption}";
        ?>";
			theButton.accessKey='x';
			toolbar.appendChild(theButton);
		}
	<?php

    } 
} 

?>
