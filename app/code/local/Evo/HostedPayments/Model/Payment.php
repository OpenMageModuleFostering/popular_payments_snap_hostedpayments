<?php

/* Copyright (c) 2015 EVO Payments International - All Rights Reserved.
*
* This software and documentation is subject to and made
* available only pursuant to the terms of an executed license
* agreement, and may be used only in accordance with the terms
* of said agreement. This software may not, in whole or in part,
* be copied, photocopied, reproduced, translated, or reduced to
* any electronic medium or machine-readable form without
* prior consent, in writing, from EVO Payments International, INC.
*
* Use, duplication or disclosure by the U.S. Government is subject
* to restrictions set forth in an executed license agreement
* and in subparagraph (c)(1) of the Commercial Computer
* Software-Restricted Rights Clause at FAR 52.227-19; subparagraph
* (c)(1)(ii) of the Rights in Technical Data and Computer Software
* clause at DFARS 252.227-7013, subparagraph (d) of the Commercial
* Computer Software--Licensing clause at NASA FAR supplement
* 16-52.227-86; or their equivalent.
*
* Information in this software is subject to change without notice
* and does not represent a commitment on the part of EVO Payments International.
* 
* Sample Code is for reference Only and is intended to be used for educational purposes. It's the responsibility of 
* the software company to properly integrate into thier solution code that best meets thier production needs. 
*/

/**
 * EVO Snap* Hosted Payments Payment model class.
 *
 * @category EVO
 * @package	Evo
 * @copyright Copyright (c) 2015 EVO Snap* (http://www.evosnap.com)
 * @license	EVO Payments International EULA
 */

require_once(Mage::getBaseDir('lib') . '/hostedpayments/index.php');

class Evo_HostedPayments_Model_Payment extends Mage_Payment_Model_Method_Abstract
{

	const MERCHANT_CHECKOUT_URL_TEST = 'https://cert-hp.evosnap.com';
	const MERCHANT_CHECKOUT_URL = 'https://hp.evosnap.com';
	
	/**
	* Availability options
	*/
	protected $_isGateway = false;
	protected $_canAuthorize = false;
	protected $_canCapture = true;
	protected $_canCapturePartial = false;
	protected $_canRefund = false;
	protected $_canVoid = false;
	protected $_canUseInternal = true;
	protected $_canUseCheckout = true;
	protected $_canUseForMultishipping = false;
	protected $_canSaveCc = false;

	/**
	* Module identifiers
	*/
	protected $_code = 'hostedpayments';
	protected $_formBlockType = 'hostedpayments/form';
	protected $_infoBlockType = 'hostedpayments/info';

    /**
     * Assign data to info model instance
     *
     * @param   mixed $data
     * @return  Mage_Payment_Model_Info
     */
    public function assignData($data)
    {
        if (!($data instanceof Varien_Object)) {
            $data = new Varien_Object($data);
        }
        
        $info = $this->getInfoInstance();
        $info->setAdditionalInformation('token_id', $data->getTokenId());
        $info->setAdditionalInformation('create_token', $data->getCreateToken());
        
        return $this;
    }
    
	/**
	* Retrieve model helper
	*
	* @return Mage_Payment_Helper_Data
	*/
	protected function _getHelper()
	{
		Mage::log(" --- Snap* Hosted Payments API : getHelper --- ");
		return Mage::helper('hostedpayments');
	}

	/**
	* Get checkout session namespace
	*
	* @return Mage_Checkout_Model_Session
	*/
	public function getCheckout()
	{
		Mage::log(" --- Snap* Hosted Payments API : getCheckout --- ");
		return Mage::getSingleton('checkout/session');
	}

	/**
	* Get order model
	*
	* @return Mage_Sales_Model_Order
	*/
	public function getOrder()
	{

		Mage::log(" --- Snap* Hosted Payments API : getOrder --- ");
		
		if (!$this->_order && (null !== $this->getInfoInstance()->getOrder()))
		{
			$this->_order = Mage::getModel('sales/order')
					->loadByIncrementId($this->getInfoInstance()->getOrder()->getRealOrderId());
		}
		return $this->_order;
	}

	/**
	* Get Customer Id
	*
	* @return string
	*/
	public function getCustomerId()
	{
		Mage::log(" --- Snap* Hosted Payments API : getCustomerId --- ");
		$customer = Mage::getSingleton('customer/session');
		if ($customer->isLoggedIn())
		{
			return $customer->getCustomerId();
		}
		else
		{
			return null;
		}
	}

