<?php
/*
Plugin Name: Multiple Ajax Calendar
Plugin URI: http://thesquaremedia.com/blog/plugins/multiple-ajax-calendar/
Description: The wordpress calendar widget enhanced to allow multiple instances of it in one page. 
Version: 2.2
Stable tag: 2.2
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.html
Author: Xavier Serrano
Author URI: http://thesquaremedia.com/blog/
*/

class MultipleAjaxCalendarWidget extends WP_Widget {
	var $category_ids = array();

	function MultipleAjaxCalendarWidget() {
		$widget_ops  = array( 'classname' => 'multiple_ajax_calendar_widget', 'description' => __( 'Ajax Calendar that Allows you to add more than one instance of it.', 'multiple-ajax-calendar' ) );
		$control_ops = array( 'width' => 300, 'height' => 300 );

		$this->WP_Widget( 'multiple-ajax-calendar', __( 'Multiple Ajax Calendar', 'multiple-ajax-calendar' ), $widget_ops, $control_ops );

		add_action( 'template_redirect', array( &$this, 'tsm_multiple_ajax_template_redirect' ) );
		wp_enqueue_script('jquery');
		wp_register_style( 'tsm_multiple_ajax_widget', plugins_url('style.css', __FILE__) );
        wp_enqueue_style( 'tsm_multiple_ajax_widget' );
	}
	
	function tsm_multiple_ajax_template_redirect() {
		global $variable,$jVariable,$widget_id;
		if ( is_date() && isset( $_GET['ajax'] ) && $_GET['ajax'] == 'true' ) {
			$settings = $this->get_settings();
			$settings = $settings[$this->number];
			$search=array("-","_"); 
			$replace=array("",""); 
			$widget_id=$_GET['widget_id'];
			$variable=str_replace($search,$replace,$_GET['widget_id']);
			$jVariable=$_GET['widget_id'];
			$instance     = wp_parse_args( $settings, array( 'title' => __( 'AJAX Calendar', 'ajax-calendar' ) ) );
			echo $this->getMultipleAjaxCalendar();
			die();
		}
	}
	
	/**
	 * Display the widget
	 *
	 * @param string $args Widget arguments
	 * @param string $instance Widget instance
	 * @return void
	 **/
	function widget( $args, $instance ) {
		extract( $args );
		$search=array("-","_"); 
		$replace=array("",""); 
		global $variable,$jVariable,$widget_id;
		$widget_id=$args['widget_id'];
		$variable=str_replace($search,$replace,$args['widget_id']);
		$jVariable=$args['widget_id'];
		$instance     = wp_parse_args( (array)$instance, array( 'title' => __( 'AJAX Calendar', 'ajax-calendar' ), 'category_id' => '' ) );
		$title        = apply_filters( 'widget_title', $instance['title'] );
		
		echo $before_widget;
	
		if ( $title )
			echo $before_title . stripslashes( $title ) . $after_title;

		echo $this->getMultipleAjaxCalendar();

		// MicroAJAX: http://www.blackmac.de/index.php?/archives/31-Smallest-JavaScript-AJAX-library-ever!.html
?>
<script type="text/javascript">
function calendar_AJAX_<?php echo $variable; ?>(theURL,action,wID){  
					jQuery.ajax({
						type	: "GET",
                        url     : theURL,
						data: { ajax: action, widget_id: wID },
                        success : function(response) {
                            // The server has finished executing PHP and has returned something,
                            // so display it!
                            jQuery(".wp-calendar.<?php echo $jVariable; ?> ").replaceWith(response);
                        }
                    });
				  }
</script>
<?php
		// After
		echo $after_widget;
	}
	
	function getMultipleAjaxCalendar() {
		global $wpdb, $m, $monthnum, $year, $wp_locale, $posts,$variable,$jVariable,$widget_id;
		
		
		
		
		$text = $this->get_custom_tsm_calendar( true, false );

		
		$text = str_replace( 'id="wp-calendar"', 'class="wp-calendar '.$jVariable.'"', $text );
		
		$text = str_replace( '<td colspan="3" id="next"><a', '<td colspan="3" id="next"><a onclick="calendar_AJAX_'.$variable.'(jQuery(this).attr(\'href\'),true,\''.$widget_id.'\'); return false"', $text );
		$text = str_replace( '<td colspan="3" id="prev"><a', '<td colspan="3" id="prev"><a onclick="calendar_AJAX_'.$variable.'(jQuery(this).attr(\'href\'),true,\''.$widget_id.'\'); return false"', $text );
		$text.="<script type=\"text/javascript\">
jQuery(document).ready(function(){
	jQuery(\".$jVariable a.events-day,.day-events\").hover(
	function(e){
		jQuery(this).parent().find('.day-events').show();
	}
	,function(e){
		jQuery(this).parent().find('.day-events').hide();
	});
});</script>";
		return $text;
	}
	
