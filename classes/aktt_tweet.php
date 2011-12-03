<?php

class AKTT_Tweet {
	var $post_id = null;
	var $raw_data = null;
		
	static $prefix = '_aktt_tweet_';
	
	/**
	 * Set up the tweet with the ID from twitter
	 *
	 * @param mixed $data - tweet id or full tweet object from twitter API
	 * @param bool $from_db - Whether to auto-populate this object from the DB
	 */
	function __construct($data, $from_db = false) {
		if (is_object($data)) {
			$this->populate_from_twitter_obj($data);
		}
		else {
			// Assign the tweet ID to this object
			$this->id = $data;
			
			// Flag to populate the object from the DB on construct
			if ($from_db == true) {
				$this->populate_from_db();
			}
		}
	}
	
	
	/**
	 * Allows the object's properties to be populated from a post object in the DB
	 *
	 * @return void
	 */
	function populate_from_db() {
		$post = $this->get_post(AKTT::$post_type);

		if (is_wp_error($post) || empty($post)) {
			return false;
		}
		
		$this->raw_data = get_post_meta($post->ID, self::$prefix.'raw_data', true);
		$this->data = json_decode($this->raw_data);
	}
	
	
	/**
	 * Populates the object from a twitter API response object
	 *
	 * @param object $tweet_obj 
	 * @return void
	 */
	function populate_from_twitter_obj($tweet_obj) {
		$this->data = $tweet_obj;
		$this->raw_data = json_encode($tweet_obj);
		$this->id = $tweet_obj->id_str;
	}
	
	/**
	 * Accessor function for tweet id
	 *
	 * @return string|null
	 */
	public function id() {
		return (isset($this->data) ? $this->data->id_str : null);
	}
	
	/**
	 * Accessor function for tweet text shortened for post title
	 *
	 * @return string
	 */
	public function title() {
		if (isset($this->data)) {
			$title = substr($this->data->text, 0, 50);
			if (strlen($this->data->text) > 50) {
				$title = $title.'...';
			}
		}
		else {
			$title = null;
		}
		return $title;
	}
	
	/**
	 * Accessor function for tweet text
	 *
	 * @return string
	 */
	public function content() {
		return (isset($this->data) ? $this->data->text : null);
	}
	
	/**
	 * Accessor function for tweet date/time
	 *
	 * @return string
	 */
	public function date() {
		return (isset($this->data) ? $this->data->created_at : null);
	}
	
	/**
	 * Accessor function for tweet author's username
	 *
	 * @return string
	 */
	public function username() {
		return (isset($this->data) ? $this->data->user->screen_name : null);
	}
	
	/**
	 * Accessor function for tweet reply-to username
	 *
	 * @return string
	 */
	public function reply_screen_name() {
		return (isset($this->data) ? $this->data->in_reply_to_screen_name : null);
	}
	
	/**
	 * Accessor function for tweet reply-to tweet id
	 *
	 * @return string
	 */
	public function reply_id() {
		return (isset($this->data) ? $this->data->in_reply_to_status_id_str : null);
	}
	
	/**
	 * Accessor function for tweet's hashtags
	 *
	 * @return string
	 */
	public function hashtags() {
		return (isset($this->data) && isset($this->data->entities) ? $this->data->entities->hashtags : array());
	}
	
	/**
	 * Accessor function for tweet's mentions
	 *
	 * @return string
	 */
	public function mentions() {
		return (isset($this->data) && isset($this->data->entities) ? $this->data->entities->user_mentions : array());
	}
	
	/**
	 * Accessor function for tweet's URLS
	 *
	 * @return string
	 */
	public function urls() {
		return (isset($this->data) && isset($this->data->entities) ? $this->data->entities->urls : array());
	}
	
	/**
	 * Takes the twitter date format and gets a timestamp from it
	 *
	 * @param string $date - "Fri Aug 05 20:33:38 +0000 2011"
	 * @return int - timestamp
	 */
	static function twdate_to_time($date) {
		$parts = explode(' ', $date);
		$date = strtotime($parts[1].' '.$parts[2].', '.$parts[5].' '.$parts[3]);
		return $date;
	}
	
	
	/**
	 * See if the tweet ID matches any tweet ID post meta value
	 *
	 * @return bool
	 */
	function exists() {
		$test = $this->get_post(AKTT::$post_type);
		return (bool) (count($test) > 0);
	}
	
	
	/**
	 * Checks the posts to see if this tweet has been attached to 
	 * any of them.
	 *
	 * @return bool
	 */
	function tweet_post_exists() {
		$posts = $this->get_post('post');
		return (bool) (count($posts) > 0);
	}

	
	/**
	 * Generate a GUID for WP post based on tweet ID.
	 *
	 * @return mixed
	 */
	static function guid_from_twid($tweet_id = null) {
		return (empty($tweet_id) ? false : 'http://twitter-'.$tweet_id);
	}


