Your `config/netsuite.php` should look something like this

	$config['login'] = array(
		'production' => array(
			'email' => "email@domain.com",
		    'account' => "accountnumber",
		    'password' => "password",
		    'role' => "role"
		),
		'sandbox' => array(
			'email' => 'mbianco@ascensionpress.com',
			'password' => 'password',
		    'account' => 'accountnumber',
		    'role' => "role",
		)
	);

Here are some examples that are useful in inspecting netsuite objects in order to understand how to pull information or manipulate them.

	$netsuiteClientConnection = netsuite::getNetsuiteConnection();
	$customRecordSearch = new nsComplexObject("CustomRecordSearchBasic");
	$customRecordSearch->setFields(array(
		'recType' => new nsCustomRecordRef(array('internalId' => id of custom record)),
		'internalIdNumber' => array(
			'operator' => 'equalTo',
			'searchValue' => 'internal id of record to inspect'
		)
	));
	
	$netsuiteClientConnection->setSearchPreferences(TRUE, 10);
	$searchResponse = $netsuiteClientConnection->search($customRecordSearch);
	print_r($searchResponse);
	

The acceptable options for the `getAll()` method defined in the PHPToolkit are found [here](https://webservices.netsuite.com/xsd/platform/v2010_2_0/coreTypes.xsd) under the `name="GetAllRecordType"` node.

Note that pulling information about a record that is linked to a record which you don't have access to view will result in the [following error](http://usergroup.netsuite.com/users/showthread.php?t=28090) `Invalid custrecord_fieldname reference key 12345` with error code `INVALID_KEY_OR_REF`