	/**
	 * Retrieves the URL to process the payment within the module.
	 * @return string
	 */
	public function getOrderPlaceRedirectUrl()
	{
        return Mage::getUrl('hostedpayments/processing/pay');
	}
	
	/**
	 * Gets 3D Secure Trigger value.
	 * @return float
	 */
	public function getTrigger3ds(){
		$trigger3ds = $this->getConfigData('trigger3ds');
		
		if(trim($trigger3ds) == ''){
			$trigger3ds = null;
		}
		
		return $trigger3ds;
	}
	
	/**
	 * Gets Checkout Layout.
	 * @return float
	 */
	public function getCheckoutLayout(){
		$useIframe = $this->getConfigData('checkout_iframe');
		return $useIframe? 'iframe' : null;
	}
	
	/**
	 * Gets Hosted Payments configuration bean.
	 * @return HostedPayments
	 */
	public function getHostedPaymentsConfiguration(){
		$result = new HostedPayments();
		$result->code = $this->getConfigData('merchant_code');
		$result->key = $this->getConfigData('merchant_authkey');
		$test = $this->getConfigData('test_mode');
		$result->environment = isset($test)? !$test : true;
		 
		return $result;
	}

	/**
	 * Gets the payment URL.
	 */
	public function getPaymentUrl(){
		$order = $this->getOrder();
		$hostedpayment = Mage::getModel('hostedpayments/hostedpayment');
		$hostedpayment->load($order->getRealOrderId(), 'order_id');
		
		$paymentUrl = $hostedpayment->getUrl();
				
		if(!isset($paymentUrl) || trim($paymentUrl) == '' ){
			$paymentUrl = EvoSnapApi::getCheckoutUrl($this->_getOrderCheckout(),
					$this->getTrigger3ds(), $this->getHostedPaymentsConfiguration());
				
			$order->addStatusToHistory($order->getStatus(), Mage::helper('hostedpayments')->__('Customer was redirected to the Snap* Hosted Payments Checkout for payment.'));
			$order->save();
			
			$hostedpayment = Mage::getModel('hostedpayments/hostedpayment');
			$hostedpayment->setOrderId($order->getRealOrderId());
			$hostedpayment->setUrl($paymentUrl);
			$hostedpayment->setPrefix($this->getConfigData('order_prefix'));
			$hostedpayment->setDataChanges(true);
			$hostedpayment->save();
		}
		
		return $paymentUrl;
	}
	
	/**
	 * Retrieves Snap* order.
	 * @return SnapOrder
	 */
	public function getSnapOrder(){
		$order = $this->getOrder();
		$hostedpayment = Mage::getModel('hostedpayments/hostedpayment');
		$hostedpayment->load($order->getRealOrderId(), 'order_id');
		
		return EvoSnapApi::getOrder($this->_getOrderId(((null !== $hostedpayment->getId()) && ($hostedpayment->getId() != 0))? $hostedpayment->getPrefix() : $this->getConfigData('order_prefix'), $this->getOrder()->getRealOrderId()), $this->getHostedPaymentsConfiguration());
	}
	
	/**
	 * Retrieves Snap* order URL.
	 * @return String
	 */
	public function getSnapOrderUrl(){
		$result = null;
		
		$order = $this->getOrder();
		
		if(isset($order) && ($order->getStatus() == 'pending') && (null !== $order->getRealOrderId())){
			$hostedpayment = Mage::getModel('hostedpayments/hostedpayment');
			$hostedpayment->load($order->getRealOrderId(), 'order_id');
			
			if((null !== $hostedpayment->getId()) && ($hostedpayment->getId() != 0) &&
				(trim($hostedpayment->getUrl()) != '')){
				$result = Mage::getUrl('hostedpayments/processing/return', array('id' => $order->getRealOrderId(), '_secure' => true));
			}
		}
		
		return $result;
	}
	
	/**
	 * Cancels order.
	 */
	public function cancelOrder(){
		$order = $this->getOrder();
		
		if ($order->canCancel()){
			$order->cancel();
			$order->addStatusToHistory($order->getStatus(), Mage::helper('hostedpayments')->__('Customer cancelled this order via Snap* Hosted Payments Checkout.'));
			$order->save();
			$this->_clearHostedpaymentUrl($order->getRealOrderId());
		}
	}
	
	/**
	 * Pays order.
	 */
	public function payOrder($snapOrder){
		$order = $this->getOrder();
		
		if($this->_isCardSaved()){
		    $this->_saveCard($snapOrder);
		}
		
		$order->getPayment()->setTransactionId($this->_getTransactionId($snapOrder));
		$order->getPayment()->capture(null);
		$order->save();
		$this->_clearHostedpaymentUrl($order->getRealOrderId());
	}
	
