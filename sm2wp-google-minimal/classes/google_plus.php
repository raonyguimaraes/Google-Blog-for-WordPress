<?php

require_once(ABSPATH.'wp-admin/includes/image.php');
require_once(plugin_dir_path(dirname(__FILE__)).'classes/common.php');

class GFW_Comment {
    public $id = null;
    public $postId = null;
    public $author = null;
    public $authorURL = null;
    public $content = null;
    public $date = null;

    public function __construct($postId, $commentId, $avatar, $author, $authorURL, $content, $date) {
        $this->id = $commentId;
        $this->postId = $postId;
        $this->avatar = $avatar;
        $this->author = $author;
        $this->authorURL = $authorURL;
        $this->content = $content;
//         gmdate('Y-m-d H:i:s', strtotime($published_date));
        if (get_option('timezone_string')) {
            $this->date = date('Y-m-d H:i:s', strtotime($date));
            $this->dategmt = gmdate('Y-m-d H:i:s', strtotime($date));
        } else {
            $this->date = get_date_from_gmt(date('Y-m-d H:i:s', strtotime($date)), 'Y-m-d H:i:s');
            $this->dategmt = date('Y-m-d H:i:s', strtotime($date));
        }
    }

    public function save_to_wordpress() {
        $wp_comment = array(
            'comment_post_ID' => $this->postId,
            'comment_author' => $this->author,
            'comment_author_email' => $this->avatar,
            'comment_author_url' => $this->authorURL,
            'comment_content' => $this->content,
            'type' => 'comment',
            'comment_parent' => 0,
            'user_id' => 0,
            'comment_author_IP' => 'Google+',
            'comment_agent' => $this->id,
            'comment_date' => $this->date,
            'comment_date_gmt' => $this->dategmt,
            'comment_approved' => 1
        );

        return wp_insert_comment($wp_comment, true) !== false;

    }
}

class GFW_Post extends SM2WP_Post {
    public $id = null;
    public $postId = null;
    public $url = null;
    protected $_type = null;
    protected $_content = null;
    protected $_date = null;
    protected $_dategmt = null;
    protected $_images = array();
    protected $_video = null;
    protected $_article = null;
    protected $_title = null;
    protected $_album = null;

    protected $_parent = null;
    protected $_annotation = null;

    protected $_originally_posted_by;
    protected $_author_url;

    public $reshares = 0;
    public $plusones = 0;

    public function __construct($parent, $id, $url, $type, $content, $date, $annotation='', $poster=null, $poster_url, $images=array(), $video=null, $article=array(), $album=array()) {
        $this->id = $id;
        $this->url = $url;
        $this->_parent = $parent;
        $this->_type = $type;
        $this->_content = str_replace('<br>', '<br />', $content);
        $this->_annotation = str_replace('<br>', '<br />', $annotation);
        $this->_originally_posted_by = $poster;
        $this->_author_url = $poster_url;
        if (get_option('timezone_string')) {
            $this->_date = date('Y-m-d H:i:s', strtotime($date));
            $this->_dategmt = gmdate('Y-m-d H:i:s', strtotime($date));
        } else {
            $this->_date = get_date_from_gmt(date('Y-m-d H:i:s', strtotime($date)), 'Y-m-d H:i:s');
            $this->_dategmt = date('Y-m-d H:i:s', strtotime($date));
        }
        $this->_images = $images;
        $this->_video = $video;
        $this->_article = $article;
    }

    public function add_image($image, $thumbnail, $url, $link=null) {
        $this->_images[] = array('thumb' => $thumbnail, 'full' => $image, 'url' => $url);
    }

    public function add_video($video, $title=null) {
        $this->_video = $video;
        $this->_title = $title;
    }

    public function add_article($url, $title, $annotation, $thumbnail=null) {
        $this->_article = array($url, strip_tags($title), $annotation, $thumbnail);
        $this->_title = $title;   
    }

    public function add_album($url, $title) {
        $this->_album = array($url, strip_tags($title));
    }

    protected function _has_first_line_title() {
        return (($pos = strpos($this->_annotation.$this->_content, '<br />')));# && strpos($this->_content, '<br />') < 80);
    }

    public function get_annotation() {
      if ($this->_has_first_line_title() && trim($this->_annotation)) {
       return trim(substr($this->_annotation, strpos($this->_annotation, '<br />')));
      }
    }

