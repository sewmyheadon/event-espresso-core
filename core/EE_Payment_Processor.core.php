<?php
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
 * EE_Payment_Processor
 *
 * CLass for handling processing of payments for transactions.
 *
 * @package			Event Espresso
 * @subpackage		core/libraries/payment_methods
 * @author			Mike Nelson
 *
 * ------------------------------------------------------------------------
 */
class EE_Payment_Processor{
	/**
     * 	@var EE_Payment_Processor $_instance
	 * 	@access 	private 	
     */
	private static $_instance = NULL;
	
	/**
	 *@singleton method used to instantiate class object
	 *@access public
	 *@return EE_Payment_Processor instance
	 */	
	public static function instance() {
		// check if class object is instantiated
		if ( self::$_instance === NULL  or ! is_object( self::$_instance ) or ! ( self::$_instance instanceof EE_Data_Migration_Manager )) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}	
	/**
	 * Using the selected gateway, processes the payment for that transaction. 
	 * @param int $payment_method ID of the payment method to use
	 * @param EE_Transaction $transaction
	 * @param array $billing_info array of simple-key-value-pairs for cc details, billing address, etc
	 * @param string $success_url string used mostly by offsite gateways to specify where to go AFTER the offsite gateway
	 * @param string $fail_url similar to $success_url, except used by some gateways in case of failure
	 * @param string $method like 'CART', indicates who the client who called this was
	 * @param float $amount if only part of the transaction is to be paid for, how much. Leave null if payment is for the full amount owing
	 * @return EE_Payment
	 * @throws EE_Error (espeically if the specified payment method's type is no longer defined)
	 */
	public function process_payment($payment_method,$transaction, $billing_info = null, $success_url = null,$fail_url = null, $method = 'CART', $by_admin = false, $amount = null ){
		$payment_method = EEM_Payment_Method::instance()->ensure_is_obj($payment_method,true);
		$transaction->set_payment_method_ID($payment_method->ID());
		$payment = $payment_method->type_obj()->process_payment($transaction,$billing_info,$success_url,$fail_url,$method,$by_admin,$amount);
		if(empty($payment)){
			$transaction->set_status(EEM_Transaction::incomplete_status_code);
			$transaction->save();
			do_action( 'AHEE__EE_Gateway__update_transaction_with_payment__no_payment', $transaction );
			
		}else{
			$payment = $this->_PAY->ensure_is_obj($payment,true);
			//ok, now process the transaction according to the payment
			$transaction->update_based_on_payments();//also saves transaction
			do_action( 'AHEE__EE_Gateway__update_transaction_with_payment__done', $transaction, $payment );
		}
		return $payment;
	}

	/**
	 * 
	 * @param EE_Transaction $transaction
	 * @param type $payment_method
	 */
	public function get_ipn_url_for_payment_method($transaction, $payment_method){
		$transaction = EEM_Transaction::instance()->ensure_is_obj($transaction);
		$primary_reg = $transaction->primary_registration();
		if( ! $primary_reg ){
			throw new EE_Error(sprintf(__("Cannot get IPN URL for transaction with ID %d because it has no primary registration", "event_espresso"),$transaction->ID()));
		}
		$payment_method = EEM_Payment_Method::instance()->ensure_is_obj($payment_method,true);
		$url=add_query_arg(array('e_reg_url_link'=>$primary_reg->reg_url_link(),
					'ee_payment_method'=>$payment_method->slug()),
				get_permalink(EE_Registry::instance()->CFG->core->txn_page_id));
		return $url;
	}
	
	/**
	 * Process the IPN. Firstly, we'll hope we put the standard args into the IPN URL so 
	 * we can easily find what registration the IPN is for and what paymetn method.
	 * However, if not, we'll give all payment methods a chance to claim it and process it.
	 * @param string $registraiton_url_link optional
	 * @param string $payment_method_slug optioanl
	 * @return EE_Payment
	 * @throws EE_Error
	 */
	public function process_ipn($_req_data, $registration_url_link = NULL,$payment_method_slug = NULL){
		//do_action('AHEE__log',__FILE__,__FUNCTION__,  sprintf("Logged IPN for payment method %s, registration_url_link '%s'", ))
		try{
			if ($registration_url_link && $payment_method_slug){//payment processor knows who this is for!
				$transaction = EEM_Transaction::instance()->get_transaction_from_reg_url_link($registration_url_link);
				$payment_method = EEM_Payment_Method::instance()->get_one_by_slug($payment_method_slug);
				$payment = $payment_method->type_obj()->handle_ipn($_req_data, $transaction);
			}else{//ya... payment processor doesn't have enough info figure otu who this ipn is for
				//give all active payment methods a chance to claim it
				$active_pms = EEM_Payment_Method::instance()->get_all_active();
				$payment = NULL;
				foreach($active_pms as $payment_method){
					try{
						$payment = $payment_method->type_obj()->handle_unclaimed_ipn($_req_data);
						break;
					}catch(EE_Error $e){
						//that's fine- it apparently couldn't handle the IPN
					}
				}
			}
			return $payment;
		}catch(EE_Error $e){
			do_action('AHEE__log',__FILE__,__FUNCTION__,sprintf("Error occured while receiving IPN. Paymetn method: %s, registration url link: %s, req data: %s. THe error was '%s'",$payment_method_slug,$registration_url_link,print_r($_req_data,true),$e->getMessage()));
			throw $e;
		}
	}
	/**
	 * 
	 * @param type $payment_method_name
	 * @param type $payment_to_refund
	 * @param type $amount
	 * @return EE_Payment
	 */
	public function process_refund($payment_method_name,$payment_to_refund,$amount = null){
		throw new EE_Error("processing of refund not yet implemented. Should intake a payment object, then send off ot the gatewya for the refund, and be return the refund 'payment'");
	}
}
