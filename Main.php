<?php
/*
 * Plugin Name: Magic-fields
 * Plugin URI: http://magicfields.org
 * Description: Create custom write panels and easily retrieve their values in your templates.
 * Author: Hunk
 * Version: 1.7.4
 * Author URI: http://magicfields.org
 * Text Domain: magic-fields
 * Domain Path: /languages
 */

/**
 * This work is free software; you can redistribute it and/or 
 * modify it under the terms of the GNU General Public License 
 * as published by the Free Software Foundation; either version 
 * 2 of the License, or any later version.
 *
 * This work is distributed in the hope that it will be useful, 
 * but without any warranty; without even the implied warranty 
 * of merchantability or fitness for a particular purpose. See 
 * Version 2 and version 3 of the GNU General Public License for
 * more details. You should have received a copy of the GNU General 
 * Public License along with this program; if not, write to the 
 * Free Software Foundation, Inc., 51 Franklin St, Fifth Floor, 
 * Boston, MA 02110-1301 USA
 */

// Globals
global $wpdb,$post,$current_user,$FIELD_TYPES,$current_user,$flag,$is_wordpress_mu;


if(isset($current_blog)){
	$is_wordpress_mu=true;
}else{
	$is_wordpress_mu=false;
}

//for the save_post action doesn't be execute  twice
$flag = 0;


// Getting the  Custom field object
require_once 'PanelFields.php';

// Getting the RCCWP_CustomGroup object for work with groups
require_once 'RCCWP_CustomGroup.php';

// Getting the constants
require_once 'MF_Constant.php';

// Getting the RCCWP_CustomField object for work with  Custom Fields
require_once 'RCCWP_CustomField.php';

// Getting the RCCWP_CustomWritePanel for work with Writepanels
require_once 'RCCWP_CustomWritePanel.php';

// Include files containing Magic Fields public functions
require_once 'get-custom.php';

// Include other files used in this script

//Include files for put  the  write panels in the menu
require_once 'RCCWP_Menu.php';

require_once 'RCCWP_CreateCustomFieldPage.php';

//Debug tool
require_once 'tools/debug.php';

//Inflection class
require_once 'tools/inflect.php';
require_once ('RCCWP_Options.php');
require_once ('RCCWP_Query.php');

require_once 'MF_GetFile.php';
require_once 'MF_GetDuplicate.php';
require_once 'MF_ImageMedia.php';

 /**
  * function for languages
  */
global $mf_domain;
$mf_domain = 'magic-fields';

function mf_load_plugin_textdomain() {
	global $mf_domain;
    load_plugin_textdomain( $mf_domain, FALSE, basename( dirname( __FILE__ ) ) . '/languages/' );
}
add_action( 'plugins_loaded', 'mf_load_plugin_textdomain' );

		

/**
 *  Here actions/hooks only required in the Admin area
 */
if (is_admin()) {
	require_once ('RCCWP_Application.php');
	require_once ('RCCWP_WritePostPage.php');
	
	register_activation_hook(dirname(__FILE__) . '/Main.php', array('RCCWP_Application', 'Install'));
	
	//Attaching the Magic Fields Menus
	add_action('admin_menu', array('RCCWP_Menu', 'AttachMagicFieldsMenus'));

	if($is_wordpress_mu) {
		//checking if the method Install was executed before
		//if exists the option called "mf_custom_write_panel" 
		//is because Magic Fields was already installed
		$option = get_option('mf_custom_write_panel');
		
		if(!$option){
			RCCWP_Application::Install();
			add_action('admin_menu', array('RCCWP_Application', 'ContinueInstallation'));
		}
	}


	if (get_option(RC_CWP_OPTION_KEY) !== false) {
    	require_once ('RCCWP_Processor.php');
		add_action('init', array('RCCWP_Processor', 'Main'));
		

		add_action('admin_menu', array('RCCWP_Menu', 'AttachCustomWritePanelMenuItems'));
		add_action('admin_menu', array('RCCWP_Menu', 'DetachWpWritePanelMenuItems'));
		add_action('admin_menu', array('RCCWP_Menu', 'AttachOptionsMenuItem'));
		
		add_filter('posts_where', array('RCCWP_Menu', 'FilterPostsPagesList'));
		add_filter('posts_join_paged', array('RCCWP_Menu', 'FilterPostsPagesListJoin'));
		add_action('admin_head', array('RCCWP_Menu', 'HighlightCustomPanel'));
		
		// add_action('admin_head', 'mf_admin_style');
	


		// -- Hook all functions related to saving posts in order to save custom fields values
		require_once ('RCCWP_Post.php');	
		
		add_action('save_post', array('RCCWP_Post', 'SaveCustomFields'));
 		add_action('before_delete_post', array('RCCWP_Post','DeletePostMetaData'));		
		
		add_filter('wp_redirect', array('RCCWP_Processor', 'Redirect'));

		add_action('shutdown', array('RCCWP_Processor', 'FlushAllOutputBuffer'));

		add_action('admin_notices', array('RCCWP_Application', 'CheckInstallation'));  
		add_action('admin_notices', array('RCCWP_WritePostPage', 'FormError'));
		
	}

        //add bottons visual editor
        add_filter('mce_buttons', 'register_media_button');
        if ( !function_exists('register_media_button') ) {
        function register_media_button($buttons) {
          array_push($buttons, "separator","add_image","add_video","add_audio","add_media");
          return $buttons;
        }
    	}

    	if ( !function_exists('tmce_not_remove_p_and_br') ) {
        function tmce_not_remove_p_and_br(){
          ?>
          <script type="text/javascript">
            //<![CDATA[                                                                                     
            jQuery('body').bind('afterPreWpautop', function(e, o){
                o.data = o.unfiltered
                  .replace(/caption\]\[caption/g, 'caption] [caption')
                  .replace(/<object[\s\S]+?<\/object>/g, function(a) {
                              return a.replace(/[\r\n]+/g, ' ');
              });
              }).bind('afterWpautop', function(e, o){
                o.data = o.unfiltered;
              });
          //]]>                                                                                           
          </script>
          <?php
        }
    	}
    	
        if( RCCWP_Application::InWritePostPanel() ){
          require_once ('RCCWP_Options.php');
          $dont_remove = RCCWP_Options::Get('dont-remove-tmce');
          if($dont_remove){
            add_action( 'admin_print_footer_scripts', 'tmce_not_remove_p_and_br', 50 );
          }
        }
}

    


