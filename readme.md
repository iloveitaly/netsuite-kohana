##Config##
Your `config/netsuite.php` should look something like this

	$config['login'] = array(
		'production' => array(
			'email' => "email@domain.com",
		    'account' => "accountnumber",
		    'password' => "password",
		    'role' => "role"
		),
		'sandbox' => array(
			'email' => 'email@domain.com',
			'password' => 'password',
		    'account' => 'accountnumber',
		    'role' => "role",
		)
	);

##Examples##
###Search Custom Record###

	$netsuiteClientConnection = netsuite::getNetsuiteConnection();
	$customRecordSearch = new nsComplexObject("CustomRecordSearchBasic");
	$customRecordSearch->setFields(array(
		'recType' => new nsCustomRecordRef(array('internalId' => $customRecordTypeID)),
		'internalIdNumber' => array(
			'operator' => 'equalTo',
			'searchValue' => 'internal id of record to inspect'
		)
	));
	
	$netsuiteClientConnection->setSearchPreferences(TRUE, 10);
	$searchResponse = $netsuiteClientConnection->search($customRecordSearch);
	print_r($searchResponse);

###Retrieving All Items###

	$ns = netsuite::getNetsuiteConnection(FALSE);
	$searchResults = netsuite::entitySearch('ItemSearchBasic', array());

	do {
		foreach($searchResults as $item) {
			$fields = $item->getFields();
			print_r($fields)
		
		}
	} while(($searchResults = netsuite::nextEntitySearchPage()));

##Notes##
The acceptable options for the `getAll()` method defined in the PHPToolkit are found [here](https://webservices.netsuite.com/xsd/platform/v2010_2_0/coreTypes.xsd) under the `name="GetAllRecordType"` node.

Links to the XSD files can be found in the [Web Services Platform Guide PDF document](http://www.netsuite.com/portal/partners/integration/download/SuiteTalkWebServicesPlatformGuide_2011.1.pdf) under the heading "System Constants XSD Files".

Note that pulling information about a record that is linked to a record which you don't have access to view will result in the [following error](http://usergroup.netsuite.com/users/showthread.php?t=28090) `Invalid custrecord_fieldname reference key 12345` with error code `INVALID_KEY_OR_REF`
