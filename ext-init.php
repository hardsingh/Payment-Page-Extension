<?php

//hook is working only for stripe
add_action( 'payment_page_payment_received', 'payment_page_success_hook', 30, 2);
function payment_page_success_hook($information, $pg_data){
	$gateway = $information['gateway'];
	$pg_data = json_decode( json_encode($pg_data), true );
	$pg_data_array = $pg_data[$gateway]['object'];
	$transaction_id = $pg_data_array['id'];
	$order_data = $information;
	//$wp_user = new WP_User( $information['user_id'] );
	//$user_ID = $wp_user->ID;
	$new_order = array(
		'post_title' => 'Order',
		'post_status' => 'publish',
		'post_date' => current_time( 'Y-m-d H:i:s' ),
		'post_type' => 'pp_payments',
		'meta_input' => array( 
      'transaction_id'=> $transaction_id,
      'created'=> $pg_data_array['created'],
      'livemode'=> $pg_data_array['livemode'],
      'currency'=> $pg_data_array['currency'],
      'status'=> $pg_data_array['status'],
      'order_data' => $order_data,
      'pg_data'=>$pg_data_array['charges']
    )
	);
	$order_id = wp_insert_post( $new_order );
	if(!is_wp_error($order_id)){
		$new_title = $new_order['post_title'].'-'.$order_id;
		$order_updated = wp_update_post( array('ID'=>$order_id, 'post_title'=>$new_title) );
    /*
    *you can also trigger any function here.
    *
    */
	}

}

add_action( 'admin_menu', 'payment_orders_backend_pages', 15 );
function payment_orders_backend_pages(){
	$parent_slug = 'payment-page';
	$smenu_slug = 'edit.php?post_type=pp_payments';
	add_submenu_page(
        $parent_slug,
        __( 'Payments', 'textdomain' ),
        __( 'Payments', 'textdomain' ),
        'manage_options',
        $smenu_slug
    );
	add_submenu_page(
        $parent_slug,
        __( 'PP Customers', 'textdomain' ),
        __( 'PP Customers', 'textdomain' ),
        'manage_options',
        'pp_customers',
        'pp_customer_page_callback',
		30
    );
}

function pp_customer_page_callback(){
	$output = '<h2>Paying Members</h2>';
	global $wpdb;
	$table_name = $wpdb->prefix.'payment_page_stripe_customers';
	$columns = $wpdb->get_results( 'DESCRIBE '.$table_name, ARRAY_A );
	$column_count = count($columns);
	$output .= '<table class="wp-list-table widefat fixed striped table-view-lis pp_payments"><thead><tr>';
    foreach( $columns as $row ){
		$output .= '<th>'.str_replace( '_', ' ', ucfirst($row['Field']) ).'</th>';
    }
	$output .= '</tr></thead><tbody>';
	$query_sql = 'Select * FROM '.$table_name;
	$payments = $wpdb->get_results($query_sql);
	
	if($payments){
		foreach($payments as $key=>$payment){
			$output .= '<tr>';
			foreach($payment as $field=>$value){
				if($field == 'created_at' || $field == 'updated_at'){
					$value = date('d F, Y H:i:s', $value);
				}
				$output .= '<td>'.$value.'</td>';
			}
			$output .= '</tr>';
		}
	} else {
		$output .= '<tr class="no-items"><td class="colspanchange" colspan="'.$column_count.'">No payments found.</td></tr>';
	}
	$output .= '</tbody><tfoot><tr>';
	foreach($columns as $row) {
        $output .= '<th>'.str_replace( '_', ' ', ucfirst($row['Field']) ).'</th>';
    }
	$output .= '</tr></tfoot></table>';
	
	echo $output;
}


function render_pp_extention() {
    new ppExtension();
}
render_pp_extention();

class ppExtension {
 
    /**
     * Hook into the appropriate actions when the class is constructed.
     */
    public function __construct() {
		add_action( "init", array( $this, "register_order_post_type" ) );
        add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
        add_action( 'save_post',      array( $this, 'save_order'         ) );
    }
 