	private function _saveCard($snapOrder){
	    $token = $snapOrder->payment_transaction;
	    $order = $this->getOrder();
	    
	    $storedCard = Mage::getModel('hostedpayments/storedcard');
		$storedCard->setCustomerId($order->getCustomerId());
		$storedCard->setTokenId($snapOrder->merchant_order_id.'-ODT-1');
		$storedCard->setAcctName($token->acct_name);
		$storedCard->setAcctNum(substr($token->acct_num,-4,4));
		$storedCard->setAcctExp(strtotime($token->acct_exp));
		$storedCard->setAcctType($token->acct_type);
		$storedCard->setCurrencyCode($token->currency_code);
		$storedCard->setDataChanges(true);
		$storedCard->save();
	}
	
	/**
	 * Pays order.
	 */
	private function _payOrder($transactionId){
		$order = $this->getOrder();
		
		$order->getPayment()->setTransactionId($transactionId);
		$order->getPayment()->capture(null);
		$order->save();
	}
	
	private function _clearHostedpaymentUrl($order_id){
		$hostedpayment = Mage::getModel('hostedpayments/hostedpayment');
		$hostedpayment->load($order_id, 'order_id');
		$hostedpayment->setUrl('');
		$hostedpayment->setDataChanges(true);
		$hostedpayment->save();
	}
	
	private function _getTransactionId($snapOrder){
		$result = null;
		if($snapOrder->payment_transaction){
			$result = $snapOrder->payment_transaction->txn_id;
		}
		
		return $result;
	}
	
	/**
	 * Gets order checkout object.
	 * @return SnapCheckoutAbstract
	 */
	private function _getOrderCheckout(){
		$layout = $this->getCheckoutLayout();
		
		$mOrder = $this->getOrder();
		
		$checkout = new SnapOrder_Checkout();
		$checkout->return_url = Mage::getUrl('hostedpayments/processing/return', array('id' => $mOrder->getRealOrderId(), '_secure' => true));
		$checkout->cancel_url = $checkout->return_url;
		$checkout->auto_return = true;
		$checkout->checkout_layout = $layout;
		$checkout->create_token = $this->_isCardSaved();
		$checkout->language = EvoSnapTools::getLanguage(Mage::app()->getLocale()->getLocale()->getLanguage());

		$customer = new SnapCustomer();
		
		$customer->first_name = $mOrder->getCustomerFirstname();
		$customer->last_name = $mOrder->getCustomerLastname();
		$customer->email = $mOrder->getCustomerEmail();
		$customer->phone = $mOrder->getBillingAddress()->getTelephone();
		
		$checkout->customer = $customer;
		
		$checkout->order = $this->_getSnapOrder();

		return $checkout;
	}
	
	private function _getOrderItem($orderItem){
		$order_line = new SnapOrderLine();
		
		$order_line->sku = $orderItem->getSku();
		$order_line->name = $orderItem->getName();
		$order_line->description = $orderItem->getDescription();
		$order_line->qty = $orderItem->getQtyOrdered();
		$order_line->price = $orderItem->getPrice();
		$order_line->tax = $orderItem->getTaxAmount();
		
		
		return $order_line;
	}
	
	/**
	 * 
	 * @param Mage_Sales_Model_Order_Address $mAddress
	 * @return SnapAddress
	 */
	private function _getAddress($mAddress){
		$address = new SnapAddress();
		$address->company = $mAddress->getCompany();
		$address->first_name = $mAddress->getFirstname();
		$address->last_name = $mAddress->getLastname();
		$address->address1 = $mAddress->getStreet1();
		$address->address2 = $mAddress->getStreet2();
		$address->house_number = $mAddress->getStreet1();
		$address->city = $mAddress->getCity();
		$address->zipcode = $mAddress->getPostcode();
	
		$address->country = $mAddress->getCountry();
		$address->state = $mAddress->getRegionCode();
	
		return $address;
	}
	
	private function _getOrderId($order_prefix, $id_order){
		if(!empty($order_prefix)){
			$result = $order_prefix.$id_order;
		}else{
			$result = $id_order;
		}
	
		return $result;
	}
	
	
	/**
	 * Retrieves stored card data.
	 * @return Mage_Core_Model_Abstract
	 */
	public function getStoredCardData() {
	    $tokenId = (int) $this->getInfoInstance()->getAdditionalInformation('token_id');
	     
	    if($tokenId && ($tokenId > 0)){
	       return Mage::getModel('hostedpayments/storedcard')->load($tokenId);
	    }
	    
	    return false;
	}

