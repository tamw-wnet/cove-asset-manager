<?php

if ( ! defined( 'ABSPATH' ) ) exit;

class COVE_Asset_Manager {
	private $dir;
	private $file;
	private $assets_dir;
	private $assets_url;
  public  $use_media_manager;

	public function __construct( $file ) {
		$this->dir = dirname( $file );
		$this->file = $file;
		$this->assets_dir = trailingslashit( $this->dir ) . 'assets';
		$this->assets_url = esc_url( trailingslashit( plugins_url( '/assets/', $file ) ) );
    $this->use_media_manager = (get_option('coveam_use_mm_ingest') == 'true') ? true : false;

		// Handle localisation
		$this->load_plugin_textdomain();
		add_action( 'init', array( $this, 'load_localisation' ), 0 );

		// setup the tazonomy
    if ($this->use_media_manager) {
		  add_action( 'init', array( $this, 'add_cove_topics_taxonomy' ), 0 );
    }

    // setup the post types
    add_action( 'init', array( $this, 'register_post_types' ), 0 );  

		// Load public-facing style sheet and JavaScript.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

    // Setup the shortcode
    add_shortcode( 'covevideoasset', array($this, 'cove_player_shortcode') );

    add_action( 'wp_ajax_coveam_get_episode_option_list', array( $this, 'ajax_get_episode_option_list'));

    if (! has_action( 'coveam_import_media_manager_asset')) {
      add_action( 'coveam_import_media_manager_asset', array($this, 'import_media_manager_asset'), 10, 2 );
    }



	}
	public function enqueue_scripts () {
        $scriptPath = $this->assets_url . 'js/jquery.cove-videoplayer-1.2.js';
	  wp_register_script( 'coveam_video-player', $scriptPath,  array('jquery'), 1.8, true );
    wp_enqueue_script( 'coveam_video-player' );
	}
	public function load_localisation () {
		load_plugin_textdomain( 'cove_asset_manager' , false , dirname( plugin_basename( $this->file )  . '/lang/' ) );
	}
	
	public function load_plugin_textdomain () {
	    $domain = 'cove_asset_manager';
	    
	    $locale = apply_filters( 'plugin_locale' , get_locale() , $domain );
	 
	    load_textdomain( $domain , WP_LANG_DIR . '/' . $domain . '/' . $domain . '-' . $locale . '.mo' );
	    load_plugin_textdomain( $domain , FALSE , dirname( plugin_basename( $this->file ) ) . '/lang/' );
	}

	public function add_cove_topics_taxonomy () {
    if (!taxonomy_exists('cove_topics')) {
	    $labels = array(
		    'name'              => _x( 'COVE Topics', 'taxonomy general name' ),
		    'singular_name'     => _x( 'COVE Topic', 'taxonomy singular name' ),
		    'search_items'      => __( 'Search COVE Topics' ),
		    'all_items'         => __( 'All COVE Topics' ),
		    'parent_item'       => __( 'Parent COVE Topic' ),
		    'parent_item_colon' => __( 'Parent COVE Topic:' ),
		    'edit_item'         => __( 'Edit COVE Topic' ),
		    'update_item'       => __( 'Update COVE Topic' ),
		    'add_new_item'      => __( 'Add New COVE Topic' ),
		    'new_item_name'     => __( 'New COVE Topic Name' ),
		    'menu_name'         => __( 'COVE Topics' ),
	    );
      register_taxonomy(
        'cove_topics',
        array( 'post' ),
        array(
          'labels' => $labels,
          'hierarchical' => true,
          'public' => true,
          'show_ui' => false,
          'query_var' => false,
          'rewrite' => false
        )
      );
    }
  }

