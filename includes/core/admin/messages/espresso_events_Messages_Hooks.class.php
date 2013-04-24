<?php
if (!defined('EVENT_ESPRESSO_VERSION') )
	exit('NO direct script access allowed');

/**
 * Event Espresso
 *
 * Event Registration and Management Plugin for Wordpress
 *
 * @package		Event Espresso
 * @author		Seth Shoultes
 * @copyright	(c)2009-2012 Event Espresso All Rights Reserved.
 * @license		http://eventespresso.com/support/terms-conditions/  ** see Plugin Licensing **
 * @link		http://www.eventespresso.com
 * @version		4.0
 *
 * ------------------------------------------------------------------------
 *
 * espresso_events_Messages_Hooks
 * Hooks various messages logic so that it runs on indicated Events Admin Pages.
 * Commenting/docs common to all children classes is found in the EE_Admin_Hooks parent.
 * 
 *
 * @package		espresso_events_Messages_Hooks
 * @subpackage	includes/core/admin/messages/espresso_events_Messages_Hooks.class.php
 * @author		Darren Ethier
 *
 * ------------------------------------------------------------------------
 */
class espresso_events_Messages_Hooks extends EE_Admin_Hooks {


	public function __construct( EE_Admin_Page $admin_page ) {
		parent::__construct($admin_page);
	}



	protected function _set_hooks_properties() {
		
		$this->_name = 'messages';
		$this->_ajax_func = array(
			'ee_msgs_switch_template' => 'switch_template'
			);
		$this->_metaboxes = array(
			0 => array(
				'page_route' => array('edit_event','add_event'),
				'func' => 'messages_metabox',
				'label' => __('Notifications', 'event_espresso'),
				'priority' => 'core'
				)
			);
		
		//see explanation for layout in EE_Admin_Hooks
		$this->_scripts_styles = array(
			'registers' => array(
				'events_msg_admin' => array(
					'url' => EE_MSG_ASSETS_URL . 'events_messages_admin.js',
					'depends' => array('jquery', 'ee-parse-uri', 'ee-serialize-full-array')
					),
				'events_msg_admin_css' => array(
					'url' => EE_MSG_ASSETS_URL . 'ee_msg_events_admin.css',
					'type' => 'css'
					)
				),
			'enqueues' => array(
				'events_msg_admin' => array('edit_event'),
				'events_msg_admin_css' => array('edit_event')
				)
			); /**/
	}



	public function messages_metabox($event, $callback_args) {

		//let's get the active messengers (b/c messenger objects have the active message templates)
		//convert 'evt_id' to 'EVT_ID'
		if ( isset( $this->_req_data['evt_id'] ) && !isset($this->_req_data['EVT_ID'] ) )
			$this->_req_data['EVT_ID'] = $this->_req_data['evt_id'];

		//set flag for whether we are adding or editing an event.
		$add_event = !isset($this->_req_data['EVT_ID']) ? TRUE : FALSE;

		if ( !$add_event ) {

			$EEM_controller = new EE_messages;
			$active_messengers = $EEM_controller->get_active_messengers();
			$tabs = array();

			//empty messengers?
			//Note message types will always have at least one available because every messenger has a default message type associated with it (payment) if no other message types are selected.
			if ( empty( $active_messengers ) ) {
				$msg_activate_url = EE_Admin_Page::add_query_args_and_nonce( array('action' => 'activate', 'activate_view' => 'messengers'), EE_MSG_ADMIN_URL );
				$error_msg = sprintf( __('There are no active messengers. So no notifications will NOT go out for <strong>any</strong> events.  You will want to %sActivate a Messenger%s.', 'event_espresso'), '<a href="' . $msg_activate_url . '">', '</a>');
				$error_content = '<div class="error"><p>' . $error_msg . '</p></div>';
				$internal_content = '<div id="messages-error"><p>' . $error_msg . '</p></div>';

				if ( defined('DOING_AJAX') )
					return $error_content . $intenral_content;

				echo $error_content;
				echo $internal_content;
				return;
			}
			
			
			//get content for active messengers
			foreach ( $active_messengers as $name => $messenger ) {
				$event_id = isset($this->_req_data['EVT_ID']) ? $this->_req_data['EVT_ID'] : NULL;
				$tabs[$name] = $messenger->get_messenger_admin_page_content('events', 'edit', array('event' => $event_id) );
			}


			require_once EVENT_ESPRESSO_PLUGINFULLPATH . '/helpers/EE_Tabbed_Content.helper.php';
			//we want this to be tabbed content so let's use the EE_Tabbed_Content::display helper.
			$tabbed_content = EE_Tabbed_Content::display($tabs);
			if ( is_wp_error($tabbed_content) ) {
				$tabbed_content = $tabbed_content->get_error_message();
			}
		} else {
			$tabbed_content = '<p>' . __( 'You will see notifications options after you add the initial details for your event and save it', 'event_espresso' ) . '</p>';
		}

		$notices = '<div class="ajax-loader-grey"></div><div class="ee-notices"></div>';

		if ( defined('DOING_AJAX' ) )
			return $tabbed_content;

		echo $notices . '<div class="messages-tabs-content">' . $tabbed_content . '</div>';
		
	}


	/**
	 * This takes the incoming ajax request to switch an events template from whatever it is currently using to global.  If the request is to switch to a custom event template that hasn't been created yet, then we need to walk through the process of setting up the custom event template.
	 *
	 * @access public
	 * @return string either an html string will be returned or a success message
	 */
	public function switch_template() {
		//set EE_Admin_Page object (see method details in EE_Admin_Hooks parent
		$this->_set_page_object();

		//is this a template switch if so EE_Admin_Page child needs this object
		$this->_page_object->set_hook_object( $this );

		//let's route according to the sent page route
		$this->_page_object->route_admin_request();
	}



	/**
	 * This is the dynamic method for this class that will end up hooking into the 'admin_footer' hook on the 'edit_event' route in the events page.
	 * @return string (admin_footer contents)
	 */
	public function edit_event_admin_footer() {
		//dialog container
		$d_cont = '<div id="messages-change-edit-templates-dv" class="messages-change-edit-templates-option auto-hide hidden">' . "\n";
		$d_cont .= '<div class="ajax-loader-grey"></div>';	
		$d_cont .= '<div class="messages-change-edit-templates-content"></div>';		
		$d_cont .= '</div>';

		$notices = '<div class="ee-notices"></div>';
		echo $notices . $d_cont;
	}
}