	/**
	 * Tests if the card data is going to be saved in EVO Snap*.
	 * @return boolean
	 */
	private function _isCardSaved() {
	    $tokenId = (int) $this->getInfoInstance()->getAdditionalInformation('token_id');
	    return (boolean)($this->getInfoInstance()->getAdditionalInformation('create_token') === 'true') &&
	       !($tokenId && ($tokenId > 0));
	}
	
	/**
	 * Gets order checkout object.
	 * @return SnapCheckoutAbstract
	 */
	private function _getSnapOrder(){
	    $mOrder = $this->getOrder();
	     
		$order = new SnapOrder();
		
		$order->id = $this->_getOrderId($this->getConfigData('order_prefix'), $mOrder->getRealOrderId());
		$order->total_subtotal = $mOrder->getSubtotal();
		$order->total_discount = abs($mOrder->getDiscountAmount());
		$order->total_shipping = $mOrder->getShippingAmount();
		$order->total_tax = $mOrder->getTaxAmount();
		$order->total = $mOrder->getBaseGrandTotal();
		$order->currency_code = $mOrder->getOrderCurrencyCode();
		
		$products = $mOrder->getAllItems();
		for($i = 0; $i < count($products); $i++) {
			$order_lines[$i] = $this->_getOrderItem($products[$i]);
		}
		$order->lines = $order_lines;
		
		$billing = $mOrder->getBillingAddress();
		$shipping = $mOrder->getShippingAddress();
		
		$order->billto_address = $this->_getAddress($billing);
		if(!empty($shipping)){
			$order->shipto_address = $this->_getAddress($billing);
		}
		
		return $order;
	}

    /**
     * Pays the order with the specified token.
     * 
     * @param Storedcard $storedCard            
     */
    public function payWithToken($storedCard)
    {
        if(!$this->getConfigData('store_cards')){
            throw new Mage_Payment_Model_Info_Exception(Mage::helper('hostedpayments')->__('Card Store is disabled. Please contact support.'));
        }
        $order = $this->_getSnapOrder();
        $snapOrder = EvoSnapApi::processTokenOrder($storedCard->getTokenId(), $order, $this->getHostedPaymentsConfiguration());
        $this->_payOrder($snapOrder['txn_id']);
    }
	