    public function get_title() {
        $t = '';
        if($this->_title){
            $t = $this->_title;
        }
        elseif ($this->_has_first_line_title()) { // Title First Line
            if (trim($this->_annotation)) {
              $t = rtrim(strip_tags(substr($this->_annotation, 0, strpos($this->_annotation, '<br />'))), '.');
            }
            if (!$t) {
              $t = rtrim(strip_tags(substr($this->_content, 0, strpos($this->_content, '<br />'))), '.');
            }
        }
        else {
            $c = $this->_annotation.$this->_content;
            $t = rtrim(strip_tags(substr($c, 0, strpos($c, '.'))), '.');
            if (!$t) {
              $t = rtrim(strip_tags(substr($c, 0, 80)), '.');
              $t = strlen($c) > 80 ? $t.'...' : $t;
            }
        }
        return trim($t) ? trim($t) : 'Untitled';
    }


    public function get_content() {
        $images = $thumbs = $video = $article = $credit = '';
        $content = get_option('gfw_template', "{{if-reshare-start}}<p>{{reshared-annotation}}</p><p><i>Originally shared by <a href='{{original-poster-url}}'>+{{original-poster-name}}</a></i></p>{{if-reshare-end}}<p>{{content}}</p>{{if-photo-start}}<div><a href='{{photo-url}}'><img src='{{photo}}' /></a></div><div>{{loop-thumbnail-start}}<a href='{{album-thumbnail-url}}' style='width:50px;height:50px;display:inline-block;background-size:cover;background-image:url(\"{{album-thumbnail}}\");'></a> {{loop-thumbnail-end}}</div>{{if-album-start}}<br /><p><a href='{{album-url}}'>In Album {{album-title}}</a></p>{{if-album-end}}{{if-photo-end}}{{if-video-start}}<div><iframe type='text/html' width='100%' height='385' src='{{video}}' frameborder='0'></iframe></div>{{if-video-end}}{{if-link-start}}<p><a href='{{link-url}}'><img style='display:block;' src='{{link-image}}' border='0' />{{link-title}}</a><br />{{link-description}}</p>{{if-link-end}}");

        if ($this->_has_first_line_title() && !trim($this->_annotation)) { // Title First Line
            $c = trim(substr($this->_content, strpos($this->_content, '<br />')));
        } else {
          $c = trim($this->_content);
        }

        list($linkUrl, $linkTitle, $linkDescription, $linkImage) = ($this->_article ? $this->_article : array('','','',''));
        list($albumUrl, $albumTitle) = ($this->_album ? $this->_album : array('','','',''));

        if (get_option('gfw_remove_hashtags', false)) $c = $this->_remove_hashtags($c);
        @preg_match('/{{loop-thumbnail-start}}(.*){{loop-thumbnail-end}}/msU', $content, $matches);

        $thumbnailTemplate = $matches[1];
        if ($thumbnailTemplate && count($this->_images) > 1) {
            foreach (array_slice($this->_images, 1) as $t) {
                $thumbs .= str_replace(array('{{album-thumbnail}}', '{{album-thumbnail-url}}'), array($t['thumb'], $t['url']), $thumbnailTemplate);
            }
        }

        $content = str_replace(array('{{title}}',
                                     '{{photo}}',
                                     '{{photo-url}}',
                                     '{{video}}',
                                     '{{link-url}}',
                                     '{{link-title}}',
                                     '{{link-image}}',
                                     '{{link-description}}',
                                     '{{reshared-annotation}}',
                                     '{{original-poster-name}}',
                                     '{{original-poster-url}}',
                                     '{{content}}',
                                     '{{google-url}}',
                                     '{{google-url-encoded}}',
                                     '{{album-title}}',
                                     '{{album-url}}',
                                     '{{plusones}}',
                                     '{{reshares}}'),
                               array($this->get_title(),
                                     $this->_images ? $this->_images[0]['full'] : '',
                                     $this->_images ? $this->_images[0]['url'] : '',
                                     $this->_video,
                                     $linkUrl,
                                     $linkTitle,
                                     $linkImage,
                                     $linkDescription,
                                     $this->get_annotation(),
                                     $this->_originally_posted_by,
                                     $this->_author_url,
                                     $c,
                                     $this->url,
                                     urlencode($this->url),
                                     $albumTitle,
                                     $albumUrl,
                                     $this->plusones,
                                     $this->reshares),
                                     $content);

        $test = 0;
        if (!$this->_originally_posted_by) $content = preg_replace('/{{if-reshare-start}}(.*){{if-reshare-end}}/msU', '', $content, -1,$test);
        if (!$this->_images) $content = preg_replace('/{{if-photo-start}}(.*){{if-photo-end}}/msU', '', $content);
        if (!$this->_video) $content = preg_replace('/{{if-video-start}}(.*){{if-video-end}}/msU', '', $content);
        if (count($this->_images) > 1) {
            $content = preg_replace('/{{loop-thumbnail-start}}(.*){{loop-thumbnail-end}}/msU', $thumbs, $content);
        } else {
            $content = preg_replace('/{{if-album-start}}(.*){{if-album-end}}/msU', '', $content);
        }
        if (!$this->_article) $content = preg_replace('/{{if-link-start}}(.*){{if-link-end}}/msU', '', $content);
        if (!$this->_album) $content = preg_replace('/{{if-album-start}}(.*){{if-album-end}}/msU', '', $content);

        $content = preg_replace('/{{(.*)}}/msU', '', $content);

        return $content;
    }

