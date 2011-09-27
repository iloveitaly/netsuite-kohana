<?
class netsuite {
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
	
	public static function bestMatchContact($data) {
		// this is the tricky function: contacts can be stored in many different ways
		// Here are a couple different cases:
		//		First & last name match but email doesn't (b/c the user is using a different email)
		//		First & last name match but zip doesn't (but the state does... maybe the user moved)
		//		We have the date modified and it was a year ago, the state, first, and last matches but the zip / email are different
		
	}
	
	public static function findContact($data) {
		$netsuiteClientConnection = netsuite::getNetsuiteConnection();
		
		$searchFields = array();
		
		// data should be an array of searchKey => searchValue i.e. array('email' => 'hello@gmail.com', 'firstName' => 'hello')

		foreach($data as $searchKey => $searchValue) {
			$searchFields[$searchKey] = array(
				'operator' => 'is',
				'searchValue' => $searchValue
			);
		}
		
		// find record
		
		$contactRecordSearch = new nsComplexObject("ContactSearchBasic");
		$contactRecordSearch->setFields(array_merge($setFields, array(
			// 'recType' => 'contact',
		)));
		
		// FALSE sets bodyFieldsOnly
		$netsuiteClientConnection->setSearchPreferences(FALSE, 100);
		
		$searchResponse = $netsuiteClientConnection->search($contactRecordSearch);
		
		// var_dump($searchResponse);
		
		if($searchResponse->totalRecords > 0) {
			if($searchResponse->totalRecords == 1) {
				// then we have found a match, modify / update existing information
				$currentRecord = $searchResponse->recordList[0];
				return $currentRecord->getField('internalId');
			} else {
				return $searchResponse->recordList;
			}
		} else {
			return FALSE;
		}
	}
	
	public static function createContact($data) {
		
	}
	
	public static function getCustomRecord($customRecordIdentifier) {
	    // note that the identifier can be either a ID or scriptId
	    
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
	
	public static function getCustomList($customListIdentifier) {
	    // note that the identifier can be either a ID or scriptId
	    
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
        
        // $customRecords = self::$netsuiteConnection->getCustomizationId('customRecordType', true);
        
        return $customListVarExport;
	}
	
	public static function getRecordMapping($record) {
        $mapping = Kohana::config('netsuite.mapping');
        
	    if(empty($mapping)) {
	        $mapping = array('list' => self::getCustomListMapping());
	        
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
                    
                    break;
                
                // this can be a reference to a custom list
                case '_multipleSelect':
                    print_r($customField);
                    
                    // decide if it a list or a record
                    $customFieldInternalId = $customField->getField('selectRecordType')->getField('internalId');
                    
                    if(isset($mapping['list'][$customFieldInternalId])) {
                        // then it a list
                        $recordMapping[$fieldName]['internalId'] = $customFieldInternalId;
                        $recordMapping[$fieldName]['scriptId'] = $mapping['list'][$customFieldInternalId]['scriptId'];
                    } else {
                        
                    }
                    break;
            }
        }
        
        print_r($recordMapping);
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
}
?>