	/**
	* prepare params array to send it to gateway page via POST
	*
	* @return array
	*/
	public function getFormFields()
	{
		Mage::log(" --- Snap* Hosted Payments API : getFormFields --- ");

		$aOBFields = array();
		$billing = $this->getOrder()->getBillingAddress();
		$shipping = $this->getOrder()->getShippingAddress();
		$items = $this->getOrder()->getAllItems();

		// customer info
		$aOBFields['customer[merchant_customer_id]'] = $this->getOrder()->getCustomerId();
		$aOBFields['customer[email]'] = $this->getOrder()->getCustomerEmail();
		$aOBFields['customer[first_name]'] = $billing->getFirstname();
		$aOBFields['customer[last_name]'] = $billing->getLastname();
		$aOBFields['customer[phone]'] = $billing->getTelephone();

		// order header
		$aOBFields['order[merchant_order_id]'] = $this->getOrder()->getRealOrderId();
		$aOBFields['order[total_subtotal]'] = self::_currencyAmount($this->getOrder()->getSubtotal());
		$aOBFields['order[total_discount]'] = self::_currencyAmount(abs($this->getOrder()->getDiscountAmount()));
		$aOBFields['order[total_shipping]'] = self::_currencyAmount($this->getOrder()->getShippingAmount());
		$aOBFields['order[total_tax]'] = self::_currencyAmount($this->getOrder()->getTaxAmount());
		$aOBFields['order[total]'] = self::_currencyAmount($this->getOrder()->getBaseGrandTotal());
		//$aOBFields['order[ship_method]'] = '';

		// billing fields
		$aOBFields['order[billto_company]'] = $billing->getCompany();
		$aOBFields['order[billto_first_name]'] = $billing->getFirstname();
		$aOBFields['order[billto_last_name]'] = $billing->getLastname();
		$aOBFields['order[billto_address1]'] = $billing->getStreet1();
		$aOBFields['order[billto_address2]'] = $billing->getStreet2();
		$aOBFields['order[billto_city]'] = $billing->getCity();
		$aOBFields['order[billto_state]'] = $billing->getRegionCode();
		$aOBFields['order[billto_country]'] = $billing->getCountry();
		$aOBFields['order[billto_zipcode]'] = $billing->getPostcode();

		// shipping fields
		if(!empty($shipping))
		{
			$aOBFields['order[shipto_company]'] = $shipping->getCompany();
			$aOBFields['order[shipto_first_name]'] = $shipping->getFirstname();
			$aOBFields['order[shipto_last_name]'] = $shipping->getLastname();
			$aOBFields['order[shipto_address1]'] = $shipping->getStreet1();
			$aOBFields['order[shipto_address2]'] = $shipping->getStreet2();
			$aOBFields['order[shipto_city]'] = $shipping->getCity();
			$aOBFields['order[shipto_state]'] = $shipping->getRegionCode();
			$aOBFields['order[shipto_country]'] = $shipping->getCountry();
			$aOBFields['order[shipto_zipcode]'] = $shipping->getPostcode();
		}

		// items
		if (!empty($items))
		{
			for($nCount=0; $nCount<count($items); $nCount++)
			{
				$aOBFields['order_item['.$nCount.'][sku]'] = $items[$nCount]->getSku();
				$aOBFields['order_item['.$nCount.'][name]'] = $items[$nCount]->getName();
				$aOBFields['order_item['.$nCount.'][price]'] = self::_currencyAmount($items[$nCount]->getPrice());
				$aOBFields['order_item['.$nCount.'][qty]'] = $items[$nCount]->getQtyOrdered();
				$aOBFields['order_item['.$nCount.'][description]'] = $items[$nCount]->getDescription();
				$aOBFields['order_item['.$nCount.'][tax]'] = self::_currencyAmount($items[$nCount]->getTaxAmount());
			}
		}

		// return URLs
		$aOBFields['return_url'] = Mage::getUrl('hostedpayments/standard/success', array('_secure' => true));
		$aOBFields['cancel_url'] = Mage::getUrl('hostedpayments/standard/cancel', array('_secure' => true));

		// check to see if the aheadWorks SARP module is being used and is enabled. if it is then we need to
		// add additional fields to the post fields array
		if($this->_isUsingSARP() === true)
		{
			Mage::log("is using SARP ");

			$aSubscriptionIDs = array();
			$aSubscriptionIDs = $this->_getSubscriptionByOrderID($this->getOrder()->getId());

			/*
			foreach($aSubscriptionIDs as $nID)
			{
				$oSubscription = Mage::getSingleton('sarp/subscription')->load($nID);
			}
			*/

			if(!empty($aSubscriptionIDs))
			{
				$oSubscription = Mage::getSingleton('sarp/subscription')->load($aSubscriptionIDs[0]);
				$dStartDate =  new Zend_Date($oSubscription->getNextSubscriptionEventDate($oSubscription->getDateStart()), Zend_Date::DATE_LONG);
				$nTotalOccurrences = 0;

				if($oSubscription->isInfinite())
					$nTotalOccurrences = 9999;
				else
					$nTotalOccurrences = Mage::getModel('sarp/sequence')->getCollection()->addSubscriptionFilter($oSubscription)->count();

				//Mage::log("Subscription ID: " . $aSubscriptionIDs[0]);
				//Mage::log("Subscription Inerval: " . $oSubscription->getPeriod()->getPeriodType()."s");
				//Mage::log("Subscription Occurences: " . $nTotalOccurrences);

				$aOBFields['sub[auto_process]'] = 0;
				$aOBFields['sub[start_date]'] = $dStartDate;
				$aOBFields['sub[interval_length]'] = $oSubscription->getPeriod()->getPeriodValue();
				$aOBFields['sub[interval_unit]'] = $oSubscription->getPeriod()->getPeriodType()."s";
				$aOBFields['sub[total_occurrences]'] = $nTotalOccurrences;

				$aOBFields['sub[trial_occurrences]'] = '';
				$aOBFields['sub[trial_amount]'] = 0.00;

				// order header
				$aOBFields['sub[merchant_subscription_id]'] = $this->getOrder()->getRealOrderId();
				//$aOBFields['sub[total_subtotal]'] = self::_currencyAmount($this->getOrder()->getSubtotal());

				// grand total should be the normal price not the first period price

				$aOBFields['sub[total_discount]'] = self::_currencyAmount(abs($this->getOrder()->getDiscountAmount()));
				$aOBFields['sub[total_tax]'] = self::_currencyAmount($this->getOrder()->getTaxAmount());
				$aOBFields['sub[ship_method]'] = '';
				$aOBFields['sub[total]'] = self::_currencyAmount($this->getOrder()->getBaseGrandTotal());

				// billing fields
				$aOBFields['sub[billto_company]'] = $billing->getCompany();
				$aOBFields['sub[billto_first_name]'] = $billing->getFirstname();
				$aOBFields['sub[billto_last_name]'] = $billing->getLastname();
				$aOBFields['sub[billto_address1]'] = $billing->getStreet1();
				$aOBFields['sub[billto_address2]'] = $billing->getStreet2();
				$aOBFields['sub[billto_city]'] = $billing->getCity();
				$aOBFields['sub[billto_state]'] = $billing->getRegionCode();
				$aOBFields['sub[billto_country]'] = $billing->getCountry();
				$aOBFields['sub[billto_zipcode]'] = $billing->getPostcode();

				// shipping fields
				if(!empty($shipping))
				{
					$aOBFields['sub[shipto_company]'] = $shipping->getCompany();
					$aOBFields['sub[shipto_first_name]'] = $shipping->getFirstname();
					$aOBFields['sub[shipto_last_name]'] = $shipping->getLastname();
					$aOBFields['sub[shipto_address1]'] = $shipping->getStreet1();
					$aOBFields['sub[shipto_address2]'] = $shipping->getStreet2();
					$aOBFields['sub[shipto_city]'] = $shipping->getCity();
					$aOBFields['sub[shipto_state]'] = $shipping->getRegionCode();
					$aOBFields['sub[shipto_country]'] = $shipping->getCountry();
					$aOBFields['sub[shipto_zipcode]'] = $shipping->getPostcode();
				}

				$items = $this->getOrder()->getAllItems();
				$nCount = 0;
				$nSubCount = 0;
				$aOBFields['sub[total_subtotal]'] = 0;
				$aOBFields['sub[total]'] = 0;

				// items
				if (!empty($items))
				{
					for($nCount=0; $nCount<count($items); $nCount++)
					{
						$oItem = $items[$nCount];

						if(Mage::helper('sarp')->isSubscriptionType($items[$nCount]) == 1)
						{
							$productId = $oItem->getProductId();
							$oProduct = Mage::getModel('catalog/product')->load($productId);
							$nFirstPeriodPrice = self::_currencyAmount($oProduct->getAwSarpFirstPeriodPrice());
							$nNormalPrice = self::_currencyAmount((!empty($nFirstPeriodPrice) ? $oProduct->getAwSarpSubscriptionPrice() : $items[$nCount]->getPrice()));

							Mage::log("Normal Price:" . $nNormalPrice);
							Mage::log("First Period Price:" . $nFirstPeriodPrice);

							if ($oItem->canInvoice()) $qtys[$oItem->getId()] = $oItem->getQtyToInvoice();

							$aOBFields['sub_item[' . $nSubCount . '][sku]'] = $items[$nCount]->getSku();
							$aOBFields['sub_item[' . $nSubCount . '][name]'] = $items[$nCount]->getName();
							$aOBFields['sub_item[' . $nSubCount . '][price]'] = $nNormalPrice;
							$aOBFields['sub_item[' . $nSubCount . '][qty]'] = $items[$nCount]->getQtyOrdered();
							$aOBFields['sub_item[' . $nSubCount . '][description]'] = $items[$nCount]->getDescription();
							//$aOBFields['sub_item[' . $nSubCount . '][details]'] = '';

							if ($oItem->getIsVirtual())
							{
								$aOBFields['sub[total_shipping]'] = 0.00;
							}
							else
							{
								$aOBFields['sub[total_shipping]'] = self::_currencyAmount($this->getOrder()->getShippingAmount());
							}

							/*
							$aOBFields['sub[trial_amount]'] = $nFirstPeriodPrice;
							*/

							$aOBFields['sub[total_subtotal]'] += $aOBFields['sub_item[' . $nSubCount . '][price]'];
							$aOBFields['sub[total]'] += $aOBFields['sub_item[' . $nSubCount . '][price]'];

							$nSubCount++;
						}
						else
							Mage::log("Not a subscription item: " . $items[$nCount]->getName());
					}
					Mage::log("Sub. Sub Total: " . $aOBFields['sub[total_subtotal]']);
					Mage::log("Sub. Total: " . $aOBFields['sub[total]']);
				}

				$aOBFields['sub[total]'] = $aOBFields['sub[total]'] + $aOBFields['sub[total_shipping]'];
			}
		}

		// generate the MAC and add it as the final param
		$aOBFields['mac'] = $this->getFormFieldsMAC($aOBFields);

		Mage::log(print_r($aOBFields, true));

		return $aOBFields;
	}