	public function register_order_post_type(){
		$custom_posttypes = array('PP_payments');
		foreach( $custom_posttypes as $custom_posttype ){
			$pluralname = $custom_posttype;
			$singularname = str_replace('s', '', $pluralname);
			$lowercase = strtolower($custom_posttype);
			$menu_icon = 'dashicons-cart';

			$labels = array(
				'name'                  => _x( $pluralname, 'Post type general name', 'textdomain' ),
				'singular_name'         => _x( $singularname, 'Post type singular name', 'textdomain' ),
				'menu_name'             => _x( $pluralname, 'Admin Menu text', 'textdomain' ),
				'name_admin_bar'        => _x( $pluralname, 'Add New on Toolbar', 'textdomain' ),
				'add_new'               => __( 'Add New '.$singularname, 'textdomain' ),
				'add_new_item'          => __( 'Add New '.$singularname, 'textdomain' ),
				'new_item'              => __( 'New '.$singularname, 'textdomain' ),
				'edit_item'             => __( 'Edit '.$singularname, 'textdomain' ),
				'view_item'             => __( 'View '.$singularname, 'textdomain' ),
				'all_items'             => __( 'All '.$pluralname, 'textdomain' ),
				'search_items'          => __( 'Search '.$pluralname, 'textdomain' ),
				'parent_item_colon'     => __( 'Parent '.$pluralname.':', 'textdomain' ),
				'not_found'             => __( 'No '.$lowercase.'s found.', 'textdomain' ),
				'not_found_in_trash'    => __( 'No '.$lowercase.'s found in Trash.', 'textdomain' ),
				'archives'              => _x( $singularname.' archives', 'The post type archive label used in nav menus. Default "Post Archives". Added in 4.4', 'textdomain' ),
				'insert_into_item'      => _x( 'Insert into '.$singularname, 'Overrides the "Insert into post"/"Insert into page" phrase (used when inserting media into a post). Added in 4.4', 'textdomain' ),
				'uploaded_to_this_item' => _x( 'Uploaded to this '.$lowercase, 'Overrides the "Uploaded to this post"/"Uploaded to this page" phrase (used when viewing media attached to a post). Added in 4.4', 'textdomain' ),
				'filter_items_list'     => _x( 'Filter '.$lowercase.'s list', 'Screen reader text for the filter links heading on the post type listing screen. Default "Filter posts list"/"Filter pages list". Added in 4.4', 'textdomain' ),
				'items_list_navigation' => _x( $pluralname.' list navigation', 'Screen reader text for the pagination heading on the post type listing screen. Default "Posts list navigation"/"Pages list navigation". Added in 4.4', 'textdomain' ),
				'items_list'            => _x( $pluralname.' list', 'Screen reader text for the items list heading on the post type listing screen. Default "Posts list"/"Pages list". Added in 4.4', 'textdomain' ),
			);

			$args = array(
				'labels'             => $labels,
				'public'             => true,
				'hierarchical'       => false,
				'exclude_from_search' => true,
				'publicly_queryable' => false,
				'show_ui'            => true,
				'show_in_menu'       => 'payment-orders', //true,
				'show_in_nav_menus' => false,
				'show_in_admin_bar' => false,
				'show_in_rest'		=> true,
				'menu_position'      => 110,
				'menu_icon'			=> $menu_icon,
				'capability_type'    => 'post', //array( 'create_posts' => false ),
				//'map_meta_cap' => true,
				'supports'			=> array('title'),
				'has_archive'        => false,
				'rewrite'            => array( 'slug' => $lowercase ),
				'query_var'          => true,
				'can_export'		=> true
			);
			register_post_type( $lowercase, $args );
		}
	}
	
    /**
     * Adds the meta box container.
     */
    public function add_meta_box( $post_type ) {
        // Limit meta box to certain post types.
        $post_types = array( 'pp_payments' );
 
        if ( in_array( $post_type, $post_types ) ) {
            add_meta_box(
                'pp_payments_meta_box',
                __( 'Order Data', 'textdomain' ),
                array( $this, 'render_meta_box_content' ),
                $post_type,
                'advanced',
                'high'
            );
        }
    }
 
    /**
     * Save the meta when the post is saved.
     *
     * @param int $post_id The ID of the post being saved.
     */
    public function save_order( $post_id ) {
 
        /*
         * We need to verify this came from the our screen and with proper authorization,
         * because save_post can be triggered at other times.
         */
 
        // Check if our nonce is set.
        if ( ! isset( $_POST['pp_payments_inner_custom_box_nonce'] ) ) {
            return $post_id;
        }
 
        $nonce = $_POST['pp_payments_inner_custom_box_nonce'];
 
        // Verify that the nonce is valid.
        if ( ! wp_verify_nonce( $nonce, 'pp_payments_inner_custom_box' ) ) {
            return $post_id;
        }
 
        /*
         * If this is an autosave, our form has not been submitted,
         * so we don't want to do anything.
         */
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return $post_id;
        }
 
        // Check the user's permissions.
        if ( 'page' == $_POST['post_type'] ) {
            if ( ! current_user_can( 'edit_page', $post_id ) ) {
                return $post_id;
            }
        } else {
            if ( ! current_user_can( 'edit_post', $post_id ) ) {
                return $post_id;
            }
        }
 
