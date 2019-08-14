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
 * EVO Snap* Hosted Payments processing class.
 *
 * @category EVO
 * @package	Evo
 * @copyright Copyright (c) 2015 EVO Snap* (http://www.evosnap.com)
 * @license	EVO Payments International EULA
 */
class Evo_HostedPayments_ProcessingController extends Mage_Core_Controller_Front_Action
{
	const STATUS_APPROVED   = 'APPROVED';

	const EVO_CALLBACK_STATUS_CHECKOUT_CANCELLED = 'CHECKOUT_CANCELLED';
	const EVO_CALLBACK_STATUS_CHECKOUT_COMPLETED = 'CHECKOUT_COMPLETED';
	const EVO_CALLBACK_STATUS_CHECKOUT_HELD_FOR_ADMIN = 'CHECKOUT_HELD_FOR_ADMIN';
	const EVO_CALLBACK_STATUS_SUB_NOTIFIED = 'SUB_NOTIFIED';
	const EVO_CALLBACK_STATUS_SUB_PROCESSED = 'SUB_PROCESSED';
	const EVO_CALLBACK_STATUS_SUB_COMPLETED = 'SUB_COMPLETED';
	const EVO_CALLBACK_STATUS_SUB_DECLINED = 'SUB_DECLINED';
	const EVO_CALLBACK_STATUS_SUB_FAILED = 'SUB_FAILED';
	const EVO_CALLBACK_STATUS_SUB_CANCELLED = 'SUB_CANCELLED';
	const EVO_CALLBACK_STATUS_ORDER_CREDIT = 'ORDER_CREDIT';
	const EVO_CALLBACK_STATUS_ORDER_CHARGEBACK = 'ORDER_CHARGEBACK';

	const EVO_ORDER_STATUS_CANCELED = 'Canceled';
	const EVO_ORDER_STATUS_PENDING = 'Pending';
	const EVO_ORDER_STATUS_PAID = 'Paid';
	const EVO_ORDER_STATUS_REFUNDED = 'Refunded';
	const EVO_ORDER_STATUS_DENIED = 'Denied';
	
	/**
	 * Get singleton of Checkout Session Model
	 *
	 * @return Mage_Checkout_Model_Session
	 */
	private function _getCheckout()
	{
		return Mage::getSingleton('checkout/session');
	}

	/**
	 * Payment action.
	 */
	public function payAction()
	{
		try {
			$session = $this->_getCheckout();
	
			$orderId = $this->getRequest()->getParam('id');
			
			if(isset($orderId)){
				$session->setEvoRealOrderId($orderId);
				$session->unsEvoPaymentUrl();
			}else{
				$session->setEvoRealOrderId($session->getLastRealOrderId());
			}
	
			$paymentUrl = $session->getEvoPaymentUrl();
			$useIframe = $session->getEvoUseIframe();
		
			if(!isset($paymentUrl)){
				$order = Mage::getModel('sales/order');
		
				$order->loadByIncrementId($session->getEvoRealOrderId());
				
				$paymentInst = $order->getPayment()->getMethodInstance();
				
				$paymentUrl = $paymentInst->getPaymentUrl();
				$session->setEvoPaymentUrl($paymentUrl);
				$useIframe = $paymentInst->getCheckoutLayout() === 'iframe'; 
				$session->setEvoUseIframe($useIframe);
			}
			
			if($useIframe){
				$this->loadLayout();
 				$this->getLayout()->getBlock('hostedpayments.iframe')->setPaymentUrl($paymentUrl);
				$this->renderLayout();
			}else{
				$this->_redirectUrl($paymentUrl);
			}
	
			$session->unsQuoteId();
			$session->unsLastRealOrderId();
		} catch (HostedPayments_Exception $e) {
			Mage::log('Hosted Payments Error: '.$e->getMessage(), Zend_Log::ERR);
			$this->_redirect('customer/account');
		}
	}

	/**
	 * Return action.
	 */
	public function returnAction()
	{
		$this->_getCheckout()->unsEvoPaymentUrl();
		try {
			$order = Mage::getModel('sales/order');
	
			$order->loadByIncrementId($this->getRequest()->getParam('id'));
			$noiframe = $this->getRequest()->getParam('noiframe');
			
			$paymentInst = $order->getPayment()->getMethodInstance();
			
			if(!isset($noiframe) && ($paymentInst->getCheckoutLayout() === 'iframe')){
				$validationUrl = Mage::getUrl('hostedpayments/processing/return', array('id' => $this->getRequest()->getParam('id'), 'noiframe' => 1, '_secure' => true));
				$this->loadLayout();
 				$this->getLayout()->getBlock('hostedpayments.iframevalidation')->setValidationUrl($validationUrl);
				$this->renderLayout();
			}else{
				$snapOrder = $paymentInst->getSnapOrder();
				
				switch ($snapOrder->status){
					case self::EVO_ORDER_STATUS_CANCELED:
						$paymentInst->cancelOrder();
						$this->_redirect('customer/account');
						break;
					case self::EVO_ORDER_STATUS_PAID:
						$paymentInst->payOrder($snapOrder);
						$this->_successAction($paymentInst->getOrder());
						break;
					case self::EVO_ORDER_STATUS_PENDING:
					case self::EVO_ORDER_STATUS_DENIED:
						$this->_redirect('hostedpayments/processing/pay', array('id' => $this->getRequest()->getParam('id'), '_secure' => true));
						break;
					default:
						$this->_redirect('customer/account');
						break;
				}
			}
		} catch (HostedPayments_Exception $e) {
			Mage::log('Hosted Payments Error: '.$e->getMessage(), Zend_Log::ERR);
			$this->_redirect('customer/account');
		}
	}

	public function callbackAction()
	{
		$aResponse = $this->_verifyCallback();
		if($aResponse['success'] === true)
		{
			$aResponse = $this->_processCallback();
		}
		echo json_encode($aResponse);
		Mage::log($aResponse);
	}

	/**
	 * Evo returns POST variables to this action
	 */
	private function _successAction(Mage_Sales_Model_Order $order)
	{
		$session = $this->_getCheckout();

		$session->unsEvoRealOrderId();
		$session->setQuoteId($order->getQuoteId());
		$session->getQuote()->setIsActive(false)->save();

		if($order->getId())
		{
			$order->sendNewOrderEmail();
		}

		$this->_redirect('checkout/onepage/success');
	}

	private function _verifyCallback()
	{
		try
		{
			$aMessageTypes = array(
					self::EVO_CALLBACK_STATUS_CHECKOUT_CANCELLED,
					self::EVO_CALLBACK_STATUS_CHECKOUT_COMPLETED,
					self::EVO_CALLBACK_STATUS_CHECKOUT_HELD_FOR_ADMIN,
					self::EVO_CALLBACK_STATUS_SUB_NOTIFIED,
					self::EVO_CALLBACK_STATUS_SUB_PROCESSED,
					self::EVO_CALLBACK_STATUS_SUB_COMPLETED,
					self::EVO_CALLBACK_STATUS_SUB_DECLINED,
					self::EVO_CALLBACK_STATUS_SUB_FAILED,
					self::EVO_CALLBACK_STATUS_SUB_CANCELLED,
					self::EVO_CALLBACK_STATUS_ORDER_CREDIT,
					self::EVO_CALLBACK_STATUS_ORDER_CHARGEBACK
			);

			// check to make sure we have a post to check
			if($this->getRequest()->isPost() !== true)
				throw new Exception('Invalid request type');

			// check response
			$response = $this->getRequest()->getPost();
			if(empty($response))
				throw new Exception('Response doesn\'t contain POST elements');

			// check message type
			if(!in_array($response['message_type'], $aMessageTypes))
				throw new Exception('Invalid message type '.$response['message_type']);

			// check to make sure we have an order with the corresponding passed
			if (empty($response['merchant_order_id']) || strlen($response['merchant_order_id']) > 50)
				throw new Exception('Missing or invalid order ID # '.$response['merchant_order_id']);

			// load the order, make sure it's valid
			$this->_order = Mage::getModel('sales/order')->loadByIncrementId($response['merchant_order_id']);
			if (!$this->_order->getId())
				throw new Exception('Non-existent order # '.$response['merchant_order_id']);

			// load the payment instance and verify the MAC
			$this->_paymentInst = $this->_order->getPayment()->getMethodInstance();
			$response['code'] = $this->_paymentInst->getConfigData('merchant_code');
			if ($this->_paymentInst->_verifyMAC($response['mac'], $response, array('code','message_type','merchant_order_id','merchant_subscription_id','txn_id')) !== true)
				throw new Exception('Invalid mac signature');

			return array('success' => true);
		}
		catch (Exception $e)
		{
			return array('success' => false, 'message' => $e->getMessage());
		}
	}

	private function _processCallback()
	{
		$response = $this->getRequest()->getPost();
		$aStatus = array('success' => false);
		$order = Mage::getModel('sales/order');
		$order->loadByIncrementId($response['merchant_order_id']);
		$paymentInst = $order->getPayment()->getMethodInstance();
		$paymentInst->setResponse($response);

		switch($response['message_type'])
		{
			case self::EVO_CALLBACK_STATUS_CHECKOUT_COMPLETED:
				$order->getPayment()->setTransactionId($response['txn_id']);
				$invoice = $order->prepareInvoice();
				$invoice->register()->capture();
				Mage::getModel('core/resource_transaction')
				->addObject($invoice)
				->addObject($invoice->getOrder())
				->save();
				$aStatus = array('success' => true);
				break;

			case self::EVO_CALLBACK_STATUS_CHECKOUT_CANCELLED:
				if ($order->canCancel())
					$order->cancel();
					$order->addStatusToHistory($order->getStatus(), Mage::helper('hostedpayments')->__('Customer cancelled this order via Snap* Hosted Payments Checkout.'));
					$aStatus = array('success' => true);
					break;

			case self::EVO_CALLBACK_STATUS_ORDER_CREDIT:

				$order->getPayment()
				->setPreparedMessage($response["message_description"])
				->setTransactionId($response['txn_id'])
				->setIsTransactionClosed(true)
				->registerRefundNotification(-1*$order->base_grand_total);
				$order->save();
				//$order->getPayment()->refund();
				logToFile($order->getStatus());
				$order->addStatusToHistory($order->getStatus(), Mage::helper('hostedpayments')->__('Payment for this refunded via Snap* Hosted Payments API&trade;.'));

				if ($creditmemo = $paymentInst->getCreatedCreditmemo()) {
					$creditmemo->sendEmail();
					$comment = $order->addStatusHistoryComment(
							Mage::helper('paypal')->__('Notified customer about creditmemo #%s.', $creditmemo->getIncrementId())
					)
					->setIsCustomerNotified(true)
					->save();
				}


				$aStatus = array('success' => true);
				break;

		}

		$order->save();
		return $aStatus;
	}

}