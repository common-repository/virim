<?php if (!defined('WPINC')) die("No outside script access allowed.");

/*
    Plugin Name: Virim for WordPress
    Plugin URI: http://danzarrella.com/virim
    Description: Social sharing analytics for your WordPress blog.
    Version: 0.4
    Author: Dan Zarrella
    Author URI: http://danzarrella.com
*/

global $virim_db_version;
global $metric_colors;
global $virim_path;

$virim_path = WP_PLUGIN_URL.'/'.str_replace(basename( __FILE__),"",plugin_basename(__FILE__)); 
$virim_time_window = mktime()-604800; //only check posts older than one week
$virim_db_version = "0.1";

$metric_colors['tweets'] = '35ccff';
$metric_colors['fb_shares'] = '3b5998';
$metric_colors['comments'] = 'D83019';	

register_activation_hook(__FILE__,'virim_install');
register_deactivation_hook(__FILE__, 'virim_deactivation');

add_action('virim_hourly_event', 'update_virim_stats');

add_filter('cron_schedules', 'virirm_cron_definer');    

if ( is_admin() ){ // admin actions
	add_action('admin_menu', 'add_virim_pages');	
} else {
	
}


function virirm_cron_definer($schedules)
{
  $schedules['15Minutes'] = array(
      'interval'=> 900,
      'display'=>  __('Every 15 Minutes')
  );
   $schedules['1Minute'] = array(
      'interval'=> 60,
      'display'=>  __('Every Minute')
  );
  
  return $schedules;
}


function virim_deactivation() {
   global $wpdb;
	wp_clear_scheduled_hook('virim_hourly_event');
	$table_name = $wpdb->prefix . "virim_post_stats";
	$wpdb->query("DROP TABLE IF EXISTS $table_name");
}

function virim_install () {
   global $wpdb;
   global $virim_db_version;
// add_option("virim_db_version", $virim_db_version);

	
	//wp_schedule_event(mktime(), '1Minute', 'virim_minute_event');

   $table_name = $wpdb->prefix . "virim_post_stats";
   if($wpdb->get_var("show tables like '$table_name'") != $table_name) {
      
      $sql = "CREATE TABLE " . $table_name . " (
	  id mediumint(9) NOT NULL AUTO_INCREMENT,
	  post_id int(11)  NOT NULL,
	  url VARCHAR(255) NOT NULL,
	  tweets int(11),
	  fb_shares int(11),
	  comments int(11),
	  title varchar(255),
	  dt int(11),
	  UNIQUE KEY id (id),
	  unique key url (url)
	);";
      require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
      dbDelta($sql);      
   }
   wp_schedule_event(mktime(), 'hourly', 'virim_hourly_event');
}


function add_virim_pages() {
    add_submenu_page('index.php', 'Virim', 'Virim', 'manage_options', 'virim-stats', 'display_virim_stats');
}



function display_virim_stats() {
	global $metric_colors;
	$path = WP_PLUGIN_URL.'/'.str_replace(basename( __FILE__),"",plugin_basename(__FILE__)); 


	
	echo "<div class='wrap' style='padding-left:20px;'>\n";
	echo "<h1>Virim: Social Intelligence for WordPress</h1>";
	
	//echo "<h2>Virim Stats</h2>\n";
    
	if($_GET['virim_update']=='true') {
		update_virim_stats() ;	
	}
	
	//echo "<pre>";
	if(virim_checkEmpty()) {
		echo "<div style='border:1px solid #ffd541; background-color: #ffe690; padding:5px; margin:10px; margin-bottom:30px; width:800px; text-align:center;'> Virim hasn't collected any social data on your posts yet. Click the \"Fetch Social Data\" button below to start.</div>";
	}
	else {
	
		echo "<img src='$path/legend.jpg' style='padding-top:15px; padding-bottom:10px;'><br/>";
		virim_getStats();
	}	

	
	virim_update_button_html();	
	
	
		
	echo "</div>\n";

}