  public function register_post_types() {
    if (!post_type_exists('episodes')) {
		  register_post_type('episodes', array(
        'labels' => array(
            'name' => __('Full Episodes'),
            'singular_name' => __('Full Episode'),
            'search_items' => __('Search Episodes'),
            'add_new_item' => __('Add New Episode'),
            'edit_item' => __('Edit Episode')
        ),
        'public' => true,
        'has_archive' => true,
        'rewrite' => array(
            'slug' => 'episode'
        ),
        'query_var' => true,
        'exclude_from_search' => true,
        'menu_position' => 5,
        'menu_icon' => 'dashicons-format-video',
        'supports' => array(
            'title',
            'editor',
            'author',
            'excerpt',
            'thumbnail',
            'custom-fields',
            'comments'
        ),
        'taxonomies' => array('post_tag')
      ));
    }
    if (!post_type_exists('videos')) {
	    register_post_type('videos', array(
        'labels' => array(
            'name' => __('Videos'),
            'singular_name' => __('Video'),
            'search_items' => __('Search Videos'),
            'add_new_item' => __('Add New Video'),
            'edit_item' => __('Edit Video')
        ),
        'public' => true,
        'has_archive' => true,
        'rewrite' => array(
            'slug' => 'videos'
        ),
        'query_var' => true,
        'exclude_from_search' => true,
        'menu_position' => 5,
        'menu_icon' => 'dashicons-video-alt2',
        'supports' => array(
            'title',
            'thumbnail',
            'author',
            'custom-fields'
        ),
        'taxonomies' => array(
            'post_tag',
            'topic'
        )
      ));
    }
  }

  public function cove_player_shortcode( $atts ) {
	if (is_single() || is_post_type_archive(array('rundown','making_sense','arts','poetry_series')) || (is_feed())) {
	global $post;
    extract(shortcode_atts(array(
      'id' => $post->ID,
      'legacy_id' => null,
      'player_chrome' => 'show'), 
      $atts));
    if ($legacy_id) {
      $thisid = '';
      /*
      $args = array(
        'post_type' => 'videos',
        'post_status' => 'publish',
        'meta_key' => '_coveam_legacy_id',
        'meta_value' => $legacy_id,
        'posts_per_page' => 1
      );
      $theseposts = new WP_Query($args);
      if ($theseposts->have_posts()){
        while ( $theseposts->have_posts() ) : $theseposts->the_post();
          $thisid = $post->ID;
        endwhile;
      }
      wp_reset_postdata();
      */

      global $wpdb;
      $query = sprintf("SELECT pm.post_id FROM $wpdb->postmeta as pm where pm.meta_key = '_coveam_legacy_id' AND pm.meta_value = %d limit 1",$legacy_id);
      $thisid = $wpdb->get_var($query);
      
      $id = $thisid;
    }
    $theseargs = array('player_chrome' => $player_chrome); 
    $player = coveam_render_player( $id, $theseargs );
    return $player;
   } 
  }
  
  public function get_media_manager_client( $api_key=false, $api_secret=false, $api_endpoint=false ) {
    if (!class_exists('PBS_Media_Manager_API_Client')) {
      return array('errors' => 'Media Manager API Client not present');
    }
    $client_key = !empty(get_option('coveam_mm_api_key')) ? get_option('coveam_mm_api_key') : false;
    $client_secret = !empty(get_option('coveam_mm_api_secret')) ? get_option('coveam_mm_api_secret') : false;
    $client_endpoint = !empty(get_option('coveam_mm_api_endpoint')) ? get_option('coveam_mm_api_endpoint') : false;
    if ($api_key && $api_secret && $api_endpoint) {
      $client_key = $api_key;
      $client_secret = $api_secret;
      $client_endpoint = $api_endpoint;
    }
    if (!$client_key || !$client_secret || !$client_endpoint) {
      return array('errors' => 'Missing key, secret, or endpoint');
    }
    $client = new PBS_Media_Manager_API_Client($client_key, $client_secret, $client_endpoint);
    return $client;
  }

