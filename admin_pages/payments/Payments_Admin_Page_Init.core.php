<?php
if (!defined('EVENT_ESPRESSO_VERSION') ){
	exit('NO direct script access allowed');
}
/**
 * Payments_Admin_Page_Init
 *
 * This is the init for the EE Payments Admin Pages.  See EE_Admin_Page_Init for method inline docs.
 *
 *
 * @package		Payments_Admin_Page_Init
 * @subpackage	includes/core/admin/Payments_Admin_Page_Init.core.php
 * @author		Darren Ethier
 */
class Payments_Admin_Page_Init extends EE_Admin_Page_Init {

	/**
	 * @var \EventEspresso\core\services\database\TableAnalysis $table_analysis
	 */
	protected $table_analysis;



	/**
	 * Payments_Admin_Page_Init constructor.
	 */
	public function __construct() {
		//define some page related constants
		define( 'EE_PAYMENTS_PG_SLUG', 'espresso_payment_settings' );
		define( 'EE_PAYMENTS_ADMIN_URL', admin_url( 'admin.php?page=' . EE_PAYMENTS_PG_SLUG ));
		define( 'EE_PAYMENTS_ADMIN', EE_ADMIN_PAGES . 'payments' . DS );
		define( 'EE_PAYMENTS_TEMPLATE_PATH', EE_PAYMENTS_ADMIN . 'templates' . DS );
		define( 'EE_PAYMENTS_ASSETS_URL', EE_ADMIN_PAGES_URL . 'payments/assets/' );
		$this->table_analysis = EE_Registry::instance()->create( 'TableAnalysis', array(), true );
		//check that there are active gateways on all admin page loads. but dont do it just yet
//		echo "constructing payments admin page";die;
		add_action('admin_notices',array($this,'check_payment_gateway_setup'));
		parent::__construct();
	}

	protected function _set_init_properties() {
		$this->label = __('Payment Methods', 'event_espresso');
	}



	/**
	 * _set_menu_map
	 *
	 * @return void
	 */
	protected function _set_menu_map() {
		$this->_menu_map = new EE_Admin_Page_Sub_Menu(
			array(
				'menu_group'      => 'settings',
				'menu_order'      => 30,
				'show_on_menu'    => EE_Admin_Page_Menu_Map::BLOG_ADMIN_ONLY,
				'parent_slug'     => 'espresso_events',
				'menu_slug'       => EE_PAYMENTS_PG_SLUG,
				'menu_label'      => __( 'Payment Methods', 'event_espresso' ),
				'capability'      => 'ee_manage_gateways',
				'admin_init_page' => $this,
			)
		);
	}



	/**
	 * Checks that there is at least one active gateway. If not, add a notice
	 *
	 * @throws \EE_Error
	 */
	public function check_payment_gateway_setup(){
		//ONLY do this check if models can query
		//and avoid a bug where when we nuke EE4's data that this causes a fatal error
		//because the tables are deleted just before this request runs. see https://events.codebasehq.com/projects/event-espresso/tickets/7539
		if (
			! EE_Maintenance_Mode::instance()->models_can_query()
			|| ! $this->table_analysis->tableExists( EEM_Payment_Method::instance()->table() )
		) {
			return;
		}


		// ensure Payment_Method model is loaded
		EE_Registry::instance()->load_model( 'Payment_Method' );
		$actives = EEM_Payment_Method::instance()->count_active( EEM_Payment_Method::scope_cart );
		if( $actives  < 1 ){
			$url = EE_Admin_Page::add_query_args_and_nonce(array(), EE_PAYMENTS_ADMIN_URL);
			echo '<div class="error">
				 <p>'.  sprintf(__("There are no Active Payment Methods setup for Event Espresso. Please %s activate at least one.%s", "event_espresso"),"<a href='$url'>","</a>").'</p>
			 </div>';
		}
	}

} //end class Payments_Admin_Page_Init