	/**
	* prepare the mac param based on the params array
	*
	* @return string
	*/
	public function getFormFieldsMAC($aOBFields)
	{
		Mage::log(" --- Snap* Hosted Payments API : getFormFieldsMAC --- ");
		return $this->_getMAC(array(
			'code' => $this->getConfigData('merchant_code'),
			'email' => (isset($aOBFields['customer[email]']) ? $aOBFields['customer[email]'] : ''),
			'merchant_order_id' => (isset($aOBFields['order[merchant_order_id]']) ? $aOBFields['order[merchant_order_id]'] : ''),
			'order_total_subtotal' => (isset($aOBFields['order[total_subtotal]']) ? $aOBFields['order[total_subtotal]'] : ''),
			'order_total' => (isset($aOBFields['order[total]']) ? $aOBFields['order[total]'] : ''),
			'merchant_subscription_id' => (isset($aOBFields['sub[merchant_subscription_id]']) ? $aOBFields['sub[merchant_subscription_id]'] : ''),
			'sub_total_occurrences' => (isset($aOBFields['sub[total_occurrences]']) ? $aOBFields['sub[total_occurrences]'] : ''),
			'sub_trial_occurrences' => (isset($aOBFields['sub[trial_occurrences]']) ? $aOBFields['sub[trial_occurrences]'] : ''),
			'sub_trial_amount' => (isset($aOBFields['sub[trial_amount]']) ? $aOBFields['sub[trial_amount]'] : ''),
			'sub_total_subtotal' => (isset($aOBFields['sub[total_subtotal]']) ? $aOBFields['sub[total_subtotal]'] : ''),
			'sub_total' => (isset($aOBFields['sub[total]']) ? $aOBFields['sub[total]'] : '')
		));
	}