	/**
	 * Generate a GUID for WP post based on this objects' tweet ID.
	 *
	 * @uses guid_from_twid
	 *
	 * @return mixed
	 */
	function guid() {
		return $this->guid_from_twid($this->id());
	}
	
	
	/**
	 * Grabs the post from the DB
	 * 
	 * @uses get_posts
	 *
	 * @return obj|false 
	 */
	function get_post($post_type) {
// TODO (future) - search by GUID instead?
		$posts = get_posts(array(
			'post_type' => $post_type,
			'meta_key' => self::$prefix.'id',
			'meta_value' => $this->id,
		));
		return is_array($posts) ? array_shift($posts) : false;
	}
	

	/**
	 * Twitter data changed - users still expect anything starting with @ is a reply
	 *
	 * @return bool
	 */
	function is_reply() {
		return (bool) (substr($this->content(), 0, 1) == '@' || !empty($this->data->in_reply_to_screen_name));
	}
	

	/**
	 * Is this a retweet?
	 *
	 * @return bool
	 */
	function is_retweet() {
		return (bool) (substr($this->content(), 0, 2) == 'RT' || !empty($this->data->retweeted_status));
	}
	
	
	/**
	 * Look up whether this tweet came from a broadcast
	 *
	 * @return bool
	 */
	function was_broadcast() {
		$was_broadcast = false;
		if (isset($this->data) && !empty($this->data->source)) {
			$was_broadcast = (bool) (strpos($this->data->source, 'sopresto.mailchimp.com') !== false);
		}
		else {
			$was_broadcast = (bool) (strpos($this->content(), home_url()) !== false);
		}
		return $was_broadcast;
	}
	
	function link_entities() {
		$entities = array();
// mentions
		$anywhere = Social::option('twitter_anywhere_api_key');
		if (empty($anywhere) || is_feed()) {
			foreach ($this->mentions() as $entity) {
				$entities['start_'.str_pad($entity->indices[0], 5, '0', STR_PAD_LEFT)] = array(
					'find' => $entity->screen_name,
					'replace' => AKTT::profile_link($entity->screen_name),
					'start' => $entity->indices[0],
					'end' => $entity->indices[1],
				);
			}
		}
// hashtags
		foreach ($this->hashtags() as $entity) {
			$entities['start_'.str_pad($entity->indices[0], 5, '0', STR_PAD_LEFT)] = array(
				'find' => $entity->text,
				'replace' => AKTT::hashtag_link($entity->text),
				'start' => $entity->indices[0],
				'end' => $entity->indices[1],
			);
		}
// URLs
		foreach ($this->urls() as $entity) {
			$entities['start_'.str_pad($entity->indices[0], 5, '0', STR_PAD_LEFT)] = array(
				'find' => $entity->url,
				'replace' => '<a href="'.esc_url($entity->expanded_url).'">'.esc_html($entity->display_url).'</a>',
				'start' => $entity->indices[0],
				'end' => $entity->indices[1],
			);
		}
		ksort($entities);

		$str = $this->content();
		$diff = 0;
		foreach ($entities as $entity) {
			$start = $entity['start'] + $diff;
			$end = $entity['end'] + $diff;
// $log = array();
// $log[] = 'diff: '.$diff;
// $log[] = 'entity start: '.$entity['start'];
// $log[] = 'entity start chars: '.substr($this->content(), $entity['start'], 3);
// $log[] = 'diff start: '.$start;
// $log[] = 'diff start chars: '.substr($str, $start, 3);
// $log[] = 'entity end: '.$entity['end'];
// $log[] = 'diff end: '.$end;
// $log[] = 'find len: '.strlen($entity['find']);
// $log[] = 'find: '.htmlspecialchars($entity['find']);
// $log[] = 'replace len: '.strlen($entity['replace']);
// $log[] = 'replace: '.htmlspecialchars($entity['replace']);
// echo '<p>'.implode('<br>', $log).'</p>';
			$str = substr_replace($str, $entity['replace'], $start, ($end - $start));
			$diff += strlen($entity['replace']) - ($end - $start);
		}
		return $str;
	}
	
	
	/**
	 * Creates an aktt_tweet post_type with its meta
	 *
	 * @param array $args 
	 * @return void
	 */
	function add() {
		$tax_input = array(
			'aktt_account' => array($this->username()),
			'aktt_hashtags' => array(),
			'aktt_mentions' => array(),
			'aktt_types' => array(),
		);
		foreach ($this->hashtags() as $hashtag) {
			$tax_input['aktt_hashtags'][] = $hashtag->text;
		}
		foreach ($this->mentions() as $mention) {
			$tax_input['aktt_mentions'][] = $mention->screen_name;
		}
		$special = 0;
		if ($this->is_reply()) {
			$special++;
			$tax_input['aktt_types'][] = 'reply';
		}
		else {
			$tax_input['aktt_types'][] = 'not-a-reply';
		}
		if ($this->is_retweet()) {
			$special++;
			$tax_input['aktt_types'][] = 'retweet';
		}
		else {
			$tax_input['aktt_types'][] = 'not-a-retweet';
		}
		if ($this->was_broadcast()) {
			$special++;
			$tax_input['aktt_types'][] = 'social-broadcast';
		}
		else {
			$tax_input['aktt_types'][] = 'not-a-social-broadcast';
		}
		if (!$special) {
			$tax_input['aktt_types'][] = 'status';
		}
		
		// Build the post data
		$data = apply_filters('aktt_tweet_add', array(
			'post_title' => $this->title(),
			'post_content' => $this->content(),
			'post_status' => 'publish',
			'post_type' => AKTT::$post_type,
			'post_date' => date('Y-m-d H:i:s', self::twdate_to_time($this->date()) + (get_option('gmt_offset') * 3600)),
			'guid' => $this->guid(),
//			'tax_input' => $tax_input, // see below...
		));
		
		$this->post_id = wp_insert_post($data, true);

		if (is_wp_error($this->post_id)) {
			AKTT::log('WP_Error:: '.$this->post_id->get_error_message());
			return false;
		}

// have to set up taxonomies after the insert in case we are in a context without
// a 'current user' - see: http://core.trac.wordpress.org/ticket/19373

		foreach ($tax_input as $tax => $terms) {
			if (count($terms)) {
				wp_set_post_terms($this->post_id, $terms, $tax);
			}
		}
		
		update_post_meta($this->post_id, self::$prefix.'id', $this->id());
		update_post_meta($this->post_id, self::$prefix.'raw_data', addslashes($this->raw_data));
		
		// Allow things to hook in here
		do_action('AKTT_Tweet_add', $this);
		
		return true;
	}
	
