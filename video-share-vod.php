<?php
/*
Plugin Name: Video Share VOD
Plugin URI: http://www.videosharevod.com
Description: <strong>Video Share / Video on Demand (VOD)</strong> plugin allows WordPress users to share videos and others to watch on demand. Allows publishing VideoWhisper Live Streaming broadcasts.
Version: 1.1.9
Author: VideoWhisper.com
Author URI: http://www.videowhisper.com/
Contributors: videowhisper, VideoWhisper.com
*/

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if (!class_exists("VWvideoShare"))
{
	class VWvideoShare {

		function VWvideoShare() { //constructor

		}

		static function install() {
			// do not generate any output here
			VWvideoShare::setupOptions();
			flush_rewrite_rules();
		}

		// Register Custom Post Type
		function video_post() {

			//only if missing
			if (post_type_exists('video')) return;

			$labels = array(
				'name'                => _x( 'Videos', 'Post Type General Name', 'text_domain' ),
				'singular_name'       => _x( 'Video', 'Post Type Singular Name', 'text_domain' ),
				'menu_name'           => __( 'Videos', 'text_domain' ),
				'parent_item_colon'   => __( 'Parent Video:', 'text_domain' ),
				'all_items'           => __( 'All Videos', 'text_domain' ),
				'view_item'           => __( 'View Video', 'text_domain' ),
				'add_new_item'        => __( 'Add New Video', 'text_domain' ),
				'add_new'             => __( 'New Video', 'text_domain' ),
				'edit_item'           => __( 'Edit Video', 'text_domain' ),
				'update_item'         => __( 'Update Video', 'text_domain' ),
				'search_items'        => __( 'Search Videos', 'text_domain' ),
				'not_found'           => __( 'No Videos found', 'text_domain' ),
				'not_found_in_trash'  => __( 'No Videos found in Trash', 'text_domain' ),
			);

			$args = array(
				'label'               => __( 'video', 'text_domain' ),
				'description'         => __( 'Video Videos', 'text_domain' ),
				'labels'              => $labels,
				'supports'            => array( 'title', 'editor', 'author', 'thumbnail', 'comments', 'custom-fields', 'page-attributes', ),
				'taxonomies'          => array( 'category', 'post_tag' ),
				'hierarchical'        => false,
				'public'              => true,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'show_in_nav_menus'   => true,
				'show_in_admin_bar'   => true,
				'menu_position'       => 5,
				'can_export'          => true,
				'has_playlist'         => true,
				'exclude_from_search' => false,
				'publicly_queryable'  => true,
				'capability_type'     => 'post',
			);

			register_post_type( 'video', $args );

			// Add new taxonomy, make it hierarchical (like categories)
			$labels = array(
				'name'              => _x( 'Playlists', 'taxonomy general name' ),
				'singular_name'     => _x( 'Playlist', 'taxonomy singular name' ),
				'search_items'      => __( 'Search Playlists' ),
				'all_items'         => __( 'All Playlists' ),
				'parent_item'       => __( 'Parent Playlist' ),
				'parent_item_colon' => __( 'Parent Playlist:' ),
				'edit_item'         => __( 'Edit Playlist' ),
				'update_item'       => __( 'Update Playlist' ),
				'add_new_item'      => __( 'Add New Playlist' ),
				'new_item_name'     => __( 'New Playlist Name' ),
				'menu_name'         => __( 'Playlists' ),
			);

			$args = array(
				'hierarchical'      => true,
				'labels'            => $labels,
				'show_ui'           => true,
				'show_admin_column' => true,
				'update_count_callback' => '_update_post_term_count',
				'query_var'         => true,
				'rewrite'           => array( 'slug' => 'playlist' ),
			);

			register_taxonomy( 'playlist', array( 'video' ), $args );

			flush_rewrite_rules();
		}

		function video_delete($video_id)
		{
			if (get_post_type( $video_id ) != 'video') return;

			//delete source video
			$videoPath = get_post_meta($post_id, 'video-source-file', true);
			if (file_exists($videoPath)) unlink($videoPath);

			//delete all generated video files
			$videoAdaptive = get_post_meta($video_id, 'video-adaptive', true);
			if ($videoAdaptive) $videoAlts = $videoAdaptive;
			else $videoAlts = array();

			foreach ($videoAlts as $alt)
			{
				if (file_exists($alt['file'])) unlink($alt['file']);
			}
		}

		function adminMenu()
		{
			$options = get_option('VWvideoShareOptions');

			add_menu_page('Video Share VOD', 'Video Share VOD', 'manage_options', 'video-share', array('VWvideoShare', 'adminOptions'), '',81);
			add_submenu_page("video-share", "Video Share VOD", "Options", 'manage_options', "video-share", array('VWvideoShare', 'adminOptions'));
			add_submenu_page("video-share", "Upload", "Upload", 'manage_options', "video-share-upload", array('VWvideoShare', 'adminUpload'));
			add_submenu_page("video-share", "Import", "Import", 'manage_options', "video-share-import", array('VWvideoShare', 'adminImport'));

			if (class_exists("VWliveStreaming")) add_submenu_page('video-share', 'Live Streaming', 'Live Streaming', 'manage_options', 'video-share-ls', array('VWvideoShare', 'adminLiveStreaming'));
			add_submenu_page("video-share", "Documentation", "Documentation", 'manage_options', "video-share-docs", array('VWvideoShare', 'adminDocs'));
		}

		/*
		function updatePages()
		{
			$options = get_option('VWvideoShareOptions');

			//if not disabled create
			if ($options['disablePages']=='0')
			{
				global $user_ID;
				$page = array();
				$page['post_type']    = 'page';
				$page['post_content'] = '[videowhisper_video_import]';
				$page['post_parent']  = 0;
				$page['post_author']  = $user_ID;
				$page['post_status']  = 'publish';
				$page['post_title']   = 'Import Videos';
				$page['comment_status'] = 'closed';

				$page_id = get_option( "vwvs_page_import" );
				if ($page_id>0) $page['ID'] = $page_id;

				$pageid = wp_insert_post ($page);
				update_option( "vwvs_page_import", $pageid);
			}

		}

		function deletePages()
		{
			$options = get_option( 'VWvideoShareOptions' );

			if ($options['disablePage'])
			{
				$page_id = get_option( "vwvs_page_import" );
				if ($page_id > 0)
				{
					wp_delete_post($page_id);
					update_option( "vwvs_page_import", -1);
				}
			}

		}

*/


		function init()
		{
			$options = get_option('VWvideoShareOptions');


			add_action( 'wp_enqueue_scripts', array('VWvideoShare','scripts') );

			/* Fire our meta box setup function on the post editor screen. */
			add_action( 'load-post.php', array('VWvideoShare', 'post_meta_boxes_setup' ) );
			add_action( 'load-post-new.php', array( 'VWvideoShare', 'post_meta_boxes_setup' ) );

			//listings
			add_filter('pre_get_posts', array('VWvideoShare','pre_get_posts'));


			add_filter('manage_video_posts_columns', array( 'VWvideoShare', 'columns_head_video') , 10);
			add_filter( 'manage_edit-video_sortable_columns', array('VWvideoShare', 'columns_register_sortable') );
			add_filter( 'request', array('VWvideoShare', 'duration_column_orderby') );
			add_action('manage_video_posts_custom_column', array( 'VWvideoShare', 'columns_content_video') , 10, 2);
			add_filter( 'parse_query', array( 'VWvideoShare', 'post_edit_screen') );

			add_action( 'before_delete_post',  array( 'VWvideoShare','video_delete') );

			//video post page
			add_filter( "the_content", array('VWvideoShare','video_page'));

			if (class_exists("VWliveStreaming"))  if ($options['vwls_channel']) add_filter( "the_content", array('VWvideoShare','channel_page'));

				//shortcodes
				add_shortcode('videowhisper_player', array( 'VWvideoShare', 'shortcode_player'));
			add_shortcode('videowhisper_videos', array( 'VWvideoShare', 'shortcode_videos'));
			add_shortcode('videowhisper_upload', array( 'VWvideoShare', 'shortcode_upload'));
			add_shortcode('videowhisper_preview', array( 'VWvideoShare', 'shortcode_preview'));
			add_shortcode('videowhisper_player_html', array( 'VWvideoShare', 'shortcode_player_html'));
			add_shortcode('videowhisper_import', array( 'VWvideoShare', 'shortcode_import'));

			//widget
			wp_register_sidebar_widget( 'videowhisper_videos', 'Videos',  array( 'VWvideoShare', 'widget_videos'), array('description' => 'List videos and updates using AJAX.') );
			wp_register_widget_control( 'videowhisper_videos', 'videowhisper_videos', array( 'VWvideoShare', 'widget_videos_options') );

			//ajax videos
			add_action( 'wp_ajax_vwvs_videos', array('VWvideoShare','vwvs_videos'));
			add_action( 'wp_ajax_nopriv_vwvs_videos', array('VWvideoShare','vwvs_videos'));

			//upload videos
			add_action( 'wp_ajax_vwvs_upload', array('VWvideoShare','vwvs_upload'));

			//Live Streaming support
			if (class_exists("VWliveStreaming")) if ($options['vwls_playlist'])
				{
					add_filter('vw_ls_manage_channel', array('VWvideoShare', 'vw_ls_manage_channel' ), 10, 2);
					add_filter('vw_ls_manage_channels_head', array('VWvideoShare', 'vw_ls_manage_channels_head' ));
				}

			//check db and update if necessary
			/*
			$vw_db_version = "0.0";

			$installed_ver = get_option( "vwvs_db_version" );
			if( $installed_ver != $vw_db_version )
			{
				$tab_formats = $wpdb->prefix . "vwvs_formats";
				$tab_process = $wpdb->prefix . "vwvs_process";

				global $wpdb;
				$wpdb->flush();
				$sql = "";

				require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
				dbDelta($sql);
				if (!$installed_ver) add_option("vwvs_db_version", $vw_db_version);
				else update_option( "vwvs_db_version", $vw_db_version );
			}
			*/


		}

		function widgetSetupOptions()
		{
			$widgetOptions = array(
				'title' => '',
				'perpage'=> '6',
				'perrow' => '',
				'playlist' => '',
				'order_by' => '',
				'category_id' => '',
				'select_category' => '1',
				'select_order' => '1',
				'select_page' => '1',
				'include_css' => '0'

			);

			$options = get_option('VWvideoShareWidgetOptions');

			if (!empty($options)) {
				foreach ($options as $key => $option)
					$widgetOptions[$key] = $option;
			}

			update_option('VWvideoShareWidgetOptions', $widgetOptions);

			return $widgetOptions;
		}

		function widget_videos_options($args=array(), $params=array())
		{

			$options = VWvideoShare::widgetSetupOptions();

			if (isset($_POST))
			{
				foreach ($options as $key => $value)
					if (isset($_POST[$key])) $options[$key] = trim($_POST[$key]);
					update_option('VWvideoShareWidgetOptions', $options);
			}
?>

	Title:<br />
	<input type="text" class="widefat" name="title" value="<?php echo stripslashes($options['title']); ?>" />
	<br /><br />

	Playlist:<br />
	<input type="text" class="widefat" name="playlist" value="<?php echo stripslashes($options['playlist']); ?>" />
	<br /><br />

	Category ID:<br />
	<input type="text" class="widefat" name="category_id" value="<?php echo stripslashes($options['category_id']); ?>" />
	<br /><br />

 Order By:<br />
	<select name="order_by" id="order_by">
  <option value="post_date" <?php echo $options['order_by']=='post_date'?"selected":""?>>Video Date</option>
    <option value="video-views" <?php echo $options['order_by']=='video-views'?"selected":""?>>Views</option>
    <option value="video-lastview" <?php echo $options['order_by']=='video-lastview'?"selected":""?>>Recently Watched</option>
</select><br /><br />

	Videos per Page:<br />
	<input type="text" class="widefat" name="perpage" value="<?php echo stripslashes($options['perpage']); ?>" />
	<br /><br />

	Videos per Row:<br />
	<input type="text" class="widefat" name="perrow" value="<?php echo stripslashes($options['perrow']); ?>" />
	<br /><br />

 Category Selector:<br />
	<select name="select_category" id="select_category">
  <option value="1" <?php echo $options['select_category']?"selected":""?>>Yes</option>
  <option value="0" <?php echo $options['select_category']?"":"selected"?>>No</option>
</select><br /><br />

 Order Selector:<br />
	<select name="select_order" id="select_order">
  <option value="1" <?php echo $options['select_order']?"selected":""?>>Yes</option>
  <option value="0" <?php echo $options['select_order']?"":"selected"?>>No</option>
</select><br /><br />

	Page Selector:<br />
	<select name="select_page" id="select_page">
  <option value="1" <?php echo $options['select_page']?"selected":""?>>Yes</option>
  <option value="0" <?php echo $options['select_page']?"":"selected"?>>No</option>
</select><br /><br />

	Include CSS:<br />
	<select name="include_css" id="include_css">
  <option value="1" <?php echo $options['include_css']?"selected":""?>>Yes</option>
  <option value="0" <?php echo $options['include_css']?"":"selected"?>>No</option>
</select><br /><br />
	<?php
		}

		function widget_videos($args=array(), $params=array())
		{

			$options = get_option('VWvideoShareWidgetOptions');

			echo stripslashes($args['before_widget']);

			echo stripslashes($args['before_title']);
			echo stripslashes($options['title']);
			echo stripslashes($args['after_title']);

			echo do_shortcode('[videowhisper_videos playlist="' . $options['playlist'] . '" category_id="' . $options['category_id'] . '" order_by="' . $options['order_by'] . '" perpage="' . $options['perpage'] . '" perrow="' . $options['perrow'] . '" select_category="' . $options['select_category'] . '" select_order="' . $options['select_order'] . '" select_page="' . $options['select_page'] . '" include_css="' . $options['include_css'] . '"]');

			echo stripslashes($args['after_widget']);
		}

		function pre_get_posts($query)
		{

			//add channels to post listings
			if(is_category() || is_tag() || is_archive())
			{

				$query_type = get_query_var('post_type');
				
				if (!is_array($query_type)) 
				{
					$query->set('post_type', $query_type);
					return $query;

				}

				if ($query_type)
				{
					//if (!is_array($query_type)) $query_type = array($query_type);
				
					if (in_array('post', $query_type) && !in_array('video', $query_type))
					$query_type[] = 'video';
				}
				else  //default
				{
					$query_type = array('post', 'video');
				}

				$query->set('post_type', $query_type);
			}

			return $query;
		}


		function scripts()
		{
			wp_enqueue_script("jquery");

		}

		function shortcode_videos($atts)
		{

			$options = get_option('VWvideoShareOptions');

			$atts = shortcode_atts(
				array(
					'perpage'=> $options['perPage'],
					'perrow' => '',
					'playlist' => '',
					'order_by' => '',
					'category_id' => '',
					'select_category' => '1',
					'select_order' => '1',
					'select_page' => '1',
					'include_css' => '1',
					'id' => ''
				),
				$atts, 'videowhisper_videos');


			$id = $atts['id'];
			if (!$id) $id = uniqid();

			$ajaxurl = admin_url() . 'admin-ajax.php?action=vwvs_videos&pp=' . $atts['perpage'] . '&pr=' . $atts['perrow'] . '&playlist=' . urlencode($atts['playlist']) . '&ob=' . $atts['order_by'] . '&cat=' . $atts['category_id'] . '&sc=' . $atts['select_category'] . '&so=' . $atts['select_order'] . '&sp=' . $atts['select_page']. '&id=' .$id;

			$htmlCode = <<<HTMLCODE
<script type="text/javascript">
var aurl$id = '$ajaxurl';
var \$j = jQuery.noConflict();

	function loadVideos$id(message){

	if (message)
	if (message.length > 0)
	{
	  \$j("#videowhisperVideos$id").html(message);
	}

		\$j.ajax({
			url: aurl$id,
			success: function(data) {
				\$j("#videowhisperVideos$id").html(data);
			}
		});
	}


	\$j(function(){
		loadVideos$id();
		setInterval("loadVideos$id('')", 60000);
	});

</script>

<div id="videowhisperVideos$id">
    Loading Videos...
</div>

HTMLCODE;

			if ($atts['include_css']) $htmlCode .= html_entity_decode(stripslashes($options['customCSS']));

			return $htmlCode;
		}

		function vwvs_videos()
		{
			$options = get_option('VWvideoShareOptions');

			$perPage = (int) $_GET['pp'];
			if (!$perPage) $perPage = $options['perPage'];

			$playlist = sanitize_file_name($_GET['playlist']);

			$id = sanitize_file_name($_GET['id']);

			$category = (int) $_GET['cat'];

			$page = (int) $_GET['p'];
			$offset = $page * $perPage;

			$perRow = (int) $_GET['pr'];

			$order_by = sanitize_file_name($_GET['ob']);
			if (!$order_by) $order_by = 'post_date';

			//options
			$selectCategory = (int) $_GET['sc'];
			$selectOrder = (int) $_GET['so'];
			$selectPage = (int) $_GET['sp'];

			//query
			$args=array(
				'post_type' => 'video',
				'post_status' => 'publish',
				'posts_per_page' => $perPage,
				'offset'           => $offset,
				'order'            => 'DESC',
			);

			if ($order_by != 'post_date')
			{
				$args['orderby'] = 'meta_value_num';
				$args['meta_key'] = $order_by;
			}
			else
			{
				$args['orderby'] = 'post_date';
			}

			if ($playlist)  $args['playlist'] = $playlist;
			if ($category)  $args['category'] = $category;

			$postslist = get_posts( $args );

			ob_clean();
			//output

			//var_dump ($args);
			//echo $order_by;
			$ajaxurl = admin_url() . 'admin-ajax.php?action=vwvs_videos&pp=' . $perPage .  '&pr=' .$perRow. '&playlist=' . urlencode($playlist) . '&sc=' . $selectCategory . '&so=' . $selectOrder . '&sp=' . $selectPage .  '&id=' . $id;

			$ajaxurlP = $ajaxurl . '&p='.$page;
			$ajaxurlPC = $ajaxurlP . '&cat=' . $category ;
			$ajaxurlPO = $ajaxurlP . '&ob='. $order_by;

			echo '<div class="videowhisperListOptions">';

			if ($selectCategory)
			{
				echo '<div class="videowhisperDropdown">' . wp_dropdown_categories('show_count=1&echo=0&name=category' . $id . '&hide_empty=1&class=videowhisperSelect&show_option_all=All&selected=' . $category).'</div>';
				echo '<script>var category' . $id . ' = document.getElementById("category' . $id . '"); 			category' . $id . '.onchange = function(){aurl' . $id . '=\'' . $ajaxurlPO.'&cat=\'+ this.value; loadVideos' . $id . '(\'Loading category...\')}
			</script>';
			}

			if ($selectOrder)
			{
				echo '<div class="videowhisperDropdown"><select class="videowhisperSelect" id="order_by' . $id . '" name="order_by' . $id . '" onchange="aurl' . $id . '=\'' . $ajaxurlPC.'&ob=\'+ this.value; loadVideos' . $id . '(\'Ordering videos...\')">';
				echo '<option value="">Order By:</option>';
				echo '<option value="post_date"' . ($order_by == 'post_date'?' selected':'') . '>Video Date</option>';
				echo '<option value="video-views"' . ($order_by == 'video-views'?' selected':'') . '>Views</option>';
				echo '<option value="video-lastview"' . ($order_by == 'video-lastview'?' selected':'') . '>Watched Recently</option>';
				echo '</select></div>';
			}
			echo '</div>';


			$ajaxurlCO = $ajaxurlP . '&cat=' . $category . '&ob='.$order_by ;

			if (count($postslist)>0)
			{
				$k = 0;
				foreach ( $postslist as $item )
				{
					if ($perRow) if ($k) if ($k % $perRow == 0) echo '<br>';

							$videoDuration = get_post_meta($item->ID, 'video-duration', true);
						$imagePath = get_post_meta($item->ID, 'video-thumbnail', true);

					$views = get_post_meta($item->ID, 'video-views', true) ;
					if (!$views) $views = 0;

					$duration = VWvideoShare::humanDuration($videoDuration);
					$age = VWvideoShare::humanAge(time() - strtotime($item->post_date));

					$info = 'Title: ' . $item->post_title . "\r\nDuration: " . $duration . "\r\nAge: " . $age . "\r\nViews: " . $views;
					$views .= ' views';

					echo '<div class="videowhisperVideo">';
					echo '<a href="' . get_permalink($item->ID) . '" title="' . $info . '"><div class="videowhisperTitle">' . $item->post_title. '</div></a>';
					echo '<div class="videowhisperTime">' . $duration . '</div>';
					echo '<div class="videowhisperDate">' . $age . '</div>';
					echo '<div class="videowhisperViews">' . $views . '</div>';

					if (!$imagePath || !file_exists($imagePath)) //video thumbnail?
						{
						$imagePath = plugin_dir_path( __FILE__ ) . 'no_video.png';
						VWvideoShare::updatePostThumbnail($item->ID);
					}
					else //what about featured image?
						{
						$post_thumbnail_id = get_post_thumbnail_id($item->ID);
						if ($post_thumbnail_id) $post_featured_image = wp_get_attachment_image_src($post_thumbnail_id, 'featured_preview') ;

						if (!$post_featured_image) VWvideoShare::updatePostThumbnail($item->ID);
					}



					echo '<a href="' . get_permalink($item->ID) . '" title="' . $info . '"><IMG src="' . VWvideoShare::path2url($imagePath) . $noCache .'" width="' . $options['thumbWidth'] . 'px" height="' . $options['thumbHeight'] . 'px" ALT="' . $info . '"></a>';

					echo '</div>
					';

					$k++;
				}

			} else echo "No videos.";

			if ($selectPage)
			{
				echo "<BR>";
				if ($page>0) echo ' <a class="videowhisperButton button g-btn type_secondary mk-button dark-color  mk-shortcode two-dimension" href="JavaScript: void()" onclick="aurl' . $id . '=\'' . $ajaxurlCO.'&p='.($page-1). '\'; loadVideos' . $id . '(\'Loading previous page...\');">Previous</a> ';
				echo '<a class="videowhisperButton button g-btn type_secondary mk-button dark-color  mk-shortcode two-dimension" href="#"> Page ' . ($page+1) . ' </a>' ;
				if (count($postslist) >= $perPage) echo ' <a class="videowhisperButton button g-btn type_secondary mk-button dark-color  mk-shortcode two-dimension" href="JavaScript: void()" onclick="aurl' . $id . '=\'' . $ajaxurlCO.'&p='.($page+1). '\'; loadVideos' . $id . '(\'Loading next page...\');">Next</a> ';
			}
			//output end
			die;

		}

		function shortcode_import($atts)
		{
			global $current_user;

			get_currentuserinfo();

			if (!is_user_logged_in())
			{
				return 'videowhisper_import: Login required!';
			}

			$options = get_option('VWvideoShareOptions');

			$atts = shortcode_atts(array('category' => '', 'playlist' => '', 'owner' => '', 'path' => '', 'prefix' => '', 'tag' => '', 'description' => ''), $atts, 'videowhisper_import');

			if (!$atts['path']) return 'videowhisper_import: Path required!';

			if (!file_exists($atts['path'])) return 'videowhisper_import: Path not found!';

			if ($atts['category']) $categories = '<input type="hidden" name="category" id="category" value="'.$atts['category'].'"/>';
			else $categories = '<label for="category">Category: </label><div class="videowhisperDropdown">' . wp_dropdown_categories('show_count=1&echo=0&name=category&hide_empty=0&class=videowhisperSelect').'</div>';

			if ($atts['playlist']) $playlists = '<br><label for="playlist">Playlist: </label>' .$atts['playlist'] . '<input type="hidden" name="playlist" id="playlist" value="'.$atts['playlist'].'"/>';
			elseif ( current_user_can('edit_posts') ) $playlists = '<br><label for="playlist">Playlist(s): </label> <br> <input size="48" maxlength="64" type="text" name="playlist" id="playlist" value="' . $current_user->display_name .'"/> (comma separated)';
			else $playlists = '<br><label for="playlist">Playlist: </label> ' . $current_user->display_name .' <input type="hidden" name="playlist" id="playlist" value="' . $current_user->display_name .'"/> ';

			if ($atts['owner']) $owners = '<input type="hidden" name="owner" id="owner" value="'.$atts['owner'].'"/>';
			else
				$owners = '<input type="hidden" name="owner" id="owner" value="'.$current_user->ID.'"/>';

			if ($atts['tag'] != '_none' )
				if ($atts['tag']) $tags = '<br><label for="playlist">Tags: </label>' .$atts['tag'] . '<input type="hidden" name="tag" id="tag" value="'.$atts['tag'].'"/>';
				else $tags = '<br><label for="tag">Tag(s): </label> <br> <input size="48" maxlength="64" type="text" name="tag" id="tag" value=""/> (comma separated)';

				if ($atts['description'] != '_none' )
					if ($atts['description']) $descriptions = '<br><label for="description">Description: </label>' .$atts['description'] . '<input type="hidden" name="description" id="description" value="'.$atts['description'].'"/>';
					else $descriptions = '<br><label for="description">Description: </label> <br> <input size="48" maxlength="256" type="text" name="description" id="description" value=""/>';


					$url  =  get_permalink();

				$htmlCode .= '<h3>Import Videos</h3>' . $atts['path'] . $atts['prefix'];

			$htmlCode .=  '<form action="' . $url . '" method="post">';

			$htmlCode .= $categories;
			$htmlCode .= $playlists;
			$htmlCode .= $tags;
			$htmlCode .= $descriptions;
			$htmlCode .= $owners;

			$htmlCode .= '<br>' . VWvideoShare::importFilesSelect( $atts['prefix'], array('flv', 'mp4', 'f4v'), $atts['path']);

			$htmlCode .= '<INPUT class="button button-primary" TYPE="submit" name="import" id="import" value="Import">';

			$htmlCode .= ' <INPUT class="button button-primary" TYPE="submit" name="delete" id="delete" value="Delete">';

			$htmlCode .= '</form>';

			$htmlCode .= html_entity_decode(stripslashes($options['customCSS']));

			return $htmlCode;
		}

		function shortcode_upload($atts)
		{

			global $current_user;

			get_currentuserinfo();

			if (!is_user_logged_in())
			{
				return 'Login required!';
			}

			$options = get_option('VWvideoShareOptions');

			$atts = shortcode_atts(array('category' => '', 'playlist' => '', 'owner' => '', 'tag' => '', 'description' => ''), $atts, 'videowhisper_upload');

			$ajaxurl = admin_url() . 'admin-ajax.php?action=vwvs_upload';

			if ($atts['category']) $categories = '<input type="hidden" name="category" id="category" value="'.$atts['category'].'"/>';
			else $categories = '<label for="category">Category: </label><div class="videowhisperDropdown">' . wp_dropdown_categories('show_count=1&echo=0&name=category&hide_empty=0&class=videowhisperSelect').'</div>';

			if ($atts['playlist']) $playlists = '<label for="playlist">Playlist: </label>' .$atts['playlist'] . '<input type="hidden" name="playlist" id="playlist" value="'.$atts['playlist'].'"/>';
			elseif ( current_user_can('edit_users') ) $playlists = '<br><label for="playlist">Playlist(s): </label> <br> <input size="48" maxlength="64" type="text" name="playlist" id="playlist" value="' . $current_user->display_name .'" class="text-input"/> (comma separated)';
			else $playlists = '<label for="playlist">Playlist: </label> ' . $current_user->display_name .' <input type="hidden" name="playlist" id="playlist" value="' . $current_user->display_name .'"/> ';

			if ($atts['owner']) $owners = '<input type="hidden" name="owner" id="owner" value="'.$atts['owner'].'"/>';
			else $owners = '<input type="hidden" name="owner" id="owner" value="'.$current_user->ID.'"/>';

			if ($atts['tag'] != '_none' )
				if ($atts['tag']) $tags = '<br><label for="playlist">Tags: </label>' .$atts['tag'] . '<input type="hidden" name="tag" id="tag" value="'.$atts['tag'].'"/>';
				else $tags = '<br><label for="tag">Tag(s): </label> <br> <input size="48" maxlength="64" type="text" name="tag" id="tag" value="" class="text-input"/> (comma separated)';

				if ($atts['description'] != '_none' )
					if ($atts['description']) $descriptions = '<br><label for="description">Description: </label>' .$atts['description'] . '<input type="hidden" name="description" id="description" value="'.$atts['description'].'"/>';
					else $descriptions = '<br><label for="description">Description: </label> <br> <input size="48" maxlength="256" type="text" name="description" id="description" value="" class="text-input"/>';



					$iPod    = stripos($_SERVER['HTTP_USER_AGENT'],"iPod");
				$iPhone  = stripos($_SERVER['HTTP_USER_AGENT'],"iPhone");
			$iPad    = stripos($_SERVER['HTTP_USER_AGENT'],"iPad");
			$Android = stripos($_SERVER['HTTP_USER_AGENT'],"Android");

			if ($iPhone || $iPad || $iPod || $Android) $mobile = true; else $mobile = false;

			if ($mobile)
			{
				$mobiles = 'capture="camcorder"';
				$accepts = 'accept="video/*;capture=camcorder"';
				$multiples = '';
				$filedrags = '';
			}
			else
			{
				$mobiles = '';
				$accepts = 'accept="video/*"';
				$multiples = 'multiple="multiple"';
				$filedrags = '<div id="filedrag">or Drag & Drop files to this upload area<br>(select rest of options first)</div>';
			}

			wp_enqueue_script( 'vwvs-upload', plugin_dir_url(  __FILE__ ) . '/upload.js');


			$htmlCode .= <<<EOHTML
<form id="upload" action="$ajaxurl" method="POST" enctype="multipart/form-data">

<fieldset>
$categories
$playlists
$tags
$descriptions
$owners

<legend><h3>Video Upload</h3></legend>
<input type="hidden" id="MAX_FILE_SIZE" name="MAX_FILE_SIZE" value="128000000" />

<div>
	<label for="fileselect">Videos to upload: </label>
	<br><input class="videowhisperButton g-btn type_midnight small" type="file" id="fileselect" name="fileselect[]" $mobiles $multiples $accepts />

$filedrags

<div id="submitbutton">
	<button class="videowhisperButton g-btn type_green small" type="submit" name="upload" id="upload">Upload Files</button>
</div>

<div id="progress"></div>

</fieldset>
</form>

<STYLE>

#filedrag
{
 height: 100px;
 border: 1px solid #AAA;
 border-radius: 9px;
 color: #AAA;
 background: #243;
 padding: 4px;
 margin: 4px;
 text-align:center;
}

#progress
{
padding: 4px;
margin: 4px;
}

#progress div {
	position: relative;
	background: #555;
	-moz-border-radius: 9px;
	-webkit-border-radius: 9px;
	border-radius: 9px;

	padding: 4px;
	margin: 4px;

	color: #DDD;

}

#progress div > span {
	display: block;
	height: 20px;

	   -webkit-border-top-right-radius: 4px;
	-webkit-border-bottom-right-radius: 4px;
	       -moz-border-radius-topright: 4px;
	    -moz-border-radius-bottomright: 4px;
	           border-top-right-radius: 4px;
	        border-bottom-right-radius: 4px;
	    -webkit-border-top-left-radius: 4px;
	 -webkit-border-bottom-left-radius: 4px;
	        -moz-border-radius-topleft: 4px;
	     -moz-border-radius-bottomleft: 4px;
	            border-top-left-radius: 4px;
	         border-bottom-left-radius: 4px;

	background-color: rgb(43,194,83);

	background-image:
	   -webkit-gradient(linear, 0 0, 100% 100%,
	      color-stop(.25, rgba(255, 255, 255, .2)),
	      color-stop(.25, transparent), color-stop(.5, transparent),
	      color-stop(.5, rgba(255, 255, 255, .2)),
	      color-stop(.75, rgba(255, 255, 255, .2)),
	      color-stop(.75, transparent), to(transparent)
	   );

	background-image:
		-webkit-linear-gradient(
		  -45deg,
	      rgba(255, 255, 255, .2) 25%,
	      transparent 25%,
	      transparent 50%,
	      rgba(255, 255, 255, .2) 50%,
	      rgba(255, 255, 255, .2) 75%,
	      transparent 75%,
	      transparent
	   );

	background-image:
		-moz-linear-gradient(
		  -45deg,
	      rgba(255, 255, 255, .2) 25%,
	      transparent 25%,
	      transparent 50%,
	      rgba(255, 255, 255, .2) 50%,
	      rgba(255, 255, 255, .2) 75%,
	      transparent 75%,
	      transparent
	   );

	background-image:
		-ms-linear-gradient(
		  -45deg,
	      rgba(255, 255, 255, .2) 25%,
	      transparent 25%,
	      transparent 50%,
	      rgba(255, 255, 255, .2) 50%,
	      rgba(255, 255, 255, .2) 75%,
	      transparent 75%,
	      transparent
	   );

	background-image:
		-o-linear-gradient(
		  -45deg,
	      rgba(255, 255, 255, .2) 25%,
	      transparent 25%,
	      transparent 50%,
	      rgba(255, 255, 255, .2) 50%,
	      rgba(255, 255, 255, .2) 75%,
	      transparent 75%,
	      transparent
	   );

	position: relative;
	overflow: hidden;
}

#progress div.success
{
    color: #DDD;
	background: #3C6243 none 0 0 no-repeat;
}

#progress div.failed
{
 	color: #DDD;
	background: #682C38 none 0 0 no-repeat;
}
</STYLE>
EOHTML;

			$htmlCode .= html_entity_decode(stripslashes($options['customCSS']));

			return $htmlCode;

		}

		function vwvs_upload()
		{

			global $current_user;
			get_currentuserinfo();

			if (!is_user_logged_in())
			{
				echo 'Login required!';
				exit;
			}

			$owner = $_SERVER['HTTP_X_OWNER'] ? intval($_SERVER['HTTP_X_OWNER']) : intval($_POST['owner']);

			if ($owner && ! current_user_can('edit_users') && $owner != $current_user->ID )
			{
				echo 'Only admin can upload for others!';
				exit;
			}
			if (!$owner) $owner = $current_user->ID;


			$playlist = $_SERVER['HTTP_X_PLAYLIST'] ? $_SERVER['HTTP_X_PLAYLIST'] :$_POST['playlist'];

			//if csv sanitize as array
			if (strpos($playlist, ',') !== FALSE)
			{
				$playlists = explode(',', $playlist);
				foreach ($playlists as $key => $value) $playlists[$key] = sanitize_file_name(trim($value));
				$playlist = $playlists;
			}

			if (!$playlist)
			{
				echo 'Playlist required!';
				exit;
			}

			$category = $_SERVER['HTTP_X_CATEGORY'] ? sanitize_file_name($_SERVER['HTTP_X_CATEGORY']) : sanitize_file_name($_POST['category']);


			$tag = $_SERVER['HTTP_X_TAG'] ? $_SERVER['HTTP_X_TAG'] :$_POST['tag'];

			//if csv sanitize as array
			if (strpos($tag, ',') !== FALSE)
			{
				$tags = explode(',', $tag);
				foreach ($tags as $key => $value) $tags[$key] = sanitize_file_name(trim($value));
				$tag = $tags;
			}


			$description = sanitize_text_field( $_SERVER['HTTP_X_DESCRIPTION'] ? $_SERVER['HTTP_X_DESCRIPTION'] :$_POST['description'] );

			$options = get_option( 'VWvideoShareOptions' );

			$dir = $options['uploadsPath'];
			if (!file_exists($dir)) mkdir($dir);

			$dir .= '/uploads';
			if (!file_exists($dir)) mkdir($dir);

			$dir .= '/';


			ob_clean();
			$fn = (isset($_SERVER['HTTP_X_FILENAME']) ? $_SERVER['HTTP_X_FILENAME'] : false);

			function generateName($fn)
			{
				$ext = strtolower(pathinfo($fn, PATHINFO_EXTENSION));

				if (!in_array($ext, array('3gp', 'avi', 'f4v', 'flv', 'mp4', 'mpg', 'mpeg', 'mov', 'ts', 'webm', 'wmv') ))
				{
					echo 'Extension not allowed!';
					exit;
				}

				//unpredictable name
				return md5(uniqid($fn, true))  . '.' . $ext;
			}

			$path = '';

			if ($fn)
			{
				// AJAX call
				file_put_contents($path = $dir . generateName($fn), file_get_contents('php://input') );
				$title = ucwords(str_replace('-', ' ', sanitize_file_name(array_shift(explode(".", $fn)))));

				echo VWvideoShare::importFile($path, $title, $owner, $playlist, $category, $tag, $description);

				//echo "Video was uploaded.";
			}
			else
			{
				// form submit
				$files = $_FILES['fileselect'];

				if ($files['error']) if (is_array($files['error']))
						foreach ($files['error'] as $id => $err)
						{
							if ($err == UPLOAD_ERR_OK) {
								$fn = $files['name'][$id];
								move_uploaded_file( $files['tmp_name'][$id], $path = $dir . generateName($fn) );
								$title = ucwords(str_replace('-', ' ', sanitize_file_name(array_shift(explode(".", $fn)))));

								echo VWvideoShare::importFile($path, $title, $owner, $playlist, $category) . '<br>';

								//echo "Video was uploaded.";
							}
						}

			}


			die;
		}

		function shortcode_preview($atts)
		{
			$atts = shortcode_atts(array('video' => '0'), $atts, 'videowhisper_player');

			$video_id = intval($atts['video']);
			if (!$video_id) return 'shortcode_preview: Missing video id!';

			$video = get_post($video_id);
			if (!$video) return 'shortcode_preview: Video #'. $video_id . ' not found!';

			$options = get_option( 'VWvideoShareOptions' );

			//res
			$vWidth = get_post_meta($video_id, 'video-width', true);
			$vHeight = get_post_meta($video_id, 'video-height', true);
			if (!$vWidth) $vWidth = $options['thumbWidth'];
			if (!$vHeight) $vHeight = $options['thumbHeight'];

			//snap
			$imagePath = get_post_meta($video_id, 'video-snapshot', true);
			if ($imagePath)
				if (file_exists($imagePath))
					$imageURL = VWvideoShare::path2url($imagePath);
				else VWvideoShare::updatePostThumbnail($update_id);

				if (!$imagePath) $imageURL = VWvideoShare::path2url(plugin_dir_path( __FILE__ ) . 'no_video.png');
				$video_url = get_permalink($video_id);
			$htmlCode = "<a href='$video_url'><IMG SRC='$imageURL' width='$vWidth' height='$vHeight'></a>";

			return $htmlCode;
		}

		function shortcode_player_html($atts)
		{
			$options = get_option( 'VWvideoShareOptions' );

			$atts = shortcode_atts(
				array(
					'poster' => '',
					'width' => $options['thumbWidth'],
					'height' => $options['thumbHeight'],
					'poster' => $options['thumbHeight'],
					'source' => '',
					'source_type' => '',
					'id' => '0',
					'fallback' => 'You must have a HTML5 capable browser to watch this video. Read more about video sharing solutions and players on <a href="http://videosharevod.com/">Video Share VOD</a> website.'
				), $atts, 'videowhisper_player_html');

			$player = $options['html5_player'];
			if (!$player) $player = 'native';

			switch ($player)
			{
			case 'native':

				if ($atts['poster']) $posterProp = ' poster="' . $atts['poster'] . '"';
				else $posterProp ='';

				$htmlCode .='<video width="' . $atts['width'] . '" height="' . $atts['height'] . '"  preload="metadata" autobuffer controls="controls"' . $posterProp . '>';

				$htmlCode .=' <source src="' . $atts['source'] . '" type="' . $atts['source_type'] . '">';

				$htmlCode .='<div class="fallback"> <p>' . $atts['fallback'] . '</p></div> </video>';

				break;

			case 'wordpress':
				$htmlCode .= do_shortcode('[video src="' . $atts['source'] . '" poster="' . $atts['poster'] . '" width="' . $atts['width'] . '" height="' . $atts['height'] . '"]');
				break;

			case 'video-js':
				wp_enqueue_style( 'video-js', plugin_dir_url(__FILE__) .'video-js/video-js.min.css');
				wp_enqueue_script('video-js', plugin_dir_url(__FILE__) .'video-js/video.js');


				//VAST


				$showAds = $options['adsGlobal'];

				//video exception playlists
				if ($atts['id'])
				{
					$lists = wp_get_post_terms(  $atts['id'], 'playlist', array( 'fields' => 'names' ) );
					foreach ($lists as $playlist)
					{
						if (strtolower($playlist) == 'sponsored') $showAds= true;
						if (strtolower($playlist) == 'adfree') $showAds= false;
					}

				}
				

				//no ads for premium users
				if ($showAds) if (VWvideoShare::hasPriviledge($options['premiumList'])) $showAds= false;


				$vast = $showAds;

				if (!$options['vast']) $vast = false;

				if ($vast)
				{
					wp_enqueue_script('video-js1', plugin_dir_url(__FILE__) .'video-js/1/vast-client.js');

					wp_enqueue_script('video-js2', plugin_dir_url(__FILE__) .'video-js/2/videojs.ads.js', array( 'video-js') );
					wp_enqueue_style( 'video-js2', plugin_dir_url(__FILE__) .'video-js/2/videojs.ads.css');


					wp_enqueue_script('video-js3', plugin_dir_url(__FILE__) .'video-js/3/videojs.vast.js', array( 'video-js', 'video-js1', 'video-js2') );
					wp_enqueue_style( 'video-js3', plugin_dir_url(__FILE__) .'video-js/3/videojs.vast.css');
				}

				$id = 'vwVid' . $atts['id'];


				$htmlCode .= '<script>var $j = jQuery.noConflict();
				$j(document).ready(function(){ videojs.options.flash.swf = "' . plugin_dir_url(__FILE__) .'video-js/video-js.swf' . '";});</script>';

				if ($vast)
				{
					$htmlCode .= '<script>			
					$j(document).ready(function(){  var ' . $id . ' = videojs("' . $id . '"); ' . $id . '.ads(); ' . $id . '.vast({ url: \'' . $options['vast'] . '\' })});</script>';
				}


				if ($atts['poster']) $posterProp = ' poster="' . $atts['poster'] . '"';
				else $posterProp ='';

				$htmlCode .= '<video id="' . $id . '" class="video-js vjs-default-skin vjs-big-play-centered"  controls="controls" preload="metadata" width="' . $atts['width'] . '" height="' . $atts['height'] . '"' . $posterProp . ' data-setup="{}">';

				$htmlCode .=' <source src="' . $atts['source'] . '" type="' . $atts['source_type'] . '">';

				$htmlCode .='<div class="fallback"> <p>' . $atts['fallback'] . '</p></div> </video>';

				break;
			}

			return $htmlCode;
		}

		function hasPriviledge($csv)
		{

			if (strpos($csv,'Guest') !== false) return 1;

			//if any key matches any listing
			function inList($keys, $data)
			{
				if (!$keys) return 0;

				$list = explode(",", strtolower(trim($data)));

				foreach ($keys as $key)
					foreach ($list as $listing)
						if ( strtolower(trim($key)) == trim($listing) ) return 1;

						return 0;
			}

			if (is_user_logged_in())
			{
				global $current_user;
				get_currentuserinfo();

				//access keys : roles, #id, email
				if ($current_user)
				{
					$userkeys = $current_user->roles;
					$userkeys[] = $current_user->ID;
					$userkeys[] = $current_user->user_email;
				}

				if (inList($username, $csv)) return 1;
			}

			return 0;
		}

		function hasRole($role)
		{
			if (!is_user_logged_in()) return false;

			global $current_user;
			get_currentuserinfo();

			$role = strtolower($role);

			if (in_array($role, $current_user->roles)) return true;
			else return false;
		}

		function getRoles()
		{
			if (!is_user_logged_in()) return 'None';

			global $current_user;
			get_currentuserinfo();

			return implode(", ", $current_user->roles);
		}

		function poweredBy()
		{
			$options = get_option('VWvideoShareOptions');

			$state = 'block' ;
			if (!$options['videowhisper']) $state = 'none';

			return '<div id="VideoWhisper" style="display: ' . $state . ';"><p>Published with VideoWhisper <a href="http://videosharevod.com/">Video Share VOD</a>.</p></div>';
		}

		function shortcode_player($atts)
		{
			$atts = shortcode_atts(array('video' => '0'), $atts, 'videowhisper_player');

			$video_id = intval($atts['video']);
			if (!$video_id) return 'shortcode_player: Missing video id!';

			$video = get_post($video_id);
			if (!$video) return 'shortcode_player: Video #'. $video_id . ' not found!';

			$options = get_option( 'VWvideoShareOptions' );

			//VOD
			$deny = '';

			//global
			if (!VWvideoShare::hasPriviledge($options['watchList'])) $deny = 'Your current membership does not allow watching videos.';

			//by playlists
			$lists = wp_get_post_terms( $video_id, 'playlist', array( 'fields' => 'names' ) );

			//playlist role required?
			if ($options['vod_role_playlist'])
				foreach ($lists as $key=>$playlist)
				{
					$lists[$key] = $playlist = strtolower(trim($playlist));

					//is role
					if (get_role($playlist)) //video defines access roles
						{
						$deny = 'This video requires special membership. Your current membership: ' .VWvideoShare::getRoles() .'.' ;
						if (VWvideoShare::hasRole($playlist)) //has required role
							{
							$deny = '';
							break;
						}
					}
				}

			//exceptions
			if (in_array('free', $lists)) $deny = '';

			if (in_array('registered', $lists))
				if (is_user_logged_in()) $deny = '';
				else $deny = 'Only registered users can watch this videos. Please login first.';

				if (in_array('unpublished', $lists)) $deny = 'This video has been unpublished.';

				if ($deny)
				{
					$htmlCode .= str_replace('#info#',$deny, html_entity_decode(stripslashes($options['accessDenied'])));
					$htmlCode .= '<br>';
					$htmlCode .= do_shortcode('[videowhisper_preview video="' . $video_id . '"]') . VWvideoShare::poweredBy();
					return $htmlCode;
				}

			//update stats
			$views = get_post_meta($video_id, 'video-views', true);
			if (!$views) $views = 0;
			$views++;
			update_post_meta($video_id, 'video-views', $views);
			update_post_meta($video_id, 'video-lastview', time());

			//snap
			$imagePath = get_post_meta($video_id, 'video-snapshot', true);
			if ($imagePath)
				if (file_exists($imagePath))
				{
					$imageURL = VWvideoShare::path2url($imagePath);
					$posterVar = '&poster=' . urlencode($imageURL);
					$posterProp = ' poster="' . $imageURL . '"';
				} else VWvideoShare::updatePostThumbnail($update_id);



			$player = $options['player_default'];

			//Detect special conditions devices
			$iPod    = stripos($_SERVER['HTTP_USER_AGENT'],"iPod");
			$iPhone  = stripos($_SERVER['HTTP_USER_AGENT'],"iPhone");
			$iPad    = stripos($_SERVER['HTTP_USER_AGENT'],"iPad");
			$Android = stripos($_SERVER['HTTP_USER_AGENT'],"Android");

			$Safari  = (stripos($_SERVER['HTTP_USER_AGENT'],"Safari") && !stripos($_SERVER['HTTP_USER_AGENT'], 'Chrome'));

			$Mac = stripos($_SERVER['HTTP_USER_AGENT'],"Mac OS");
			$Firefox = stripos($_SERVER['HTTP_USER_AGENT'],"Firefox");


			if ($Mac && $Firefox) $player = $options['player_firefox_mac'];

			if ($Safari) $player = $options['player_safari'];

			if ($Android) $player = $options['player_android'];

			if ($iPod || $iPhone || $iPad) $player = $options['player_ios'];

			if (!$player) $player = $options['player_default'];

			//res
			$vWidth = get_post_meta($video_id, 'video-width', true);
			$vHeight = get_post_meta($video_id, 'video-height', true);
			if (!$vWidth) $vWidth = $options['thumbWidth'];
			if (!$vHeight) $vHeight = $options['thumbHeight'];

			switch ($player)
			{
			case 'strobe':

				$videoPath = get_post_meta($video_id, 'video-source-file', true);
				$videoURL = VWvideoShare::path2url($videoPath);

				$player_url = plugin_dir_url(__FILE__) . 'strobe/StrobeMediaPlayback.swf';
				$flashvars ='src=' .urlencode($videoURL). '&autoPlay=false' . $posterVar;

				$htmlCode .= '<object class="videoPlayer" width="' . $vWidth . '" height="' . $vHeight . '" type="application/x-shockwave-flash" data="' . $player_url . '"> <param name="movie" value="' . $player_url . '" /><param name="flashvars" value="' .$flashvars . '" /><param name="allowFullScreen" value="true" /><param name="allowscriptaccess" value="always" /><param name="wmode" value="direct" /></object>';
				break;

			case 'strobe-rtmp':
				$videoPath = get_post_meta($video_id, 'video-source-file', true);
				$ext = pathinfo($videoPath, PATHINFO_EXTENSION);


				if (in_array($ext, array('flv','mp4','m4v')))
				{
					//use source if compatible
					$stream = VWvideoShare::path2stream($videoPath);
				}
				else
				{
					//use conversion
					$videoAdaptive = get_post_meta($video_id, 'video-adaptive', true);
					if ($videoAdaptive) $videoAlts = $videoAdaptive;
					else $videoAlts = array();

					if ($alt = $videoAlts['mobile'])
						if (file_exists($alt['file']))
						{
							$ext = pathinfo($alt['file'], PATHINFO_EXTENSION);
							$stream = VWvideoShare::path2stream($alt['file']);

						}else $htmlCode .= 'Mobile adaptive format file missing for this video!';
					else $htmlCode .= 'Mobile adaptive format missing for this video!';

				}

				if ($stream)
				{

					if ($ext == 'mp4') $stream = 'mp4:' . $stream;

					$player_url = plugin_dir_url(__FILE__) . 'strobe/StrobeMediaPlayback.swf';
					$flashvars ='src=' .urlencode($options['rtmpServer'] . '/' . $stream). '&autoPlay=false' . $posterVar;

					$htmlCode .= '<object class="videoPlayer" width="' . $vWidth . '" height="' . $vHeight . '" type="application/x-shockwave-flash" data="' . $player_url . '"> <param name="movie" value="' . $player_url . '" /><param name="flashvars" value="' .$flashvars . '" /><param name="allowFullScreen" value="true" /><param name="allowscriptaccess" value="always" /><param name="wmode" value="direct" /></object>';
				}
				else $htmlCode .= 'Stream not found!';

				break;

			case 'html5':
				//user original if mp4
				$videoPath = get_post_meta($video_id, 'video-source-file', true);
				$ext = pathinfo($videoPath, PATHINFO_EXTENSION);

				if ($ext == 'mp4')
				{
					$videoURL = VWvideoShare::path2url($videoPath);
					$videoType = 'video/mp4';

					$width = $vWidth;
					$height = $vHeight;
				}
				else
				{

					$videoAdaptive = get_post_meta($video_id, 'video-adaptive', true);
					if ($videoAdaptive) $videoAlts = $videoAdaptive;
					else $videoAlts = array();

					if ($alt = $videoAlts['mobile'])
						if (file_exists($alt['file']))
						{
							$videoURL = VWvideoShare::path2url($alt['file']);
							$videoType = $alt['type'];
							$width = $alt['width'];
							$height = $alt['height'];

						}else $htmlCode .= 'Mobile adaptive format file missing for this video!';
					else $htmlCode .= 'Mobile adaptive format missing for this video!';

				}


				if (($videoURL)) $htmlCode .= do_shortcode('[videowhisper_player_html source="' . $videoURL . '" source_type="' . $videoType . '" poster="' . $imageURL . '" width="' . $width . '" height="' . $height . '" id="' . $video_id . '"]');

				break;

			case 'html5-mobile':

				//only mobile sources

				$videoAdaptive = get_post_meta($video_id, 'video-adaptive', true);
				if ($videoAdaptive) $videoAlts = $videoAdaptive;
				else $videoAlts = array();

				if ($alt = $videoAlts['mobile'])
					if (file_exists($alt['file']))
					{
						$videoURL = VWvideoShare::path2url($alt['file']);
						$videoType = $alt['type'];
						$width = $alt['width'];
						$height = $alt['height'];

					}else $htmlCode .= 'Mobile adaptive format file missing for this video!';
				else $htmlCode .= 'Mobile adaptive format missing for this video!';


				if (($videoURL)) $htmlCode .= do_shortcode('[videowhisper_player_html source="' . $videoURL . '" source_type="' . $videoType . '" poster="' . $imageURL . '" width="' . $width . '" height="' . $height . '" id="' . $video_id . '"]');

				break;


			case 'hls':

				//use conversion
				$videoAdaptive = get_post_meta($video_id, 'video-adaptive', true);
				if ($videoAdaptive) $videoAlts = $videoAdaptive;
				else $videoAlts = array();

				if ($alt = $videoAlts['mobile'])
					if (file_exists($alt['file']))
					{
						$stream = VWvideoShare::path2stream($alt['file']);
						$videoType = $alt['type'];
						$width = $alt['width'];
						$height = $alt['height'];

					}else $htmlCode .= 'Mobile adaptive format file missing for this video!';
				else $htmlCode .= 'Mobile adaptive format missing for this video!';

				if ($stream)
				{
					$stream = 'mp4:' . $stream;

					$streamURL = $options['hlsServer'] . '_definst_/' . $stream . '/playlist.m3u8';

					$htmlCode .= do_shortcode('[videowhisper_player_html source="' . $streamURL . '" source_type="' . $videoType . '" poster="' . $imageURL . '" width="' . $width . '" height="' . $height . '" id="' . $video_id . '"]');

				} else $htmlCode .= 'Stream not found!';

				break;
			}


			return $htmlCode . VWvideoShare::poweredBy();
		}

		function video_page($content)
		{
			if (!is_single()) return $content;
			$postID = get_the_ID() ;

			if (get_post_type( $postID ) != 'video') return $content;

			$addCode = '' . '[videowhisper_player video="' . $postID . '"]';


			//playlist

			$options = get_option( 'VWvideoShareOptions' );
			global $wpdb;

			$terms = get_the_terms( $postID, 'playlist' );

			if ( $terms && ! is_wp_error( $terms ) )
			{



				$addCode .=  '<div class="w-actionbox">';
				foreach ( $terms as $term )
				{

					if (class_exists("VWliveStreaming"))  if ($options['vwls_channel'])
						{


							$channelID = $wpdb->get_var( "SELECT ID FROM $wpdb->posts WHERE post_name = '" . $term->slug . "' and post_type='channel' LIMIT 0,1" );

							if ($channelID)
								$addCode .= ' <a title="Channel: '. $term->name .'" class="videowhisper_playlist_channel button g-btn type_red size_small mk-button dark-color  mk-shortcode two-dimension small" href="'. get_post_permalink( $channelID ) . '">' . $term->name . ' Channel</a> ' ;
						}


					$addCode .= ' <a title="Playlist: '. $term->name .'" class="videowhisper_playlist button g-btn type_secondary size_small mk-button dark-color  mk-shortcode two-dimension small" href="'. get_term_link( $term->slug, 'playlist') . '">' . $term->name . '</a> ' ;


				}
				$addCode .=  '</div>';

			}


			$views = get_post_meta($postID, 'video-views', true);
			if (!$views) $views = 0;

			$addCode .= '<div class="videowhisper_views">Video Views: ' . $views . '</div>';

			return $content . $addCode ;

		}

		function channel_page($content)
		{
			if (!is_single()) return $content;
			$postID = get_the_ID() ;

			if (get_post_type( $postID ) != 'channel') return $content;

			$channel = get_post( $postID );

			$addCode = '<div class="w-actionbox color_alternate"><h3>Channel Playlist</h3> ' . '[videowhisper_videos playlist="' . $channel->post_name . '"] </div>';

			return $addCode . $content;

		}

		function convertVideo($post_id, $overwrite = false)
		{

			if (!$post_id) return;

			$videoPath = get_post_meta($post_id, 'video-source-file', true);
			if (!$videoPath) return;

			$videoAdaptive = get_post_meta($post_id, 'video-adaptive', true);

			if ($videoAlts)
				if (is_array($videoAdaptive)) $videoAlts = $videoAdaptive;
				else $videoAlts = unserialize($videoAdaptive);
				else $videoAlts = array();

				$formats = array();
			$formats[0] = array
			(
				//Mobile: MP4/H.264, Baseline profile, 480×360, for wide compatibility
				'id' => 'mobile',
				'cmd' => '-s 480x360 -r 15 -vb 400k -vcodec libx264 -coder 0 -bf 0 -level 3.1 -g 30 -maxrate 440k -acodec libfaac -ac 2 -ar 22050 -ab 40k -x264opts vbv-maxrate=364:qpmin=4:ref=4',
				'width' => 480,
				'height' => 360,
				'bitrate' => 440,
				'type' => 'video/mp4',
				'extension' => 'mp4'
			);

			//HD Mobile: MP4/H.264, Main profile, 1280×720, for newer iOS devices (iPhone 4, iPad, Apple TV)


			$options = get_option( 'VWvideoShareOptions' );

			$path =  dirname($videoPath);

			foreach ($formats as $format)
				if (!$videoAlts[$format['id']] || $overwrite)
				{
					$alt = $format;
					unset($alt['cmd']);

					$newFile = md5(uniqid($post_id . $alt['id'], true))  . '.' . $alt['extension'];
					$alt['file'] = $path . '/' . $newFile;
					$logPath = $path . '/' . $post_id . '-' . $alt['id'] . '.txt';
					$cmdPath = $path . '/' . $post_id . '-' . $alt['id'] . '-cmd.txt';

					$videoAlts[$alt['id']] = $alt;

					$cmd = $options['ffmpegPath'] . ' -y '. $format['cmd'] . ' ' . $alt['file'] . ' -i ' . $videoPath . ' >&' . $logPath . ' &';

					exec($cmd, $output, $returnvalue);
					exec("echo '$cmd' >> $cmdPath", $output, $returnvalue);
				}

			update_post_meta( $post_id, 'video-adaptive', $videoAlts );

		}

		function generateSnapshots($post_id)
		{
			if (!$post_id) return;

			$videoPath = get_post_meta($post_id, 'video-source-file', true);
			if (!$videoPath) return;

			$options = get_option( 'VWvideoShareOptions' );

			$path =  dirname($videoPath);
			$imagePath =  $path . '/' . $post_id . '.jpg';
			$thumbPath =  $path . '/' . $post_id . '_thumb.jpg';
			$logPath = $path . '/' . $post_id . '-snap.txt';
			$cmdPath = $path . '/' . $post_id . '-snap-cmd.txt';

			$cmd = $options['ffmpegPath'] . ' -y -i "'.$videoPath.'" -ss 00:00:09.000 -f image2 -vframes 1 "' . $imagePath . '" >& ' . $logPath .' &';

			exec($cmd, $output, $returnvalue);
			exec("echo '$cmd' >> $cmdPath", $output, $returnvalue);

			update_post_meta( $post_id, 'video-snapshot', $imagePath );

			//probably source snap not ready, yet
			update_post_meta( $post_id, 'video-thumbnail', $thumbPath );

			list($width, $height) = VWvideoShare::generateThumbnail($imagePath, $thumbPath);
			if ($width) update_post_meta( $post_id, 'video-width', $width );
			if ($height) update_post_meta( $post_id, 'video-height', $height );
		}


		function generateThumbnail($src, $dest)
		{
			if (!file_exists($src)) return;

			$options = get_option( 'VWvideoShareOptions' );

			//generate thumb
			$thumbWidth = $options['thumbWidth'];
			$thumbHeight = $options['thumbHeight'];

			$srcImage = @imagecreatefromjpeg($src);
			if (!$srcImage) return;

			list($width, $height) = getimagesize($src);

			$destImage = imagecreatetruecolor($thumbWidth, $thumbHeight);

			imagecopyresampled($destImage, $srcImage, 0, 0, 0, 0, $thumbWidth, $thumbHeight, $width, $height);
			imagejpeg($destImage, $dest, 95);

			//return source dimensions
			return array($width, $height);
		}


		function updatePostThumbnail($post_id, $overwrite = false)
		{
			$imagePath = get_post_meta($post_id, 'video-snapshot', true);
			$thumbPath = get_post_meta($post_id, 'video-thumbnail', true);

			if (!$imagePath) VWvideoShare::generateSnapshots($post_id);
			elseif (!file_exists($imagePath)) VWvideoShare::generateSnapshots($post_id);
			elseif ($overwrite) VWvideoShare::generateSnapshots($post_id);

			if (!$thumbPath) VWvideoShare::generateSnapshots($post_id);
			elseif (!file_exists($thumbPath)) list($width, $height) = VWvideoShare::generateThumbnail($imagePath, $thumbPath);
			else
			{
				if ($overwrite) list($width, $height) = VWvideoShare::generateThumbnail($imagePath, $thumbPath);

				if (!get_the_post_thumbnail($post_id)) //insert if missing
					{
					$wp_filetype = wp_check_filetype(basename($thumbPath), null );

					$attachment = array(
						'guid' => $thumbPath,
						'post_mime_type' => $wp_filetype['type'],
						'post_title' => preg_replace( '/\.[^.]+$/', '', basename( $thumbPath, ".jpg" ) ),
						'post_content' => '',
						'post_status' => 'inherit'
					);

					// Insert the attachment.
					$attach_id = wp_insert_attachment( $attachment, $thumbPath, $post_id );
					set_post_thumbnail($post_id, $attach_id);
				}
				else //just update
					{
					$attach_id = get_post_thumbnail_id($post_id );
					//$thumbPath = get_attached_file($attach_id);
				}

				// Make sure that this file is included, as wp_generate_attachment_metadata() depends on it.
				require_once( ABSPATH . 'wp-admin/includes/image.php' );


				// Generate the metadata for the attachment, and update the database record.
				$attach_data = wp_generate_attachment_metadata( $attach_id, $thumbPath );
				wp_update_attachment_metadata( $attach_id, $attach_data );

			}

			if ($width) update_post_meta( $post_id, 'video-width', $width );
			if ($height) update_post_meta( $post_id, 'video-height', $height );

		}

		function updatePostDuration($post_id, $overwrite = false)
		{
			if (!$post_id) return;

			$videoPath = get_post_meta($post_id, 'video-source-file', true);
			if (!$videoPath) return;

			$videoDuration = get_post_meta($post_id, 'video-duration', true);
			if ($videoDuration && !$overwrite) return;

			$options = get_option( 'VWvideoShareOptions' );

			$path =  dirname($videoPath);
			$logPath = $path . '/' . $post_id . '-dur.txt';
			$cmdPath = $path . '/' . $post_id . '-dur-cmd.txt';

			$cmd = $options['ffmpegPath'] . ' -y -i "'.$videoPath.'" 2>&1';

			$info = shell_exec($cmd);
			exec("echo '$info' >> $logPath", $output, $returnvalue);
			exec("echo '$cmd' >> $cmdPath", $output, $returnvalue);

			preg_match('/Duration: (.*?),/', $info, $matches);
			$duration = explode(':', $matches[1]);

			$videoDuration = intval($duration[0]) * 3600 + intval($duration[1]) * 60 + intval($duration[2]);
			if ($videoDuration) update_post_meta( $post_id, 'video-duration', $videoDuration );

			preg_match('/bitrate:\s(?<bitrate>\d+)\skb\/s/', $info, $matches);
			$videoBitrate = $matches['bitrate'];
			if ($videoBitrate) update_post_meta( $post_id, 'video-bitrate', $videoBitrate );

			$videoSize = filesize($videoPath);
			if ($videoSize) update_post_meta( $post_id, 'video-source-size', $videoSize );


			return $videoDuration;
		}

		function vw_ls_manage_channel($val, $cid)
		{
			$options = get_option( 'VWvideoShareOptions' );

			$htmlCode .= '<div class="w-actionbox color_alternate"><h4>Manage Videos</h4>';

			$channel = get_post( $cid );
			$htmlCode .= '<p>Available '.$channel->post_title.' videos: ' . VWvideoShare::importFilesCount( $channel->post_title, array('flv', 'mp4', 'f4v'), $options['vwls_archive_path']) .'</p>';

			$link  = add_query_arg( array( 'playlist_import' => $channel->post_title), get_permalink() );
			$link2  = add_query_arg( array( 'playlist_upload' => $channel->post_title), get_permalink() );

			$htmlCode .= ' <a class="videowhisperButton g-btn type_blue" href="' .$link.'">Import</a> ';
			$htmlCode .= ' <a class="videowhisperButton g-btn type_green" href="' .$link2.'">Upload</a> ';

			$htmlCode .= '<h4>Channel Videos</h4>';

			$htmlCode .= do_shortcode('[videowhisper_videos perpage="4" playlist="'.$channel->post_name.'"]');

			$htmlCode .= '</div>';

			return $htmlCode;
		}


		function vw_ls_manage_channels_head($val)
		{
			$htmlCode = '';

			if ($channel_upload = sanitize_file_name($_GET['playlist_upload']))
			{
				$htmlCode = '[videowhisper_upload playlist="'.$channel_upload.'"]';
			}

			if ($channel_name = sanitize_file_name($_GET['playlist_import']))
			{

				$options = get_option( 'VWvideoShareOptions' );

				$url  = add_query_arg( array( 'playlist_import' => $channel_name), get_permalink() );


				$htmlCode .=  '<form id="videowhisperImport" name="videowhisperImport" action="' . $url . '" method="post">';

				$htmlCode .= "<h3>Import <b>" . $channel_name . "</b> Videos to Playlist</h3>";

				$htmlCode .= VWvideoShare::importFilesSelect( $channel_name, array('flv', 'mp4', 'f4v'), $options['vwls_archive_path']);

				$htmlCode .=  '<input type="hidden" name="playlist" id="playlist" value="' . $channel_name . '">';

				//same category as channel
				global $wpdb;
				$postID = $wpdb->get_var( "SELECT ID FROM $wpdb->posts WHERE post_name = '" . $channel_name . "' and post_type='channel' LIMIT 0,1" );

				if ($postID)
				{
					$cats = wp_get_post_categories( $postID);
					if (count($cats)) $category = array_pop($cats);
					$htmlCode .=  '<input type="hidden" name="category" id="category" value="' . $category . '">';
				}

				$htmlCode .=   '<INPUT class="button button-primary" TYPE="submit" name="import" id="import" value="Import">';

				$htmlCode .=  ' <INPUT class="button button-primary" TYPE="submit" name="delete" id="delete" value="Delete">';

				$htmlCode .=  '</form>';
			}

			return $htmlCode;
		}

		function humanDuration($t,$f=':') // t = seconds, f = separator
			{
			return sprintf("%02d%s%02d%s%02d", floor($t/3600), $f, ($t/60)%60, $f, $t%60);
		}

		function humanAge($t)
		{
			if ($t<30) return "NOW";
			return sprintf("%d%s%d%s%d%s", floor($t/86400), 'd ', ($t/3600)%24,'h ', ($t/60)%60,'m');
		}


		function humanFilesize($bytes, $decimals = 2) {
			$sz = 'BKMGTP';
			$factor = floor((strlen($bytes) - 1) / 3);
			return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . @$sz[$factor];
		}

		function path2url($file, $Protocol='http://') {
			return $Protocol.$_SERVER['HTTP_HOST'].str_replace($_SERVER['DOCUMENT_ROOT'], '', $file);
		}

		function path2stream($path)
		{
			$options = get_option( 'VWvideoShareOptions' );

			$stream = substr($path, strlen($options['streamsPath']));
			if ($stream[0]=='/') $stream = substr($stream,1);

			if (!file_exists($options['streamsPath'] . '/' . $stream)) return '';
			else return $stream;
		}

		function importFilesSelect($prefix, $extensions, $folder)
		{
			if (!file_exists($folder)) return "<div class='error'>Video folder not found: $folder !</div>";

			$htmlCode .= '';

			//import files
			if ($_POST['import'])
			{

				if (count($importFiles = $_POST['importFiles']))
				{

					$owner = (int) $_POST['owner'];

					global $current_user;
					get_currentuserinfo();

					if (!$owner) $owner = $current_user->ID;
					elseif ($owner != $current_user->ID && ! current_user_can('edit_users')) return "Only admin can import for others!";

					//handle one or many playlists
					$playlist = $_POST['playlist'];

					//if csv sanitize as array
					if (strpos($playlist, ',') !== FALSE)
					{
						$playlists = explode(',', $playlist);
						foreach ($playlists as $key => $value) $playlists[$key] = sanitize_file_name(trim($value));
						$playlist = $playlists;
					}

					if (!$playlist) return "Importing requires an playlist name!";

					//handle one or many tags
					$tag = $_POST['tag'];

					//if csv sanitize as array
					if (strpos($tag, ',') !== FALSE)
					{
						$tags = explode(',', $playlist);
						foreach ($tags as $key => $value) $tags[$key] = sanitize_file_name(trim($value));
						$tag = $tags;
					}

					$description = sanitize_text_field($_POST['description']);

					$category = sanitize_file_name($_POST['category']);

					foreach ($importFiles as $fileName)
					{
						$fileName = sanitize_file_name($fileName);
						$ext = pathinfo($fileName, PATHINFO_EXTENSION);
						if (!$ztime = filemtime($folder . $fileName)) $ztime = time();
						$videoName = basename($fileName, '.' . $ext) .' '. date("M j", $ztime);

						$htmlCode .= VWvideoShare::importFile($folder . $fileName, $videoName, $owner, $playlist, $category, $tag, $description);
					}
				}else $htmlCode .= '<div class="warning">No files selected to import!</div>';

			}

			//delete files
			if ($_POST['delete'])
			{

				if (count($importFiles = $_POST['importFiles']))
				{
					foreach ($importFiles as $fileName)
					{
						$htmlCode .= '<BR>Deleting '.$fileName.' ... ';
						$fileName = sanitize_file_name($fileName);
						if (!unlink($folder . $fileName)) $htmlCode .= 'Removing file failed!';
						else $htmlCode .= 'Success.';

					}
				}else $htmlCode .= '<div class="warning">No files selected to delete!</div>';
			}

			//preview file
			if ($preview_name = sanitize_file_name($_GET['import_preview']))
			{
				$preview_url = VWvideoShare::path2url($folder . $preview_name);
				$player_url = plugin_dir_url(__FILE__) . 'strobe/StrobeMediaPlayback.swf';
				$flashvars ='src=' .urlencode($preview_url). '&autoPlay=true';

				$htmlCode .= '<h4>Preview '.$preview_name.'</h4>';

				$htmlCode .= '<object class="previewPlayer" width="480" height="360" type="application/x-shockwave-flash" data="' . $player_url . '"> <param name="movie" value="' . $player_url . '" /><param name="flashvars" value="' .$flashvars . '" /><param name="allowFullScreen" value="true" /><param name="allowscriptaccess" value="always" /><param name="wmode" value="direct" /></object>';
			}

			//list files
			$fileList = scandir($folder);

			$ignored = array('.', '..', '.svn', '.htaccess');

			$prefixL=strlen($prefix);

			//list by date
			$files = array();
			foreach ($fileList as $fileName)
			{

				if (in_array($fileName, $ignored)) continue;
				if (!in_array(strtolower(pathinfo($fileName, PATHINFO_EXTENSION)), $extensions  )) continue;
				if ($prefixL) if (substr($fileName,0,$prefixL) != $prefix) continue;

					$files[$fileName] = filemtime($folder . $fileName);
			}

			arsort($files);
			$fileList = array_keys($files);

			if (!$fileList) $htmlCode .=  "<div class='warning'>No matching videos found!</div>";
			else
			{
				$htmlCode .=
					'<script language="JavaScript">
function toggleImportBoxes(source) {
  var checkboxes = new Array();
  checkboxes = document.getElementsByName(\'importFiles\');
  for (var i = 0; i < checkboxes.length; i++)
    checkboxes[i].checked = source.checked;
}
</script>';
				$htmlCode .=  "<table class='widefat videowhisperTable'>";
				$htmlCode .=  '<thead class=""><tr><th><input type="checkbox" onClick="toggleImportBoxes(this)" /></th><th>File Name</th><th>Preview</th><th>Size</th><th>Date</th></tr></thead>';

				foreach ($fileList as $fileName)
				{
					$htmlCode .=  '<tr>';
					$htmlCode .= '<td><input type="checkbox" name="importFiles[]" value="' . $fileName .'"'. ($fileName==$preview_name?' checked':'').'></td>';
					$htmlCode .=  "<td>$fileName</td>";
					$htmlCode .=  '<td>';
					$link  = add_query_arg( array( 'playlist_import' => $prefix, 'import_preview' => $fileName), get_permalink() );

					$htmlCode .=  " <a class='button size_small g-btn type_blue' href='" . $link ."'>Play</a> ";
					echo '</td>';
					$htmlCode .=  '<td>' .  VWvideoShare::humanFilesize(filesize($folder . $fileName)) . '</td>';
					$htmlCode .=  '<td>' .  date('jS F Y H:i:s', filemtime($folder  . $fileName)) . '</td>';
					$htmlCode .=  '</tr>';
				}
				$htmlCode .=  "</table>";

			}
			return $htmlCode;

		}

		function importFilesCount($prefix, $extensions, $folder)
		{
			if (!file_exists($folder)) return '';

			$kS=$k=0;

			$fileList = scandir($folder);

			$ignored = array('.', '..', '.svn', '.htaccess');

			$prefixL=strlen($prefix);

			foreach ($fileList as $fileName)
			{

				if (in_array($fileName, $ignored)) continue;
				if (!in_array(strtolower(pathinfo($fileName, PATHINFO_EXTENSION)), $extensions  )) continue;
				if ($prefixL) if (substr($fileName,0,$prefixL) != $prefix) continue;

					$k++;
				$kS+=filesize($folder . $fileName);
			}

			return $k . ' ('.VWvideoShare::humanFilesize($kS).')';
		}


		function importFile($path, $name, $owner, $playlists, $category = '', $tags = '', $description = '')
		{

			if (!file_exists($path)) return "<br>$name:File missing: $path";
			if (!$owner) return "<br>Missing owner!";
			if (!$playlists) return "<br>Missing playlists!";

			//handle one or many playlists
			if (is_array($playlists)) $playlist = sanitize_file_name(current($playlists));
			else $playlist = sanitize_file_name($playlists);

			if (!$playlist) return "<br>Missing playlist!";

			$htmlCode = '';
			$options = get_option( 'VWvideoShareOptions' );

			//uploads/owner/playlist/src/file
			$dir = $options['uploadsPath'];
			if (!file_exists($dir)) mkdir($dir);

			$dir .= '/' . $owner;
			if (!file_exists($dir)) mkdir($dir);

			$dir .= '/' . $playlist;
			if (!file_exists($dir)) mkdir($dir);

			//$dir .= '/src';
			//if (!file_exists($dir)) mkdir($dir);

			if (!$ztime = filemtime($path)) $ztime = time();

			$ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
			$newFile = md5(uniqid($owner, true))  . '.' . $ext;
			$newPath = $dir . '/' . $newFile;

			//$htmlCode .= "<br>Importing $name as $newFile ... ";

			if (!rename($path, $newPath))
			{
				$htmlCode .= 'Rename failed. Trying copy ...';
				if (!copy($path, $newPath))
				{
					$htmlCode .= 'Copy also failed. Import failed!';
					return $htmlCode;
				}
				// else $htmlCode .= 'Copy success ...';

				if (!unlink($path)) $htmlCode .= 'Removing original file failed!';
			}

			//$htmlCode .= 'Moved source file ...';

			$postdate = date("Y-m-d H:i:s", $ztime);

			$post = array(
				'post_name'      => $name,
				'post_title'     => $name,
				'post_author'    => $owner,
				'post_type'      => 'video',
				'post_status'    => 'publish',
				'post_date'   => $postdate,
				'post_content'   => $descriptions
			);

			$post_id = wp_insert_post( $post);
			if ($post_id)
			{
				update_post_meta( $post_id, 'video-source-file', $newPath );

				wp_set_object_terms($post_id, $playlists, 'playlist');

				if ($tags) wp_set_object_terms($post_id, $tags, 'post_tag');

				if ($category) wp_set_post_categories($post_id, array($category));

				VWvideoShare::updatePostDuration($post_id, true);
				VWvideoShare::updatePostThumbnail($post_id, true);
				VWvideoShare::convertVideo($post_id, true);

				$htmlCode .= '<br>Video post created: <a href='.get_post_permalink($post_id).'> #'.$post_id.' '.$name.'</a> <br>Snapshot, video info and thumbnail will be processed shortly.' ;
			}
			else $htmlCode .= '<br>Video post creation failed!';

			return $htmlCode;
		}

		/* Meta box setup function. */
		function post_meta_boxes_setup() {
			/* Add meta boxes on the 'add_meta_boxes' hook. */
			add_action( 'add_meta_boxes', array( 'VWvideoShare', 'add_post_meta_boxes' ) );

			/* Save post meta on the 'save_post' hook. */
			add_action( 'save_post', array( 'VWvideoShare', 'save_post_meta'), 10, 2 );
		}


		/* Create one or more meta boxes to be displayed on the post editor screen. */
		function add_post_meta_boxes() {

			add_meta_box(
				'video-post',      // Unique ID
				esc_html__( 'Video Post' ),    // Title
				array( 'VWvideoShare', 'post_meta_box'),   // Callback function
				'video',         // Admin page (or post type)
				'normal',         // Context
				'high'         // Priority
			);
		}

		/* Display the post meta box. */
		function post_meta_box( $object, $box ) {

			$value = esc_attr( get_post_meta( $object->ID, 'video-source-file', true ) )

?>

  <?php wp_nonce_field( basename( __FILE__ ), 'video_post_nonce' ); ?>
  <p>
    <label for="video-source-file"><?php _e( "Path to source video file" ); ?></label>
    <br />
    <input class="widefat" type="text" name="video-source-file" id="video-source-file" value="<?php echo $value; ?>" size="30" <?php echo $value?'readonly':''; ?> />
  </p>

<?php }

		/* Save the meta box's post metadata. */
		function save_post_meta( $post_id, $post ) {

			/* Verify the nonce before proceeding. */
			if ( !isset( $_POST['video_post_nonce'] ) || !wp_verify_nonce( $_POST['video_post_nonce'], basename( __FILE__ ) ) )
				return $post_id;

			/* Get the post type object. */
			$post_type = get_post_type_object( $post->post_type );

			/* Check if the current user has permission to edit the post. */
			if ( !current_user_can( $post_type->cap->edit_post, $post_id ) )
				return $post_id;

			foreach (array('video-source-file') as $meta_key)
			{
				/* Get the posted data and sanitize it for use as an HTML class. */
				$new_meta_value = ( isset( $_POST[$meta_key] ) ? $_POST[$meta_key] : '' );

				/* Get the meta value of the custom field key. */
				$meta_value = get_post_meta( $post_id, $meta_key, true );

				//first added: import
				if ($new_meta_value && !$meta_value && $meta_key = 'video-source-file')
					importFile($new_meta_value, $post->post_title, $post->post_author, $post->post_name);

				/* If a new meta value was added and there was no previous value, add it. */
				if ( $new_meta_value && '' == $meta_value )
					add_post_meta( $post_id, $meta_key, $new_meta_value, true );

				/* If the new meta value does not match the old value, update it. */
				elseif ( $new_meta_value && $new_meta_value != $meta_value )
					update_post_meta( $post_id, $meta_key, $new_meta_value );

				/* If there is no new meta value but an old value exists, delete it. */
				elseif ( '' == $new_meta_value && $meta_value )
					delete_post_meta( $post_id, $meta_key, $meta_value );
			}

		}

		function columns_head_video($defaults) {
			$defaults['featured_image'] = 'Thumbnail';
			$defaults['duration'] = 'Duration &amp; Info';

			return $defaults;
		}

		function columns_register_sortable( $columns ) {
			$columns['duration'] = 'duration';

			return $columns;
		}


		function columns_content_video($column_name, $post_id)
		{

			if ($column_name == 'featured_image')
			{
				$post_thumbnail_id = get_post_thumbnail_id($post_id);

				if ($post_thumbnail_id)
				{
					$post_featured_image = wp_get_attachment_image_src($post_thumbnail_id, 'featured_preview');

					if ($post_featured_image)
					{
						echo '<img src="' . $post_featured_image[0] . '" />';
					}

				}
				else
				{
					echo 'Generating ... ';
					VWvideoShare::updatePostThumbnail($post_id);

				}
			}

			if ($column_name == 'duration')
			{
				$videoDuration = get_post_meta($post_id, 'video-duration', true);
				if ($videoDuration)
				{
					echo 'Duration: ' . VWvideoShare::humanDuration($videoDuration);
					echo '<br>Resolution: ' . get_post_meta($post_id, 'video-width', true). 'x' . get_post_meta($post_id, 'video-height', true);
					echo '<br>Bitrate: '. get_post_meta($post_id, 'video-bitrate', true) . ' kbps';
					echo '<br>Source Size: ' . VWvideoShare::humanFilesize(get_post_meta($post_id, 'video-source-size', true));

					$url  = add_query_arg( array( 'updateVideo'  => $post_id), admin_url('edit.php?post_type=video') );

					echo '<br><a href="'.$url.'">Update Info</a>';
				}
				else
				{
					echo 'Retrieving Info...';
					VWvideoShare::updatePostDuration($update_id, true);
				}

			}

		}

		function post_edit_screen($query)
		{
			global $pagenow;

			if (is_admin() && $pagenow=='edit.php')
			{

				if ($update_id = (int) $_GET['updateVideo'])
				{
					//echo 'Updating #' .$update_id. '... <br>';
					VWvideoShare::updatePostDuration($update_id, true);
					VWvideoShare::updatePostThumbnail($update_id, true);
					VWvideoShare::convertVideo($update_id, true);
				}

			}
		}

		function duration_column_orderby( $vars ) {
			if ( isset( $vars['orderby'] ) && 'duration' == $vars['orderby'] ) {
				$vars = array_merge( $vars, array(
						'meta_key' => 'video-duration',
						'orderby' => 'meta_value_num'
					) );
			}

			return $vars;
		}

		function setupOptions() {

			$root_url = get_bloginfo( "url" ) . "/";
			$upload_dir = wp_upload_dir();

			$adminOptions = array(
				'disablePage' => '0',
				'vwls_playlist' => '1',
				'vwls_archive_path' =>'/home/youraccount/public_html/streams/',
				'importPath' => '/home/youraccount/public_html/streams/',
				'vwls_channel' => '1',
				'ffmpegPath' => '/usr/local/bin/ffmpeg',
				'html5_player' => 'native',
				'player_default' => 'strobe',
				'player_ios' => 'html5-mobile',
				'player_safari' => 'html5',
				'player_android' => 'html5-mobile',
				'player_firefox_mac' =>'strobe',
				'thumbWidth' => '240',
				'thumbHeight' => '180',
				'perPage' =>'6',
				'watchList' => 'Super Admin, Administrator, Editor, Author, Contributor, Subscriber, Guest',
				'accessDenied' => '<h3>Access Denied</h3>
<p>#info#</p>',
				'vod_role_playlist' => '1',
				'vast' => '',
				'adsGlobal' => '0',
				'premiumList' => '',
				'uploadsPath' => $upload_dir['basedir'] . '/vw_videoshare',
				'rtmpServer' => 'rtmp://your-site.com/videowhisper-x2',
				'streamsPath' =>'/home/youraccount/public_html/streams/',
				'hlsServer' =>'http://your-site.com:1935/videowhisper-x2/',
				'videowhisper' => '0',
				'customCSS' => <<<HTMLCODE
<style type="text/css">

.videowhisperVideo
{
position: relative;
display:inline-block;

border:1px solid #aaa;
background-color:#777;
padding: 0px;
margin: 2px;

width: 240px;
height: 180px;
}

.videowhisperVideo:hover {
	border:1px solid #fff;
}

.videowhisperVideo IMG
{
padding: 0px;
margin: 0px;
border: 0px;
}

.videowhisperTitle
{
position: absolute;
top:0px;
left:0px;
margin:8px;
font-size: 14px;
color: #FFF;
text-shadow:1px 1px 1px #333;
}

.videowhisperTime
{
position: absolute;
bottom:5px;
left:0px;
margin:8px;
font-size: 14px;
color: #FFF;
text-shadow:1px 1px 1px #333;
}

.videowhisperDate
{
position: absolute;
bottom:5px;
right:0px;
margin: 8px;
font-size: 11px;
color: #FFF;
text-shadow:1px 1px 1px #333;
}

.videowhisperDropdown {
    border: 1px solid #111;
    border-radius: 4px;
    overflow: hidden;
    background: #eee;
    width: 240px;
}

.videowhisperSelect {
    width: 100%;
    border: none;
    box-shadow: none;
    background: transparent;
    background-image: none;
    -webkit-appearance: none;
}

.videowhisperSelect:focus {
    outline: none;
}

.videowhisperButton {
	-moz-box-shadow:inset 0px 1px 0px 0px #ffffff;
	-webkit-box-shadow:inset 0px 1px 0px 0px #ffffff;
	box-shadow:inset 0px 1px 0px 0px #ffffff;
	-webkit-border-top-left-radius:6px;
	-moz-border-radius-topleft:6px;
	border-top-left-radius:6px;
	-webkit-border-top-right-radius:6px;
	-moz-border-radius-topright:6px;
	border-top-right-radius:6px;
	-webkit-border-bottom-right-radius:6px;
	-moz-border-radius-bottomright:6px;
	border-bottom-right-radius:6px;
	-webkit-border-bottom-left-radius:6px;
	-moz-border-radius-bottomleft:6px;
	border-bottom-left-radius:6px;
	text-indent:0;
	border:1px solid #dcdcdc;
	display:inline-block;
	color:#666666;
	font-family:Verdana;
	font-size:15px;
	font-weight:bold;
	font-style:normal;
	height:50px;
	line-height:50px;
	width:200px;
	text-decoration:none;
	text-align:center;
	text-shadow:1px 1px 0px #ffffff;
	background-color:#e9e9e9;

}

.videowhisperButton:hover {
	background-color:#f9f9f9;
}

.videowhisperButton:active {
	position:relative;
	top:1px;
}
</style>

HTMLCODE
				,

				'videowhisper' => 0
			);

			$options = get_option('VWvideoShareOptions');
			if (!empty($options)) {
				foreach ($options as $key => $option)
					$adminOptions[$key] = $option;
			}
			update_option('VWvideoShareOptions', $adminOptions);

			return $adminOptions;
		}

		function adminUpload()
		{
?>
		<div class="wrap">
<?php screen_icon(); ?>
		<h2>Video Share / Video on Demand (VOD)</h2>
		<?php
			echo do_shortcode("[videowhisper_upload]");
?>
		Use this to upload one or multiple videos to server. Configure category, playlists and then choose files or drag and drop files to upload area.
		<br>Playlist(s): Assign videos to multiple playlists, as comma separated values. Ex: subscriber, premium
		<p><a target="_blank" href="http://videosharevod.com/features/video-uploader/">About Video Uploader ...</a></p>

		</div>
		<?php
		}

		function adminDocs()
		{
?>
		<div class="wrap">
<?php screen_icon(); ?>
		<h2>Video Share / Video on Demand (VOD)</h2>
		<h3>Shortcodes</h3>

		<h4>[videowhisper_videos playlist="" category_id="" order_by="" perpage="" perrow="" select_category="1" select_order="1" select_page="1" include_css="1" id=""]</h4>
		Displays video list. Loads and updates by AJAX. Optional parameters: video playlist name, maximum videos per page, maximum videos per row.
		<br>order_by: post_date / video-views / video-lastview
		<br>select attributes enable controls to select category, order, page
		<br>include_css: includes the styles (disable if already loaded once on same page)
		<br>id is used to allow multiple instances on same page (leave blank to generate)

		<h4>[videowhisper_upload playlist="" category="" owner=""]</h4>
		Displays interface to upload videos.
		<br>playlist: If not defined owner name is used as playlist for regular users. Admins with edit_users capability can write any playlist name. Multiple playlists can be provided as comma separated values.
		<br>category: If not define a dropdown is listed.
		<br>owner: User is default owner. Only admins with edit_users capability can use different.

	   <h4>[videowhisper_import path="" playlist="" category="" owner=""]</h4>
		Displays interface to import videos.
		<br>path: Path where to import from.
		<br>playlist: If not defined owner name is used as playlist for regular users. Admins with edit_users capability can write any playlist name. Multiple playlists can be provided as comma separated values.
		<br>category: If not define a dropdown is listed.
		<br>owner: User is default owner. Only admins with edit_users capability can use different.

		<h4>[videowhisper_player video="0"]</h4>
		Displays video player. Video post ID is required.

		<h4>[videowhisper_preview video="0"]</h4>
		Displays video preview (snapshot) with link to video post. Video post ID is required.
		Used to display VOD inaccessible items.

		<h4>[videowhisper_player_html source="" source_type="" poster="" width="" height=""]</h4>
		Displays configured HTML5 player for a specified video source.
		<br>Ex. [videowhisper_player_html source="http://test.com/test.mp4" type="video/mp4" poster="http://test.com/test.jpg"]

		<h3>More...</h3>
		Read more details about <a href="http://videosharevod.com/features/">available features</a> on <a href="http://videosharevod.com/">official plugin site</a> and <a href="http://www.videowhisper.com/tickets_submit.php">contact us</a> anytime for questions, clarifications.
		</div>
		<?php
		}

		function adminOptions()
		{
			$options = VWvideoShare::setupOptions();

			if (isset($_POST))
			{
				foreach ($options as $key => $value)
					if (isset($_POST[$key])) $options[$key] = trim($_POST[$key]);
					update_option('VWvideoShareOptions', $options);
			}

			/*
            $page_id = get_option("vwvs_page_import");
            if ($page_id != '-1' && $options['disablePage']!='0') VWvideoShare::deletePages();
*/


			$active_tab = isset( $_GET[ 'tab' ] ) ? $_GET[ 'tab' ] : 'server';
?>


<div class="wrap">
<?php screen_icon(); ?>
<h2>Video Share / Video on Demand (VOD)</h2>
<h2 class="nav-tab-wrapper">
	<a href="<?php echo get_permalink(); ?>admin.php?page=video-share&tab=server" class="nav-tab <?php echo $active_tab=='server'?'nav-tab-active':'';?>">Server</a>
	<a href="<?php echo get_permalink(); ?>admin.php?page=video-share&tab=display" class="nav-tab <?php echo $active_tab=='display'?'nav-tab-active':'';?>">Display</a>
	<a href="<?php echo get_permalink(); ?>admin.php?page=video-share&tab=players" class="nav-tab <?php echo $active_tab=='players'?'nav-tab-active':'';?>">Players</a>
	<a href="<?php echo get_permalink(); ?>admin.php?page=video-share&tab=ls" class="nav-tab <?php echo $active_tab=='ls'?'nav-tab-active':'';?>">Live Streaming</a>
	<a href="<?php echo get_permalink(); ?>admin.php?page=video-share&tab=vod" class="nav-tab <?php echo $active_tab=='vod'?'nav-tab-active':'';?>">VOD</a>
	<a href="<?php echo get_permalink(); ?>admin.php?page=video-share&tab=vast" class="nav-tab <?php echo $active_tab=='vast'?'nav-tab-active':'';?>">VAST</a>
</h2>

<form method="post" action="<?php echo $_SERVER["REQUEST_URI"]; ?>">

<?php
			switch ($active_tab)
			{
			case 'server':
?>
<h3>Server Configuration</h3>

<h4>Uploads Path</h4>
<p>Path where video files will be stored. Make sure you use a location outside plugin folder to avoid losing files on updates and plugin uninstallation.</p>
<input name="uploadsPath" type="text" id="uploadsPath" size="80" maxlength="256" value="<?php echo $options['uploadsPath']?>"/>
<br>Ex: /home/-your-account-/public_html/wp-content/uploads/vw_videoshare
<br>Ex: /home/-your-account-/public_html/streams/videoshare
<br>If you ever decide to change this, previous files must remain in old location.

<h4>FFMPEG Path</h4>
<p>Path to latest FFMPEG. Required for extracting snapshots, info and converting videos.</p>
<input name="ffmpegPath" type="text" id="ffmpegPath" size="100" maxlength="256" value="<?php echo $options['ffmpegPath']?>"/>
<?php
				echo "<BR>FFMPEG: ";
				$cmd = $options['ffmpegPath'] . ' -codecs';
				exec($cmd, $output, $returnvalue);
				if ($returnvalue == 127)  echo "not detected: $cmd"; else echo "detected";

				//detect codecs
				if ($output) if (count($output))
						foreach (array('h264','faac','speex', 'nellymoser') as $cod)
						{
							$det=0; $outd="";
							echo "<BR>$cod codec: ";
							foreach ($output as $outp) if (strstr($outp,$cod)) { $det=1; $outd=$outp; };
							if ($det) echo "detected ($outd)"; else echo "missing: please configure and install ffmpeg with $cod";
						}
?>

<h4>RTMP Address</h4>
<p>Optional: Required only for RTMP playback. Recommended: <a href="http://videosharevod.com/hosting/" target="_blank">Wowza RTMP Hosting</a>.
<br>RTMP application address for playback.</p>
<input name="rtmpServer" type="text" id="rtmpServer" size="80" maxlength="256" value="<?php echo $options['rtmpServer']?>"/>
<br>Ex: rtmp://your-site.com/videowhisper-x2
<br>Do not use a rtmp address that requires some form of authentication or verification done by another web script as player will not be able to connect.
<br>Avoid using a shared rtmp address. Setup a special rtmp application for playback of videos. For Wowza configure &lt;StreamType&gt;file&lt;/StreamType&gt;.

<h4>RTMP Streams Path</h4>
<p>Optional: Required only for RTMP playback.
<br>Path where rtmp server is configured to stream videos from. Uploads path must be a subfolder of this path to allow rtmp access to videos. </p>
<input name="streamsPath" type="text" id="streamsPath" size="80" maxlength="256" value="<?php echo $options['streamsPath']?>"/>
<br>This must be a substring of, or same as Uploads Path.
<br>Ex: /home/your-account/public_html/streams
<?php
					if (!strstr($options['uploadsPath'], $options['streamsPath']))
						echo '<br><b class="error">Current value seems wrong!</b>';
					else echo '<br>Current value seems fine.';
?>
<h4>HLS URL</h4>
<p>Optional: Required only for HLS playback.
<br>HTTP address to access by HTTP Live Streaming (HLS).</p>
<input name="hlsServer" type="text" id="hlsServer" size="80" maxlength="256" value="<?php echo $options['hlsServer']?>"/>
<br>Ex: http://your-site.com:1935/videowhisper-x2/
<br>For Wowza disable live packetizers: &lt;LiveStreamPacketizers&gt;&lt;/LiveStreamPacketizers&gt;.
<?php
					break;
			case 'ls':
?>
<h3>Live Streaming</h3>
<p>
<a target="_blank" href="http://videosharevod.com/features/live-streaming/">About Live Streaming...</a><br>

VideoWhisper Live Streaming is a plugin that allows users to broadcast live video channels.
<br>Detection:
<?php
				if (class_exists("VWliveStreaming")) echo "Installed."; else echo "Not detected. Please install and activate plugin to use this functionality."
?>
</p>

<h4>Import Live Streaming Playlists</h4>
Enables Live Streaming channel owners to import playlistd streams. Videos must be playlistd locally.
<br><select name="vwls_playlist" id="vwls_playlist">
  <option value="1" <?php echo $options['vwls_playlist']?"selected":""?>>Yes</option>
  <option value="0" <?php echo $options['vwls_playlist']?"":"selected"?>>No</option>
</select>

<h4>List Channel Videos</h4>
List videos on channel.
<br><select name="vwls_channel" id="vwls_channel">
  <option value="1" <?php echo $options['vwls_channel']?"selected":""?>>Yes</option>
  <option value="0" <?php echo $options['vwls_channel']?"":"selected"?>>No</option>
</select>

<h4>Path to Video Archive</h4>
<input name="vwls_archive_path" type="text" id="vwls_archive_path" size="80" maxlength="256" value="<?php echo $options['vwls_archive_path']; ?>"/>
<br>Ex: /home/your-account/public_html/streams/
<br>When using Wowza Streaming Engine configure [install-dir]/conf/Server.xml to save as FLV instead of MP4:
<br>&lt;DefaultStreamPrefix&gt;flv&lt;/DefaultStreamPrefix&gt;
<br>FLV includes support for web based flash audio codecs.

<?php
				break;
			case 'players':

?>
<h3>Players</h3>

<h4>HTML5 Player</h4>
<select name="html5_player" id="html5_player">
  <option value="native" <?php echo $options['html5_player']=='native'?"selected":""?>>Native HTML5 Tag</option>
  <option value="wordpress" <?php echo $options['html5_player']=='wordpress'?"selected":""?>>WordPress Player (MediaElement.js)</option>
  <option value="video-js" <?php echo $options['html5_player']=='video-js'?"selected":""?>>Video.js</option>
 </select>

<h3>Player Compatibility</h3>
Setup appropriate player type and video source depending on OS and browser.
<h4>Default Player Type</h4>
<select name="player_default" id="player_default">
  <option value="strobe" <?php echo $options['player_default']=='strobe'?"selected":""?>>Strobe (Flash)</option>
  <option value="html5" <?php echo $options['player_default']=='html5'?"selected":""?>>HTML5</option>
  <option value="html5-mobile" <?php echo $options['player_default']=='html5-mobile'?"selected":""?>>HTML5 Mobile</option>
   <option value="strobe-rtmp" <?php echo $options['player_default']=='strobe-rtmp'?"selected":""?>>Strobe RTMP</option>
</select>
<BR>HTML5 Mobile plays lower profile converted video, for mobile support, even if source video is MP4.

<h4>Player on iOS</h4>
<select name="player_ios" id="player_ios">
  <option value="html5-mobile" <?php echo $options['player_ios']=='html5-mobile'?"selected":""?>>HTML5 Mobile</option>
   <option value="hls" <?php echo $options['player_ios']=='hls'?"selected":""?>>HTML5 HLS</option>
</select>

<h4>Player on Safari</h4>
<select name="player_safari" id="player_safari">
  <option value="strobe" <?php echo $options['player_safari']=='strobe'?"selected":""?>>Strobe</option>
  <option value="html5" <?php echo $options['player_safari']=='html5'?"selected":""?>>HTML5</option>
  <option value="html5-mobile" <?php echo $options['player_default']=='html5-mobile'?"selected":""?>>HTML5 Mobile</option>
   <option value="strobe-rtmp" <?php echo $options['player_safari']=='strobe-rtmp'?"selected":""?>>Strobe RTMP</option>
   <option value="hls" <?php echo $options['player_safari']=='hls'?"selected":""?>>HTML5 HLS</option>
</select>
<BR>Safari requires user to confirm flash player load. Use HTML5 player to avoid this.

<h4>Player on Firefox for MacOS</h4>
<select name="player_firefox_mac" id="player_default">
  <option value="strobe" <?php echo $options['player_firefox_mac']=='strobe'?"selected":""?>>Strobe</option>
   <option value="strobe-rtmp" <?php echo $options['player_firefox_mac']=='strobe-rtmp'?"selected":""?>>Strobe RTMP</option>
</select>
<BR>Firefox for Mac did not support MP4 HTML5 playback, last time we checked. See <a href="https://bugzilla.mozilla.org/show_bug.cgi?id=851290">bug status</a>.

<h4>Player on Android</h4>
<select name="player_android" id="player_android">
  <option value="html5" <?php echo $options['player_android']=='html5-mobile'?"selected":""?>>HTML5 Mobile</option>
  <option value="strobe" <?php echo $options['player_android']=='strobe'?"selected":""?>>Flash Strobe</option>
   <option value="strobe-rtmp" <?php echo $options['player_android']=='strobe-rtmp'?"selected":""?>>Flash Strobe RTMP</option>
</select>
<BR>Latest Android no longer supports Flash in default browser, so HTML5 is recommended.

<?php
				break;

			case 'display':

				$options['customCSS'] = htmlentities(stripslashes($options['customCSS']));
?>
<h3>Display &amp; Listings</h3>

<h4>Default Videos Per Page</h4>
<input name="perPage" type="text" id="perPage" size="3" maxlength="3" value="<?php echo $options['perPage']?>"/>


<h4>Thumbnail Width</h4>
<input name="thumbWidth" type="text" id="thumbWidth" size="4" maxlength="4" value="<?php echo $options['thumbWidth']?>"/>

<h4>Thumbnail Height</h4>
<input name="thumbHeight" type="text" id="thumbHeight" size="4" maxlength="4" value="<?php echo $options['thumbHeight']?>"/>

<h4>Custom CSS</h4>
<textarea name="customCSS" id="customCSS" cols="64" rows="5"><?php echo $options['customCSS']?></textarea>
<BR>Styling used in elements added by this plugin. Must include CSS container &lt;style type=&quot;text/css&quot;&gt; &lt;/style&gt; .

<h4>Show VideoWhisper Powered by</h4>
<select name="videowhisper" id="videowhisper">
  <option value="0" <?php echo $options['videowhisper']?"":"selected"?>>No</option>
  <option value="1" <?php echo $options['videowhisper']?"selected":""?>>Yes</option>
</select>
<br>Show a mention that videos where posted with VideoWhisper plugin.
<?php
				break;
			case 'vod':
				$options['accessDenied'] = htmlentities(stripslashes($options['accessDenied']));

?>
<h3>Membership Video On Demand</h3>
<a target="_blank" href="http://videosharevod.com/features/video-on-demand/">About Video On Demand...</a>

<h4>Members allowed to watch video</h4>
<textarea name="watchList" cols="64" rows="3" id="watchList"><?php echo $options['watchList']?>
</textarea>
<BR>Global video access list: comma separated Roles, user Emails, user ID numbers. Ex: <i>Subscriber, Author, submit.ticket@videowhisper.com, 1</i>
<BR>"Guest" will allow everybody including guests (unregistered users) to watch videos.

<h4>Role Playlists</h4>
Enables access by role playlists: Assign video to a playlist that is a role name.
<br><select name="vod_role_playlist" id="vod_role_playlist">
  <option value="1" <?php echo $options['vod_role_playlist']?"selected":""?>>Yes</option>
  <option value="0" <?php echo $options['vod_role_playlist']?"":"selected"?>>No</option>
</select>
<br>Multiple roles can be assigned to same video. User can have any of the assigned roles, to watch. If user has required role, access is granted even if not in global access list.
<br>Videos without role playlists are accessible as per global video access.

<h4>Exceptions</h4>
Assign videos to these Playlists:
<br><b>free</b> : Anybody can watch, including guests.
<br><b>registered</b> : All members can watch.
<br><b>unpublished</b> : Video is not accessible.

<h4>Access denied message</h4>
<textarea name="accessDenied" cols="64" rows="3" id="accessDenied"><?php echo $options['accessDenied']?>
</textarea>
<BR>HTML info, shows with preview if user does not have access to watch video.
<br>Including #info# will mention rule that was applied.
<?php
				break;

			case 'vast':
				$options['vast'] = trim($options['vast']);

?>
<h3>Video Ad Serving Template (VAST)</h3>
VAST is currently supported with Video.js HTML5 player.
<br>There are multiple ad networks that support VAST.
<br>VAST data structure configures: (1) The ad media that should be played (2) How should the ad media be played (3) What should be tracked as the media is played. In example pre-roll video ads can be implemented with VAST.

<h4>Video Ads</h4>
Enable ads for all videos.
<br><select name="adsGlobal" id="adsGlobal">
  <option value="1" <?php echo $options['adsGlobal']?"selected":""?>>Yes</option>
  <option value="0" <?php echo $options['adsGlobal']?"":"selected"?>>No</option>
</select>
<br>Exception Playlists:
<br><b>sponsored</b>: Show ads.
<br><b>adfree</b>: Do not show ads.

<h4>VAST Address</h4>
<textarea name="vast" cols="64" rows="2" id="vast"><?php echo $options['vast']?>
</textarea>
<br>Ex: http://ad3.liverail.com/?LR_PUBLISHER_ID=1331&LR_CAMPAIGN_ID=229&LR_SCHEMA=vast2
<br>Leave blank to disable video ads.

<h4>Premium Users List</h4>
<p>Premium uses watch videos without advertisements (exception for VAST).</p>
<textarea name="premiumList" cols="64" rows="3" id="premiumList"><?php echo $options['premiumList']?>
</textarea>
<BR>VAST excepted users: comma separated Roles, user Emails, user ID numbers. Ex: <i>Author, Editor, submit.ticket@videowhisper.com, 1</i>

<?php
				break;
			}

			if (!in_array($active_tab, array( 'shortcodes')) ) submit_button(); ?>

</form>
</div>
	 <?php
		}

		function adminImport()
		{
			$options = VWvideoShare::setupOptions();

			if (isset($_POST))
			{
				foreach ($options as $key => $value)
					if (isset($_POST[$key])) $options[$key] = trim($_POST[$key]);
					update_option('VWvideoShareOptions', $options);
			}


			screen_icon(); ?>
<h2>Import Videos from Folder</h2>
	Use this to mass import any number of videos already existent on server.

<?php
			if (file_exists($options['importPath'])) echo do_shortcode('[videowhisper_import path="' . $options['importPath'] . '"]');
			else echo 'Import folder not found on server: '. $options['importPath'];
?>
<form method="post" action="<?php echo $_SERVER["REQUEST_URI"]; ?>">
<h4>Import Path</h4>
<p>Server path to import videos from</p>
<input name="importPath" type="text" id="importPath" size="100" maxlength="256" value="<?php echo $options['importPath']?>"/>
<br>Ex: /home/youraccount/public_html/streams
<?php submit_button(); ?>
</form>
	<?php
		}



		function adminLiveStreaming()
		{
			$options = get_option( 'VWvideoShareOptions' );

			screen_icon(); ?>

<h3>Import Archived Channel Videos</h3>
This allows importing stream archives to playlist of their video channel. <a target="_blank" href="http://videosharevod.com/features/live-streaming/">About Live Streaming...</a><br>
<?php

			if ($channel_name = sanitize_file_name($_GET['playlist_import']))
			{

				$url  = add_query_arg( array( 'playlist_import' => $channel_name), admin_url('admin.php?page=video-share-ls') );


				echo '<form action="' . $url . '" method="post">';
				echo "<h4>Import Archived Videos to Playlist <b>" . $channel_name . "</b></h4>";
				echo VWvideoShare::importFilesSelect( $channel_name, array('flv', 'mp4', 'f4v'), $options['vwls_archive_path']);
				echo '<INPUT class="button button-primary" TYPE="submit" name="import" id="import" value="Import">';
				global $wpdb;
				$postID = $wpdb->get_var( "SELECT ID FROM $wpdb->posts WHERE post_name = '" . sanitize_file_name($channel_name) . "' and post_type='channel' LIMIT 0,1" );

				if ($postID)
				{
					$channel = get_post( $postID );
					$owner = $channel->post_author;

					$cats = wp_get_post_categories( $postID);
					if (count($cats)) $category = array_pop($cats);
				}
				else
				{
					global $current_user;
					get_currentuserinfo();
					$owner = $current_user->ID;
					echo ' as ' . $current_user->display_name;
				}

				echo '<input type="hidden" name="playlist" id="playlist" value="' . $channel_name . '">';
				echo '<input type="hidden" name="owner" id="owner" value="' . $owner . '">';
				echo '<input type="hidden" name="category" id="category" value="' . $category . '">';

				echo ' <INPUT class="button button-primary" TYPE="submit" name="delete" id="delete" value="Delete">';

				echo '</form>';
			}


			echo "<h4>Recent Activity</h4>";

			function format_age($t)
			{
				if ($t<30) return "LIVE";
				return sprintf("%d%s%d%s%d%s", floor($t/86400), 'd ', ($t/3600)%24,'h ', ($t/60)%60,'m');
			}

			global $wpdb;
			$table_name3 = $wpdb->prefix . "vw_lsrooms";
			$items =  $wpdb->get_results("SELECT * FROM `$table_name3` ORDER BY edate DESC LIMIT 0, 100");
			echo "<table class='wp-list-table widefat'><thead><tr><th>Channel</th><th>Videos</th><th>Actions</th><th>Last Access</th><th>Type</th></tr></thead>";
			if ($items) foreach ($items as $item)
					if (($fcount = VWvideoShare::importFilesCount( $item->name, array('flv', 'mp4', 'f4v'), $options['vwls_archive_path']))!='0 (0.00B)')
					{
						echo "<tr><th>" . $item->name . "</th>";

						echo "<td>". $fcount . "</td>";

						$link  = add_query_arg( array( 'playlist_import' => $item->name), admin_url('admin.php?page=video-share-ls') );

						echo '<td><a class="button button-primary" href="' .$link.'">Import</a></td>';
						echo "<td>".format_age(time() - $item->edate)."</td>";
						echo '<td>' . ($item->type==2?"Premium":"Standard") . '</td>';
						echo "</tr>";
					}
				echo '<tr><th>Total</th><th colspan="4">' . VWvideoShare::importFilesCount( '', array('flv', 'mp4', 'f4v'), $options['vwls_archive_path']) . '</th></tr>';
			echo "</table>";
		}
		//fc above
	}
}

//instantiate
if (class_exists("VWvideoShare")) {
	$videoShare = new VWvideoShare();
}

//Actions and Filters
if (isset($videoShare)) {

	register_activation_hook( __FILE__, array(&$videoShare, 'install' ) );

	add_action( 'init', array(&$videoShare, 'video_post'));
	add_action('admin_menu', array(&$videoShare, 'adminMenu'));
	add_action("plugins_loaded", array(&$videoShare , 'init'));

}
?>