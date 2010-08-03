<?
class netsuite {
	public static $netsuiteConnection;
	
	public static function getNetsuiteConnection() {
		if(empty(netsuite::$netsuiteConnection)) {
			require Kohana::find_file('vendor', "netsuite/PHPtoolkit.php");
			require Kohana::find_file('vendor', "netsuite/directory_v2010.1.php");

			netsuite::$netsuiteConnection = new nsClient(nsHost::live);

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
}
?>