function virim_checkEmpty() {
	global $wpdb;
	$table_name = $wpdb->prefix . "virim_post_stats";
	
	$myrows = $wpdb->get_results("select * from $table_name order by dt");
	if(count($myrows)<1) {
		return true;
	}
	else {
		return false;
	}
}
function update_virim_stats() {
	$posts = virim_getPosts();
	$got_post_count = virim_getPostData($posts);
	virim_getTweeters();	
	return $got_post_count;
}

function virim_getPosts() {
	global $virim_time_window;
	$data = get_posts( array(	'numberposts' => -1	)  );	
	foreach($data as $line) {
		if(strtotime($line->post_date)< $virim_time_window) {
			$ret[$line->ID]['url'] = get_permalink( $line->ID );
			$ret[$line->ID]['dt'] = strtotime($line->post_date);
			$ret[$line->ID]['comment_count'] = $line->comment_count;
			$ret[$line->ID]['title'] = $line->post_title;
		}
	}
	return $ret;
}

function virim_getStats() {
	global $wpdb;
	$table_name = $wpdb->prefix . "virim_post_stats";
	
	$myrows = $wpdb->get_results("select * from $table_name order by dt");
	//echo "<pre><h1>here</h1>";
	if(count($myrows)<1) {
	
	}
	else {
		foreach($myrows as $line) {

			$post_count++;

			$line->title = virim_clean($line->title);
			
			$ws = split(' ', $line->title);
			foreach($ws as $w) {
				$words[$w]['fb_shares'][] = $line->fb_shares;
				$words[$w]['comments'][] = $line->comments;
				$words[$w]['tweets'][] = $line->tweets;			
			}				
			
			$hour = date('G', $line->dt);
			$day = date('w', $line->dt);
			
			$hours[$hour]['fb_shares'][] = $line->fb_shares;
			$hours[$hour]['comments'][] = $line->comments;
			$hours[$hour]['tweets'][] = $line->tweets;	
			
			$days[$day]['fb_shares'][] = $line->fb_shares;
			$days[$day]['comments'][] = $line->comments;
			$days[$day]['tweets'][] = $line->tweets;			
			
			$totals['fb_shares'][] = $line->fb_shares;
			$totals['comments'][] = $line->comments;
			$totals['tweets'][] = $line->tweets;	
			
			$p = $line->url;
			$posts[$p]['fb_shares'] = $line->fb_shares;
			$posts[$p]['comments'] = $line->comments;
			$posts[$p]['tweets'] = $line->tweets;		
				
			$titles[$p] = $line->title;	
			
		}			
	
		foreach($totals as $metric=>$vals) {
			$avgs[$metric] = array_sum($vals)/count($vals);	
		}
								
		//echo "Analyzed $post_count posts <br/>";
		echo '<div id="virim_over_time" class="virim_tab_content"> <h2>Total Social Activity Over Time</h2>';	
		virim_display_over_time();	
		echo "</div><br/><br/>";
						
		echo '<div id="virim_hours" class="virim_tab_content"><h2>Activity by Hour of Day</h2>';
		virim_display_hour_data($hours, $avgs);
		echo "</div><br/><br/>";
		
		echo '<div id="virim_days" class="virim_tab_content"><h2>Activity by Day of Week</h2>';
		virim_display_day_data($days, $avgs);
		echo "</div><br/><br/>";
		
		echo '<div id="virim_words" class="virim_tab_content"><h2>Activity by Word</h2>';
		virim_display_word_data($words, $avgs);
		echo "</div><br/><br/>";
		
		echo '<div id="virim_words" class="virim_tab_content"><h2>Top Posts</h2>';
		virim_display_top_post_data($posts, $avgs, $titles);
		echo "</div><br/><br/>";
		
		echo '<div id="virim_over_time" class="virim_tab_content"> <h2>Recent Twitter Influencers</h2>';	
		virim_display_users();
		echo "</div><br/><br/>";
		
	}
	
}
function virim_display_users() {
	global $metric_colors;
	$space = 200;
	$user_data  = get_option('virim_user_data');	
	foreach($user_data as $user=>$data) {
		$avg[] = $data['followers'];	
		$tweeters[$user] = $data['followers'];
	}
	$avg = array_sum($avg)/count($avg);
	
	$max = max($tweeters);
	$min = min($tweeters);
	$diff = $max - $min;
	$scale = $space / $diff;
	$y=0;	

	foreach($tweeters as $user=>$v) {									
		if( ($y<=20) and ($v>0) and ($user) ) {								
			$y++;								
			$size = $v * $scale;
			$output .= "<tr><td style='padding-right:10px;'><a href='http://twitter.com/$user'>$user</a></td>";
			//$output .= "<td>".$user_data[$user]['tweets']."</td>";	
			$output .= "<td>$v</td>";		
			$output .= "<td valign='top'><div style='width:".$size."px; height:20px; background-color:#".$metric_colors['tweets'].";'></div></td>";
			$output .= "</tr>\n";
		}
	}

	echo "<table width='800'>";
	echo "<tr style='font-weight:bold;'><td>User</td><td colspan='2'>Followers</td></tr>";
	echo $output;
	echo "</table>";
}

