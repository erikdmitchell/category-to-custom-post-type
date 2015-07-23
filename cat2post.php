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

	public function __construct() {
		add_action('admin_menu',array($this,'menu_page'));
	}

	public function menu_page() {
		add_management_page('Cat 2 Post','Cat 2 Post','manage_options','cat2posttypetax-options',array($this,'admin_page'));
	}

	public function admin_page() {
		echo '<h1>Category 2 Post</h1>';

		global $post;
		global $wpdb;
		$wpdb->show_errors();

		wp_enqueue_script('jquery');
		wp_enqueue_script('cats',plugins_url('js/cats.js',__File__),'','0.5');

		wp_enqueue_style('main-style',plugins_url('css/style.css', __FILE__));
		?>

		<?php
		$message=null;
		if (isset($_POST["submit"]) && $_POST["submit"]=="Submit") {
			$main_cat=get_term_by('id',$_POST["cat"],'category');

			if ($_POST["new-cat"]==-1) {
				$parent=0;
			} else {
				$parent=$_POST["new-cat"];
			}

			$new_cat=array(
				'cat_name' => $main_cat->name,
				'category_description' => $main_cat->description,
				'category_nicename' => $_POST["post"]."-".$main_cat->slug,
				'category_parent' => $parent,
				'taxonomy' => $_POST["tax"],
			);
	/*
	echo '<pre>';
	print_r($_POST);
	print_r($new_cat);
	echo'</pre>';
	*/
			$new_cat_id = wp_insert_category($new_cat);

			// update posts to custom post type //
			global $post;

			$args = array(
				'numberposts' => -1,
				'category' => $_POST["cat"],
				'post_status' => 'all',
			);

			$myposts = get_posts( $args );
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

			$arr=array('term_taxonomy_id'=>$new_tt_id->term_taxonomy_id);
			$table=$wpdb->term_relationships;
			$update_tax=$wpdb->update($table,$arr,array('term_taxonomy_id'=>$tt_id->term_taxonomy_id));

			$message.="Category moved.";

			// if the children button is selected, update child taxonomies //
			if ($_POST["move-children"]=="y") {
				//echo "CHILDREN";

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

					//echo "UPDATE wp_term_taxonomy SET count=$tt_id->count WHERE term_id=$new_sub_cat_id;";
					//echo "UPDATE wp_term_relationships SET term_taxonomy_id=$new_tt_id->term_taxonomy_id WHERE term_taxonomy_id=$tt_id->term_taxonomy_id;";

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
				$message.=" Children moved.";
			} // end move children //

			if ($_POST["delete-old"]=="y") {
				// delete old category //
				wp_delete_category($_POST["cat"]);
				$message.=" Old categories deleted.";
			}
			?>

			<div class="updated">
				<p><?php echo $message; ?></p>
			</div>

			<?php
		} // end if post //

		$args=array(
			'public'   => true,
			'_builtin' => false
		);
		$tax=get_taxonomies($args);

		$wp_dd_args=array(
			'hide_empty' => 0,
			'hierarchical' => 1,
			'show_option_none'=>'Select Category',
		);

		$post_args=array(
			'public' => true,
			'_builtin' => false,
		);
		$post_output='objects';
		$post_types=get_post_types($post_args,$post_output);
		?>
		<p>&nbsp;</p>
		<form id="update-form" name="update-form" action="<?php echo "http://".$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];?>" method="post">
			<div class="row">
				<label for="cat">Category</label>
				<?php wp_dropdown_categories($wp_dd_args); ?>
			</div><!-- .row -->
			<div class="row">
				<label for="post">Custom Post Type</label>
				<select name="post" id="post" class="postform">
					<option value="0">Select Post Type</option>
					<?php
					foreach ($post_types as $type) {
						echo '<option value="'.$type->name.'">'.$type->label.'</option>';
					}
					?>
				</select>
			</div><!-- .row -->
			<div class="row">
				<label for="tax">Custom Taxonomy</label>
				<select name="tax" id="tax" class="postform">
					<option value="0">Select Taxonomy</option>
					<?php	foreach ($tax as $t) { ?>
						<option value="<?php echo $t; ?>"><?php echo $t; ?></option>
					<?php } ?>
				</select>
			</div><!-- .row -->
			<div class="row">
				<label for="new-cat">New Category Parent (optional)</label>
				<?php
				// create various dropdowns for each taxonomy, jQuery will handle the displaying of each //
				foreach ($tax as $t) {
					$wp_new_dd_args=array(
						'name' => 'new-cat',
						'hide_empty' => 0,
						'hierarchical' => 1,
						'taxonomy' => $t,
						'show_option_none'=>'Select Category',
					);
					?>
					<div id="<?php echo $t; ?>" class="new-cat-dd" style="display:none">
						<?php wp_dropdown_categories($wp_new_dd_args); ?>
					</div>
				<?php	}	?>
			</div><!-- .row -->
			<div class="row">
				<label for="move-children">Move Children</label>
				<input type="radio" name="move-children" value="y" />&nbsp;Yes&nbsp;
				<input type="radio" name="move-children" value="n" checked="checked" />&nbsp;No
			</div><!-- .row -->
			<div class="row">
				<label for="delete-old">Delete Category</label>
				<input type="radio" name="delete-old" value="y" />&nbsp;Yes&nbsp;
				<input type="radio" name="delete-old" value="n" checked="checked" />&nbsp;No
			</div>
			<p class="submit">
				<input type="submit" name="submit" id="submit" class="button button-primary" value="Run Script">
			</p>
		</form>
	<?php
	} // end function //

}

new Cat2PostTypeTax();
?>