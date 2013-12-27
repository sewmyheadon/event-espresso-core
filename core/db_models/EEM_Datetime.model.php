<?php if ( ! defined('EVENT_ESPRESSO_VERSION')) exit('No direct script access allowed');
/**
 * Event Espresso
 *
 * Event Registration and Management Plugin for WordPress
 *
 * @ package			Event Espresso
 * @ author				Seth Shoultes
 * @ copyright		(c) 2008-2011 Event Espresso  All Rights Reserved.
 * @ license			http://eventespresso.com/support/terms-conditions/   * see Plugin Licensing *
 * @ link					http://www.eventespresso.com
 * @ version		 	4.0
 *
 * ------------------------------------------------------------------------
 *
 *	Datetime Model
 *
 * @package			Event Espresso
 * @subpackage		includes/models/
 * @author				Brent Christensen
 *
 * ------------------------------------------------------------------------
 */
require_once ( EE_MODELS . 'EEM_Soft_Delete_Base.model.php' );
require_once ( EE_CLASSES . 'EE_Datetime.class.php' );

class EEM_Datetime extends EEM_Soft_Delete_Base {

  	// private instance of the EEM_Datetime object
	private static $_instance = NULL;
	
	/**
	 *		private constructor to prevent direct creation
	 *		@Constructor
	 *		@access private
	 *		@param string $timezone string representing the timezone we want to set for returned Date Time Strings (and any incoming timezone data that gets saved).  Note this just sends the timezone info to the date time model field objects.  Default is NULL (and will be assumed using the set timezone in the 'timezone_string' wp option)
	 *		@return void
	 */
	protected function __construct( $timezone ) {
		$this->singlular_item = __('Datetime','event_espresso');
		$this->plural_item = __('Datetimes','event_espresso');		

		$this->_tables = array(
			'Datetime'=> new EE_Primary_Table('esp_datetime', 'DTT_ID')
		);
		$this->_fields = array(
			'Datetime'=>array(
				'DTT_ID'=> new EE_Primary_Key_Int_Field('DTT_ID', __('Datetime ID','event_espresso')),
				'EVT_ID'=>new EE_Foreign_Key_Int_Field('EVT_ID', __('Event ID','event_espresso'), false, 0, 'Event'),
				'DTT_EVT_start'=>new EE_Datetime_Field('DTT_EVT_start', __('Start time/date of Event','event_espresso'), false, current_time('timestamp'), $timezone ),
				'DTT_EVT_end'=>new EE_Datetime_Field('DTT_EVT_end', __('End time/date of Event','event_espresso'), false, current_time('timestamp'), $timezone ),
				'DTT_reg_limit'=>new EE_Infinite_Integer_Field('DTT_reg_limit', __('Registration Limit for this time','event_espresso'), true, INF),
				'DTT_sold'=>new EE_Integer_Field('DTT_sold', __('How many sales for this Datetime that have occurred', 'event_espresso'), true, 0 ),
				'DTT_is_primary'=>new EE_Boolean_Field('DTT_is_primary', __("Flag indicating datetime is primary one for event", "event_espresso"), false,false),
				'DTT_order' => new EE_Integer_Field('DTT_order', __('The order in which the Datetime is displayed', 'event_espresso'), false, 0),
				'DTT_parent' => new EE_Integer_Field('DTT_parent', __('Indicates what DTT_ID is the parent of this DTT_ID'), true, 0 ),
				'DTT_deleted' => new EE_Trashed_Flag_Field('DTT_deleted', __('Flag indicating datetime is archived', 'event_espresso'), false, false ),
			));
		$this->_model_relations = array(
			'Ticket'=>new EE_HABTM_Relation('Datetime_Ticket'),
			'Event'=>new EE_Belongs_To_Relation(),
			'Checkin'=>new EE_Has_Many_Relation(),
			'Promotion_Object'=>new EE_Has_Many_Any_Relation()
		);

		parent::__construct( $timezone );
	}





