<?
class netsuite_Core {
	public static $netsuiteConnection;
	
	public static function getNetsuiteConnection($sandbox = false) {
		if(empty(netsuite::$netsuiteConnection)) {
			require Kohana::find_file('vendor', "netsuite/PHPtoolkit");
			require Kohana::find_file('vendor', "netsuite/directory_v2011.1");

			netsuite::$netsuiteConnection = new nsClient($sandbox ? nsHost::sandbox : nsHost::live);

			// set request level credentials. (email, password, account#, internal id of role)
			netsuite::$netsuiteConnection->setPassport(
				Kohana::config('netsuite.email'),
				Kohana::config('netsuite.password'),
				Kohana::config('netsuite.account'),
				Kohana::config('netsuite.role')
			);
		}
		
		return netsuite::$netsuiteConnection;		
	}
	
	// this will grab the structure of a custom record that can then be used to generate a mapping
	// note that the identifier can be either a ID or scriptId
	public static function getCustomRecord($customRecordIdentifier) {
		if(is_string($customRecordIdentifier) && strstr($customRecordIdentifier, 'custrecord_') !== FALSE) {
			$referenceType = 'scriptId';
		} else {
			$referenceType = 'internalId';
		}
		
		$response = self::$netsuiteConnection->getList(array(
			new nsCustomizationRef(array(
				$referenceType => $customRecordIdentifier,
				'type' => 'customRecordType',
			))
		));

		$response = $response[0];
		
		if(!$response->isSuccess) {
			return false;
		}
		
		return $response;
		
	}
	
	// this will get the raw information about a custom list. It's used within getCustomListMapping()
	// note that the identifier can be either a ID or scriptId
	public static function getCustomList($customListIdentifier) {
		if(is_string($customListIdentifier) && strstr($customListIdentifier, 'customlist_') !== FALSE) {
			$referenceType = 'scriptId';
		} else {
			$referenceType = 'internalId';
		}
		
		$response = self::$netsuiteConnection->getList(array(
			new nsCustomizationRef(array(
				$referenceType => $customListIdentifier,
				'type' => 'customList',
			))
		));

		$response = $response[0];
		
		if(!$response->isSuccess) {
			return false;
		}
		
		// $response->record contains a ComplexObject which contains a customValueList field which contains all the values
		return $response->record;
	}
	
	public static function getAllCustom($customRecordTypeInternalId) {
		// TODO: handle pagination
		// note: this should not be used for custom record types which have many records associated with them
		
		$customRecordSearch = new nsComplexObject("CustomRecordSearchBasic");
		$customRecordSearch->setFields(array(
			'recType' => new nsCustomRecordRef(array(
				'internalId' => $customRecordTypeInternalId,
			)),
		));
		
		self::$netsuiteConnection->setSearchPreferences(TRUE, 100);
		$searchResponse = self::$netsuiteConnection->search($customRecordSearch);
		
		return $searchResponse;
	}
	
	public static function getCustomWithID($customRecordTypeInternalId, $recordInternalId) {
		$result = self::$netsuiteConnection->getList(array(
			new nsCustomRecordRef(array(
				'typeId' => $customRecordTypeInternalId,
				'internalId' => $recordInternalId
			))
		));
		
		$result = $result[0];
		
		if($result->isSuccess) {
			return $result->record;
		} else {
			return false;
		}
	}
	
	public static function getCustomRecordMapping() {
		$customRecords = self::$netsuiteConnection->getCustomizationId('customRecordType', true);
		$customRecordShortcuts = array();
		
		foreach($customRecords->customizationRefList as $customRecord) {
			$customRecordId = $customRecord->getField('internalId');
			
			// attempt to get the values
			// note that we don't want to grab the values if there is *many* records
			// the cut off is 25
			
			$customRecordSearch = new nsComplexObject("CustomRecordSearchBasic");
			$customRecordSearch->setFields(array(
				'recType' => new nsCustomRecordRef(array(
					'internalId' => $customRecordId,
				)),
			));

			self::$netsuiteConnection->setSearchPreferences(FALSE, 50);
			$searchResponse = self::$netsuiteConnection->search($customRecordSearch);
			$customRecordList = array();
			
			$customRecordList = array();
			
			if($searchResponse->totalPages == 1) {
				// echo "List for record: ".$customRecord->getField('scriptId');
				// print_r($searchResponse);
				
				foreach($searchResponse->recordList as $record) {
					$customRecordList[$record->getField('internalId')] = $record->getField('name');
				}
			} else {
				// echo "Record not a list: ".$customRecord->getField('scriptId');
				// just display an empty list of values for a 'real' custom record that isn't being used as a list
			}
			
			$customRecordShortcuts[$customRecordId] = array(
				'scriptId' => $customRecord->getField('scriptId'),
				'name' => $customRecord->getField('name'),
				'values' => $customRecordList
			);
			
			// print_r($customRecord);
		}
		
		echo format_php_export(var_export($customRecordShortcuts, true));
	}
	