add_action('pre_get_posts', array('RCCWP_Query', 'FilterPrepare'));
add_filter('posts_where', array('RCCWP_Query', 'FilterCustomPostsWhere'));
add_filter('posts_where', array('RCCWP_Query','ExcludeWritepanelsPosts'));
add_filter('posts_orderby', array('RCCWP_Query', 'FilterCustomPostsOrderby'));
add_filter('posts_fields', array('RCCWP_Query', 'FilterCustomPostsFields'));
add_filter('posts_join_paged', array('RCCWP_Query', 'FilterCustomPostsJoin'));

//in search add conditions for look in postmeta
add_filter('posts_where_request', array('RCCWP_Query', 'AddConditionForSearchInPostmeta'));


$condense = RCCWP_Options::Get('condense-menu');
if($condense ){
	//adding Column for posts
	add_filter('manage_posts_columns',array('RCCWP_Query','ColumnWritePanel'));
	add_action('manage_posts_custom_column',array('RCCWP_Query','ColumnWritePanelData'));
	
	//adding Column for pages
	add_filter('manage_pages_columns',array('RCCWP_Query','ColumnWritePanel'));
	add_action('manage_pages_custom_column',array('RCCWP_Query','ColumnWritePanelData'));
}



add_action('edit_page_form','cwp_add_pages_identifiers');
add_action('edit_form_advanced','cwp_add_type_identifier');

add_action('edit_form_advanced','mf_put_write_panel_id');
add_action('edit_page_form','mf_put_write_panel_id');
/**
 * put the id of the write panel as a hidden field in the 'create post/page' and 'edit post/page'
 */
if ( !function_exists('mf_put_write_panel_id') ) {
	function mf_put_write_panel_id(){
		global $CUSTOM_WRITE_PANEL;

		echo "<input type='hidden' name='rc-custom-write-panel-verify-key' id='rc-custom-write-panel-verify-key' value='".wp_create_nonce('rc-custom-write-panel')."'/>"; // traversal, moved this out of the if to allow posts to be attached to panels 
	
		if(!empty($CUSTOM_WRITE_PANEL->id)){
			echo "<input type='hidden' name='rc-cwp-custom-write-panel-id' value='".$CUSTOM_WRITE_PANEL->id."'/>";
			echo "<input type='hidden' value='' name='magicfields_remove_files' id='magicfields_remove_files' >";
		}
	}
}

if ( !function_exists('cwp_add_type_identifier') ) {
	function cwp_add_type_identifier(){

		global $wpdb;
		global $post;
		
		
		if( isset($_GET['custom-write-panel-id']) && !empty($_GET['custom-write-panel-id'])) {
			$sql = $wpdb->prepare( "SELECT id, type FROM " . MF_TABLE_PANELS ." WHERE id= %d", array( $_GET['custom-write-panel-id'] ) );
			$getPostID = $wpdb->get_results($sql);
			echo "<input type=\"hidden\" id=\"post_type\" name=\"post_type\" value=\"". $getPostID[0]->type ."\" />";

		}else{
			printf('<input type="hidden" id="post_type" name="post_type" value="%s" />',$post->post_type);
	 }
	}
}