	/**
	 *		This funtion is a singleton method used to instantiate the Espresso_model object
	 *
	 *		@access public
	 *		@param string $timezone string representing the timezone we want to set for returned Date Time Strings (and any incoming timezone data that gets saved).  Note this just sends the timezone info to the date time model field objects.  Default is NULL (and will be assumed using the set timezone in the 'timezone_string' wp option)
	 *		@return EEM_Datetime instance
	 */
	public static function instance( $timezone = NULL ){

		// check if instance of Espresso_model already exists
		if ( self::$_instance === NULL ) {
			// instantiate Espresso_model
			self::$_instance = new self( $timezone );
		}

		//we might have a timezone set, let set_timezone decide what to do with it
		self::$_instance->set_timezone( $timezone );
		
		// Espresso_model object
		return self::$_instance;
	}

	/**
	*		create new blank datetime
	*
	* 		@access		public
	*		@return 		EE_Datetime[]		array on success, FALSE on fail
	*/
	public function create_new_blank_datetime() {
		$times = array( 
				EE_Datetime::new_instance( 
					array(
						'DTT_EVT_start' => time('timestamp') + (60 * 60 * 24 * 30), 
						'DTT_EVT_end' => time('timestamp') + (60 * 60 * 24 * 30),
						'DTT_is_primary' => 1,
						'DTT_order' => 1,
						'DTT_reg_limit' => INF
						/*NULL,
						NULL*/
					)
				)
		);

		$times[0]->set_start_time("8am");
		$times[0]->set_end_time("5pm");
		return $times;
	}





	/**
	*		get event start date from db
	*
	* 		@access		public
	* 		@param		int 			$EVT_ID
	*		@return 		mixed		array on success, FALSE on fail
	*/
	public function get_all_event_dates( $EVT_ID = FALSE ) {
		if ( ! $EVT_ID ) { // on add_new_event event_id gets set to 0
			return $this->create_new_blank_datetime();
		}
		$results =  $this->get_datetimes_for_event_ordered_by_importance($EVT_ID);
		return $results;
	}

	/**
	 * Gets the datetimes for the event (with the given limit), and orders them by "importance". By importance, we mean
	 * that the primary datetimes are most important, and then the earlier datetimes are the most important. Maybe we'll want
	 * this to take into account datetimes that haven't already passed, but we don't yet.
	 * @param int $EVT_ID
	 * @param int $limit
	 * @return EE_Datetime[]
	 */
	public function get_datetimes_for_event_ordered_by_importance( $EVT_ID = FALSE, $limit = NULL){
		return $this->get_all( array(array('Event.EVT_ID'=>$EVT_ID),
			'limit'=>$limit,
			'order_by'=>array('DTT_is_primary'=>'DESC','DTT_EVT_start'=>'ASC'),
			'default_where_conditions' => 'none'));
	}

	/**
	 * Gets the most important datetime for a particular event (ie, the primary event usually. But if for some WACK
	 * reason it doesn't exist, we consider teh earliest event the most important)
	 * @param int $EVT_ID
	 * @return EE_Datetime
	 */
	public function get_most_important_datetime_for_event($EVT_ID){
		$results = $this->get_datetimes_for_event_ordered_by_importance($EVT_ID, 1);
		if($results){
			return array_shift($results);
		}else{
			return null;
		}
	}



	/**
	 * This returns a wpdb->results array of all DTT month and years matching the incoming query params and grouped by month and year.
	 * @param  array  $query_params Array of query_parms as described in the comments for EEM_Base::get_all()
	 * @return wpdb results array
	 */
	public function get_dtt_months_and_years( $where_params ) {
		$query_params[0] = $where_params;
		$query_params['group_by'] = array('dtt_year', 'dtt_month');
		$columns_to_select = array(
			'dtt_year' => array('YEAR(DTT_EVT_start)', '%s'),
			'dtt_month' => array('MONTHNAME(DTT_EVT_start)', '%s')
			);
		return $this->_get_all_wpdb_results( $query_params, OBJECT, $columns_to_select );
	}

	/**
	 * Updates the DTT_sold attribute on each datetime (based on the registrations
	 * for the tickets for each datetime)
	 * @param EE_Datetime[] $datetimes
	 */
	public function update_sold($datetimes){
		foreach($datetimes as $datetime){
			$datetime->update_sold();
		}
	}

}
// End of file EEM_Datetime.model.php
// Location: /includes/models/EEM_Datetime.model.php
