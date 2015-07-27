<?php
/*
Plugin Name: Category to Custom Post Type
Description: Allows you to move categories to custom post types and/or taxonomies.
Version: 1.0.3
Author: Erik Mitchell
Author URI: http://www.millerdesignworks.com
License: GPL2
*/

class Cat2PostTypeTax {

	public function __construct() {
		add_action('admin_menu',array($this,'menu_page'));
		add_action('admin_enqueue_scripts',array($this,'admin_scripts_styles'));

		add_action('wp_ajax_get_custom_fields_in_category',array($this,'ajax_get_custom_fields'));
		add_action('wp_ajax_get_metabox_fields_in_post_type',array($this,'ajax_get_meta_boxes'));
	}

	public function admin_scripts_styles($hook) {
		if ($hook!='tools_page_cat2posttypetax-options')
			return false;

		wp_enqueue_script('jquery');
		wp_enqueue_script('c2p-admin-script',plugins_url('js/admin.js',__File__),array('jquery'),'0.5');

		wp_enqueue_style('c2p-admin-style',plugins_url('css/admin.css', __FILE__));
		wp_enqueue_style('c2p-bootstrap',plugins_url('css/bootstrap.css', __FILE__),array(),'3.3.5');
	}

	public function menu_page() {
		add_management_page('Cat 2 Post','Cat 2 Post','manage_options','cat2posttypetax-options',array($this,'admin_page'));
	}

	public function admin_page() {
		$html=null;
		$message=null;

		$html.='<div class="cat2post-wrapper container">';
			$html.='<h1>Category 2 Post</h1>';

			if (isset($_POST['c2p_run']) && $_POST['c2p_run']) {
				$messages=$this->proccess_category();

				foreach ($messages as $type => $msgs) :
					if (count($msgs)) :
						foreach ($msgs as $message) :
							$html.='<div class="'.$type.'"><p>'.$message.'</p></div>';
						endforeach;
					endif;
				endforeach;
			}

			$html.=$this->get_c2p_form();
		$html.='</div>';

		echo $html;
	}

	protected function get_c2p_form() {
		global $wpdb;

		$html=null;
		$args=array(
			'public'   => true,
			'_builtin' => false
		);
		$tax=get_taxonomies($args);
		$wp_dd_args=array(
			'hide_empty' => 0,
			'hierarchical' => 1,
			'show_option_none'=>'Select Category',
			'echo' => 0,
			'orderby' => 'NAME'
		);
		$post_args=array(
			'public' => true,
			'_builtin' => false,
		);
		$post_output='objects';
		$post_types=get_post_types($post_args,$post_output);

		$html.='<form id="update-form" class="" name="update-form" action="" method="post">';
			$html.='<div class="row">';
				$html.='<div class="col-sm-2"><label for="cat">Category</label></div>';
				$html.='<div class="col-sm-10">'.wp_dropdown_categories($wp_dd_args).'</div>';
			$html.='</div><!-- .row -->';
			$html.='<div class="row">';
				$html.='<div class="col-sm-2"><label for="custom_post_type">Custom Post Type</label></div>';
				$html.='<div class="col-sm-10">';
					$html.='<select name="custom_post_type" id="custom_post_type" class="postform">';
						$html.='<option value="0">Select Post Type</option>';
						foreach ($post_types as $type) :
							$html.='<option value="'.$type->name.'">'.$type->label.'</option>';
						endforeach;
					$html.='</select>';
				$html.='</div>';
			$html.='</div><!-- .row -->';

			$html.='<div class="row match-custom-fields">';
				$html.='<div class="col-sm-2"><label for="post">Match Custom Fields</label></div>';
				$html.='<div class="col-sm-10">';
					$html.='<input type="checkbox" id="match-custom-fields" name="match_custom_fields" value="1" /> Match custom fields to new post type metabox';
				$html.='</div>';
			$html.='</div><!-- .row -->';

			$html.='<div class="row fields-match">';
				$html.='<div class="custom-fields col-sm-3">';
					$html.='<label for="custom-fields">Custom Fields</label>';
					$html.='<div id="custom-fields" name="custom_fields"></div><!-- #custom-fields -->';
				$html.='</div><!-- .custom-fields -->';

				$html.='<div class="metabox-fields col-sm-3">';
					$html.='<label for="metabox-fields">Metabox Fields</label>';
					$html.='<div id="metabox-fields" name="metabox_fields"></div><!-- #metabox-fields -->';
				$html.='</div><!-- .metabox-fields -->';
			$html.='</div><!-- .fields-match -->';

			$html.='<div class="row">';
				$html.='<div class="col-sm-2"><label for="delete-old">Delete Category</label></div>';
				$html.='<div class="col-sm-10">';
					$html.='<input type="radio" name="delete_old" value="1" />&nbsp;Yes&nbsp;';
					$html.='<input type="radio" name="delete_old" value="0" checked="checked" />&nbsp;No';
				$html.='</div>';
			$html.='</div>';
			$html.='<p class="submit">';
				$html.='<input type="submit" name="submit" id="submit" class="button button-primary" value="Run Script">';
			$html.='</p>';

			$html.='<input type="hidden" name="c2p_run" value="1" />';
		$html.='</form>';

		return $html;
	}