	function create_blog_post($args = array()) {
		extract($args);
		
		// Add a space if we have a prefix
		$title_prefix = empty($title_prefix) ? '' : $title_prefix.' ';

		// Build the post data
		$data = array(
			'post_title' => $title_prefix.$this->title(),
			'post_content' => $this->content(),
			'post_author' => $post_author,
			'tax_input' => array(
				'category' => array($post_category),
				'post_tag' => array_map('trim', explode(',', $post_tags)),
			),
			'post_status' => 'publish',
			'post_type' => 'post',
			'post_date' => date('Y-m-d H:i:s', self::twdate_to_time($this->meta['created_at'])),
			'guid' => $this->guid().'-post'
		);

		$this->blog_post_id = wp_insert_post($data, true);
		
		if (is_wp_error($this->blog_post_id)) {
			AKTT::log('WP_Error:: '.$this->blog_post_id->get_error_message());
			return false;
		}

		set_post_format($this->blog_post_id, 'status');

		update_post_meta($this->blog_post_id, self::$prefix.'id', $this->id()); // twitter's tweet ID
		update_post_meta($this->blog_post_id, self::$prefix.'post_id', $this->post_id); // twitter's post ID
		
		// Add it to the tweet's post_meta as well
		update_post_meta($this->post_id, self::$prefix.'blog_post_id', $this->blog_post_id);

		// Let Social know to aggregate info about this post
		foreach (AKTT::$accounts as $aktt_account) {
			if ($aktt_account->social_acct->id() == $this->data->user->id_str) {
				$account = $aktt_account->social_acct;
				break;
			}
		}
		$social = Social::instance();
		$social->add_broadcasted_id($this->blog_post_id, 'twitter', $this->id(), $this->content(), $account, null);
		
		// Let the account know we were successful
		return true;
	}
	
	
	/**
	 * Gets the name of a tag from its ID
	 *
	 * @param int $tag_id 
	 * @return string
	 */
	function get_tag_name($tag_id) {
		$tag_name = get_term_field('name', $tag_id, 'post_tag', 'db');
		if (is_wp_error($tag_name)) {
			$tag_name = '';
		}
		return $tag_name;
	}
	
}