if ( !function_exists('cwp_add_pages_identifiers') ) {
	function cwp_add_pages_identifiers(){
		global $post;
		global $wpdb;

		$key = wp_create_nonce('rc-custom-write-panel');
		$id = "";
		$sql = $wpdb->prepare( "SELECT meta_value
								FROM $wpdb->postmeta
								WHERE post_id = %d and meta_key = %s", array( $post->ID, "_mf_write_panel_id" ) );
		$result = $wpdb->get_results( $sql, ARRAY_A );
		
		if (count($result) > 0)
			$id = $result[0]['meta_value'];
		echo 
<<<EOF
	<input type="hidden" name="rc-custom-write-panel-verify-key" id="rc-custom-write-panel-verify-key" value="$key" />
	
EOF;
	}
}

if ( !function_exists('cwp_add_pages_identifiers') ) {
	function cwp_add_pages_identifiers() {
		$url = MF_URI.'css/admin.css';
		echo '<link rel="stylesheet" type="text/css" href="' . $url . '" />';
	}
}

/**
*  Check the mime type of the file for 
*  avoid upload any dangerous file.
*  
*  @param string $mime is the type of file can be "image","audio" or "file"
*  @param string $file_type  is the mimetype of the field
*/
if ( !function_exists('valid_mime') ) {
	function valid_mime($mime,$file_type){
		$imagesExts = array(
							'image/gif',
							'image/jpeg',
							'image/pjpeg',
							'image/png',
							'image/x-png'
							);
		$audioExts = array(
							'audio/mpeg',
							'audio/mpg',
							'audio/x-wav',
							'audio/mp3'
							);
		$fileExts = array(
						"application/pdf",
						"application/msword",
						"application/vnd.ms-excel",
						"application/vnd.ms-powerpoint",
						"text/plain",
						"image/jpeg",
						"image/vnd.adobe.photoshop",
						"image/gif",
						"image/png",
						"application/vnd.openxmlformats-officedocument.wordprocessingml.document",
						"application/vnd.openxmlformats-officedocument.presentationml.presentation",
						"application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
						"application/vnd.ms-powerpoint.slideshow.macroenabled.12",
						"application/vnd.openxmlformats-officedocument.presentationml.slideshow",
						"application/zip",
						"application/x-rar-compressed",
						"application/x-gzip",
						"audio/x-mpeg",
						"application/vnd.americandynamics.acc",
						"audio/mpeg",
						"audio/mpg",
						"audio/x-wav",
						"audio/mp4",
						"video/mp4",
						"application/mp4",
						"audio/x-wav",
						"audio/x-ms-wma",
						"audio/x-wav",
						"audio/x-aiff",
						"application/ogg",
						"audio/ogg",
						"video/ogg",
						"video/x-flv",
						"video/x-f4v",
						"video/quicktime",
						"video/msvideo",
						"video/divx",
						"video/x-divx",
						"application/gpx+xml"
						);
							
		if($file_type == "image"){
			if(in_array($mime,$imagesExts)){
				return true;
			}
		}elseif($file_type == "audio"){
			if(in_array($mime,$audioExts)){
				return true;
			}
		}else{
			if(in_array($mime,$fileExts)){
				return true;
			}
		}
		return false;
	}
}

/* Loading modules */

add_action( 'plugins_loaded', 'mf_load_modules', 1 );

if ( !function_exists('mf_load_modules') ) {
function mf_load_modules() {
        $dir = WP_PLUGIN_DIR."/".MF_PLUGIN_DIR."/modules";

        if ( ! ( is_dir( $dir ) && $dh = opendir( $dir ) ) )
                return false;

        while ( ( $module = readdir( $dh ) ) !== false ) {
                if ( substr( $module, -4 ) == '.php' ) {
                        include_once $dir . '/' . $module;
                }
        }
}
}

/* add filter for upload attachment image (new field image)*/
/* load_link_media_upload in custom_fields/media_image.js */
add_filter('attachment_fields_to_edit', 'charge_link_after_upload_image', 10, 2);

if ( !function_exists('charge_link_after_upload_image') ) {
function charge_link_after_upload_image($fields){
	$wp_version = floatval(get_bloginfo('version'));

    if(
        $wp_version < 3.5 ||
        (( isset($_REQUEST['fetch']) && $_REQUEST['fetch'] ) ||
        ( isset($_REQUEST['tab']) && ($_REQUEST['tab'] == 'library' || $_REQUEST['tab'] == 'gallery') ))
      ) {
      	$nonce_ajax_get_image_media_info = wp_create_nonce('nonce_ajax_get_image_media_info');
   		printf("
      		<script type=\"text/javascript\">
      		//<![CDATA[
        	load_link_in_media_upload();
        	var nonce_ajax_get_image_media_info = \"%s\";
      		//]]>
      		</script>",$nonce_ajax_get_image_media_info);
		}
      return $fields;
}
}

/* Function for manage page (write panels) */
require_once('MF_ManageWritePanels.php');


/** Wordpress 3.0 and beyond**/
/*
if( is_wp30() ){
	///
	// Post Type Panels
	//
	require_once('MF_PostTypesPage.php'); 
	add_action('admin_menu',array('MF_PostTypePages','TopMenu'));

	//CSS/
	add_action('admin_init','mf_css');

	function mf_css(){
		wp_enqueue_style('mf_base',MF_URI.'css/base.css',false,'1.5','all');
	}
	 //CSS//
}
*/
