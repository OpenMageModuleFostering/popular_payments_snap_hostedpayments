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
 * EVO Snap* Hosted Info Block.
 *
 * @category EVO
 * @package	Evo
 * @copyright Copyright (c) 2015 EVO Snap* (http://www.evosnap.com)
 * @license	EVO Payments International EULA
 */
class Evo_HostedPayments_Block_Info extends Mage_Payment_Block_Info
{
	/**
	 * Varien constructor
	 */
	protected function _construct()
	{
		parent::_construct();
		$this->setTemplate('evo/info.phtml');
	}
	
	protected function _getPaymentLink(){
		$result = null;
		$paymentInst = $this->getInfo()->getMethodInstance();
		$snapUrl = $paymentInst->getSnapOrderUrl();
		if($snapUrl !== null){
			$result = $snapUrl;
		}
		
		return $result;
	}
	
	/**
	 * Retrieves stored card data.
	 * @return Mage_Core_Model_Abstract
	 */
	public function getStoredCardData() {
	    $tokenId = (int) $this->getInfo()->getAdditionalInformation('token_id');
	     
	    if($tokenId && ($tokenId > 0)){
	       return Mage::getModel('hostedpayments/storedcard')->load($tokenId);
	    }
	    return false;
	}

	/**
	 * Tests if the card data is going to be saved in EVO Snap*.
	 * @return boolean
	 */
	public function isCardSaved() {
	    $tokenId = (int) $this->getInfo()->getAdditionalInformation('token_id');
	    return ($this->getInfo()->getAdditionalInformation('create_token') === 'true') &&
	       !($tokenId && ($tokenId > 0));
	}

}