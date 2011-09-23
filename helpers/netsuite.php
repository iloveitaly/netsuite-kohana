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
	    
        return $response;
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
	
	public static function getRecordMapping($record) {
	    // note that this has only been tested on custom records
	    
        $recordMapping = array();
        $customFieldList = $record->getField('fieldList')->getField('customField');

        foreach($customFieldList as $customField) {
            // fieldName = scriptId = internalId... obvious, right?
            $fieldName = strtolower($customField->getField('internalId'));
            $currentMapping = $recordMapping[$fieldName] = array(
                'type' => $customField->nsComplexObject_type
            );
            print_r($customField);
            if($currentMapping['type'] == 'SelectCustomFieldRef') {
                
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