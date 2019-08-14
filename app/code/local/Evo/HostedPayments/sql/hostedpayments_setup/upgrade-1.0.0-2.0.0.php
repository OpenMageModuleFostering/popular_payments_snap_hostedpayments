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
 * EVO Snap* Hosted Payments Upgrade script.
 *
 * @category EVO
 * @package	Evo
 * @copyright Copyright (c) 2016 EVO Snap* (http://www.evosnap.com)
 * @license	EVO Payments International EULA
 */
$installer = $this;
$installer->startSetup();
$table = $installer->getConnection()->newTable($installer->getTable('hostedpayments/storedcard'))
    ->addColumn('id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
        'unsigned' => true,
        'nullable' => false,
        'primary' => true,
        'identity' => true,
        ), 'Entity ID')
	->addColumn('customer_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
        'nullable' => false,
        'unique' => true,
        ), 'Customer ID')
    ->addColumn('token_id', Varien_Db_Ddl_Table::TYPE_VARCHAR, 50, array(
        'nullable' => false,
        'unique' => true,
        ), 'Token ID')
    ->addColumn('acct_name', Varien_Db_Ddl_Table::TYPE_TEXT, 255, array(
        'nullable' => false,
        ), 'Account Name')
    ->addColumn('acct_num', Varien_Db_Ddl_Table::TYPE_TEXT, 4, array(
        'nullable' => false,
        ), 'Account Number')
    ->addColumn('acct_exp', Varien_Db_Ddl_Table::TYPE_DATE, null, array(
        'nullable' => false,
        ), 'Account Type')
    ->addColumn('acct_type', Varien_Db_Ddl_Table::TYPE_TEXT, 16, array(
        'nullable' => false,
        ), 'Account Type')
    ->addColumn('currency_code', Varien_Db_Ddl_Table::TYPE_TEXT, 3, array(
        'nullable' => true
        ), 'Currency Code')
    ->addIndex($installer->getIdxName('hostedpayments/storedcard', array('customer_id')),
        array('customer_id'))
    ->addForeignKey($installer->getFkName('hostedpayments/storedcard', 'customer_id', 'customer/entity', 'entity_id'),
        'customer_id', $installer->getTable('customer/entity'), 'entity_id',
        Varien_Db_Ddl_Table::ACTION_CASCADE, Varien_Db_Ddl_Table::ACTION_CASCADE)
    ->setComment('Snap* Hosted Payments Stored Cards table');
$installer->getConnection()->createTable($table);
$installer->endSetup();