  public function COVEslugify($text) { 
    // replace non letter or digits by -
    $text = preg_replace('~[^\\pL\d]+~u', '-', $text);
    // trim
    $text = trim($text, '-');
    // transliterate
    $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
    // lowercase
    $text = strtolower($text);
    // remove unwanted characters
    $text = preg_replace('~[^-\w]+~', '', $text);
    if (empty($text)) {
      return 'n-a';
    }
    return $text;
  }

  public function MediaManagerTranslateTypeToNumber($str) {
    /* this is a stupid system that is left over for translating between our old data and new */
    if (strtolower($str) == 'full_length') {
      return '0';
    } elseif (strtolower($str) == 'preview') {
      return '1';
    } elseif (strtolower($str) == 'clip') {
      return '4';
    } 
  }

  public function COVETranslateNumberToType($num) {
    /* this is a stupid system that is left over for translating between our old data and new */
    if ($num == 0) {
      return 'full_length'; 
    } elseif ($num == 1) {
      return 'preview';
    } else { 
      return 'clip';
    }
  }

  public function determineMediaManagerStatus($obj) {
    /* this function takes the complex data array from Media Manager and works out if the asset is actually available somehow */
    if (empty($obj['attributes'])) {
      return 'not_found';
    }
    $data = $obj['attributes'];

    // date restrictions override the publish_state
    $now = time();
    $endtimeobj = !empty($data['availabilities']['public']['end']) ? new DateTime($data['availabilities']['public']['end']) : false;
    $endtime = is_object($endtimeobj) ? $endtimeobj->format('U') : false;
    $starttimeobj = !empty($data['availabilities']['public']['start']) ?  new DateTime($data['availabilities']['public']['start']) : false;
    $starttime = is_object($starttimeobj) ? $starttimeobj->format('U') : false;

    if ($starttime > $now) {
      return "not_yet_available";
    }
    if ($endtime && $endtime < $now) {
      return "expired";
    }

    if ($data['publish_state'] == 1) {
      // because in the old COVE API this was the good string.  Good as anything else
      return 'available';
    }
    $ingest_status = array();
    if (!empty($data['original_video'])) {
      if (!empty($data['original_video']['ingestion_error'])){
        $ingest_status['ingestion_error'] = $data['original_video']['ingestion_error'];
      }
      $video_ingest_status = $data['original_video']['ingestion_status'];
      if ($video_ingest_status !== 'done') {
        $ingest_status['ingestion_status'] = $video_ingest_status;
      }
    }
    if (!empty($data['original_caption'])) {
      if (!empty($data['original_caption']['ingestion_error'])){
        $ingest_status['caption_error'] = $data['original_caption']['ingestion_error'];
      }
      // PBS is going to change this value someday
      if ($data['original_caption']['ingestion_status'] != 1 ) {
        $ingest_status['caption_status'] = $data['original_caption']['ingestion_status'];
      }
    } 
    if (empty($data['images'][0]['image'])) {
      $ingest_status['image'] = 'no image';
    }

    // return an array if not null
    if (count($ingest_status) > 0) {
      return $ingest_status;
    }

    // fallback for other cases 
    if (!$data['publish_state']) {
      return "not_published";
    }
    // final fallback
    return "could_not_determine";
  }

  public function get_latest_media_manager_episode($season_id = false) {
    $client = $this->get_media_manager_client();
    $result = $client->get_season_episodes($season_id);
    if (!empty($result['errors'])) {
      return $result;
    }
    foreach ($result as $episode) {
      if (!empty($episode['attributes']['ordinal'])) {
        // just return the first one, don't care about the others
        return $episode['attributes'];
      }
    }
  }

