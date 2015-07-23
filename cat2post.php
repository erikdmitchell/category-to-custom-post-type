<?php
/*
Plugin Name: Category to Custom Post Type
Description: Allows you to move categories to custom post types and/or taxonomies.
Version: 1.0.2
Author: Erik Mitchell
Author URI: http://www.millerdesignworks.com
License: GPL2
*/

class Cat2PostTypeTax {

	protected $debug=false;

	public function __construct() {
		add_action('admin_menu',array($this,'menu_page'));
		add_action('admin_enqueue_scripts',array($this,'admin_scripts_styles'));
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
				$html.='<div class="col-sm-2"><label for="post">Custom Post Type</label></div>';
				$html.='<div class="col-sm-10">';
					$html.='<select name="post" id="post" class="postform">';
						$html.='<option value="0">Select Post Type</option>';
						foreach ($post_types as $type) :
							$html.='<option value="'.$type->name.'">'.$type->label.'</option>';
						endforeach;
					$html.='</select>';
				$html.='</div>';
			$html.='</div><!-- .row -->';
			$html.='<div class="row">';
				$html.='<div class="col-sm-2"><label for="tax">Custom Taxonomy</label></div>';
				$html.='<div class="col-sm-10">';
					$html.='<select name="tax" id="tax" class="postform">';
						$html.='<option value="0">Select Taxonomy</option>';
						foreach ($tax as $t) :
							$html.='<option value="'.$t.'">'.$t.'</option>';
						endforeach;
					$html.='</select>';
				$html.='</div>';
			$html.='</div><!-- .row -->';
			$html.='<div class="row">';
				$html.='<div class="col-sm-2"><label for="new-cat">New Category Parent (optional)</label></div>';
				// create various dropdowns for each taxonomy, jQuery will handle the displaying of each //
				foreach ($tax as $t) :
					$wp_new_dd_args=array(
						'name' => 'new-cat',
						'hide_empty' => 0,
						'hierarchical' => 1,
						'taxonomy' => $t,
						'show_option_none'=>'Select Category',
					);
					$html.='<div id="<?php echo $t; ?>" class="new-cat-dd" style="display:none">';
						$html.=wp_dropdown_categories($wp_new_dd_args);
					$html.='</div>';
				endforeach;
			$html.='</div><!-- .row -->';
			$html.='<div class="row">';
				$html.='<div class="col-sm-2"><label for="move-children">Move Children</label></div>';
				$html.='<div class="col-sm-10">';
					$html.='<input type="radio" name="move-children" value="y" />&nbsp;Yes&nbsp;';
					$html.='<input type="radio" name="move-children" value="n" checked="checked" />&nbsp;No';
				$html.='</div>';
			$html.='</div><!-- .row -->';
			$html.='<div class="row">';
				$html.='<div class="col-sm-2"><label for="delete-old">Delete Category</label></div>';
				$html.='<div class="col-sm-10">';
					$html.='<input type="radio" name="delete-old" value="y" />&nbsp;Yes&nbsp;';
					$html.='<input type="radio" name="delete-old" value="n" checked="checked" />&nbsp;No';
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

		$messages=array(
			'error' => array(),
			'success' => array()
		);
		$parent=0;
		$main_cat=get_term_by('id',$_POST["cat"],'category');

		extract($_POST);

		if ($this->debug)
			echo 'Begin proccess_category()<br>';

		// check category //
		if ($cat==-1) :
			$messages['error'][]='Category not found.';
			return $messages;
		endif;

		// check for custom post type //
		if (!$post) :
			$messages['error'][]='Post Type not found.';
			return $messages;
		endif;

		if (isset($_POST['new-cat']) && $_POST["new-cat"]!=-1)
			$parent=$_POST["new-cat"];

		$new_cat=array(
			'cat_name' => $main_cat->name,
			'category_description' => $main_cat->description,
			'category_nicename' => $_POST["post"]."-".$main_cat->slug,
			'category_parent' => $parent,
			'taxonomy' => $_POST["tax"],
		);

		$new_cat_id = wp_insert_category($new_cat);

		// update posts to custom post type //
		$args = array(
			'numberposts' => -1,
			'category' => $_POST["cat"],
			'post_status' => 'all',
		);
		$myposts = get_posts( $args );

		// if no posts are found //
		if (!count($myposts)) :
			$messages['error'][]='No posts to move.';
			return $messages;
		endif;

		foreach ($myposts as $post) :	setup_postdata($post);
			//echo "UPDATE wp_posts SET post_type='".$_POST["post"]."' WHERE id=$post->ID;<br />";
			$arr=array('post_type'=>$_POST["post"]);
			$table=$wpdb->posts;
			$update_posts=$wpdb->update($table,$arr,array('id'=>$post->ID));
		endforeach;

		// update term taxonomies //
		$tt_id=$wpdb->get_row("SELECT term_taxonomy_id,count FROM $wpdb->term_taxonomy WHERE term_id=".$_POST["cat"]."");
		$new_tt_id=$wpdb->get_row("SELECT term_taxonomy_id FROM $wpdb->term_taxonomy WHERE term_id=$new_cat_id");

		$arr=array('count'=>$tt_id->count);
		$table=$wpdb->term_taxonomy;
		$update_count=$wpdb->update($table,$arr,array('term_id'=>$new_cat_id));

		if (isset($new_tt_id->term_taxonomy_id))
			$arr=array('term_taxonomy_id'=>$new_tt_id->term_taxonomy_id);

		$table=$wpdb->term_relationships;
		$update_tax=$wpdb->update($table,$arr,array('term_taxonomy_id'=>$tt_id->term_taxonomy_id));

		$messages['success'][]='Category moved.';

		// if the children button is selected, update child taxonomies //
		if ($_POST["move-children"]=="y") {
			$child_args=array(
				'child_of' => $_POST["cat"],
				'hide_empty' => false,
			);

			$categories=get_categories($child_args);

			foreach ($categories as $cat) {
				$new_sub_cat=array(
					'cat_name' => $cat->name,
					'category_description' => $cat->description,
					'category_nicename' => $_POST["post"]."-".$cat->slug, // MIGHT WANT TO MAKE PARENT //
					'category_parent' => $new_cat_id,
					'taxonomy' => $_POST["tax"],
				);

				$new_sub_cat_id = wp_insert_category($new_sub_cat);

				// update term taxonomies //
				$tt_id=$wpdb->get_row("SELECT term_taxonomy_id,count FROM $wpdb->term_taxonomy WHERE term_id=".$cat->term_id."");
				$new_tt_id=$wpdb->get_row("SELECT term_taxonomy_id FROM $wpdb->term_taxonomy WHERE term_id=$new_sub_cat_id");

				$arr=array('count'=>$tt_id->count);
				$table=$wpdb->term_taxonomy;
				$update_count=$wpdb->update($table,$arr,array('term_id'=>$new_sub_cat_id));

				$arr=array('term_taxonomy_id'=>$new_tt_id->term_taxonomy_id);
				$table=$wpdb->term_relationships;
				$update_tax=$wpdb->update($table,$arr,array('term_taxonomy_id'=>$tt_id->term_taxonomy_id));

				if ($_POST["delete-old"]=="y") {
					// delete old category //
					wp_delete_category($cat->cat_ID);
				}
			}
			$messages['success'][]='Children moved.';
		} // end move children //

		if ($_POST["delete-old"]=="y") {
			// delete old category //
			wp_delete_category($_POST["cat"]);
			$messages['success'][]='Old categories removed.';
		}

		return $messages;
	}

}

new Cat2PostTypeTax();
?>