	function get_custom_tsm_calendar($initial = true, $echo = true) {
		global $wpdb, $m, $monthnum, $year, $wp_locale, $posts;
	
		$cache = array();
		$key = md5( $m . $monthnum . $year );
		if ( $cache = wp_cache_get( 'get_calendar', 'calendar' ) ) {
			if ( is_array($cache) && isset( $cache[ $key ] ) ) {
				if ( $echo ) {
					echo apply_filters( 'get_calendar',  $cache[$key] );
					return;
				} else {
					return apply_filters( 'get_calendar',  $cache[$key] );
				}
			}
		}
	
		if ( !is_array($cache) )
			$cache = array();
	
		// Quick check. If we have no posts at all, abort!
		if ( !$posts ) {
			$gotsome = $wpdb->get_var("SELECT 1 as test FROM $wpdb->posts WHERE post_type = 'post' AND post_status = 'publish' LIMIT 1");
			if ( !$gotsome ) {
				$cache[ $key ] = '';
				wp_cache_set( 'get_calendar', $cache, 'calendar' );
				return;
			}
		}
	
		if ( isset($_GET['w']) )
			$w = ''.intval($_GET['w']);
	
		// week_begins = 0 stands for Sunday
		$week_begins = intval(get_option('start_of_week'));
	
		// Let's figure out when we are
		if ( !empty($monthnum) && !empty($year) ) {
			$thismonth = ''.zeroise(intval($monthnum), 2);
			$thisyear = ''.intval($year);
		} elseif ( !empty($w) ) {
			// We need to get the month from MySQL
			$thisyear = ''.intval(substr($m, 0, 4));
			$d = (($w - 1) * 7) + 6; //it seems MySQL's weeks disagree with PHP's
			$thismonth = $wpdb->get_var("SELECT DATE_FORMAT((DATE_ADD('{$thisyear}0101', INTERVAL $d DAY) ), '%m')");
		} elseif ( !empty($m) ) {
			$thisyear = ''.intval(substr($m, 0, 4));
			if ( strlen($m) < 6 )
					$thismonth = '01';
			else
					$thismonth = ''.zeroise(intval(substr($m, 4, 2)), 2);
		} else {
			$thisyear = gmdate('Y', current_time('timestamp'));
			$thismonth = gmdate('m', current_time('timestamp'));
		}
	
		$unixmonth = mktime(0, 0 , 0, $thismonth, 1, $thisyear);
		$last_day = date('t', $unixmonth);
	
		// Get the next and previous month and year with at least one post
		$previous = $wpdb->get_row("SELECT MONTH(post_date) AS month, YEAR(post_date) AS year
			FROM $wpdb->posts
			WHERE post_date < '$thisyear-$thismonth-01'
			AND post_type = 'post' AND post_status = 'publish'
				ORDER BY post_date DESC
				LIMIT 1");
		$next = $wpdb->get_row("SELECT MONTH(post_date) AS month, YEAR(post_date) AS year
			FROM $wpdb->posts
			WHERE post_date > '$thisyear-$thismonth-{$last_day} 23:59:59'
			AND post_type = 'post' AND post_status = 'publish'
				ORDER BY post_date ASC
				LIMIT 1");
	
		/* translators: Calendar caption: 1: month name, 2: 4-digit year */
		$calendar_caption = _x('%1$s %2$s', 'calendar caption');
		$calendar_output = '<table id="wp-calendar">
		<caption>' . sprintf($calendar_caption, $wp_locale->get_month($thismonth), date('Y', $unixmonth)) . '</caption>
		<thead>
		<tr>';
	
		$myweek = array();
	
		for ( $wdcount=0; $wdcount<=6; $wdcount++ ) {
			$myweek[] = $wp_locale->get_weekday(($wdcount+$week_begins)%7);
		}
	
		foreach ( $myweek as $wd ) {
			$day_name = (true == $initial) ? $wp_locale->get_weekday_initial($wd) : $wp_locale->get_weekday_abbrev($wd);
			$wd = esc_attr($wd);
			$calendar_output .= "\n\t\t<th scope=\"col\" title=\"$wd\">$day_name</th>";
		}
	
		$calendar_output .= '
		</tr>
		</thead>
	
		<tfoot>
		<tr>';
	
		if ( $previous ) {
			$calendar_output .= "\n\t\t".'<td colspan="3" id="prev"><a href="' . get_month_link($previous->year, $previous->month) . '" title="' . esc_attr( sprintf(__('View posts for %1$s %2$s'), $wp_locale->get_month($previous->month), date('Y', mktime(0, 0 , 0, $previous->month, 1, $previous->year)))) . '">&laquo; ' . $wp_locale->get_month_abbrev($wp_locale->get_month($previous->month)) . '</a></td>';
		} else {
			$calendar_output .= "\n\t\t".'<td colspan="3" id="prev" class="pad">&nbsp;</td>';
		}
	
		$calendar_output .= "\n\t\t".'<td class="pad">&nbsp;</td>';
	
		if ( $next ) {
			$calendar_output .= "\n\t\t".'<td colspan="3" id="next"><a href="' . get_month_link($next->year, $next->month) . '" title="' . esc_attr( sprintf(__('View posts for %1$s %2$s'), $wp_locale->get_month($next->month), date('Y', mktime(0, 0 , 0, $next->month, 1, $next->year))) ) . '">' . $wp_locale->get_month_abbrev($wp_locale->get_month($next->month)) . ' &raquo;</a></td>';
		} else {
			$calendar_output .= "\n\t\t".'<td colspan="3" id="next" class="pad">&nbsp;</td>';
		}
	
		$calendar_output .= '
		</tr>
		</tfoot>
	
		<tbody>
		<tr>';
	
		// Get days with posts
		$dayswithposts = $wpdb->get_results("SELECT DISTINCT DAYOFMONTH(post_date)
			FROM $wpdb->posts WHERE post_date >= '{$thisyear}-{$thismonth}-01 00:00:00'
			AND post_type = 'post' AND post_status = 'publish'
			AND post_date <= '{$thisyear}-{$thismonth}-{$last_day} 23:59:59'", ARRAY_N);
		if ( $dayswithposts ) {
			foreach ( (array) $dayswithposts as $daywith ) {
				$daywithpost[] = $daywith[0];
			}
		} else {
			$daywithpost = array();
		}
	
		if (strpos($_SERVER['HTTP_USER_AGENT'], 'MSIE') !== false || stripos($_SERVER['HTTP_USER_AGENT'], 'camino') !== false || stripos($_SERVER['HTTP_USER_AGENT'], 'safari') !== false)
			$ak_title_separator = "\n";
		else
			$ak_title_separator = ', ';
	
		$ak_titles_for_day = array();
		$ak_post_titles = $wpdb->get_results("SELECT ID, post_title, DAYOFMONTH(post_date) as dom "
			."FROM $wpdb->posts "
			."WHERE post_date >= '{$thisyear}-{$thismonth}-01 00:00:00' "
			."AND post_date <= '{$thisyear}-{$thismonth}-{$last_day} 23:59:59' "
			."AND post_type = 'post' AND post_status = 'publish'"
		);
		if ( $ak_post_titles ) {
			foreach ( (array) $ak_post_titles as $ak_post_title ) {
	
					$post_title = esc_attr( apply_filters( 'the_title', $ak_post_title->post_title, $ak_post_title->ID ) );
	
					if ( empty($ak_titles_for_day['day_'.$ak_post_title->dom]) )
						$ak_titles_for_day['day_'.$ak_post_title->dom] = '';
					if ( empty($ak_titles_for_day["$ak_post_title->dom"]) ) // first one
						$ak_titles_for_day["$ak_post_title->dom"] = $post_title;
					else
						$ak_titles_for_day["$ak_post_title->dom"] .= $ak_title_separator . $post_title;
			}
		}
	
		// See how much we should pad in the beginning
		$pad = calendar_week_mod(date('w', $unixmonth)-$week_begins);
		if ( 0 != $pad )
			$calendar_output .= "\n\t\t".'<td colspan="'. esc_attr($pad) .'" class="pad">&nbsp;</td>';
	
		$daysinmonth = intval(date('t', $unixmonth));
		for ( $day = 1; $day <= $daysinmonth; ++$day ) {
			if ( isset($newrow) && $newrow )
				$calendar_output .= "\n\t</tr>\n\t<tr>\n\t\t";
			$newrow = false;
	
			if ( $day == gmdate('j', current_time('timestamp')) && $thismonth == gmdate('m', current_time('timestamp')) && $thisyear == gmdate('Y', current_time('timestamp')) )
				$calendar_output .= '<td id="today">';
			else
				$calendar_output .= '<td>';
	
			if ( in_array($day, $daywithpost) ) {// any posts today?
						$calendar_output .= '<a href="' . get_day_link( $thisyear, $thismonth, $day ) . '" ' . "class=\"events-day\">$day</a>";
						$calendar_output .= "<div class=\"day-events\">";
						query_posts($query_string. '&order=ASC&year=' .$thisyear .'&monthnum=' .$thismonth .'&day=' .$day);

						if (have_posts()) : 

							while (have_posts()) : the_post();
								$calendar_output .= "<a href=\"".get_permalink(get_the_ID()). "\"> ".get_the_post_thumbnail( get_the_ID(), 'thumbnail', $attr ).get_the_title(get_the_ID())." - ".get_post_time('h:i A', true)."</a>";
							endwhile;
						endif;
						$calendar_output .= "</div>";
				}else{
					$calendar_output .= $day;
				}
			$calendar_output .= '</td>';
	
			if ( 6 == calendar_week_mod(date('w', mktime(0, 0 , 0, $thismonth, $day, $thisyear))-$week_begins) )
				$newrow = true;
		}
	
		$pad = 7 - calendar_week_mod(date('w', mktime(0, 0 , 0, $thismonth, $day, $thisyear))-$week_begins);
		if ( $pad != 0 && $pad != 7 )
			$calendar_output .= "\n\t\t".'<td class="pad" colspan="'. esc_attr($pad) .'">&nbsp;</td>';
	
		$calendar_output .= "\n\t</tr>\n\t</tbody>\n\t</table>";
	
		$cache[ $key ] = $calendar_output;
		wp_cache_set( 'get_calendar', $cache, 'calendar' );
	
		if ( $echo )
			echo apply_filters( 'get_calendar',  $calendar_output );
		else
			return apply_filters( 'get_calendar',  $calendar_output );
	
	}	
	
	/**
	 * Display config interface
	 *
	 * @param string $instance Widget instance
	 * @return void
	 **/
	function form( $instance ) {
		$instance = wp_parse_args( (array)$instance, array( 'title' => __( 'Multiple Ajax Calendar', 'multiple-ajax-calendar' ) ) );

		$title        = stripslashes( $instance['title'] );

		?>
<p><label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Title:', 'multiple-ajax-calendar' ); ?> <input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>" /></label><br /></p>
		<?php
	}
		
	/**
	 * Save widget data
	 *
	 * @param string $new_instance
	 * @param string $old_instance
	 * @return void
	 **/
	function update( $new_instance, $old_instance ) {
		$instance     = $old_instance;
		$new_instance = wp_parse_args( (array)$new_instance, array( 'title' => __( 'Multiple Ajax Calendar', 'multiple-ajax-calendar' ) ) );

		$instance['title']        = wp_filter_nohtml_kses( $new_instance['title'] );
		
		return $instance;
	}
}

function register_multiple_ajax_calendar_widget() {
	register_widget( 'MultipleAjaxCalendarWidget' );
}

add_action( 'widgets_init', 'register_multiple_ajax_calendar_widget' );
add_shortcode( 'multiple-ajax-calendar', 'my_mac_shortcode' );

function my_mac_shortcode( $atts ) {
global $wp_widget_factory;
// Configure defaults and extract the attributes into variables
extract( shortcode_atts( 
	array( 
		'title'  => 'Calendar'
	), 
	$atts 
));

$widget_name = wp_specialchars('MultipleAjaxCalendarWidget');
    
    if (!is_a($wp_widget_factory->widgets[$widget_name], 'WP_Widget')):
        $wp_class = 'WP_Widget_'.ucwords(strtolower($class));
        
        if (!is_a($wp_widget_factory->widgets[$wp_class], 'WP_Widget')):
            return '<p>'.sprintf(__("%s: Widget class not found. Make sure this widget exists and the class name is correct"),'<strong>'.$class.'</strong>').'</p>';
        else:
            $class = $wp_class;
        endif;
    endif;
    
    ob_start();
    the_widget($widget_name, $instance, array('widget_id'=>'arbitrary-instance-'.$id,
        'before_widget' => '<div id="%1$s" class="widget-container %2$s">',
		'after_widget' => '</div>',
		'before_title' => '<h1 class="widget-title">',
		'after_title' => '</h1>',
    ));
    $output = ob_get_contents();
    ob_end_clean();
	return $output;
}