function virim_display_over_time() {

	$path = WP_PLUGIN_URL.'/'.str_replace(basename( __FILE__),"",plugin_basename(__FILE__)); 
	$url = $path."graph.php?type=over_time";							  
	echo "<img src='$url' style='padding-left:20px; padding-top:10px;'>";
	
}

function virim_display_day_data($days, $avgs) {

	foreach($days as $w=>$vals) {
		foreach($vals as $n=>$v) {
			if( (count($v)>1) and ($avgs[$n])) {	
				$days_avgs[$n][$w] = round((((array_sum($v)/count($v))-$avgs[$n])/$avgs[$n])*100, 2);					
			}
		}
	}		

	$path = WP_PLUGIN_URL.'/'.str_replace(basename( __FILE__),"",plugin_basename(__FILE__)); 
	$url = "$path/graph.php?s_values=".urlencode(serialize($days_avgs['fb_shares'])).
						  "&t_values=".urlencode(serialize($days_avgs['tweets'])).
						  "&c_values=".urlencode(serialize($days_avgs['comments'])).
						  "&type=days";							  

	echo "<img src='$url' style='padding-left:20px; padding-top:10px;'>";

}

function virim_display_hour_data($hours, $avgs) {

	foreach($hours as $w=>$vals) {
		foreach($vals as $n=>$v) {
			if( (count($v)>1) and ($avgs[$n])) {

				$hours_avgs[$n][$w] = round((((array_sum($v)/count($v))-$avgs[$n])/$avgs[$n])*100, 2);					
			}
		}
	}		
	

	$path = WP_PLUGIN_URL.'/'.str_replace(basename( __FILE__),"",plugin_basename(__FILE__)); 
	$url = "$path/graph.php?s_values=".urlencode(serialize($hours_avgs['fb_shares'])).
						  "&t_values=".urlencode(serialize($hours_avgs['tweets'])).
						  "&c_values=".urlencode(serialize($hours_avgs['comments'])).
						  "&type=hours";	
	echo "<img src='$url' style='padding-left:20px; padding-top:10px;'>";						  
	
	
}
function virim_display_top_post_data($posts, $avgs, $titles) {
	global $metric_colors;
	$space = 100;
	
	
	$metric_display_names['tweets'] = "ReTweets";
	$metric_display_names['fb_shares'] = "Facebook Shares";
	$metric_display_names['comments'] = "Comments";		
	

	
	foreach($posts as $w=>$vals) {
		foreach($vals as $n=>$v) {
			if($v) {
				$word_avgs[$n][$w] = $v;					
			}
		}
	}
	if(count($word_avgs>0)) {
	
		foreach($word_avgs as $metric=>$words) {
			arsort($words);
			$y=0;
			
			$max[$metric] = max($words);
			$min[$metric] = min($words);
			$diff[$metric] = $max[$metric] - $min[$metric];
			$scale[$metric] = $space / $diff[$metric];
		
			
			foreach($words as $w=>$v) {
				if( ($y<=10) and ($v>0) and ($w) ) {								
					$y++;								
					$size = $v * $scale[$metric];
					$words_output[$metric].= "<tr><td style='padding-right:10px; text-align:right; font-size:10px; text-align:left;'><a href='$w'>".$titles[$w]."</a></td><td valign='top'>";
					$words_output[$metric].= "<div style='width:".$size."px; height:20px; background-color:#".$metric_colors[$metric].";'></div>";
					$words_output[$metric].=  "</td></tr>\n";

				}
			}
		}
		
		
		
		echo "<table width='800'><tr>";
		foreach($words_output as $metric=>$html) {
			echo "<td width='33%' style='padding-left:50px; vertical-align:top;' ><b>".$metric_display_names[$metric]."</b><table><tr><td colspan='2'></td></tr>".$html."</table></td>\n";	
		}
		echo "</tr></table>";	
	}
	
}

