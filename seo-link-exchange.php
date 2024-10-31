<?php
/*
Plugin Name: SEO Link eXchange
Plugin URI: http://www.algosystems.eu/seo-link-exchange/
Description: Integrates a WordPress site with the SEO link eXchange network. It places a link in the footer or widget area and send your link into the SEO link eXchange server to be published on other sites. 
Author: AlgoSystems.eu
Author URI: http://www.AlgoSystems.eu/
Version: 0.0.5
License: GPL2
*/

/*  
    Copyright 2012  AlgoSystems.eu

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as 
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

if (!defined('SLXPLUGINDIR')) {
	define('SLXPLUGINDIR',dirname(__FILE__));
}
define('SLXVER',"0.0.5");

	function slx_plugin_activate(){
		/* 
		This will be ran when the plugin is activated in the admin panel.
		 * Prepare/reset configuration.

		*/
		$slx_siteurl =  get_option('siteurl');
                $slx_adminmail = '';

                $options = get_option("slx_options");
                $options['title'] = 'Links';
                $options['w_header'] = 0;
                $options['f_header'] = 0;
                $options['linkcat'] = 0;
                $options['backlinkurl'] = get_option('siteurl')."/";
		$options['backlinktitle'] = htmlentities(trim(get_option('blogname')),ENT_QUOTES|ENT_DISALLOWED,get_option('blog_charset'),false);
                update_option("slx_options", $options);

                $slxset = get_option("slx_set");
                $slxset['secret'] = '';
                $slxset['random'] = '';
                $slxset['lastconnect'] = 0;
                $slxset['sitescore'] = 1;
                $slxset['linkurl'] = 'http://www.algosystems.eu/';
                $slxset['linktitle'] = 'AlgoSystems';
                $slxset['lastlink'] = '';
                update_option("slx_set", $slxset);

	}