  public function create_media_manager_episode( $post_id = false, $season_id = false, $postary ) {
    /* function can be called either saving an episode post or via wp_cron.
     * defaults to creating a new episode with today's date in the current season 
     * function saves the returned cid as a postmeta field for the given post */
    if (!$post_id) {
      return array('errors' => 'no post_id' );
    }
    if (!$season_id) {
      $seasons = get_option('coveam_mm_season_id_list');
      $season_id = $seasons[0]['id'];
    }
    if (!$season_id) {
      return array( 'errors' => 'no season_id' ); 
    }
    $attribs = $this->map_post_fields_to_episode_array($postary); 

    // default values for the episode
    $datestring = get_the_date('M j, Y');
    if (empty($attribs['title'])) {
       $attribs['title'] = 'Full Episode for ' . $datestring;
    }
    if (empty($attribs['description_short'])) {
      $attribs['description_short'] = $attribs['title'];
    } 
    if (empty($attribs['description_long'])) {
      $attribs['description_long'] = $attribs['title'];
    } 
    if (empty($attribs['slug'])) {
      $attribs['slug'] = $this->COVEslugify($attribs['title']) . "-" . time();
    }
    if (empty($attribs['ordinal'])) {
      $latest = $this->get_latest_media_manager_episode($season_id);
      $attribs['ordinal'] = ($latest['ordinal'] + 1);
    } 
    $client = $this->get_media_manager_client();
    $result = $client->create_child($season_id, 'season', 'episode', $attribs);
    if (!empty($result['errors'])) {
      return $result;
    }
    // note that update_post_meta returns false on failure and also on an unchanged value
    // this will give me a literal true if an update, and a meta id if a new field
    $meta_create = update_post_meta($post_id, '_pbs_media_manager_episode_cid', $result);
    if (! $meta_create ) {
      return array('errors' => 'new meta value not created');
    }
    // this will be the cid;
    return $result;
  }

  


  public function import_media_manager_episode( $postid = false, $episode_id = '') {
    /* function imports data based on the PBS Content ID and saves it to postmeta.  Returns the retrieved object or 'errors' array
     */
    if (!$postid) {
      return array('errors' => 'no post_id');
    }
    if (!$episode_id ) {
      return array('errors' => 'no asset_id');
    }
    $client = $this->get_media_manager_client();
    if (!empty($client->errors)) { return $client; }
    $episode = $client->get_episode($episode_id);
    if (!empty($episode['errors'])) { return $episode; }
    update_post_meta($postid, '_pbs_media_manager_episode_cid', $episode_id);
    $temp_obj = $episode['data'];
    update_post_meta($postid, '_pbs_media_manager_season_cid', $temp_obj['attributes']['season']['id']);
    update_post_meta($postid, '_pbs_media_manager_season_title', sanitize_text_field((empty($temp_obj['attributes']['season']['attributes']['title']) ? $temp_obj['attributes']['season']['attributes']['ordinal'] : $temp_obj['attributes']['season']['attributes']['title'])) );
    update_post_meta($postid, '_pbs_media_manager_episode_title', sanitize_text_field($temp_obj['attributes']['title'])); 
    update_post_meta($postid, '_pbs_media_manager_episode_desc_long', sanitize_text_field($temp_obj['attributes']['description_long']));
    update_post_meta($postid, '_pbs_media_manager_episode_desc_short', sanitize_text_field($temp_obj['attributes']['description_short']));
    update_post_meta($postid, '_pbs_media_manager_episode_ordinal', $temp_obj['attributes']['ordinal']);
    update_post_meta($postid, '_pbs_media_manager_episode_airdate', $temp_obj['attributes']['premiered_on']);
    return $episode;
  }