    public function get_hashtags() {
        $tags = '';
        preg_match_all("/(?:#)([\w\+\-]+)(?=\s|\.|<|$)/", $this->_content.$this->_annotation, $matches);
        if (@count($matches))
        {
            foreach ($matches[0] as $match)
                $tags .= ', '. str_replace('#','', trim($match));
        }
        return $tags;
    }

    protected function _remove_hashtags($content) {
        // return $content;
        return preg_replace('/<a rel="nofollow" class="ot-hashtag"([^>].*)>#(\w+)<\/a>/U', '', $content);
    }

    protected function _add_featured_image($post_id) {
        $extension_lookup = array('image/jpeg' => '.jpg',
                                  'image/png' => '.png',
                                  'image/gif' => '.gif');

        $image = @file_get_contents($this->_images[0]['full']);
        $filename   = str_replace(array('%','?'),'', urldecode(basename($this->_images[0]['full'])));
        $upload_dir = wp_upload_dir();

        if( wp_mkdir_p($upload_dir['path'])) {
            $file = $upload_dir['path'] . '/' . $filename;
        } else {
            $file = $upload_dir['basedir'] . '/' . $filename;
        }
        file_put_contents($file, $image);
        $wp_filetype = wp_check_filetype_and_ext($file, $filename);
        $file_info = getimagesize($file);
        if (@key_exists($file_info['mime'], $extension_lookup)) {
            rename($file, $file.$extension_lookup[$file_info['mime']]);
            $file = $file.$extension_lookup[$file_info['mime']];
            $attachment = array(
                'post_mime_type' => $file_info['mime'],
                'post_title'     => sanitize_file_name( $filename ),
                'post_content'   => '',
                'post_status'    => 'inherit'
            );
            $attach_id = wp_insert_attachment($attachment, $file, $post_id);
            set_post_thumbnail($post_id, $attach_id);

            $attach_data = wp_generate_attachment_metadata( $attach_id, $file );
            wp_update_attachment_metadata( $attach_id, $attach_data );
        }

    }