	// generates an array which represents the structure + values of all custom lists in the netsuite instance
	// this can be useful for developing & submitting custom hosted forms which deal with netsuite custom records / lists
	// note: this is meant to be used in order to get a PHP rep of netsuite lists that can be copy/pasted into a config file
	public static function getCustomListMapping() {
		$customLists = self::$netsuiteConnection->getCustomizationId('customList', true);
		$customListVarExport = array();
		
		// TODO: Respect inactive fields
		
		foreach($customLists->customizationRefList as $customList) {
			$customListKey = $customList->getField('internalId');			 
			$listInfo = self::getCustomList($customListKey);
			
			// catch bad list
			if(!is_object($listInfo->getField('customValueList'))) {
				// echo "Invalid List!";
				// print_r($listInfo);
				
				$customListVarExport[$customListKey] = array(
					'values' => array(),
					'scriptId' => $customList->getField('scriptId'),
					'name' => $customList->getField('name'),
					'note' => 'Error: this list is either empty or is structured in an unknown fashion'
				);

				continue;
			}
			
			// used to store custom list values in the format: internalId => custom list item value
			$customValueList = array();
			
			// this will either be a single value object, or a list of objects
			$rawCustomValueList = $listInfo->getField('customValueList')->getField('customValue');
			
			if(!is_array($rawCustomValueList)) {
				// then there is only one entry, no array
				$customValueList[$rawCustomValueList->getField('valueId')] = $rawCustomValueList->getField('value');
			} else {
				// echo "Value List for: ".$listInfo->getField('scriptId');
				// print_r($rawCustomValueList);
				
				foreach($rawCustomValueList as $customValue) {
					/*
					Example Custom Value Structure:
					[0] => nsComplexObject Object
						(
							[nsComplexObject_type] => CustomListCustomValue
							[nsComplexObject_namespace] => urn:customization_2011_1.setup.webservices.netsuite.com
							[nsComplexObject_fields] => Array
								(
									[value] => Trade Books
									[isInactive] => 
									[valueId] => 5
								)

							[nsComplexObject_namespaces] => Array
								(
									[0] => setupcustomization
									[1] => platformcore
								)

						)
					*/
					
					// in the netsuite instances that I have tested with this never occurs
					if(!is_object($customValue)) {
						echo "Invalid Custom Value!\n";
						continue;
					}
					
					// echo "Adding :".$customValue->getField('valueId')."::".$customValue->getField('value');
					$customValueList[$customValue->getField('valueId')] = $customValue->getField('value');
				}
			}
			
			// add the entry
			$customListVarExport[$customListKey] = array(
				'values' => $customValueList,
				'scriptId' => $customList->getField('scriptId'),
				'name' => $customList->getField('name')
			);
		}
		
		echo format_php_export(var_export($customListVarExport, true));
		
		// get a list of scriptId => internalId for easy access
		
		echo "\n\n";
		$listShortcuts = array();
		
		foreach($customListVarExport as $internalId => $listInfo) {
			$listShortcuts[$listInfo['scriptId']] = $internalId;
		}
		
		echo format_php_export(var_export($listShortcuts, true));
		
		return $customListVarExport;
	}
	