  public function schedule_media_manager_asset_refresh_if_needed($postid = false, $assetary = array()) {
    if (!$postid) {
      return array('errors' => 'no post_id');
    }
    if (empty($assetary['attributes'])) {
      return array('errors' => 'no asset');
    }
    $assetid = $assetary['id'];
    $attribs = $assetary['attributes'];
    $retry = false;
    if (!empty($attribs['original_video']['ingestion_status'])) {
      if (!in_array($attribs['original_video']['ingestion_status'], array('done', 'failed', 'deletion_failed') )) {
        $retry = true;    
      }
    }
    if (!empty($attribs['original_caption']['ingestion_status'])) {
      if (!in_array($attribs['original_caption']['ingestion_status'], array(1, 0, 11) ) ) {
        $retry = true;
      }
    }
    if ($retry) {
      $previous = wp_next_scheduled('coveam_import_media_manager_asset', array( $postid, $assetid ));
      if ($previous) {
        wp_unschedule_event( $previous, 'coveam_import_media_manager_asset', array( $postid, $assetid ));
      }
      wp_schedule_event((time() + 300), 'coveam_import_media_manager_asset', array( $postid, $assetid ));
    }
  }

  public function import_media_manager_asset( $postid = false, $asset_id = '') {
    /* function imports data based on the PBS Content ID and saves it to postmeta.  Returns the retrieved object or 'errors' array
     */
    if (!$postid) {
      return array('errors' => 'no post_id');
    }
    if (!$asset_id ) {
      return array('errors' => 'no asset_id');
    }
    $client = $this->get_media_manager_client();
    if (!empty($client->errors)) { return $client; }
    $asset = $client->get_asset($asset_id, true);
    if (!empty($asset['errors'])) { return $asset; }
    update_post_meta($postid, '_coveam_video_asset_guid', $asset_id);
    $temp_obj = $asset['data'];
    update_post_meta($postid, '_coveam_video_title', sanitize_text_field($temp_obj['attributes']['title'])); 
    update_post_meta($postid, '_coveam_description', sanitize_text_field($temp_obj['attributes']['description_long']));
    update_post_meta($postid, '_coveam_shortdescription', sanitize_text_field($temp_obj['attributes']['description_short']));
    update_post_meta($postid, '_coveam_video_slug', $temp_obj['attributes']['slug']);
    update_post_meta($postid, '_coveam_cove_player_id', $temp_obj['attributes']['legacy_tp_media_id']);
    update_post_meta($postid, '_coveam_airdate', $temp_obj['attributes']['premiered_on'] . ' 19:00:00');
    update_post_meta($postid, '_coveam_duration', $temp_obj['attributes']['duration']);
    update_post_meta($postid, '_pbs_media_manager_episode_cid', $temp_obj['attributes']['episode']['id']);
    update_post_meta($postid, '_pbs_media_manager_episode_title', sanitize_text_field($temp_obj['attributes']['episode']['attributes']['title']));

    //translate to our system
    update_post_meta($postid, '_coveam_video_fullprogram', $this->MediaManagerTranslateTypeToNumber($temp_obj['attributes']['object_type']));
    
    $statusobj = $this->determineMediaManagerStatus($temp_obj);
    if (! is_array($statusobj)) {
      $statusline = $statusobj;
    } else {
      $statusline = json_encode($statusobj);
      $this->schedule_media_manager_asset_refresh_if_needed($postid, $temp_obj);
    }
    update_post_meta($postid, '_coveam_covestatus', $statusline);

    $rights = (!is_null($temp_obj['attributes']['availabilities']['public']['end'])) ? 'Limited' : 'Public';
    update_post_meta($postid, '_coveam_rights', $rights);

    //ingest related fields
    // Note that for video and caption, the object name will probably change to 'source' from 'destination'
    $archive_video = !empty($temp_obj['attributes']['original_video']['source']) ? $temp_obj['attributes']['original_video']['source'] : '';
    update_post_meta($postid, '_coveam_video_url', $archive_video);

    $archive_caption = !empty($temp_obj['attributes']['original_caption']['source']) ? $temp_obj['attributes']['original_caption']['source'] : '';
    update_post_meta($postid, '_coveam_video_caption', $archive_caption);

    $archive_image = !empty($temp_obj['attributes']['images'][0]['image']) ? $temp_obj['attributes']['images'][0]['image'] : '';
    update_post_meta($postid, '_coveam_video_image', $archive_image);
   
    // ugly old function that I'll replace someday 
    coveam_update_video_status($postid);

    return $asset;
  }