#	function slx_plugin_deactivate() {
#                  wp_clear_single_event('slx_cron_connect');
#
#        }

	function slx_plugin_getlinks_footer(){
		if(!is_active_widget('slx_widget')) { 
                  $options = get_option("slx_options");
		  $bookmarks = slx_plugin_getlinks();
                  foreach ($bookmarks as $bk) {
                    echo " | <a href='". $bk['url'] ."'>". $bk['title'] ."</a>";
                  }

		}
	}


        function slx_cron_update() {
               $slxset = get_option("slx_set");
               $options = get_option("slx_options");
               $api='http://www.algosystems.eu/api/';
               $get['site']=urlencode(get_option('siteurl'));
               $get['email']=urlencode(get_option('admin_email'));
               $get['backlinkurl'] = urlencode($options['backlinkurl']);
               $get['backlinktitle'] = urlencode($options['backlinktitle']);
               // no secret key, registration request construct:
               if ($slxset['secret']=='') {
                 $slxset['random']=substr(md5(time()),0,30).rand(10,99);
                 update_option("slx_set", $slxset);
                 $get['key']=$slxset['random'];
                 $get['command']='init';

                 // send request
                 $url = $api.'?site='.$get['site']
                         .'&email='.$get['email'].'&command='.$get['command'].'&key='.$get['key'];
                 $content = file_get_contents($url);
                 $json = json_decode($content, true);

                 // set secret
                 if ($json['error']=="OK") {
                   $slxset['secret']=$json['secret'];
                   $slxset['random'] = '';
                   update_option("slx_set", $slxset);
                 }

               }else{
               //send normal request
                 $get['lang']=WPLANG ? substr(WPLANG,0,2):'en';
                 $get['slxver']=SLXVER;
                 $get['command']='update';
                 $get['key']=hash_hmac('sha256',get_option('siteurl').get_option('admin_email').$options['backlinkurl'].$options['backlinktitle'], $slxset['secret'], false);
                 $url = $api.'?site='.$get['site']
                         .'&backlinkurl='.$get['backlinkurl'].'&backlinktitle='.$get['backlinktitle']
                         .'&command='.$get['command'].'&lang='.$get['lang'].'&slxver='.$get['slxver'].'&key='.$get['key'];
                 $content = file_get_contents($url);

#mail('vaclav@algosystems.eu','DEBUG: '.get_option('blogname').' slx update',$content);

                 $json = json_decode($content, true);

                 // set secret
                 if ($json['error']=="OK") {
                   if (isset($json['secret'])) $slxset['secret']=$json['secret'];
                   if (isset($json['sitescore'])) $slxset['sitescore']=$json['sitescore'];
                   if (isset($json['lastlink'])) $slxset['lastlink']=$json['lastlink'];
                   $slxset['lastconnect'] = time();
                   $slxset['linkurl'] = $json['linkurl'];
                   $slxset['linktitle'] = $json['linktitle'];
                   update_option("slx_set", $slxset);
                 }else{
                   $slxset['secret']='';
                   update_option("slx_set", $slxset);
                 }
               }



        }

        function slx_plugin_getlinks() {
                $slxset = get_option("slx_set");
                $options = get_option("slx_options");
                if ($slxset['secret']=='' || ($slxset['lastconnect']+60*60*24 < time() )) {
                  // init cron now+2 sec
                  wp_schedule_single_event(time()+2, 'slx_cron_connect');

                }

                // pre initialization, put hash of random key into the footer for auth request from SLX eXchange server:
                if ($slxset['random']) {
                  $id=hash_hmac('sha256',get_option('siteurl').get_option('admin_email'), $slxset['random'], false);
                }else $id=0;
                echo "<span class='slx' id='slx$id'></span>";

                $bookmarks[]=array('url'=>$slxset['linkurl'],'title'=>$slxset['linktitle']);
                $sort[]=$slxset['linktitle'];
                if ($options['f_header']) {
                  $bookmarks[]=array('url'=>'http://www.algosystems.eu/seo-link-exchange','title'=>'SEO Link eXchange');
                  $sort[]='SEO Link eXchange';

                }
                if ($options['linkcat']) {
                  $o=array(
                         'category' => $options['linkcat'],
                         'hide_invisible' => 1,
                         'category_orderby' => 'name', 'category_order' => 'ASC',
                         );
                  foreach (get_bookmarks($o) as $bk) {
                    $bookmarks[]=array('url'=>$bk->link_url, 'title'=>$bk->link_name);
                    $sort[]=$bk->link_name;
                  }
                }
                array_multisort($sort,SORT_ASC,$bookmarks);

                return $bookmarks;

        }
             

	function slx_widget($args){
		extract($args);
		$options = get_option("slx_options");

		// translate if exists using function from WPML
		if (function_exists('icl_t')){
			$options['title'] = icl_t('slx_options', 'slx_widgetTitle', ($options['title']));
		}
		// end wpml
		$before_widget = str_replace(array("slx","seo-link-exchange"),array("",""),$before_widget);
		echo $before_widget;
		
		// header yes no
		if(!isset($options['w_header']) || $options['w_header']==0) {
			echo $before_title.($options['title']).$after_title;
		}
		$bookmarks=slx_plugin_getlinks();
                echo "<ul>";
                foreach ($bookmarks as $bk) {
                  echo "<li><a href='". $bk['url'] ."'>". $bk['title'] ."</a></li>";
                }
                echo "</ul>";

		echo $after_widget;
	}


	function slx_widget_init(){
		if (function_exists('register_sidebar_widget')) {
			register_sidebar_widget(__('SEO Link eXchange'), 'slx_widget');
			register_widget_control(   'SEO Link eXchange', 'slx_widget_control', 200, 200 );    
		}
	}



	function slx_widget_control(){
		$options = get_option("slx_options");
		if ($_POST['slx_widget-Submit']){
			$options['title'] = htmlspecialchars($_POST['slx_widgetTitle']);
			// included header yes no
			$options['w_header'] = $_POST['slx_widget_header'];
                        $options['linkcat'] = intval($_POST['slx_linkcat']);
			update_option("slx_options", $options);
		}
		echo '<p>';
		echo '<label for="slx_widgetTitle">Widget Title: </label>';
		echo '<input type="text" id="slx_widgetTitle" name="slx_widgetTitle" value="'.$options['title'].'" />';
		echo "<br><input id='slx_widget_header' name='slx_widget_header' type='checkbox' value='1'";
		checked('1' ,$options['w_header']);
		echo " /> Remove widget header<br/><br/>";
		echo '<input type="hidden" id="slx_widget-Submit" name="slx_widget-Submit" value="1" />';

  		$link_cats = get_terms( 'link_category' );
		echo '<label for="slx_widgetLinks">Add Links from Links: </label>';
                echo '<select class="widefat" name="slx_linkcat">';
                echo '<option value="">No other Links</option>';
                foreach ( $link_cats as $link_cat ) {
                  echo '<option value="' . intval( $link_cat->term_id ) . '"'
                  . selected( $options['linkcat'], $link_cat->term_id, false )
                  . '>' . $link_cat->name . "</option>\n";
                }
                echo "</select>";


		echo '</p>';
	}


	function slx_admin_add_page() {
		add_options_page('Manage SEO Link eXchange Plugin', 'SEO Link eXchange', 'manage_options', 'seo-link-exchange-plugin', 'slx_plugin_options_page');
	}


	function slx_plugin_options_page() {
		echo '<div>';
		echo '<h2>SEO Link eXchange Plugin</h2>';
		echo 'Options For SEO Link eXchange Plugin.';
		echo '<form action="options.php" method="post">';
		settings_fields('slx_options');
		do_settings_sections('slx_plugin');
		echo '<input name="Submit" type="submit" value="'; echo esc_attr_e('Save Changes'); echo'"/>';
		echo '</form></div>';
	}


	function slx_admin_init(){
		register_setting( 'slx_options', 'slx_options', 'slx_options_validate' );
		add_settings_section('slx_info', 'Plugin information', 'slx_info_section_text', 'slx_plugin');

		add_settings_section('slx_settings', 'Plugin settings', 'slx_widget_section_text', 'slx_plugin');
		add_settings_field('slx_widget_text_string', 'Widget Title', 'slx_widget_setting_string', 'slx_plugin', 'slx_settings');
		add_settings_field('slx_footer_title', 'Link to the plugin web', 'slx_footer_title_string', 'slx_plugin', 'slx_settings');
		add_settings_field('slx_backlink_url', 'Backlink URL', 'slx_backlink_url_string', 'slx_plugin', 'slx_settings');
		add_settings_field('slx_backlink_text', 'Backlink Title', 'slx_backlink_text_string', 'slx_plugin', 'slx_settings');
		add_settings_field('slx_linkcat', 'Links integration', 'slx_linkcat_string', 'slx_plugin', 'slx_settings');
	}

	function slx_info_section_text() {
                $slxset = get_option('slx_set');
		echo '<p>These values are assigned by the SEO Link eXchange server. They are based on your site and cannot be changed. Listed here for your info:</p>';

?>

<table class="form-table"><tr valign="top">
<th scope="row">Your Site Scoring</th><td><?=$slxset['sitescore'];?><br></td>
</tr><tr valign="top">
<th scope="row">Assigned Link Title & URL:</th><td><?=$slxset['linktitle'];?> | <?=$slxset['linkurl'];?><br/><i>This link is placed on your site.</i></td>
</tr><tr valign="top">
<th scope="row">Assigned Secret Key:</th><td><?=$slxset['secret'];?><br/><i>The key for signing the communication with the SEO Link eXchange server.</i></td>
</tr><tr valign="top">
<th scope="row">Last known link partner:</th><td><a href="<?=$slxset['lastlink'];?>" target="_blank"><?=$slxset['lastlink'];?></a><br/><i>URL of last known site where your link is placed. This is not a realtime info - count with some delay.</i></td>
</tr><tr valign="top">
<th scope="row">Last Server Connect:</th><td><?=date_i18n(get_option('date_format')." ".get_option('time_format'),$slxset['lastconnect']);?><br/><i>The plugin contacts the eXchange server usually once per 24hour.</i></td>
</tr></table>

<?
	}

	function slx_widget_section_text() {
		echo '<p>Set your options:</p>';
	}


	function slx_widget_setting_string() {
		$options = get_option('slx_options');
		if (!is_array( $options )){
			$options = array('title' => 'My Title');
  		}
		echo "<input id='slx_title_text_string' name='slx_options[title]' size='40' type='text' value='".$options['title']."' />";
		echo "<br><input id='slx_widget_header' name='slx_options[w_header]' type='checkbox' value='1'";
		checked('1' ,$options['w_header']);
		echo " />";
		echo ' Check this box if you wish to have NO header for tight integration with above widget. Example with links from Blogrol or other widgets.<br>';
	}


        function slx_backlink_url_string() {
                $options = get_option('slx_options');
                echo "<input id='slx_backlink_url_string' name='slx_options[backlinkurl]' size='40' type='text' value='".$options['backlinkurl']."' />";
                echo '<br/>This link will be placed on other sites (main page by default).';
        }


        function slx_backlink_text_string() {
                $options = get_option('slx_options');
                echo "<input id='slx_backlink_text_string' name='slx_options[backlinktitle]' size='40' type='text' value='".$options['backlinktitle']."' />";
                echo '<br/>This text will be used to link to your site (blog name by default).';
        }

        function slx_footer_title_string() {
                $options = get_option('slx_options');
                echo "<input id='slx_footer_header' name='slx_options[f_header]' type='checkbox' value='1'";
                checked('1' ,$options['f_header']);
                echo " />";
                echo ' Allow a link to the SEO eXchange web';
        }

        function slx_linkcat_string() {
                $options = get_option('slx_options');
                echo '<select class="" name="slx_options[linkcat]">';
                echo '<option value="">No other Links</option>';
  		$link_cats = get_terms( 'link_category' );
                foreach ( $link_cats as $link_cat ) {
                  echo '<option value="' . intval( $link_cat->term_id ) . '"'
                  . selected( $options['linkcat'], $link_cat->term_id, false )
                  . '>' . $link_cat->name . "</option>\n";
                }
                echo "</select>";

                echo '<br/>What Wordpress Links category should be merged with SEO Links.';
        }

	function slx_options_validate($input) {



		$newinput['title'] = trim($input['title']);
                if (!$newinput['title']) 
                  $newinput['title']='Links';
		$newinput['backlinkurl'] = trim($input['backlinkurl']);
                if (strpos($newinput['backlinkurl'],'http')!==0) 
                  $newinput['backlinkurl'] = get_option('siteurl')."/";
		$newinput['backlinktitle'] = htmlentities(trim($input['backlinktitle']),ENT_QUOTES|ENT_DISALLOWED,get_option('blog_charset'),false);
                if (strlen($newinput['backlinktitle'])<2) 
                  $newinput['backlinktitle'] = htmlentities(trim(get_option('blogname')),ENT_QUOTES|ENT_DISALLOWED,get_option('blog_charset'),false);
		$newinput['w_header'] = 0+$input['w_header'];
                $newinput['f_header'] = 0+$input['f_header'];
                $newinput['linkcat'] = 0+$input['linkcat'];
                // update the server if backlink changed
                $options = get_option('slx_options');
                if ($newinput['backlinkurl']!=$options['backlinkurl'] || $newinput['backlinktitle']!=$options['backlinktitle']) {
                  wp_schedule_single_event(time()+2, 'slx_cron_connect');
                }

		// register variable just in case to translate and display using WPML
		// if no multilanguage is used no harm done but its registered should later WPML be used 
		if (function_exists('icl_register_string')){
			icl_register_string('slx_options', 'slx_widgetTitle', $newinput['title']);
		}
		return $newinput;
	}


	function slx_settings_link($links) {  
	  $settings_link = '<a href="options-general.php?page=seo-link-exchange-plugin">Settings</a>';  
	  array_unshift($links, $settings_link);  
	  return $links;  
	}  


$plugin = plugin_basename(__FILE__);  
add_filter("plugin_action_links_$plugin", 'slx_settings_link' );

add_action('admin_menu', 'slx_admin_add_page');
add_action('admin_init', 'slx_admin_init');

register_activation_hook(__FILE__, 'slx_plugin_activate');
#register_deactivation_hook(__FILE__, 'slx_plugin_deactivate');

add_action("plugins_loaded", "slx_widget_init");
add_action('wp_footer', 'slx_plugin_getlinks_footer');

add_action('slx_cron_connect','slx_cron_update');

?>