    public function save_to_wordpress() {
        global $wpdb;
        $wp_post = array(
            'post_content'  => $this->get_content(),
            'post_status'   => strtolower(get_option('gfw_post_status', 'Publish')),
            'post_title'    => $this->get_title(),
            'post_author'   => $this->_parent->author,
            'post_date'     => $this->_date,
            'post_date_gmt' => $this->_dategmt,
            'post_category' => get_option('gfw_post_categories', array()),
            'tags_input'    => get_option('gfw_post_tags', '').$this->get_hashtags()
        );

        $is_trashed = get_option('gfw_import_trashed', true) ? false : (get_posts(array('meta_key' => '_gfw_id', 'meta_value' => $this->id, 'post_status' => 'trash', 'numberposts' => 1)) ? true : false);
        if ($is_trashed) return -1;

        $existing_posts = get_posts(array('meta_key' => '_gfw_id', 'meta_value' => $this->id, 'post_status' => 'publish,pending,future,private,draft', 'numberposts' => 1));
        // $existing_post = $wpdb->get_row("SELECT * FROM $wpdb->postmeta JOIN $wpdb->posts ON $wpdb->posts.ID = $wpdb->postmeta.post_id WHERE meta_key='_gfw_id' AND meta_value='$this->id' AND post_status != 'trash' LIMIT 1");
        if ($existing_posts) {
            $this->postId = $existing_posts[0]->ID;
            $wp_post['ID'] = $this->postId;
            $wp_post['edit_date'] = true;
        }

        remove_filter('content_save_pre', 'wp_filter_post_kses');
        remove_filter('content_filtered_save_pre', 'wp_filter_post_kses');

        if (!$existing_posts) {
            if ($this->postId = wp_insert_post($wp_post)) {
                add_post_meta($this->postId, '_gfw_id', $this->id, true);
                add_post_meta($this->postId, '_gfw_url', $this->url, true);

                if (get_option('gfw_featured_images', false) && $this->_images) {
                        $this->_add_featured_image($this->postId);
                }
            }
        } else if (get_option('gfw_overwrite', true)) {
            wp_update_post($wp_post);
            if (!has_post_thumbnail($this->postId) && get_option('gfw_featured_images', false) && $this->_images) {
              $this->_add_featured_image($this->postId);
            }

        }

        $this->get_comments();
        add_filter('content_save_pre', 'wp_filter_post_kses');
        add_filter('content_filtered_save_pre', 'wp_filter_post_kses');

        return $existing_posts ? (get_option('gfw_overwrite', true) ? 0 : -1) : 1;
    }


    public function get_comments() {
        global $wpdb;
        $t = get_option('gfw_imported_comments', 0);
        $nc = 0;
        $existingComments = array();
        $newComments = array();
        $pageToken = '';

        if (!get_option('gfw_comments', false)) return;
        $comments = $wpdb->get_results("SELECT comment_agent FROM $wpdb->comments WHERE comment_post_ID='$this->postId' AND comment_author_IP='Google+'");

        foreach ($comments as $c) {
            $existingComments[] = $c->comment_agent;
        }

        $authString = $this->_parent->apiKey ? 'key='.$this->_parent->apiKey : 'access_token='.$this->_parent->accessToken;
        do
        {
            list($s, $r) = SM2WP_GooglePlus_Library::get(SM2WP_GooglePlus_Library::API_URL."activities/$this->id/comments?maxResults=100&pageToken=$pageToken&".$authString);

            if (!$r) {
                $pageToken = '';
            }
            else {
                $r = json_decode($r);
                $pageToken = @$r->nextPageToken;
                if (isset($r->items)) {
                    foreach ($r->items as $item) {
                        if (!in_array($item->id, $existingComments)) {
                            $c = new GFW_Comment($this->postId, $item->id, $item->actor->image->url, $item->actor->displayName,
                                                  $item->actor->url, $item->object->content, @$item->published);
                            if ($c->save_to_wordpress()) {
                                $t++;
                                $nc++;
                            }
                        }
                    }
                }
            }
        }
        while ($pageToken != '');
        update_option('gfw_imported_comments', $t);
        return $nc;
    }
}

class SM2WP_GooglePlus_Library extends SM2WP_Network {
    const API_URL = 'https://www.googleapis.com/plus/v1/';

    public $accessToken = null;
    public $profileId = null;
    public $id = null;

    public $name = null;
    public $avatar = null;

    public $author = null;

    public $apiKey = null;
    public $authString = null;

    public function __construct($id, $accessToken, $profileId, $name=null, $avatar=null, $author=null) {
        $this->id = $id;
        $this->accessToken = $accessToken;
        $this->profileId = $profileId;
        $this->name = $name;
        $this->avatar = $avatar;
        $this->author = $author;
        $this->apiKey = get_option('gfw_api_key');

        $this->authString = $this->apiKey ? 'key='.$this->apiKey : 'access_token='.$this->accessToken;
    }

    public function to_array() {
        return array('id' => $this->id,
                     'name' => $this->name,
                     'network_id' => $this->profileId,
                     'avatar' => $this->avatar,
                     'access_token' => $this->accessToken,
                     'author' => $this->author);
    }

    public static function create_from_array($details) {
        return new SM2WP_GooglePlus_Library($details['id'],
                                  $details['access_token'],
                                  $details['network_id'],
                                  $details['name'],
                                  $details['avatar'],
                                  $details['author']);
    }

    public static function find_profile_by_id($id, $profiles=null) {
        if (!$profiles)
            $profiles = get_option('gfw_profiles', array());
        if (@key_exists($id, $profiles))
            return SM2WP_GooglePlus_Library::create_from_array($profiles[$id]);
        return;
    }