	protected function proccess_category() {
		global $wpdb,$post;

		$match_custom_fields=0;
		$messages=array(
			'error' => array(),
			'success' => array()
		);
		$parent=0;
		$meta_fields_map=false;
		$posts_count=0;

		extract($_POST);

		// check category //
		if ($cat==-1) :
			$messages['error'][]='Category not found.';
			return $messages;
		endif;

		// check for custom post type //
		if (!$custom_post_type) :
			$messages['error'][]='Custom Post Type not found.';
			return $messages;
		endif;

		$main_cat=get_term_by('id',$cat,'category'); // get primary category id

		// setup a relational array for moving custom fields to metaboxes //
		if ($match_custom_fields)
			$meta_fields_map=$this->match_custom_fields($_POST['fields_match']);

		// update posts to custom post type //
		$args=array(
			'posts_per_page' => -1,
			'category' => $cat,
			'post_status' => 'all',
		);
		$posts=get_posts($args);

		// if no posts are found //
		if (!count($posts)) :
			$messages['error'][]='No posts to move.';
			return $messages;
		endif;

		foreach ($posts as $post) :
			setup_postdata($post);

			// map custom fields //
			if ($meta_fields_map)
				$this->run_post_meta_migration($post->ID,$meta_fields_map);

			$update_posts=$wpdb->update($wpdb->posts,array('post_type'=>$custom_post_type),array('id'=>$post->ID)); // update in db

			if ($update_posts)
				$posts_count++;
		endforeach;

		do_action('c2p_after_posts_moved',$posts);

		$messages['updated'][]='Category moved. '.$posts_count.' posts were created in '.$custom_post_type.'.';

		// delete category //
		if ($delete_old) :
			wp_delete_category($cat);
			$messages['updated'][]='Old categories removed.';
		endif;

		return $messages;
	}

	protected function match_custom_fields($data=array(),$old_key='custom_field',$new_key='metabox_field') {
		if (empty($data) || !is_array($data))
			return false;

		$meta_setup=array();
		foreach ($data as $field) :
			if ($field[$new_key]) :
				$meta_setup[$field[$old_key]]=$field[$new_key];
			endif;
		endforeach;

		return $meta_setup;
	}

	protected function run_post_meta_migration($post_id=false,$fields_map=array()) {
		if (!$post_id || !$fields_map)
			return false;

		foreach ($fields_map as $old_key => $new_key) :
			$meta_value=get_post_meta($post_id,$old_key,true); // get custom field value
			update_post_meta($post_id,$new_key,$meta_value); // update meta
		endforeach;
	}

	public function ajax_get_custom_fields() {
		extract($_POST);

		$args=array(
			'posts_per_page' => -1,
			'category' => $category_id,
			'fields' => 'ids'
		);
		$posts=get_posts($args);
		$custom_fields=array();
		$html=null;
		$counter=0;

		// get custom fields per post and put them in array //
		foreach ($posts as $post_id) :
			$custom_field_keys=get_post_custom_keys($post_id);
			foreach ($custom_field_keys as $key) :
				$custom_fields[]=$key;
			endforeach;
		endforeach;

		$custom_fields=array_unique($custom_fields); // remove duplicates
		$custom_fields=array_values($custom_fields); // reset keys

		foreach ($custom_fields as $custom_field) :
			$html.='<input type="text" class="custom-field-value" name="fields_match['.$counter.'][custom_field]" value="'.$custom_field.'" readonly="readonly" /><br />';
			$counter++;
		endforeach;

		echo $html;

		wp_die();
	}

	public function ajax_get_meta_boxes() {
		global $wp_meta_boxes;

		$html=null;
		$metabox_fields=array();

		extract($_POST);

		// there are multiple levels of metaboxes, so we need to cycle through them all and pull out the fields //
		foreach ($wp_meta_boxes[$post_type] as $position) :
			foreach ($position as $priority) :
				foreach ($priority as $metabox) :
					foreach ($metabox['callback'] as $field) :
						$metabox_fields[]=$field;
					endforeach;
				endforeach;
			endforeach;
		endforeach;

		// turn our fields into options //
		$html.='<select id="metabox-fields-box" name="fields_match">';
			$html.='<option value="0">Select Metabox Field</option>';
			foreach ($metabox_fields as $metabox_field) :
				$html.='<option value="'.$metabox_field.'">'.$metabox_field.'</option>';
			endforeach;
		$html.='</select>';

		echo $html;

		wp_die();
	}

}

new Cat2PostTypeTax();
?>