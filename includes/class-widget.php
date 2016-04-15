<?php
	class Urb_It_Postcode_Validator_Widget extends WP_Widget {
		const LANG = WooCommerce_Urb_It::LANG;
		
		static $url;
	
		/**
		 * Register widget with WordPress.
		 */
		public function __construct() {
			self::$url = WooCommerce_Urb_It::$url;
			parent::__construct(
				'urb_it_postcode_validator_widget', // Base ID
				__('Postcode Validator', self::LANG), // Name
				array('description' => __('Let your customers see if they can have deliveries from urb-it.', self::LANG)) // Args
			);
		}
	
		/**
		 * Front-end display of widget.
		 *
		 * @see WP_Widget::widget()
		 *
		 * @param array $args     Widget arguments.
		 * @param array $instance Saved values from database.
		 */
		public function widget($args, $instance) {
			echo $args['before_widget'];
			
			if(!empty($instance['title'])) echo $args['before_title'] . apply_filters('widget_title', $instance['title']). $args['after_title'];
			
			include(WooCommerce_Urb_It::$path_templates . 'postcode-validator/form.php');
			
			add_action('wp_footer', array('WooCommerce_Urb_It', 'postcode_validator_assets'));
			
			echo $args['after_widget'];
		}
	
		/**
		 * Back-end widget form.
		 *
		 * @see WP_Widget::form()
		 *
		 * @param array $instance Previously saved values from database.
		 */
		public function form( $instance ) {
			$title = !empty($instance['title']) ? $instance['title'] : '';
			?>
				<p>
					<label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title:'); ?></label> 
					<input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo esc_attr($title); ?>">
				</p>
			<?php 
		}
	
		/**
		 * Sanitize widget form values as they are saved.
		 *
		 * @see WP_Widget::update()
		 *
		 * @param array $new_instance Values just sent to be saved.
		 * @param array $old_instance Previously saved values from database.
		 *
		 * @return array Updated safe values to be saved.
		 */
		public function update( $new_instance, $old_instance ) {
			$instance = array();
			$instance['title'] = (!empty( $new_instance['title'])) ? strip_tags($new_instance['title']) : '';
	
			return $instance;
		}
	
	}
?>