    public static function delete_profile_by_id($id, $profiles=null) {
        if (!$profiles)
            $profiles = get_option('gfw_profiles', array());
        if (@key_exists($id, $profiles))
            unset($profiles[$id]);
        update_option('gfw_profiles', $profiles);
    }

    public function save_profile() {
        $profiles = get_option('gfw_profiles', array());
        if (@key_exists($this->id.'-'.$this->profileId, $profiles) && trim($profiles[$this->id.'-'.$this->profileId]['name'])) return false;
        $profiles[$this->id.'-'.$this->profileId] = $this->to_array();
        update_option('gfw_profiles', $profiles);
        return true;
    }

    public function update_profile() {
        $url = SM2WP_GooglePlus_Library::API_URL.'people/'.$this->profileId."?$this->authString";
        list($s, $r) = SM2WP_GooglePlus_Library::get($url);
//         if (in_array($s, array(401,403))) {
        if (!$this->apiKey && (!$r || json_decode($r)->error)) {
            $this->refresh();
            $url = SM2WP_GooglePlus_Library::API_URL.'people/'.$this->profileId.'?access_token='.$this->accessToken;
            list($s, $r) = SM2WP_GooglePlus_Library::get($url);
        }

        if (!$r) return false;
        $r = json_decode($r);
        $this->name = $r->displayName;
        $this->avatar = $r->image->url;
        return true;
    }

    public function get_posts() {
        log_running('Starting import of posts for '.$this->name.'.');
        $maxResults = get_option('gfw_history', 10) > 100 ? 100 : get_option('gfw_history', 10);
        $posts = 0;
        $pageToken='';
        $u = $c = $i = 0;
        $maxResolution = get_option('gfw_max_resolution', 1024);
        do {

            $url = SM2WP_GooglePlus_Library::API_URL."people/$this->profileId/activities/public?maxResults=$maxResults&pageToken=$pageToken&$this->authString";
            list($s, $r) = SM2WP_GooglePlus_Library::get($url);

            #if (in_array($s, array(401,403))) {
            if (!$this->apiKey && (!$r || json_decode($r)->error)) {
                $this->refresh();
                $url = SM2WP_GooglePlus_Library::API_URL."people/$this->profileId/activities/public?access_token=$this->accessToken&maxResults=$maxResults&pageToken=$pageToken";
                list($s, $r) = SM2WP_GooglePlus_Library::get($url);
            }

            if (!$r || $s != 200) {
                log_running("Unable to fetch posts for ".$this->name.'. ('.$s.')');
                return;
            }

            $r = json_decode($r);
            $pageToken = @$r->nextPageToken;

            foreach (@$r->items as $post) {
                if (!in_array($post->provider->title, array('Google+', 'HootSuite', 'Mobile', 'Photos', 'Google Reader', 'Reshared Post', 'Community', 'Buffer', ''))) continue;
                $posts++;
                if ($posts > get_option('gfw_history', 10)) break;
                $p = new GFW_Post($this, $post->id, $post->url, $post->verb, trim($post->object->content), $post->published, @$post->annotation, @$post->object->actor->displayName, @$post->actor->url);
                $p->plusones = $post->object->plusoners->totalItems;
                $p->reshares = $post->object->resharers->totalItems;
                if (!@$post->object->attachments) $post->object->attachments = array();
                foreach ($post->object->attachments as $a) {
                    switch ($a->objectType) {
                        case 'photo':
                            $url = @$a->url;
                            $image = @$a->fullImage->url && strlen(@$a->fullImage->url) > 10 ? $a->fullImage->url : $a->image->url;
                            $image = preg_replace('/(w\d+-h\d+(-p)*|s0-d)\//','', $image) . "?imgmax=$maxResolution";
                            $thumbnail = str_replace("?imgmax=$maxResolution", '?imgmax=300', $image);
                            $p->add_image($image, $thumbnail, $url);
                        break;
                        case 'album':
                            $p->add_album(@$a->url, @$a->displayName);
                            foreach ($a->thumbnails as $t) {
                                $url = @$t->url;
                                $image = @$t->fullImage->url && strlen(@$t->fullImage->url) > 10 ? $t->fullImage->url : $t->image->url;
                                $image = preg_replace('/(w\d+-h\d+(-p)*|s0-d)\//','', $image) . "?imgmax=$maxResolution";
                                $thumbnail = str_replace("?imgmax=$maxResolution", '?imgmax=300', $image);
                                $p->add_image($image, $thumbnail, $url);
                            }
                        break;
                        case 'video':
                            $title = $a->displayName;

                            if (substr(@strtolower($a->displayName), -4, 4) == '.mov')
                            {
                                $url = @$a->url;
                                $image = @$a->fullImage->url && strlen(@$a->fullImage->url) > 10 ? $a->fullImage->url : $a->image->url;
                                $image = preg_replace('/(w\d+-h\d+(-p)*|s0-d)\//','', $image) . "?imgmax=$maxResolution"; // Max Resolution
                                $thumbnail = str_replace("?imgmax=$maxResolution", '?imgmax=300', $image);
                                $p->add_image($image, $thumbnail, $url);
                            }
                            elseif (strstr($a->url, 'vimeo.com')) {
                                $video = str_replace('www.vimeo.com', 'player.vimeo.com/video', $a->url);
                                $video = str_replace('http://vimeo.com', 'http://player.vimeo.com/video', $a->url);
                                $p->add_video($video, $title);
                            }
                            elseif (@$a->embed->url && @strstr(@$a->embed->url, 'youtube.com')) {
                                $video = str_replace('/v/', '/embed/', $a->embed->url);

                                $p->add_video($video, $title);
                            }
                            else {
                                $video = str_replace('watch?v=', 'embed/', str_replace('&autoplay=1','', $a->url));
                                $p->add_video($video, $title);
                            }

                        break;
                        case 'article':
                            $p->add_article($a->url, $a->displayName, @$a->content, @$a->image->url);
                        break;
                    }
                }
                $ignore_tags = @array_filter(explode(',', trim(get_option('gfw_ignore_tags', ''))));
                $import_tags = @array_filter(explode(',', trim(get_option('gfw_import_tags', ''))));
                $tags = explode(', ', $p->get_hashtags());

                if ($ignore_tags && array_intersect($tags, $ignore_tags)) {
                    $i++;
                } else if (!$import_tags || array_intersect($tags, $import_tags)) {
                    $pr = $p->save_to_wordpress();
                    $pr == 1 ? $c++ : ($pr == 0 ? $u++ : $i++);
                } else {
                    $i++;
                }

            }
        } while ($pageToken != '' && ($posts <= get_option('gfw_history', 10)));

        log_running("Created $c posts, Updated $u posts and Ignored $i posts for ".$this->name.'.');
        return array($c, $u, $i);
    }