  public function create_media_manager_asset( $post_id = false, $episode_id = false, $postary ) {
    if (!$post_id) {
      return array('errors' => 'no post_id' );
    }
    if (!$episode_id) {
      return array('errors' => 'no episode_id' );
    }
    $attribs = $this->map_post_fields_to_asset_array($postary); 

    if (empty($attribs['title'])) {
      return array('errors' => 'required field title missing');
    }
    $attribs['slug'] = $this->COVEslugify($attribs['title']) . '-' . time();

    $client = $this->get_media_manager_client();
    $result = $client->create_child($episode_id, 'episode', 'asset', $attribs);
    if (!empty($result['errors'])) {
      return $result;
    }
    // note that update_post_meta returns false on failure and also on an unchanged value
    // this will give me a literal true if an update, and a meta id if a new field
    $meta_create = update_post_meta($post_id, '_coveam_video_asset_guid', $result);
    if (! $meta_create ) {
      return array('errors' => 'new meta value not created');
    }
    // this will be the cid;
    return $result;
  }

  private function map_post_fields_to_asset_array($fields) {
    $attribs = array();
    // required fields first
    $attribs['title'] = $fields['_coveam_video_title'];
    $attribs['description_long'] =  $fields['_coveam_description'];
    $attribs['description_short'] =  $fields['_coveam_shortdescription'];
    $attribs['object_type'] = $this->COVETranslateNumberToType($fields['_coveam_video_fullprogram']);
    $attribs['auto_publish'] = true;

    if (!empty($fields['_pbs_media_manager_episode_cid'])) {
      $attribs['episode'] = $fields['_pbs_media_manager_episode_cid'];
    }
    //ingest related -- submitting a null video or caption entry triggers a file delete, not submitting it at all does nothing
    if (!empty($fields['_coveam_video_url'])){
      $attribs['video'] = array("profile" => "hd-1080p-mezzanine-16x9", "source" => $fields['_coveam_video_url']);
    } else if ($fields['delete_current_video'] == true) {
      $attribs['video'] = null;
    }
    if (!empty($fields['_coveam_video_caption'])){
      $attribs['caption'] = $fields['_coveam_video_caption'];
    } else if ($fields['delete_current_caption'] == true) {
      $attribs['caption'] = null;
    }
    // images are automatically just replaced
    if (!empty($fields['_coveam_video_image'])){
      $attribs['images'][] = array("profile" => "asset-mezzanine-16x9", "source" => $fields['_coveam_video_image'] );
    }

    if (empty($fields['_coveam_airdate'])) {
      $date = new DateTime('now');
    } else {
      $date = new DateTime($fields['_coveam_airdate']);
    } 
    $formatted_date = $date->format('Y-m-d');
    $attribs['premiered_on'] = $formatted_date;
    $attribs['encored_on'] = $formatted_date;

    $attribs['availabilities']['public']['start'] = $date->format('Y-m-d\TH:i:s.u\Z');
    $attribs['availabilities']['all_members']['start'] = $attribs['availabilities']['public']['start'];
    $attribs['availabilities']['station_members']['start'] = $attribs['availabilities']['public']['start'];
    if (!empty($fields['_coveam_rights']) && $fields['_coveam_rights'] == 'Limited') {
      $date->modify('+30 day');
      $attribs['availabilities']['public']['end'] = $date->format('Y-m-d\TH:i:s.u\Z');
    } else {
      $attribs['availabilities']['public']['end'] = null;
    }
    $attribs['availabilities']['all_members']['end'] = $attribs['availabilities']['public']['end'];
    $attribs['availabilities']['station_members']['end'] = $attribs['availabilities']['public']['end'];
        

    return $attribs;
  }