	public static function getRecordMapping($record) {
		$mapping = Kohana::config('netsuite.mapping');
		
		if(empty($mapping) || empty($mapping['list']) || empty($mapping['record'])) {
			$mapping = array(
				'list' => self::getCustomListMapping(),
				'record' => self::getCustomRecordMapping()
			);
			
			echo "\n\n";
		}

		// note that this has only been tested on custom records
		
		/*
		Type Listing:
		_checkBox
		_currency
		_date
		_decimalNumber
		_document
		_eMailAddress
		_freeFormText
		_help
		_hyperlink
		_image
		_inlineHTML
		_integerNumber
		_listRecord
		_longText
		_multipleSelect
		_password
		_percent
		_phoneNumber
		_richText
		_textArea
		_timeOfDay
		*/
		
		$recordMapping = array();
		$customFieldList = $record->getField('fieldList')->getField('customField');

		foreach($customFieldList as $customField) {
			// fieldName = scriptId = internalId... obvious, right?
			$fieldName = strtolower($customField->getField('internalId'));
			$recordMapping[$fieldName] = array(
				'type' => $customField->getField('fieldType'),
				'label' => $customField->getField('label')
			);
			// print_r($customField);
			
			switch($recordMapping[$fieldName]['type']) {
				case '_freeFormText':
					
					break;
				
				// note that this can be either a customlist or customrecord
				case '_listRecord':
				// this can be a reference to a custom list
				case '_multipleSelect':
					// print_r($customField);
					
					// decide if it a list or a record
					$customFieldInternalId = $customField->getField('selectRecordType')->getField('internalId');
					$recordMapping[$fieldName]['internalId'] = $customFieldInternalId;
					
					if(isset($mapping['list'][$customFieldInternalId])) {
						// then it a list
						$recordMapping[$fieldName]['scriptId'] = $mapping['list'][$customFieldInternalId]['scriptId'];
					} else if(isset($mapping['record'][$customFieldInternalId])) {
						$recordMapping[$fieldName]['scriptId'] = $mapping['record'][$customFieldInternalId]['scriptId'];
					} else {
						// this is most likely b/c it is referencing a core record type
						echo "Uncaught list / record reference: ".$customFieldInternalId."\n";
						// print_r($customField);
						$recordMapping[$fieldName]['name'] = $customField->getField('selectRecordType')->getField('name');
					}
					break;
			}
		}
		
	   echo format_php_export(var_export($recordMapping, true));
	}
	
	public static function getCustomFields($record) {
		echo "array(\n";
		$customFieldList = $record->getField('customFieldList')->getField('customField');
		$customFieldMapping = array();
		
		$lastField = end($customFieldList);
		foreach($customFieldList as $field) {
			echo "\t'".$field->getField('internalId')."' => ''";
			
			if($field != $lastField) echo ",";
			echo "\n";
		}
		echo ");";
	}
	
	public static function getCustomValues($record) {
		// based on dump:
		/*
		[0] => nsComplexObject Object
			(
				[nsComplexObject_type] => CustomListCustomValue
				[nsComplexObject_namespace] => urn:customization_2011_1.setup.webservices.netsuite.com
				[nsComplexObject_fields] => Array
					(
						[value] => Sunday
						[isInactive] => 
						[valueId] => 1
					)

				[nsComplexObject_namespaces] => Array
					(
						[0] => setupcustomization
						[1] => platformcore
					)

			)
		*/
		
		$customValuesList = $record->getField('customValueList')->getField('customValue');
		$convertedValues = array();
		
		foreach($customValuesList as $customValue) {
			$convertedValues[$customValue->getField('valueId')] = $customValue->getField('value');
		}
		
		return $convertedValues;
	}
	