function virim_display_word_data($words, $avgs) {
	global $metric_colors;
	$space = 100;
	
	
	$metric_display_names['tweets'] = "ReTweets";
	$metric_display_names['fb_shares'] = "Facebook Shares";
	$metric_display_names['comments'] = "Comments";		
	
	foreach($words as $w=>$vals) {
		foreach($vals as $n=>$v) {
			if( (count($v)>1) and ($avgs[$n])) {
				$word_avgs[$n][$w] = round((((array_sum($v)/count($v))-$avgs[$n])/$avgs[$n])*100, 2);					
			}
		}
	}
	if(count($word_avgs>0)) {
	
		foreach($word_avgs as $metric=>$words) {
			arsort($words);
			$y=0;
			
			$max[$metric] = max($words);
			$min[$metric] = min($words);
			$diff[$metric] = $max[$metric] - $min[$metric];
			$scale[$metric] = $space / $diff[$metric];
		
			
			foreach($words as $w=>$v) {
				if( ($y<20) and ($v>0) and ($w) ) {								
					$y++;								
					$size = $v * $scale[$metric];
					$words_output[$metric].= "<tr><td style='padding-right:10px; text-align:right;'>$w</td><td>";
					$words_output[$metric].= "<div style='width:".$size."px; height:20px; background-color:#".$metric_colors[$metric].";'></div>";
					$words_output[$metric].=  "</td></tr>\n";
				}
			}
		}
		
		if(count($words_output)>0) {
		
			echo "<table width='800'><tr>";
			foreach($words_output as $metric=>$html) {
				echo "<td width='33%' style='padding-left:50px; vertical-align:top;' ><b>".$metric_display_names[$metric]."</b><table><tr><td colspan='2'></td></tr>".$html."</table></td>\n";	
			}
			echo "</tr></table>";	
		}
	}
	
}


function virim_clean($string) {
	$string = strtolower($string);	
	$string = str_replace(':', '', $string);
	$string = str_replace(',', '', $string);
	$string = str_replace('"', '', $string);
	return $string;
}

function virim_checkIfPostCached($id) {
	global $wpdb;
	$table_name = $wpdb->prefix . "virim_post_stats";
	
	$myrows = $wpdb->get_results("select count(id) as id_count from $table_name where post_id=$id");
	$res = $myrows[0]->id_count;

	if($res>0) {
		return true;	
	}
	else {
		return false;	
	}
}

function virim_getPostData($posts) {
	global $wpdb;
	$table_name = $wpdb->prefix . "virim_post_stats";
	
	foreach($posts as $id => $line) {		
		if( ($x<200) and (!virim_checkIfPostCached($id)) ) {
			$x++;
			$fb = virim_getFBStats($line['url']);
			$tweets = virim_getTweetStats($line['url']);
			$ret[$id]['fb_shares'] = $fb['total_count'];
			$ret[$id]['tweets'] = $tweets;	
			
			$ret[$id]['comments'] = $line['comment_count'];
			$ret[$id]['url'] = $line['url'];			
			$ret[$id]['dt'] = $line['dt'];	
			$ret[$id]['title'] = $line['title'];				
		}
		else {
		}
	}
	
	if(count($ret)>0) {
		foreach($ret as $id=>$line) {
			$vals['post_id'] = $id;
			foreach($line as $n=>$v) {
				$vals[$n] = $v;	
			}
			$wpdb->insert( $table_name, $vals );
		}
	}
	//echo "<pre>";
	//print_r($ret);
	return $x;
}