        /* OK, it's safe for us to save the data now. */
 
        // Sanitize the user input.
        //$mydata = sanitize_text_field( $_POST['myplugin_new_field'] );
 
        // Update the meta field.
        //update_post_meta( $post_id, '_my_meta_value_key', $mydata );
    }
 
 
    /**
     * Render Meta Box content.
     *
     * @param WP_Post $post The post object.
     */
    public function render_meta_box_content( $post ) {
        // Add an nonce field so we can check for it later.
        wp_nonce_field( 'pp_payments_inner_custom_box', 'pp_payments_inner_custom_box_nonce' );
		
		$output = '<style>
			.transaction_details {
				display: flex;
				gap: 50px;
			}

			.transaction_details span {
				flex: 1;
			}
		</style>';
        // Use get_post_meta to retrieve an existing value from the database.
        // transaction_id
        $transaction_id = get_post_meta( $post->ID, 'transaction_id', true );
		$currency = get_post_meta( $post->ID, 'currency', true );
		$created = get_post_meta( $post->ID, 'created', true );
		$status = get_post_meta( $post->ID, 'status', true );
		$livemode = get_post_meta( $post->ID, 'livemode', true );
		if(!empty($livemode)){
			$livemode = 1; 
		}
        $order_data = get_post_meta( $post->ID, 'order_data', true );
		$paymet_method = $order_data['gateway'];
		//$output .= print_r($order_data, true);
		$output .= '<h3>Transaction Details:-</h3><div class="transaction_details">
			<span>Transaction ID: <strong>'.$transaction_id.'</strong></span>
			<span>Currency: <strong>'.$currency.'</strong></span>
			<span>Created: <strong>'.$created.'</strong></span>
			<span>Status: <strong>'.$status.'</strong></span>
			<span>Is Live: <strong>'.( ($livemode == 1) ? 'YES' : 'NO' ).'</strong></span>
		</div>';
		$charge_data = '<div class="data charges_data"><table class="form-table"><tbody>';
		$i = 0;
		$break_after = 3;
		foreach($order_data as $mkey=>$mval){
			if(!is_array($mval)){
				if($i%$break_after == 0){
					$charge_data .= '<tr>';
				} else {
					//$charge_data .= '<tr>';
				}
				$size = number_format(100/$break_after, 3).'%';
				$charge_data .= '<td><label for="'.$mkey.'">'.ucfirst( str_replace( array('_','-'), array(' ',' '), $mkey ) ).'</label>
				<input type="text" id="'.$mkey.'" name="'.$mkey.'" value="'.esc_attr( $mval ).'" size="'.$size.'" readonly/></td>';
				
				if ($i % $break_after == ($break_after-1)) {
					$charge_data .= '</tr>';	
				}
				$i++;
			} else { continue; }
		}
		$charge_data .= '</tbody></table></div>';
		$output .= $charge_data;

		$pg_data = get_post_meta( $post->ID, 'pg_data', true );
		//error_log(print_r($pg_data, true));
		$payment_method_details = $pg_data['data'][0]['payment_method_details']; //json_decode($pg_data)->data[0]->payment_method_details;
		$payment_method_type = $payment_method_details['type'];
		$pg_output_data = '<div class="data pg_data"><h3>Payment Method Details</h3>';
		$pm_details = $payment_method_details[$payment_method_type];
		//$pg_output_data .= '<pre>'.print_r( $pm_details, true ).'</pre>';
		
		$pg_output_data .= '<table class="form-table"><tbody>';
		$pi = 0;
		$pbreak_after = 3;
		foreach( $pm_details as $pmkey=>$pmval ){
			//$pg_output_data .= print_r($pmval, true).', type='.gettype($pmval);
			if(!is_array($pmval) && !empty($pmval) && $pmkey !== 'fingerprint'){
				if($pi%$pbreak_after == 0){
					$pg_output_data .= '<tr>';
				} else {
				}
				$size = number_format(100/$pbreak_after, 3).'%';
				$pg_output_data .= '<td><label for="'.$pmkey.'">'.ucfirst( str_replace( array('_','-'), array(' ',' '), $pmkey ) ).'</label>
				<input type="text" id="'.$pmkey.'" name="'.$pmkey.'" value="'.esc_attr( $pmval ).'" size="'.$size.'" readonly/></td>';

				if ($pi % $pbreak_after == ($pbreak_after-1)) {
					$pg_output_data .= '</tr>';	
				}
				$pi++;
			} else { continue; }
		}
		$pg_output_data .= '</tbody></table></div>';
		$output .= $pg_output_data;
		
		echo $output;
    }
}
?>
