<?php

$installer = $this;

$installer->startSetup();

$table = $installer->getConnection()
	->newTable($installer->getTable('worldpay/payment'))
	->addColumn('id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
			'auto_increment' => true,
			'identity'  => true,
			'unsigned'  => true,
			'nullable'  => false,
			'primary'   => true
		), 'Id')
	->addColumn('customer_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
			'unsigned'  => true,
			'nullable'  => false
		), 'Customer Id')

	->addColumn('token', Varien_Db_Ddl_Table::TYPE_VARCHAR, 255, array(
		), 'Token');

$installer->getConnection()->createTable($table);

$installer->endSetup();