  private function map_post_fields_to_episode_array($fields) {
    $attribs = array();
    // required fields first
    $attribs['title'] = $fields['_pbs_media_manager_episode_title'];
    $attribs['description_long'] =  $fields['_pbs_media_manager_episode_desc_long'];
    $attribs['description_short'] =  $fields['_pbs_media_manager_episode_desc_short'];
    $attribs['ordinal'] = $fields['_pbs_media_manager_episode_ordinal'];
    $airdate = (!empty( $fields['_pbs_media_manager_episode_airdate'])) ? $fields['_pbs_media_manager_episode_airdate'] : false;
    if (!$airdate) {
      $date = new DateTime('now');
      $airdate = $date->format('Y-m-d');
    }
    $attribs['premiered_on'] = $airdate;
    $attribs['encored_on'] = $airdate;
    return $attribs;
  }

  public function update_media_manager_episode( $post_id, $episode_id, $postary ) {
    /* this function expects $_POST data */
    if (!$post_id) {
      return array('errors' => 'no post_id');
    }
    if (!$episode_id ) {
      return array('errors' => 'no episode_id');
    }
    $client = $this->get_media_manager_client();
    if (!empty($client->errors)) { return $client; }
    $attribs = $this->map_post_fields_to_episode_array($postary); 
    $response = $client->update_object($episode_id, 'episode', $attribs);
    return $response;
  }

  public function update_media_manager_asset( $post_id, $asset_id, $postary ) {
    /* this function expects $_POST data */
    if (!$post_id) {
      return array('errors' => 'no post_id');
    }
    if (!$asset_id ) {
      return array('errors' => 'no asset_id');
    }
    $client = $this->get_media_manager_client();
    if (!empty($client->errors)) { return $client; }
    $attribs = $this->map_post_fields_to_asset_array($postary); 
    $response = $client->update_object($asset_id, 'asset', $attribs);
    return $response;
  }

  public function get_episode_option_list($monthnum = 0, $year = 0) {
    $html = "";
    $args = array('post_type' => 'episodes', 'meta_key' => '_pbs_media_manager_episode_cid', 'orderby' => 'date', 'order' => 'desc', 'posts_per_page' => 40);
    if ($monthnum > 0) {
      $args['monthnum'] = $monthnum;
    }
    if ($year > 0) {
      $args['year'] = $year;
    }
		$my_query = new WP_Query($args); 
		while ($my_query->have_posts()) : $my_query->the_post(); 
      $thiscid = get_post_meta(get_the_ID(), '_pbs_media_manager_episode_cid', true);
      $html .= "<option value='". $thiscid . "'>".get_the_title(get_the_ID())."</option>";
		endwhile; 
    return $html;
  }

  public function ajax_get_episode_option_list() {
    $html = $this->get_episode_option_list($monthnum = $_GET['month'], $year = $_GET['year']);
    if (empty($html)) {
      $html .= "<option value=''>sorry, no results</option>";
    } else {
      $html = "<option value=''>select one</option>" . $html;
    }
    wp_die($html);
  }

  public function update_media_manager_season_list() {
    $show_id = get_option('coveam_mm_show_id');
    if (empty($show_id)) { return array('errors' => 'no show id set!'); }
    $client = $this->get_media_manager_client();
    if (!empty($client->errors)) { return $client; }
    $seasons = $client->get_show_seasons($show_id);
    if (!empty($seasons['errors'])) { return $seasons; }
    $seasonary = array();
    foreach ($seasons as $season => $ary) {
      $label = !empty($ary['attributes']['title']) ? $ary['attributes']['title'] : $ary['attributes']['ordinal'] ;
      $seasonary[$ary['id']] = array('value' => $ary['id'], 'label' => $label);
    }
    $result = false;
    if (!empty($seasonary)) {
      $result = update_option('coveam_mm_season_id_list', $seasonary, false);
    }
    return $result;
  }

}
