jQuery(document).ready(function($) {

	var $customFields=$('#custom-fields');

	var taxDefaultValue=$('select#tax').val();
	$('#'+taxDefaultValue).show();

	/**
	 * categroy parent change
	 */
	$('select#tax').change(function() {
		var value=$(this).val();

		$('.new-cat-dd').each(function() {
			$(this).hide();
		});
		$('#'+value).show();
	});

	/**
	 * display custom fields (mb and cm)
	 */
	$('#match-custom-fields').change(function() {
		if ($(this).is(':checked')) {
			$('.fields-match').show();
		} else {
			$('.fields-match').hide();
		}
	});

	/**
	 * display custom fields for posts in category
	 */
	$('.cat2post-wrapper #cat').change(function() {
		var category_id=$(this).val();
		var data={
			'action' : 'get_custom_fields_in_category',
			'category_id' : category_id
		};

		// uses ajax to build out our custom field box //
		$.post(ajaxurl,data,function(response) {
			$customFields.html(''); // remove existing html
			$customFields.append(response); // populate with new html
		});
	});

	/**
	 * display metabox fields for post type
	 */
	$('.cat2post-wrapper #custom_post_type').change(function() {
		var $metaboxFields=$('#metabox-fields');
		var post_type=$(this).val();
		var data={
			'action' : 'get_metabox_fields_in_post_type',
			'post_type' : post_type
		};

		// uses ajax to build out our custom field box //
		$.post(ajaxurl,data,function(response) {
			$metaboxFields.html(''); // remove existing html

			// append a dropdown for each cf input //
			$customFields.find('input').each(function() {
				$metaboxFields.append(response+'<br />'); // populate with new html
			});

			// rework our names to compare with custom fields //
			$metaboxFields.find('select').each(function(i) {
				var newName=$(this).attr('name')+'['+i+'][metabox_field]';

				$(this).attr('name',newName);
			});

		});
	});

});

/**
 * gets height of the select box
 */
function calculateMultipleSelectBoxHeight($selectBox) {
	var totalHeight=0;

	$selectBox.find('option').each(function() {
		totalHeight=totalHeight+jQuery(this).outerHeight();
	});

	return totalHeight;
}