    protected function refresh() {
        $id = $this->id;
        list($s, $r) = SM2WP_GooglePlus_Library::get(SM2WP_AUTH_URL.'r/'.$id.'/');
        if (!$r || $s != 200) {
            log_running('Unable to refresh access for "'.$this->name.'" you may need to reconnect it.');
            return;
        }

        $r = json_decode($r);
        if ($r && $r->access_token) {
            $this->accessToken = $r->access_token;
            $this->save_profile();
        }
    }

    public static function get_template() {
        return <<<EOT
{{if-reshare-start}}
<p>{{reshared-annotation}}</p>
<p><i>Originally shared by <a href='{{original-poster-url}}'>+{{original-poster-name}}</a></i></p>
{{if-reshare-end}}

<p>{{content}}</p>

{{if-photo-start}}
<div><a href='{{photo-url}}'><img src='{{photo}}' /></a></div>
<div>
{{loop-thumbnail-start}}<a href='{{album-thumbnail-url}}' style='width:50px;height:50px;display:inline-block;background-size:cover;background-image:url({{album-thumbnail}});'></a> {{loop-thumbnail-end}}
</div>
{{if-album-start}}
<br />
<p><a href='{{album-url}}'>In Album {{album-title}}</a></p>
{{if-album-end}}
{{if-photo-end}}

{{if-video-start}}
<div><iframe type='text/html' width='100%' height='385' src='{{video}}' frameborder='0'></iframe></div>
{{if-video-end}}

{{if-link-start}}
<p>
<a style='display:block;' href='{{link-url}}'>
<img src='{{link-image}}' border='0' />
</a>
<p>
<a href='{{link-url}}'>{{link-title}}</a>
{{link-description}}
</p>
</p>
{{if-link-end}}

<a href='{{google-url}}'>Check this out on Google+</a>
EOT;
   }



}