	public static function prepareFieldList($fieldData, $recordMappingKey) {
		$customFields = array();
		
		$recordFieldMapping = Kohana::config('netsuite.mapping.crud.'.$recordMappingKey);
		
		foreach($fieldData as $netsuiteFieldKey => $data) {
			if(empty($data)) continue;
			
			/*
			Type Listing:
			_checkBox
			_currency
			_date
			_decimalNumber
			_document
			_eMailAddress
			_freeFormText
			_help
			_hyperlink
			_image
			_inlineHTML
			_integerNumber
			_listRecord
			_longText
			_multipleSelect
			_password
			_percent
			_phoneNumber
			_richText
			_textArea
			_timeOfDay
			*/
			
			// check to make sure the field exists in our mapping information
			if(!isset($recordFieldMapping[$netsuiteFieldKey])) continue;
			
			switch($recordFieldMapping[$netsuiteFieldKey]['type']) {
				case '_timeOfDay':
					// seems like the only difference between time of day and date is the dmy
					// the textual representation is the same (ISO 1870 aka c in PHP)
					// ex: 1970-01-01T07:30:00.000-08:00
					$customFields[] = new nsComplexObject('DateCustomFieldRef', array(
						'internalId' => $netsuiteFieldKey,
						'value' => self::convertToNetsuiteTime($data)
					));
					break;
				case '_date':
					$customFields[] = new nsComplexObject('DateCustomFieldRef', array(
						'internalId' => $netsuiteFieldKey,
						'value' => self::convertToNetsuiteTime($data)
					));
					break;
				case '_listRecord':
				/*
				
				[2] => nsComplexObject Object
					(
						[nsComplexObject_type] => SelectCustomFieldRef
						[nsComplexObject_namespace] => urn:core_2011_1.platform.webservices.netsuite.com
						[nsComplexObject_fields] => Array
							(
								[internalId] => custrecord_customer
								[value] => nsComplexObject Object
									(
										[nsComplexObject_type] => ListOrRecordRef
										[nsComplexObject_namespace] => urn:core_2011_1.platform.webservices.netsuite.com
										[nsComplexObject_fields] => Array
											(
												[internalId] => 6303
												[typeId] => -2
											)

										[nsComplexObject_namespaces] => Array
											(
												[0] => setupcustomization
												[1] => platformcore
											)

									)

							)

						[nsComplexObject_namespaces] => Array
							(
								[0] => setupcustomization
								[1] => platformcore
							)

					)
				*/
				
					$customFields[] = new nsComplexObject('SelectCustomFieldRef', array(
						'internalId' => $netsuiteFieldKey,
						'value' =>	new nsListOrRecordRef(array(
							'internalId' => $data,
							'typeId' => $recordFieldMapping[$netsuiteFieldKey]['internalId']
						)
					)));
					
					break;
				case '_hyperlink':
				case '_freeFormText':
				case '_eMailAddress':
					$customFields[] = new nsComplexObject('StringCustomFieldRef', array(
						'internalId' => $netsuiteFieldKey,
						'value' => $data
					));
				
					break;
				case '_multipleSelect':
					// ex: days field where people can select multiple days
					// if only one item is selected the 'value' is not an array
					
					$selectedValues = array();
					foreach($data as $selectedInternalID) {
						$selectedValues[] = new nsListOrRecordRef(array(
							'internalId' => $selectedInternalID,
							'typeId' => $recordFieldMapping[$netsuiteFieldKey]['internalId']
						));
					}
					
					$customFields[] = new nsComplexObject('MultiSelectCustomFieldRef', array(
						'internalId' => $netsuiteFieldKey,
						'value' => count($selectedValues) == 1 ? $selectedValues[0] : $selectedValues
					));

					break;
				default:
					echo "Uncaught field: ".$netsuiteFieldKey."\n";
					break;
			}
		}
		
		return $customFields;
	}
	
	public static function convertToNetsuiteTime($rawTimeData) {
		// echo "Raw: ".$rawTimeData."<br/>";
		$convertedTime = strtotime($rawTimeData);

		// then (hopefully) it is a unix timestamp or malformed date
		if($convertedTime === FALSE) {
			$convertedTime = $rawTimeData;
			
			Kohana::log('info', 'Possible invalid date data for netsuite: '.$rawTimeData);
		}
		
		// note that SOAP does not pay attention to the timezone preferences of the user that is connecting through
		// netsuite recommends that you set your timezone to America/Los_Angeles
		// the user's preferences are -6GMT, default timezone set to GMT0, I think netsuite is GMT -7 --> final offset is 5 hours
		// update: strange, I changed the netsuite user preferences and it didn't effect the time
		
		// server is -7 from GMT
		// var_dump(self::$netsuiteConnection->getServerTime());exit();
		
		// not sure why it is 5 hours off, but it works
		$convertedTime += 60 * 60 * 5;
		
		// echo "Converted Date: ".date("c", $convertedTime)."<br/>";
		return date("c", $convertedTime);
	}
}
?>