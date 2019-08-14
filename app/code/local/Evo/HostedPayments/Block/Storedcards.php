<?php

/* Copyright (c) 2016 EVO Payments International - All Rights Reserved.
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
 * EVO Snap* Stored Cards Block.
 *
 * @category EVO
 * @package	Evo
 * @copyright Copyright (c) 2016 EVO Snap* (http://www.evosnap.com)
 * @license	EVO Payments International EULA
 */
class Evo_HostedPayments_Block_Storedcards extends Mage_Core_Block_Template
{
	/**
	 * Varien constructor
	 */
	protected function _construct()
	{
		parent::_construct();
		$this->setTemplate('evo/storedcards.phtml');
        $storedcards = Mage::getResourceModel('hostedpayments/storedcard_collection')->addFieldToSelect('*')
            ->addFieldToFilter('customer_id', Mage::getSingleton('customer/session')->getCustomer()->getId());
		
        $this->setStoredCards($storedcards);
	}

    protected function _prepareLayout()
    {
        $pager = $this->getLayout()->createBlock('page/html_pager', 'hostedpayments.storedcards.pager')
            ->setCollection($this->getStoredCards());
        $pager->setShowAmounts(false);
        $this->setChild('pager', $pager);
        $this->getStoredCards()->load();
        return parent::_prepareLayout();
    }
    
    /**
     * Generates Pager code.
     * @return Ambigous <string, multitype:>
     */
    public function getPagerHtml()
    {
        return $this->getChildHtml('pager');
    }
    
    /**
     * Retrieves back URL.
     * @return Ambigous <string, mixed>
     */
    public function getBackUrl()
    {
        if ($this->getRefererUrl()) {
            return $this->getRefererUrl();
        }
        return $this->getUrl('customer/account/', array('_secure'=>true));
    }
    
}