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
* EVO Snap* Hosted Payments processing class.
*
* @category EVO
* @package	Evo
* @copyright Copyright (c) 2016 EVO Snap* (http://www.evosnap.com)
* @license	EVO Payments International EULA
*/
class Evo_HostedPayments_IndexController extends Mage_Core_Controller_Front_Action {

    /**
     * Action predispatch
     *
     * Check customer authentication for some actions
     */
    public function preDispatch()
    {
        parent::preDispatch();
        $action = $this->getRequest()->getActionName();
        $loginUrl = Mage::helper('customer')->getLoginUrl();

        if (!Mage::getSingleton('customer/session')->authenticate($this, $loginUrl)) {
            $this->setFlag('', self::FLAG_NO_DISPATCH, true);
        }
    }
    
    /**
     * List stored cards action.
     */
    public function indexAction() {
        $this->loadLayout();
        $this->renderLayout();
    }

    /**
     * Deletes a stored card. 
     */
    public function deleteAction()
    {
        $storedCardId = $this->getRequest()->getParam('id', false);

        if ($storedCardId) {
            $storedCard = Mage::getModel('hostedpayments/storedcard')->load($storedCardId);

            // Validate card_id <=> customer_id
            if ($storedCard->getCustomerId() != Mage::getSingleton('customer/session')->getCustomerId()) {
                Mage::getSingleton('core/session')->addError($this->__('The stored card does not belong to this customer.'));
                $this->getResponse()->setRedirect(Mage::getUrl('*/*/index'));
                return;
            }

            try {
                $storedCard->delete();
                Mage::getSingleton('core/session')->addSuccess($this->__('The stored card has been deleted.'));
            } catch (Exception $e){
                Mage::getSingleton('core/session')->addException($e, $this->__('An error occurred while deleting the stored card.'));
            }
        }
        $this->getResponse()->setRedirect(Mage::getUrl('*/*/index'));
    }
}