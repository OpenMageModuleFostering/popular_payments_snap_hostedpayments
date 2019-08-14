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
*
* @author Antonio Molinero <antonio.molinero@evopayments.com>
* @copyright Copyright (c) 2015 EVO Snap* (http://www.evosnap.com)
* @license	EVO Payments International EULA
*/

class EvoSnapApi
{
	
	/**
	 * Retrieves orders.
	 * @param array $params request parameters.
	 * @param HostedPayments $cfg Hosted Payments Configuration.
	 * @return the order status.
	 */
	public static function getOrders($params, $cfg){
		$aPost = EvoSnapApi::getEvoSnapOrdersPost($params, $cfg->code, $cfg->key);
		$orders = EvoSnapTools::callEvoSnap($aPost, $cfg->getUrl(false), $cfg->environment);
		if ($orders ['success'] != false) {
			return $orders['orders'];
		} else {
			throw new HostedPayments_Exception($orders['message']);
		}
	}
	
	private static function getEvoSnapOrdersPost($params, $code, $key){
		$aPost = array(
				'action' => 'get_orders',
				'code' => $code,
				'return' => 'json',
		);
		$aPost['mac'] = EvoSnapApi::getOrdersMac($aPost, $key);
		$aPost = array_merge($aPost, $params);
		return $aPost;
	}
	
	private static function getOrdersMac($aPost, $key){
		$aMACParams = array(
				'code' 	=> $aPost['code'],
				'action' => $aPost['action'],
		);
		
		return EvoSnapTools::getMac($aMACParams, $key);
	}
	
	/**
	 * Retrieves an order.
	 * @param string $id the order ID.
	 * @param HostedPayments $cfg Hosted Payments Configuration.
	 * @return the order status.
	 */
	public static function getOrder($id, $cfg){
		$aPost = EvoSnapApi::getEvoSnapOrderPost($id, $cfg->code, $cfg->key);
		$order = EvoSnapTools::callEvoSnap($aPost, $cfg->getUrl(false), $cfg->environment);
		if ($order ['success'] != false) {
			$raw_order = $order['order'];
			EvoSnapApi::setOrderTransactionData($raw_order);
			return $raw_order;
		} else {
			throw new HostedPayments_Exception($order['message']);
		}
	}
	
	private static function setOrderTransactionData($raw_order){
		$total_refunded = 0;
		if(isset($raw_order->transactions) && is_array($raw_order->transactions)){
			foreach($raw_order->transactions as $txn){
				if($txn->txn_action === 'sale'){
					$raw_order->payment_transaction = $txn;
				}elseif($txn->txn_action === 'credit'){
					$total_refunded += $txn->txn_amount;
				}
			}
		}
		
		$raw_order->total_refunded = $total_refunded;
	}
	
	/**
	 * Gets order status.
	 * @param string $id the order ID.
	 * @param HostedPayments $cfg Hosted Payments Configuration.
	 * @return the order status.
	 */
	public static function getOrderStatus($id, $cfg){
		$order = EvoSnapApi::getOrder($id, $cfg);
		
		if ($order != null) {
			$result = $order->status;
		} else {
			$result = null;
		}
		
		return $result;
	}
	
	private static function getEvoSnapOrderPost($id, $code, $key){
		$aPost = array(
				'action' => 'get_order',
				'code' => $code,
				'merchant_order_id' => $id,
				'return' => 'json',
		);
		$aPost['mac'] = EvoSnapApi::getOrderMac($aPost, $key);
		
		return $aPost;
	}
	
	private static function getOrderMac($aPost, $key){
		$aMACParams = array(
				'code' 	=> $aPost['code'],
				'action' => $aPost['action'],
				'merchant_order_id' => $aPost['merchant_order_id']
		);
		
		return EvoSnapTools::getMac($aMACParams, $key);
	}
	
	/**
	 * Gets checkout order
	 * @param SnapOrder_Checkout $order the order checkout.
	 * @param float $trigger3ds value that triggers 3D secure.
	 * @param HostedPayments $cfg Hosted Payments Configuration.
	 */
	public static function getOrderCheckoutUrl($order, $trigger3ds, $cfg) {
		$aPost = EvoSnapApi::getEvoSnapCehckoutPost($order, $trigger3ds, $cfg->code, $cfg->key);
		$result = EvoSnapTools::callEvoSnap($aPost, $cfg->getUrl(true), $cfg->environment);
		if ($result ['success'] != false) {
			return $result ['url'];
		} else {
			throw new HostedPayments_Exception($result['message']);
		}
	}
	
	/**
	 * 
	 * @param SnapOrder_Checkout $checkout
	 * @param float $trigger3ds
	 * @param string $code
	 * @param string $key
	 * @return array
	 */
	private static function getEvoSnapCehckoutPost($checkout, $trigger3ds, $code, $key){
		$returnUrl = $checkout->return_url;
		$cancelUrl = $checkout->cancel_url;
		
		$customer = $checkout->customer;
		$aCustomer = array(
			'merchant_customer_id' => $customer->id,
			'first_name' =>EvoSnapTools::getString($customer->first_name),
			'last_name' => EvoSnapTools::getString($customer->last_name),
			'phone' => EvoSnapTools::getString($customer->phone, 15),
			'email' => EvoSnapTools::getString($customer->email, 50)
		);
		
		$order = $checkout->order;
		$aOrder = array(
			'merchant_order_id' => EvoSnapTools::getString($order->id, 255),
			'total_subtotal' => EvoSnapTools::getNumber($order->total_subtotal),
			'total_tax' => EvoSnapTools::getNumber($order->total_tax),
			'total_shipping' => EvoSnapTools::getNumber($order->total_shipping),
			'total_discount' => EvoSnapTools::getNumber($order->total_discount),
			'total' => EvoSnapTools::getNumber($order->total),
			'ship_method' => EvoSnapTools::getString($order->ship_method),
			'currency_code' => EvoSnapTools::getString($order->currency_code),
			'enable_3d' => EvoSnapTools::getBoolean(isset($trigger3ds) && ($checkout->order->total >= $trigger3ds))
		);
		
		$aOrderLines = array();
		$products = $order->lines;
		for($i = 0; $i < count($products); $i++) {
			$aOrderLines[$i] = EvoSnapApi::getOrderItem($products[$i]);
		}
		
		$aOrder = array_merge($aOrder, EvoSnapTools::getAddress('billto', $order->billto_address));
		if(isset($order->shipto_address)){
			$aOrder = array_merge($aOrder, EvoSnapTools::getAddress('shipto', $order->shipto_address));
		}
		
		$aPost = array(
				'action' => $checkout->getAction(),
				'code' => $code,
				'customer' => $aCustomer,
				'order' => $aOrder,
				'order_item' => $aOrderLines,
				'return_url' => $returnUrl,
				'cancel_url' => $cancelUrl,
				'auto_return' => '1',
				'checkout_layout' => $checkout->checkout_layout,
				'language' => $checkout->language
		);
		$aPost['mac'] = EvoSnapApi::getOrderCheckoutMac($aPost, $key);
		
		return $aPost;
	}
	
	/**
	 * 
	 * @param OrderLine $orderItem
	 * @return array
	 */
	private static function getOrderItem($orderItem){
		return array(
			'sku' => EvoSnapTools::getString($orderItem->sku, 25),
			'name' => EvoSnapTools::getString($orderItem->name, 30),
			'description' => EvoSnapTools::getString($orderItem->description, 300),
			'qty' => EvoSnapTools::getNumber($orderItem->qty),
			'price' => EvoSnapTools::getNumber($orderItem->price),
			'tax' => EvoSnapTools::getNumber($orderItem->tax)
		);
	}
	
	private static function getOrderCheckoutMac($aPost, $key){
		$aMACParams = array(
				'code' 	=> $aPost['code'],
				'email' => $aPost['customer']['email'],
				'order_total_subtotal' => $aPost['order']['total_subtotal'],
				'order_total' => $aPost['order']['total'],
				'merchant_order_id' => $aPost['order']['merchant_order_id']
		);
		
		return EvoSnapTools::getMac($aMACParams, $key);
	}

	/**
	 * Credits an order.
	 * @param string $id the order ID.
	 * @param string $txnId the transaction ID.
	 * @param float $amount the amount to credit, null value defaults to original transaction amount.
	 * @param HostedPayments $cfg Hosted Payments Configuration.
	 * @return the order status.
	 */
	public static function creditOrder($id, $txnId, $amount, $cfg){
		$aPost = EvoSnapApi::getEvoSnapCreditOrderPost($id, $txnId, $amount,
				$cfg->code, $cfg->key);
		$credit = EvoSnapTools::callEvoSnap($aPost, $cfg->getUrl(false), $cfg->environment);
		if ($credit ['success'] != false) {
			return $credit['txn_id'];
		} else {
			throw new HostedPayments_Exception($credit['message']);
		}
	}

	private static function getEvoSnapCreditOrderPost($id, $txnId, $amount, $code, $key){
		$aPost = array(
				'action' => 'credit',
				'code' => $code,
				'merchant_order_id' => $id,
				'txn_id' => $txnId,
				'return' => 'json'
		);
		$aPost['mac'] = EvoSnapApi::getCreditOrderMac($aPost, $key);
		
		if($amount != null){
			$aPost['amount'] = $amount;
		}
		
		return $aPost;
	}
	
	private static function getCreditOrderMac($aPost, $key){
		$aMACParams = array(
				'code' 	=> $aPost['code'],
				'action' => $aPost['action'],
				'merchant_order_id' => $aPost['merchant_order_id'],
				'txn_id' => $aPost['txn_id']
		);
		
		return EvoSnapTools::getMac($aMACParams, $key);
	}
}