	/**
	* Get base url of EVO Snap* Hosted Payments API
	*
	* @return string
	*/
	public function getEvoBaseUrl()
	{
		Mage::log(" --- Snap* Hosted Payments API : getEvoBaseUrl --- ");
		$bTestMode = $this->getConfigData('test_mode');
		return ($bTestMode ? $this->_merchantCheckoutURLTest : $this->_merchantCheckoutURL);
	}

// 	/**
// 	* capture an order payment
// 	*
// 	* @param   Varien_Object $payment
// 	* @return  Mage_Payment_Model_Abstract
// 	*/
// 	public function capture(Varien_Object $payment, $amount)
// 	{
// 		Mage::log(" --- Snap* Hosted Payments API : capture --- ");
// 		$payment->setStatus(self::STATUS_APPROVED)
// 			->setLastTransId($this->getTransactionId());

// 		return $this;
// 	}

	/**
	* cancel an order and refund the capture OR reverse the authorization which has not yet been captured,
	* depending on how the module is configured
	*
	* @param   Varien_Object $payment
	* @return  Mage_Payment_Model_Abstract
	*/
	public function cancel(Varien_Object $payment)
	{
		Mage::log(" --- Snap* Hosted Payments API : cancel --- ");
		return $this->refund($payment);
	}

	/**
	 * refund the amount with transaction id
	 *
	 * @access public
	 * @param string $payment Varien_Object object
	 * @return Mage_Payment_Model_Abstract
	 */
	public function refund(Varien_Object $oPayment, $nAmount)
	{
		Mage::log(" --- Snap* Hosted Payments API : refund --- ");

		if(empty($nAmount))
			$nAmount=null;

		if ($oPayment->getLastTransId())
		{
			// build the request
			$oOrder = $oPayment->getOrder();
			$aMACParamKeys = array('code','action','merchant_order_id','txn_id');
			$aRequest = $this->_buildAPIRequest(array(
				'action' => 'credit',
				'merchant_order_id' => $oOrder->getRealOrderId(),
				'txn_id' => $oPayment->getLastTransId(),
				'amount' => (!is_null($nAmount) ? self::_currencyAmount($nAmount) : $nAmount),
			), $aMACParamKeys);
			Mage::log($aRequest);

			// post the request
			$aResult = $this->_postAPIRequest($aRequest);
			Mage::log($aResult);

			// parse the response
			if ($aResult['success'] === true && isset($aResult['status']) && strtolower($aResult['status']) == 'approved') // approved
			{
				Mage::log('approved');
				if($aResult['txn_id'] != null)
					$oPayment->setLastTransId($aResult['txn_id']);
				$oPayment->setStatus(self::STATUS_SUCCESS);
				$oPayment->getOrder()->addStatusToHistory($oPayment->getOrder()->getStatus(), $this->_getHelper()->__('Payment has been refunded via Snap* Hosted Payments Checkout (transid # '.$aResult['txn_id'].').'));
			}
			else
			{
				$oPayment->setStatus(self::STATUS_ERROR);
				if ($aResult['success'] === true && isset($aResult['status']) && strtolower($aResult['status']) == 'declined') // declined
				{
					Mage::log('declined');
					$sError = 'Payment refund request was declined by Snap* Hosted Payments Checkout.'.(!empty($aResult['message']) ? ' '.$aResult['message'] : '');
				}
				else
				{
					Mage::log('failure');
					$sError = 'Payment refund request failed.'.(!empty($aResult['message']) ? ' '.$aResult['message'] : '');
				}
				Mage::getSingleton('adminhtml/session')->addError($this->_getHelper()->__($sError));
				$oPayment->getOrder()->addStatusToHistory($oPayment->getOrder()->getStatus(), $this->_getHelper()->__($sError));
				Mage::throwException($sError);
			}
		}

		return $this;
	}