function virim_getTweeters() {
		$url = str_replace('http://', '', site_url()); 		
		$q = 'http://search.twitter.com/search.json?rpp=100&q='.$url;
		//echo $q."<br/>";
		$data = virim_getPage($q);	
		$data = json_decode($data);
		//echo "<pre>";
		//print_r($data);	
		foreach($data->results as $line) {
			$users[$line->from_user]++;	
		}
		while($data->next_page) {
			$data = virim_getPage('http://search.twitter.com/search.json'.$data->next_page);	
			$data = json_decode($data);	
			foreach($data->results as $line) {
				$users[$line->from_user]++;	
			}			
		}
		foreach($users as $u=>$n) {
			$names[] = $u;	
		}
		//$names = $users;
		
		while((count($names)>0)) {
			$list = '';	
			while( ($x<99) and (count($names)>0) ) {
				$list = $list.",".array_shift($names);			
				$x++;
									
			}	
			$x=0;
			$list = substr($list, 1);
			
			
			$q = 'https://api.twitter.com/1/users/lookup.json?screen_name='.$list;
		//	echo $q."<br/>";
			$data = virim_getPage($q);				
			$data = json_decode($data);						
			foreach($data as $line) {
			//	print_r($line);
				$name = $line->screen_name;
				$top_followers[$name] = $line->followers_count;																
			}	
		}
		arsort($top_followers);
		//print_r($top_followers);
		foreach($top_followers as $u=>$f) {
			$user_data[$u]['followers'] = $f;
			$user_data[$u]['tweets'] = $users[$u];					
		}
		
		update_option('virim_user_data', $user_data);		
}

function virim_getTweetStats($url) {
	$url = "http://urls.api.twitter.com/1/urls/count.json?url=$url";	
	$data = json_decode(file_get_contents($url));
	return $data->count;
}


function virim_getFBStats($url) {
	$url = urlencode($url);
	$data = virim_getPage('http://api.facebook.com/restserver.php?method=links.getStats&urls='.$url);
	$od = $data;
	$data = split('</', $data);
	$toks[] = '<comment_count>';
	$toks[] = '<total_count>';
	$toks[] = '<like_count>';
	$toks[] = '<share_count>';
	
	foreach($data as $l) {
		foreach($toks as $tok) {
			if(stristr($l, $tok)) {
				$arr = str_replace('<', '', $tok);
				$arr = str_replace('>', '', $arr);
				list($junk, $l) = split("\n", $l);
				$ret[$arr] = trim(str_replace($tok, '', $l));	
				
			}
		}
	}
	if($ret['total_count']==0) {
		//print_r($od);
		return false;
	}
	else {
		return $ret;
	}
}

function virim_getPage($file) {
      $curl_handle = curl_init();
      curl_setopt($curl_handle,CURLOPT_URL,"$file");	  
  	  curl_setopt($curl_handle, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
	  curl_setopt($curl_handle, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
      curl_setopt($curl_handle, CURLOPT_SSL_VERIFYPEER, 0);  
	  curl_setopt($curl_handle, CURLOPT_SSL_VERIFYHOST, 0); 
	  curl_setopt($curl_handle, CURLOPT_HTTPHEADER, array('Expect:')); 
      curl_setopt($curl_handle,CURLOPT_CONNECTTIMEOUT,2);	 
	  curl_setopt($curl_handle,CURLOPT_TIMEOUT,10); 
      curl_setopt($curl_handle,CURLOPT_RETURNTRANSFER,1);

      $data = curl_exec($curl_handle);
  	  $error = curl_error($curl_handle);
      curl_close($curl_handle);
	  //echo "<!-- $data -->\n";
	  if(empty($error)) {
	  	return $data;
	  }
	  else {
	  	return $error;	
	  }
	 
}


function virim_update_button_html() {
	?>	
    <h3>Manually fetch social data?</h3>	
    <form action="<?php echo $_SERVER["REQUEST_URI"]; ?>&virim_update=true" method="post">
    	<p>Clicking this button will fetch social sharing data for new posts that haven't been fetched before, it may take a few moments, so please be patient. </p>
    	<input type="submit" value="Fetch Social Data" />
    </form>
    <?php	
}




?>
