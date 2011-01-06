
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
	