	/**
	 *
	 */
	protected function _buildAPIRequest($aParams=array(), $aMACParamKeys=array())
	{
		Mage::log('_buildAPIRequest()');
		Mage::log('$aParams:');
		Mage::log($aParams);

		// build the request
		$aRequest = array_merge(array('code' => $this->getConfigData('merchant_code'), 'return' => 'serialize'), $aParams);
		foreach ($aRequest as $key => $value)
		{
			if (empty($value)) unset($aRequest[$key]);
		}

		// add the MAC
		$aRequest['mac'] = $this->_getMAC($aRequest, $aMACParamKeys);

		return $aRequest;
	}

	/**
	 *
	 */
	protected function _postAPIRequest($aRequest)
	{
		$mError = false;
		Mage::log('_postAPIRequest');

		if (!extension_loaded('curl'))
		{
			$mError = 'The curl extension was requested and is not loaded; unable to proceed.';
		}
		else
		{
			Mage::log('json_encode($aRequest)');
			Mage::log(json_encode($aRequest));

			// do the request
			$hCURL = curl_init();
			curl_setopt($hCURL, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($hCURL, CURLOPT_POST, 1);
			curl_setopt($hCURL, CURLOPT_FAILONERROR, 1);
			curl_setopt($hCURL, CURLOPT_FOLLOWLOCATION, 1);
			curl_setopt($hCURL, CURLOPT_TIMEOUT, 10);
			curl_setopt($hCURL, CURLOPT_URL, $this->getEvoAPIUrl());
			curl_setopt($hCURL, CURLOPT_POSTFIELDS, $aRequest);
			curl_setopt($hCURL, CURLOPT_SSL_VERIFYPEER, false);
			$sResult = curl_exec($hCURL);

			Mage::log('$sResult');
			Mage::log($sResult);
			Mage::log(curl_error($hCURL));

			curl_close($hCURL);
		}

		if ($mError !== false)
		{
			Mage::throwException($mError);
		}

		return $this->_parseAPIResponse($sResult);
	}

	/**
	 *
	 */
	public function _parseAPIResponse($sResult)
	{
		Mage::log(" --- Snap* Hosted Payments API : _parseAPIResponse --- ");
		Mage::log('_parseAPIResponse');
		Mage::log($sResult);

		// parse the response
		if ($sResult !== false) {
			return unserialize($sResult);
		} else {
			return false;
		}
	}

	protected static function _currencyAmount($amount)
	{
		return number_format($amount, 2, '.', '');
	}


	/*
		Determine whether or not the SARP module is being used
	*/
	protected function _isUsingSARP()
	{
		Mage::log(" --- Snap* Hosted Payments API : _isUsingSARP --- ");
		$bReturn = false;

		$oModules = Mage::getConfig()->getNode();
		if(array_key_exists($this->_sSARPName, $oModules->global->resources))
			$bReturn = true;

		return $bReturn;
	}

	protected function _getSubscriptionByOrderID($nOrderID)
	{
		Mage::log(" --- Snap* Hosted Payments API : _getSubscriptionByOrderID --- ");

		$aSubscriptionIDs = array();
		$oWrite = Mage::getSingleton('core/resource')->getConnection('core_write');

		$result = $oWrite->query("SELECT id FROM aw_sarp_subscriptions WHERE real_id = " . $nOrderID . ";");

		while ($aRows = $result->fetch(PDO::FETCH_ASSOC))
		{
			$aSubscriptionIDs[] = $aRows['id'];
		}

		return $aSubscriptionIDs;
	}

}