<?php
/*
 * Copy rights info:
 * Owner: NetSuite Inc.
 * Copyright (c) 2008, 2009, 2010 NetSuite Inc.
 * All rights reserved.
 */

require_once 'directory_v2011.1.php';

global $myDirectory;
global $endpoint;

$version = "2011_1r1";

class nsComplexObject
{
    var $nsComplexObject_type;
    var $nsComplexObject_namespace;
    var $nsComplexObject_fields;
    var $nsComplexObject_namespaces; /** Required to disambiguate similarly named complex types (eg SiteCategory is a sublist and a record)*/

    function __construct ($type, array $fields = null, $namespaces = null)
    {
        $this->nsComplexObject_type = $type;
        $this->nsComplexObject_namespace = getNameSpace($this->nsComplexObject_type, $namespaces);
        $this->nsComplexObject_namespaces = $namespaces;

        if (!is_null($fields))
        {
            $this->setFields($fields);
        }
    }
    /**
     * Set an array of fields on the nsComplexObject
     *
     * @param array $fieldArray the array of fields to be set on the object
     */
    function setFields(array $fieldArray=null)
    {
        if ($fieldArray == null)
        {
            return;
        }

        global $myDirectory;

        foreach ($fieldArray as $fldName => $fldValue)
        {
            if (((is_null($fldValue) || $fldValue == "") && $fldValue !== false) || arrayValuesAreEmpty($fldValue))
            {
                continue;
            }

            if ($fldValue === 'false')
            {
                $this->nsComplexObject_fields[$fldName] = FALSE;
            }
            elseif ( $fldValue instanceof nsComplexObject )
            {
                $this->nsComplexObject_fields[$fldName] = $fldValue;
            }
            elseif (isset($myDirectory[$this->nsComplexObject_type . '/' . $fldName]) && is_array($fldValue) && array_is_associative($fldValue))
            {
                // example: 'itemList'  => array('item' => array($item1, $item2), 'replaceAll'  => false)
                $obj = $this->newNsComplexObject($this->getFieldType($this->nsComplexObject_type . '/' . $fldName));
                $obj->setFields($fldValue);
                $this->nsComplexObject_fields[$fldName] = $obj;
            }
            elseif (isset($myDirectory[$this->nsComplexObject_type . '/' . $fldName]) && is_array($fldValue) && !array_is_associative($fldValue))
            {
                // example: 'item' => array($item1, $item2)
                foreach ($fldValue as $object)
                {
                    if ($object instanceof nsComplexObject)
                    {
                        // example: $item1 = new nsComplexObject('SalesOrderItem');
                        $val[] = $object;
                    }
                    elseif ($this->getFieldType($this->nsComplexObject_type . '/' . $fldName) == "string")
                    {
                        // handle enums
                        $val[] = $object;
                    }
                    else
                    {
                        // example: $item2 = array( 'item'      => new nsComplexObject('RecordRef', array('internalId' => '17')),
                        //                          'quantity'  => '3')
                        $obj = $this->newNsComplexObject($this->getFieldType($this->nsComplexObject_type . '/' . $fldName));
                        $obj->setFields($object);
                        $val[] = $obj;
                    }
                }

                $this->nsComplexObject_fields[$fldName] = $val;
            }
            else
            {
                $this->nsComplexObject_fields[$fldName] = $fldValue;
            }
        }
    }

    /* Helper instantiator that keeps the namespaces straight */
    function newNsComplexObject($type, $fields=null)
    {
        return new nsComplexObject($type, $fields, $this->nsComplexObject_namespaces);
    }

    /* Helper function that keeps the namespaces straight */
    function getFieldType($fld)
    {
        return getFieldType($fld, $this->nsComplexObject_namespaces);
    }

    /**
     * Returns the SoapVar object for this nsComplexObject
     * @param forceOutput - Force the emission of the SOAP tag even if empty. Important for search since the element may be empty, but meaninful.
     * @return the SoapVar object for this nsComplexObject
     */
    function getSoapVar($forceOutput=false)
    {
        if (!$forceOutput && (!isset($this->nsComplexObject_fields) || $this->nsComplexObject_fields == null ))
        {
            return null;
        }

        foreach ( $this->nsComplexObject_fields as $fldName => $fldVal )
        {
            if ( $fldVal instanceof nsComplexObject )
            {
                $this->nsComplexObject_fields[$fldName] = $fldVal->getSoapVar();
            }
            elseif ( is_array($fldVal) )
            {
                foreach ($fldVal as $object)
                {
                    if ($object instanceof nsComplexObject)
                    {
                        // example: $item1 = new nsComplexObject('SalesOrderItem');
                        $val[] = $object->getSoapVar();
                    }
                    elseif ($this->getFieldType($this->nsComplexObject_type . '/' . $fldName) == "string")
                    {
                        // handle enums
                        $val[] = $object;
                    }
                    else
                    {
                        // example: $item2 = array( 'item'      => new nsComplexObject('RecordRef', array('internalId' => '17')),
                        //                          'quantity'  => '3')
                        $obj = $this->newNsComplexObject($this->getFieldType($this->nsComplexObject_type . '/' . $fldName));
                        $obj->setFields($object);
                        $val[] = $obj->getSoapVar();
                    }
                }

                $this->nsComplexObject_fields[$fldName] = $val;
            }
        }

        $oldType = $this->nsComplexObject_type; // handle the case where the type is prefixed.
        if (strpos($this->nsComplexObject_type,":") > 0)
            $this->nsComplexObject_type = substr($oldType,strpos($oldType,":")+1);

        $toRet = new SoapVar($this->nsComplexObject_fields, SOAP_ENC_OBJECT, $this->nsComplexObject_type, $this->nsComplexObject_namespace);

        $this->nsComplexObject_type = $oldType; // can't hurt

        return $toRet;
    }

    /**
     * Returns the array of fields that are currently set
     *
     * @return the array of fields that are currently set
     */
    function getFields()
    {
        return $this->nsComplexObject_fields;
    }

    /**
     * Returns the field value for a given field name
     *
     * @param $fieldName the name of the field
     * @return the field value for a given field name
     */
    function getField($fieldName)
    {
        if (isset($this->nsComplexObject_fields[$fieldName]))
        {
            return $this->nsComplexObject_fields[$fieldName];
        }
    }

    /**
     * Returns an array of values given the name of the field. An array is returned even if there is only 1 object
     *
     * @param $fieldName the name of the field
     * @return an array of values given the name of the field. An array is returned even if there is only 1 object
     */
    function getFieldArray($fieldName)
    {
        if (isset($this->nsComplexObject_fields[$fieldName]))
        {
            $objects = $this->nsComplexObject_fields[$fieldName];
            if (is_array($objects))
            {
                $objectArray = $objects;
            }
            elseif (!is_null($objects))
            {
                $objectArray = array();
                $objectArray[0] = $objects;
            }
            return $objectArray;
        }
    }

    function clearField( $fieldName )
    {
        if (isset($this->nsComplexObject_fields[$fieldName]))
        {
            unset($this->nsComplexObject_fields[$fieldName]);
        }
    }

    function setNullFields( array $fieldNames )
    {
        if (searchDirectory($this->nsComplexObject_type . "/nullFieldList", $this->nsComplexObject_namespaces) != null)
        {
            $this->setFields(array("nullFieldList"=>array("name"=>$fieldNames)));
        }
    }

}

class nsRecordRef extends nsComplexObject
{
    function __construct (array $fields = null)
    {
        parent::__construct('RecordRef');
        parent::setFields($fields);
    }
}

class nsCustomizationRef extends nsComplexObject
{
    function __construct (array $fields = null)
    {
        parent::__construct('CustomizationRef');
        parent::setFields($fields);
    }
}

class nsCustomRecordRef extends nsComplexObject
{
    function __construct (array $fields = null)
    {
        parent::__construct('CustomRecordRef');
        parent::setFields($fields);
    }
}

class nsListOrRecordRef extends nsComplexObject
{
    function __construct (array $fields)
    {
        parent::__construct('ListOrRecordRef');
        parent::setFields($fields);
    }
}


class nsSessionResponse
{
    var $isSuccess;
    var $statusDetail;
    var $wsRoleList;

    function __construct ($sessionResponse)
    {
        $this->isSuccess = $sessionResponse->status->isSuccess;

        if ( isset($sessionResponse->status->statusDetail) )
        {
            $this->statusDetail = getStatusDetail($sessionResponse->status->statusDetail);
        }

        if (is_array($sessionResponse->wsRoleList->wsRole))
        {
            foreach ($sessionResponse->wsRoleList->wsRole as $wsRole)
            {
                $wsRoleNSObject = new nsComplexObject('WsRole');

                $wsRoleFields = array();
                $wsRoleFields['isDefault'] = $wsRole->isDefault;
                $wsRoleFields['isInactive'] = $wsRole->isInactive;
                $wsRoleFields['role'] = new nsRecordRef(array(  'internalId'    => $wsRole->role->internalId,
                                                                'name'          => $wsRole->role->name));

                $wsRoleNSObject->setFields($wsRoleFields);
                $this->wsRoleList[] = $wsRoleNSObject;
            }
        }
        elseif (count($sessionResponse->wsRoleList->wsRole) > 0)
        {
            $wsRoleNSObject = new nsComplexObject('WsRole');

            $wsRoleFields = array();
            $wsRoleFields['isDefault'] = $sessionResponse->wsRoleList->wsRole->isDefault;
            $wsRoleFields['isInactive'] = $sessionResponse->wsRoleList->wsRole->isInactive;
            $wsRoleFields['role'] = new nsRecordRef(array(  'internalId'    => $sessionResponse->wsRoleList->wsRole->role->internalId,
                                                            'name'          => $sessionResponse->wsRoleList->wsRole->role->name));

            $wsRoleNSObject->setFields($wsRoleFields);
            $this->wsRoleList[] = $wsRoleNSObject;
        }
    }
}


class nsWriteResponse
{
    var $isSuccess;
    var $statusDetail;
    var $recordRef;

    function __construct ($soapResponse)
    {
        $this->isSuccess = $soapResponse->status->isSuccess;

        if ( isset($soapResponse->status->statusDetail) )
        {
            $this->statusDetail = getStatusDetail($soapResponse->status->statusDetail);
        }

        if ($this->isSuccess)
        {
            $this->recordRef = new nsRecordRef(array(   'type'          => $soapResponse->baseRef->type,
                                                        'internalId'    => $soapResponse->baseRef->internalId,
                                                        'externalId'    => $soapResponse->baseRef->externalId   ));
        }
    }
}

class nsAsyncStatusResult
{
    var $jobId;
    var $status;
    var $percentCompleted;
    var $estRemainingDuration;

    function __construct ($asyncStatusResult)
    {
        $this->jobId = $asyncStatusResult->jobId;
        $this->status = $asyncStatusResult->status;
        $this->percentCompleted = $asyncStatusResult->percentCompleted;
        $this->estRemainingDuration = $asyncStatusResult->estRemainingDuration;
    }
}

class nsStatusDetail
{
    var $code;
    var $message;
    var $type;

    function __construct ($cd, $mes, $tp)
    {
        $this->code = $cd;
        $this->message = $mes;
        $this->type = $tp;
    }
}

class nsReadResponse
{
    var $isSuccess;
    var $statusDetail;
    var $record;

    function __construct ($soapResponse)
    {
        $this->isSuccess = $soapResponse->status->isSuccess;

        if ( isset($soapResponse->status->statusDetail) )
        {
            $this->statusDetail = getStatusDetail($soapResponse->status->statusDetail);
        }

        if ($this->isSuccess)
        {
            $cleanRecord = new nsComplexObject($soapResponse->record->nsComplexObject_type,null,$soapResponse->record->nsComplexObject_namespaces);
            $cleanRecord->nsComplexObject_fields = $soapResponse->record->nsComplexObject_fields;

            $this->record = $cleanRecord;
        }
    }
}

class nsSearchResponse
{
    var $isSuccess;
    var $statusDetail;
    var $totalRecords;
    var $pageSize;
    var $totalPages;
    var $pageIndex;
    var $searchId;
    var $recordList;
    var $searchRowList;

    function __construct ($searchResponse)
    {
        $this->isSuccess = $searchResponse->status->isSuccess;

        if ( isset($searchResponse->status->statusDetail) )
        {
            $this->statusDetail = getStatusDetail($searchResponse->status->statusDetail);
        }

        $this->totalRecords = $searchResponse->totalRecords;
        $this->pageSize = $searchResponse->pageSize;
        $this->totalPages = $searchResponse->totalPages;
        $this->pageIndex = $searchResponse->pageIndex;
        $this->searchId = $searchResponse->searchId;

        if (!is_null($searchResponse->recordList))
        {
            if (is_array($searchResponse->recordList->record))
            {
                foreach ($searchResponse->recordList->record as $rec)
                {
                    $cleanRecord = new nsComplexObject($rec->nsComplexObject_type,null,$rec->nsComplexObject_namespaces);
                    $cleanRecord->nsComplexObject_fields = $rec->nsComplexObject_fields;

                    $this->recordList[] = $cleanRecord;
                }

            }
            elseif (count($searchResponse->recordList->record) > 0)
            {
                $cleanRecord = new nsComplexObject($searchResponse->recordList->record->nsComplexObject_type, null, $searchResponse->recordList->record->nsComplexObject_namespaces);
                $cleanRecord->nsComplexObject_fields = $searchResponse->recordList->record->nsComplexObject_fields;

                $this->recordList[] = $cleanRecord;
            }
        }
        elseif (!is_null($searchResponse->searchRowList))
        {
            if (is_array($searchResponse->searchRowList->searchRow))
            {
                foreach ($searchResponse->searchRowList->searchRow as $rec)
                {
                    $cleanRecord = new nsComplexObject($rec->nsComplexObject_type,null,$rec->nsComplexObject_namespaces);
                    $cleanRecord->nsComplexObject_fields = $rec->nsComplexObject_fields;

                    $this->searchRowList[] = $cleanRecord;
                }

            }
            elseif (count($searchResponse->searchRowList->searchRow) > 0)
            {
                $cleanRecord = new nsComplexObject($searchResponse->searchRowList->searchRow->nsComplexObject_type,null,$searchResponse->searchRowList->searchRow->nsComplexObject_namespaces);
                $cleanRecord->nsComplexObject_fields = $searchResponse->searchRowList->searchRow->nsComplexObject_fields;

                $this->searchRowList[] = $cleanRecord;
            }
        }
    }
}


class nsGetRecordResult {

    var $isSuccess;
    var $statusDetail;
    var $totalRecords;
    var $recordList;

    function __construct($response) {

        $this->isSuccess = $response->status->isSuccess;
        $this->totalRecords = $response->totalRecords;

        if ( isset($response->status->statusDetail) ) {

            $this->statusDetail = getStatusDetail($response->status->statusDetail);

        }

        if (is_array($response->recordList->record)) {

            foreach ($response->recordList->record as $rec) {

                $cleanRecord = new nsComplexObject($rec->nsComplexObject_type,null,$rec->nsComplexObject_namespaces);
                $cleanRecord->nsComplexObject_fields = $rec->nsComplexObject_fields;

                $this->recordList[] = $cleanRecord;

            }

        } elseif (count($response->recordList->record) > 0) {

            $cleanRecord = new nsComplexObject($response->recordList->record->nsComplexObject_type, null, $response->recordList->record->nsComplexObject_namespaces);
            $cleanRecord->nsComplexObject_fields = $response->recordList->record->nsComplexObject_fields;

            $this->recordList[] = $cleanRecord;

        }

    }

}


class nsGetRecordRefResult {

    var $isSuccess;
    var $statusDetail;
    var $totalRecords;
    var $recordRefList;

    function __construct($response) {

        $this->isSuccess = $response->status->isSuccess;
        $this->totalRecords = $response->totalRecords;

        if ( isset($response->status->statusDetail) ) {

            $this->statusDetail = getStatusDetail($response->status->statusDetail);

        }

        if ((isset($response->recordRefList) && is_array($response->recordRefList->recordRef)) || (isset($response->baseRefList) && is_array($response->baseRefList->baseRef)) || (isset($response->customizationRefList) && is_array($response->customizationRefList->customizationRef))) {

            $bRecRef = isset($response->recordRefList) ? is_array($response->recordRefList->recordRef) : FALSE;
            $bCustomizationRef = isset($response->customizationRefList) ? is_array($response->customizationRefList->customizationRef) : FALSE;
            $refList =   $bRecRef ? $response->recordRefList->recordRef : $bCustomizationRef ? $response->customizationRefList->customizationRef : $response->baseRefList->baseRef;
            foreach ($refList as $recRef) {

                $recRefFields = array();
                $recRefFields['internalId'] = $recRef->internalId;
                $recRefFields['name'] = $recRef->name;

                if (property_exists($recRef, 'scriptId'))
	                $recRefFields['scriptId'] = $recRef->scriptId;

                if (property_exists($recRef, 'typeId'))
                {
                    $recRefFields['typeId'] = $recRef->typeId;
                    $toRet = new nsCustomRecordRef($recRefFields);
                }
                elseif (property_exists($recRef, 'scriptId'))
                    $toRet = new nsCustomizationRef($recRefFields);
                else
                    $toRet = new nsRecordRef($recRefFields);

                if ($bRecRef)
                    $this->recordRefList[] = $toRet;
                elseif ($bCustomizationRef)
                    $this->customizationRefList[] = $toRet;
                else
                    $this->baseRefList[] = $toRet;
            }

        } elseif (count($response->recordRefList->record) > 0) {

            $recRefFields = array();
            $recRefFields['internalId'] = $response->recordRefList->recordRef->internalId;
            $recRefFields['name'] = $response->recordRefList->recordRef->name;

            $this->recordRefList[] = new nsRecordRef($recRefFields);

        }

    }

}


class nsGetItemAvailabilityResult {

    var $isSuccess;
    var $statusDetail;
    var $itemAvailabilityList;

    function __construct ( $getItemAvailabilityResult ) {

        $this->isSuccess = $getItemAvailabilityResult->status->isSuccess;

        if ( isset($getItemAvailabilityResult->status->statusDetail) ) {

            $this->statusDetail = getStatusDetail($getItemAvailabilityResult->status->statusDetail);

        }

        $records = $getItemAvailabilityResult->itemAvailabilityList->itemAvailability;

        if (is_array($records)) {

            foreach ($records as $rec) {

                $this->itemAvailabilityList[] = $rec;

            }

        } elseif (count($records) > 0) {

            $this->itemAvailabilityList[] = $records;

        }

    }

    /**
     * Returns the accumulated quantity on hand for a given item
     *
     * @param $itemId the internal id of the item
     * @return the accumulated quantity on hand for a given internal id of an item
     */

    function getTotalQuantityOnHand ( $itemId ) {

        $totalQOH = 0;

        foreach( $this->itemAvailabilityList as $itemAvail ) {

            if ($itemAvail->getField('item')->getField('internalId') == $itemId) {

                $totalQOH = $totalQOH + $itemAvail->getField('quantityOnHand');

            }

        }

        return $totalQOH;

    }

    /**
     * Returns the accumulated quantity available for a given item
     *
     * @param $itemId the internal id of the item
     * @return the accumulated quantity available for a given item
     */

    function getTotalQuantityAvailable ( $itemId ) {

        $totalQAvailable = 0;

        foreach( $this->itemAvailabilityList as $itemAvail ) {

            if ($itemAvail->getField('item')->getField('internalId') == $itemId) {

                $totalQAvailable = $totalQAvailable + $itemAvail->getField('quantityAvailable');

            }

        }

        return $totalQAvailable;

    }

    /**
     * Returns the name of a given item id
     *
     * @param $itemId the internal id of the item
     * @return the name of a given item id
     */

    function getItemName ( $itemId ) {

        $itemName = "";

        foreach( $this->itemAvailabilityList as $itemAvail ) {

            if ($itemAvail->getField('item')->getField('internalId') == $itemId) {

                $itemName = $itemAvail->getField('item')->getField('name');
                break;

            }

        }

        return $itemName;

    }

}

class nsItemAvailability extends nsComplexObject
{
    function __construct (array $fields = null)
    {
        parent::__construct('ItemAvailability');
        parent::setFields($fields);
    }
}

class nsCustomFieldList extends nsComplexObject
{
    function __construct (array $fields = null)
    {
        parent::__construct('CustomFieldList');
        parent::setFields($fields);
    }

    function setFields (array $fields)
    {
        if ( isset($fields["customField"]) && count($fields["customField"]) == 1)
        {
            $customFieldArray = array();

            foreach ( $fields as $fldName => $fldValue )
            {
                if ($fldName == "customField")
                {
                    $customFieldArray[] = $fldValue;
                }
            }

            $fields["customField"] = $customFieldArray;
        }

        parent::setFields($fields);
    }

    /**
     * Returns the value of given a custom field id
     *
     * @param $customFieldId the internal id of the custom field
     * @return the value of the custom field
     */
    function getCustomFieldValue ($customFieldId)
    {
        $value = null;

        foreach ( parent::getField('customField') as $custField )
        {
            if ($custField->getField('internalId') == $customFieldId)
            {
                $value = $custField->getField('value');
                break;
            }
        }

        return $value;
    }
}

class nsPricingMatrix extends nsComplexObject
{
    function __construct (array $fields = null)
    {
        parent::__construct('PricingMatrix');
        parent::setFields($fields);
    }

    function setFields (array $fields)
    {
        if ( isset($fields["pricing"]) && count($fields["pricing"]) == 1)
        {
            $pricingArray = array();

            foreach ( $fields as $fldName => $fldValue )
            {
                if ($fldName == "pricing")
                {
                    $pricingArray[] = $fldValue;
                }
            }

            $fields["pricing"] = $pricingArray;
        }

        parent::setFields($fields);
    }

    /**
     * Returns the price value for a given combination of price level, quantity, and currency.
     *
     * @param $priceLevel the internal id of the price level. Set as null if Price Level feature is turned off.
     * @param $quantity quantity. Set as null if uantity Pricing is turned off.
     * @param $currency the internal id of the currency. Set as null if Multiple Currencies is off.
     * @return the price value for a given combination of price level, quantity, and currency
     */

    function getPriceValue ( $priceLevel=null, $quantity=null, $currency=null ) {

        $pricing = $this->getPricing( $priceLevel, $currency );

        $priceArray = $pricing->getField("priceList")->getField("price");

        foreach ( $priceArray as $price ) {

            if ( $price->getField('quantity') == $quantity )
            {
                return $price->getField('value');
            }

        }

        return "";

    }

    /**
     * Returns an array that maps quantities to prices. This function should be used only if Quantity Pricing is turned on.
     *
     * @param $priceLevel the internal id of the price level. Set as null if Price Level feature is turned off.
     * @param $currency the internal id of the currency to get the prices from. Set as null if Multiple Currencies is off.
     * @param $range Set to true if the keys of the array should be returned in range format.
     * e.g. range = false yields [5] -> 13.99, and range = true yields [5 to 9] -> 13.99     *
     * @return an array that maps quantities to prices
     */

    function getPricesFromPriceLevel ( $priceLevel=null, $currency=null, $range=false ) {

        $pricing = $this->getPricing( $priceLevel, $currency );

        $priceArray = $pricing->getField("priceList")->getField("price");

        $p = array();

        for ( $i = 0; $i < count($priceArray); $i++ ) {

            $thisPrice = $priceArray[$i];
            $quantity = (int)$thisPrice->getField('quantity');
            $value = $thisPrice->getField('value');

            if ($range) {

                $k = $i + 1;

                $key = "";

                if ( $quantity == 0 ) {

                    $quantity = 1;

                }
                if ($k == count($priceArray)) {

                    $key = " and up";

                } else {

                    $key = " to " . ((int)$priceArray[$k]->getField('quantity') - 1);

                }
                $p["$quantity" .  "$key"] = $value;

            } else {

                $p[$quantity] = $value;

            }

        }

        return $p;

    }

    private function getPricing ( $priceLevel, $currency ) {

        $pricingArray = $this->getField("pricing");

        foreach ( $pricingArray as $pricing )
        {

            if( $currency == null )
            {
                if ( $pricing->getField('currency') == null && $pricing->getField('priceLevel') != null && $pricing->getField('priceLevel')->getField('internalId') == $priceLevel )
                {
                    return $pricing;
                }
            }
            else if( $priceLevel == null )
            {
                if ( $pricing->getField('priceLevel') == null && $pricing->getField('currency') != null && $pricing->getField('currency')->getField('internalId') == $currency )
                {
                    return $pricing;
                }
            }
            else
            {
                if ( $pricing->getField('priceLevel') != null && $pricing->getField('priceLevel')->getField('internalId') == $priceLevel && $pricing->getField('currency') != null && $pricing->getField('currency')->getField('internalId') == $currency )
                {
                    return $pricing;
                }
            }
        }

        throw new Exception ("Pricing object was not found. PriceLevel = $priceLevel, Currency = $currency");

    }

}

class nsPriceList extends nsComplexObject {

    function __construct (array $fields = null) {

        parent::__construct('PriceList');
        parent::setFields($fields);

    }

    function setFields (array $fields) {

        if ( isset($fields["price"]) && count($fields["price"]) == 1) {

            $priceArray = array();

            foreach ( $fields as $fldName => $fldValue ) {

                if ($fldName == "price") {

                    $priceArray[] = $fldValue;

                }

            }

            $fields["price"] = $priceArray;

        }

        parent::setFields($fields);

    }

}

class nsAddressbookList extends nsComplexObject {

    function __construct ($type, array $fields = null) {

        parent::__construct($type);
        parent::setFields($fields);

    }

    function setFields (array $fields) {

        if ( isset($fields["addressbook"]) && count($fields["addressbook"]) == 1) {

            $addressbookArray = array();

            foreach ( $fields as $fldName => $fldValue ) {

                if ($fldName == "addressbook") {

                    $addressbookArray[] = $fldValue;

                }

            }

            $fields["addressbook"] = $addressbookArray;

        }

        parent::setFields($fields);

    }

    /**
     * Returns the billing address for this nsAddressbookList object
     *
     * @return the billing address for this nsAddressbookList object
     */

    function getBillingAddress() {

        $address_array = $this->getField('addressbook');

        foreach ( $address_array as $address ) {

            if ( $address->getField('defaultBilling') == "true" ) {

                return $address;

            }

        }

    }

    /**
     * Returns the shipping address for this nsAddressbookList object
     *
     * @return the shipping address for this nsAddressbookList object
     */

    function getShippingAddress() {

        $address_array = $this->getField('addressbook');

        foreach ( $address_array as $address ) {

            if ( $address->getField('defaultShipping') == "true" ) {

                return $address;

            }

        }

    }

}

abstract class nsHost {

    const live = "https://webservices.netsuite.com";
    const beta = "https://webservices.beta.netsuite.com";
    const sandbox = "https://webservices.sandbox.netsuite.com";

}


class nsClient {

    private $client;
    private $soapHeaders = null;
    private $soapHeadersResponse = null;

    function __construct ( $host=nsHost::live ) {

        global $endpoint;
        global $version;

        $typemap = array(

            array(  "type_name" => 'Record',
                    "type_ns"   => getNameSpace('Record'),
                    "from_xml"  => 'deserializeRecord'
            ),
            array(  "type_name" => 'ItemAvailability',
                    "type_ns"   => getNameSpace('ItemAvailability'),
                    "from_xml"  => 'deserializeItemAvailability'
            ),
            array(  "type_name" => 'SearchRow',
                    "type_ns"   => getNameSpace('SearchRow'),
                    "from_xml"  => 'deserializeSearchRow'
            ),
            array(  "type_name" => 'CustomRecordCustomField',
                    "type_ns"   => getNameSpace('CustomRecordCustomField'),
                    "from_xml"  => 'deserializeCustomRecordCustomField'
            )
        );

        function deserializeCustomRecordCustomField ($obj)
        {
            return $obj;
        }

        function deserializeRecord ($obj)
        {
            $ns = retrieveNamespaces($obj);
            $obj = cleanUpNamespaces($obj);
            $xml = simplexml_load_string($obj, 'SimpleXMLElement', LIBXML_NOCDATA);
            $x = deserializeSimpleXML($xml,null,$ns);

            return $x;
        }

        function deserializeItemAvailability ($obj)
        {
            $obj = cleanUpNamespaces($obj);
            $xml = simplexml_load_string($obj, 'SimpleXMLElement', LIBXML_NOCDATA);
            $x = deserializeSimpleXML($xml, "ItemAvailability");

            return $x;
        }

        function deserializeSearchRow ($obj)
        {
            $obj = cleanUpNamespaces($obj);
            $xml = simplexml_load_string($obj, 'SimpleXMLElement', LIBXML_NOCDATA);
            $x = deserializeSimpleXML($xml);

            return $x;
        }

        /* Gets the namespaces in "directory" format, eg listswebsite for SiteCategory the Record Type
         * vs listsaccounting for SiteCategory the sublist on Item */
        function retrieveNamespaces($xml_root)
        {
            $xml_root = str_replace('xsi:type', 'xsitype', $xml_root);
            $record_element = new SimpleXMLElement($xml_root);

            $i=0;
            $toRet = Array();
            foreach ($record_element->getNamespaces(true) as $name => $ns)
            {
                if (!empty($name) && substr($ns,0,4) =="urn:")
                {
                    $nsComponents = preg_split("(:|\\.|_)",$ns);
                    $toolkitNs = $nsComponents[4] . $nsComponents[1];
                    $toRet[$i]=$toolkitNs;
                    $i=$i+1;
                }
            }
            return $toRet;
        }

        /**
         * Replaces xsi:type with xsitype and removes namespace aliases from xml tags. E.g. platformCommon:item -> item 
         *
         * @param  $xml_root the string containing xml document to process
         * @return xml without namespace aliases
         */
        function cleanUpNamespaces($xml_root)
        {
            $xml_root = str_replace('xsi:type', 'xsitype', $xml_root);
            $record_element = new SimpleXMLElement($xml_root);

            foreach ($record_element->getNamespaces(true) as $name => $ns)
            {
                if ( $name != "" )
                {
                    $xml_root = str_replace($name . ':', '', $xml_root);
                }
            }

            return $xml_root;
        }


        $this->client = new SoapClient( $host . "/wsdl/v" . $endpoint . "_0/netsuite.wsdl",
                                        array(  "location"              => $host . "/services/NetSuitePort_" . $endpoint,
                                                "trace"                 => 1,
                                                "connection_timeout"    => 5,
                                                "typemap"               => $typemap,
                                                "user_agent"            => "PHP-SOAP/" . phpversion() . " + NetSuite PHP Toolkit " . $version));

    }

    function login($email, $password, $account, $role=null) {

        $loginParams = array();
        $loginParams['email'] = $email;
        $loginParams['password'] = $password;
        $loginParams['account'] = $account;
        if ( !($role instanceof nsComplexObject) ) {

            $roleRecRef = new nsRecordRef(array ('internalId' => $role));
            $loginParams['role'] = $roleRecRef->getSoapVar();

        } else {

            $loginParams['role'] = $role;

        }

        $loginResponse = $this->makeCall("login", array(array('passport' => $loginParams)));

        return new nsSessionResponse($loginResponse->sessionResponse);

    }

    function ssoLogin($partnerId, $authenticationToken) {

        $ssoLoginParams = array();
        $ssoLoginParams['partnerId'] = $partnerId;
        $ssoLoginParams['authenticationToken'] = $authenticationToken;

        $ssoLoginResponse = $this->makeCall("ssoLogin", array(array('ssoPassport' => $ssoLoginParams)));

        return new nsSessionResponse($ssoLoginResponse->sessionResponse);

    }

    function logout() {

        $this->clearPassport();

        $logoutResponse = $this->makeCall("logout");

        return new nsSessionResponse($logoutResponse->sessionResponse);

    }

    function changePasswordOrEmail($currentPassword, $newEmail, $newEmail2, $newPassword=null, $newPassword2=null, $justThisAccount=null) {

        $changePasswordOrEmailCredentials = array();
        $changePasswordOrEmailCredentials['currentPassword'] = $currentPassword;
        $changePasswordOrEmailCredentials['newEmail'] = $newEmail;
        $changePasswordOrEmailCredentials['newEmail2'] = $newEmail2;
        $changePasswordOrEmailCredentials['newPassword'] = $newPassword;
        $changePasswordOrEmailCredentials['newPassword2'] = $newPassword2;
        $changePasswordOrEmailCredentials['justThisAccount'] = $justThisAccount;

        $changePasswordOrEmailResponse = $this->makeCall("changePasswordOrEmail", array(array('changePasswordOrEmailCredentials' => $changePasswordOrEmailCredentials)));

        return new nsSessionResponse($changePasswordOrEmailResponse->sessionResponse);

    }

    function add(nsComplexObject $record) {

        $addResponse = $this->makeCall("add", array(array('record' => $record->getSoapVar())));

        return new nsWriteResponse($addResponse->writeResponse);

    }

    function addList(array $records) {

        foreach ($records as $recs) {

            $addListRecords[] = $recs->getSoapVar();

        }

        $addResponseList = $this->makeCall("addList", array('record' => $addListRecords));

        if ( count($addResponseList->writeResponseList->writeResponse) == 1 )
        {
            $writeResponseArray[] = new nsWriteResponse( $addResponseList->writeResponseList->writeResponse );
        }
        else
        {
            foreach ($addResponseList->writeResponseList->writeResponse as $addResponse)
            {
                $writeResponseArray[] = new nsWriteResponse($addResponse);
            }
        }

        return $writeResponseArray;

    }

    function update(nsComplexObject $record) {

        $updateResponse = $this->makeCall("update", array(array('record' => $record->getSoapVar())));

        return new nsWriteResponse($updateResponse->writeResponse);

    }

    function updateList(array $records)
    {
        foreach ($records as $recs)
        {
            $updateListRecords[] = $recs->getSoapVar();
        }

        $updateResponseList = $this->makeCall("updateList", array('record' => $updateListRecords));

        if ( count($updateResponseList->writeResponseList->writeResponse) == 1 )
        {
            $writeResponseArray[] = new nsWriteResponse( $updateResponseList->writeResponseList->writeResponse );
        }
        else
        {
            foreach ($updateResponseList->writeResponseList->writeResponse as $updateResponse)
            {
                $writeResponseArray[] = new nsWriteResponse($updateResponse);
            }
        }

        return $writeResponseArray;
    }

    function get(nsRecordRef $recordRef) {

        $getResponse = $this->makeCall("get", array(array('baseRef' => $recordRef->getSoapVar())));

        return new nsReadResponse ($getResponse->readResponse);

    }

    function getList(array $recordRefs)
    {
        foreach ($recordRefs as $recs)
        {
            $recsToGet[] = $recs->getSoapVar();
        }

        $getListResponse = $this->makeCall("getList", array('baseRef' => $recsToGet));

        if ( count($getListResponse->readResponseList->readResponse) == 1 )
        {
            $getListResponseArray[] = new nsReadResponse( $getListResponse->readResponseList->readResponse );
        }
        else
        {
            for ($i = 0; $i < count($getListResponse->readResponseList->readResponse); $i++)
            {
                $getListResponseArray[] = new nsReadResponse( $getListResponse->readResponseList->readResponse[$i] );
            }
        }

        return $getListResponseArray;
    }

    function getAll($recordType) {

        $getAllResponse = $this->makeCall("getAll", array(array('record' => array('recordType' => $recordType))));

        return new nsGetRecordResult( $getAllResponse->getAllResult );

    }

    function delete(nsComplexObject $baseRef) {

        $deleteResponse = $this->makeCall("delete", array(array('baseRef' => $baseRef->getSoapVar())));

        return new nsWriteResponse($deleteResponse->writeResponse);

    }

    function deleteList(array $baseRefs)
    {
        foreach ($baseRefs as $baseRef)
        {
            $deleteListRecords[] = $baseRef->getSoapVar();
        }

        $deleteResponseList = $this->makeCall("deleteList", array('baseRef' => $deleteListRecords));

        if ( count($deleteResponseList->writeResponseList->writeResponse) == 1 )
        {
            $writeResponseArray[] = new nsWriteResponse( $deleteResponseList->writeResponseList->writeResponse );
        }
        else
        {
            foreach ($deleteResponseList->writeResponseList->writeResponse as $deleteResponse)
            {
                $writeResponseArray[] = new nsWriteResponse($deleteResponse);
            }
        }

        return $writeResponseArray;
    }

    function search(nsComplexObject $searchRecord) {

        $searchResponse = $this->makeCall("search", array(array('searchRecord' => $searchRecord->getSoapVar(true))));

        return new nsSearchResponse ($searchResponse->searchResult);

    }

    function searchMore($pageIndex) {

        $searchResponse = $this->makeCall("searchMore", array(array('pageIndex' => $pageIndex)));

        return new nsSearchResponse ($searchResponse->searchResult);

    }

    function searchMoreWithId($searchId, $pageIndex) {

        $searchResponse = $this->makeCall("searchMoreWithId", array(array('pageIndex' => $pageIndex, 'searchId' => $searchId)));

        return new nsSearchResponse ($searchResponse->searchResult);

    }

    function searchNext()
    {

        $searchResponse = $this->makeCall("searchNext");

        return new nsSearchResponse ($searchResponse->searchResult);

    }

    function getSavedSearch($searchType)
    {

        $getSavedSearchResponse = $this->makeCall("getSavedSearch", array(array('record' => array('searchType' => $searchType))));

        return new nsGetRecordRefResult( $getSavedSearchResponse->getSavedSearchResult );

    }

    function initialize(nsComplexObject $initializeRecord)
    {
        $getResponse = $this->makeCall("initialize", array(array('initializeRecord' => $initializeRecord->getSoapVar())));

        return new nsReadResponse ($getResponse->readResponse);
    }

    function initializeList(array $initializeRecords)
    {
        foreach ($initializeRecords as $recs)
        {
            $recsToInitialize[] = $recs->getSoapVar();
        }

        $initializeListResponse = $this->makeCall("initializeList", array('initializeRecord' => $recsToInitialize));

        if ( count($initializeListResponse->readResponseList->readResponse) == 1 )
        {
            $initializeListResponseArray[] = new nsReadResponse( $initializeListResponse->readResponseList->readResponse );
        }
        else
        {
            for ($i = 0; $i < count($initializeListResponse->readResponseList->readResponse); $i++)
            {
                $initializeListResponseArray[] = new nsReadResponse( $initializeListResponse->readResponseList->readResponse[$i] );
            }
        }

        return $initializeListResponseArray;
    }

    function getCustomizationId($customizationType, $includeInactives)
    {

        $getCustomizationIdResponse = $this->makeCall("getCustomizationId", array(array('customizationType' => array('getCustomizationType' => $customizationType), 'includeInactives'=> $includeInactives))	);

        return new nsGetRecordRefResult( $getCustomizationIdResponse->getCustomizationIdResult );

    }

    function getCustomization($getCustomizationType)
    {

        $getCustomizationResponse = $this->makeCall("getCustomization", array(array('customizationType' => array('getCustomizationType' => $getCustomizationType))));

        return new nsGetRecordResult( $getCustomizationResponse->getCustomizationResult );

    }

    function getSelectValue( $gsvFieldDescription, $page=1 )
    {

        $getSelectValueResponse = $this->makeCall("getSelectValue", array(array('fieldDescription' => $gsvFieldDescription->getSoapVar(),"pageIndex" => $page)));

        return new nsGetRecordRefResult( $getSelectValueResponse->getSelectValueResult );
    }

    function getServerTime() {

        $getServerTimeResponse = $this->makeCall("getServerTime");
        return $getServerTimeResponse->getServerTimeResult->serverTime;
    }

    function getItemAvailability($itemAvailabilityFilter) {

        $getItemAvailabilityResult = $this->makeCall("getItemAvailability", array(array('itemAvailabilityFilter' => $itemAvailabilityFilter->getSoapVar())));

        return new nsGetItemAvailabilityResult($getItemAvailabilityResult->getItemAvailabilityResult);

    }

    function attach( nsComplexObject $attachReference ) {

        $attachResponse = $this->makeCall("attach", array(array('attachReference' => $attachReference->getSoapVar())));

        return new nsWriteResponse($attachResponse->writeResponse);

    }

    function detach( nsComplexObject $detachReference ) {

        $detachResponse = $this->makeCall("detach", array(array('detachReference' => $detachReference->getSoapVar())));

        return new nsWriteResponse($detachResponse->writeResponse);

    }

    function getDeleted( nsComplexObject $getDeletedFilter ) {

        $getDeletedResult = $this->makeCall("getDeleted", array(array('getDeletedFilter' => $getDeletedFilter->getSoapVar())));

        return $getDeletedResult;

    }

    function mapSso(nsComplexObject $ssoCredentials) {

        $mapSsoResponse = $this->makeCall("mapSso", array(array('ssoCredentials' => $ssoCredentials->getSoapVar())));

        return new nsSessionResponse($mapSsoResponse->sessionResponse);

    }

    function asyncAddList(array $records)
    {
        foreach ($records as $recs)
        {
            $addListRecords[] = $recs->getSoapVar();
        }

        $asyncAddListResponse = $this->makeCall("asyncAddList", array('record' => $addListRecords));

        $asyncStatusResult = new nsAsyncStatusResult($asyncAddListResponse->asyncStatusResult);

        return $asyncStatusResult;
    }

    function asyncUpdateList(array $records)
    {
        foreach ($records as $recs)
        {
            $updateListRecords[] = $recs->getSoapVar();
        }

        $asyncUpdateListResponse = $this->makeCall("asyncUpdateList", array('record' => $updateListRecords));

        $asyncStatusResult = new nsAsyncStatusResult($asyncUpdateListResponse->asyncStatusResult);

        return $asyncStatusResult;
    }

    function asyncDeleteList(array $recordRefs)
    {
        foreach ($recordRefs as $recs)
        {
            $recsToDelete[] = $recs->getSoapVar();
        }

        $asyncDeleteListResponse = $this->makeCall("asyncDeleteList", array('baseRef' => $recsToDelete));

        $asyncStatusResult = new nsAsyncStatusResult($asyncDeleteListResponse->asyncStatusResult);

        return $asyncStatusResult;
    }

    function asyncGetList(array $recordRefs)
    {
        foreach ($recordRefs as $recs)
        {
            $recsToGet[] = $recs->getSoapVar();
        }

        $asyncGetListResponse = $this->makeCall("asyncGetList", array('baseRef' => $recsToGet));

        $asyncStatusResult = new nsAsyncStatusResult($asyncGetListResponse->asyncStatusResult);

        return $asyncStatusResult;
    }

    function asyncInitializeList(array $initializeRecords)
    {
        foreach ($initializeRecords as $recs)
        {
            $recsToInitialize[] = $recs->getSoapVar();
        }

        $asyncInitializeListResponse = $this->makeCall("asyncInitializeList", array('initializeRecord' => $recsToInitialize));

        $asyncStatusResult = new nsAsyncStatusResult($asyncInitializeListResponse->asyncStatusResult);

        return $asyncStatusResult;
    }

    function asyncSearch(nsComplexObject $searchRecord)
    {
        $asyncSearchResponse = $this->makeCall("asyncSearch", array(array('searchRecord' => $searchRecord->getSoapVar())));

        $asyncStatusResult = new nsAsyncStatusResult($asyncSearchResponse->asyncStatusResult);

        return $asyncStatusResult;
    }

    function checkAsyncStatus($jobId)
    {
        $checkAsyncStatusResponse = $this->makeCall("checkAsyncStatus", array(array('jobId' => $jobId)));

        $asyncStatusResult = new nsAsyncStatusResult($checkAsyncStatusResponse->asyncStatusResult);

        return $asyncStatusResult;
    }

    function getAsyncResult($jobId, $pageIndex=null)
    {
        $getAsyncResultResponse = $this->makeCall("getAsyncResult", array(array('jobId' => $jobId, 'pageIndex' => $pageIndex)));

        $asyncResult = $getAsyncResultResponse->asyncResult;

        if (isset($asyncResult->writeResponseList))
        {
            if ( count($asyncResult->writeResponseList->writeResponse) == 1 )
            {
                $response[] = new nsWriteResponse($asyncResult->writeResponseList->writeResponse);
            }
            else
            {
                foreach ($asyncResult->writeResponseList->writeResponse as $addResponse)
                {
                    $response[] = new nsWriteResponse($addResponse);
                }
            }
        }
        elseif (isset($asyncResult->readResponseList))
        {
            if ( count($asyncResult->readResponseList->readResponse) == 1 )
            {
                $response[] = new nsReadResponse( $asyncResult->readResponseList->readResponse );
            }
            else
            {
                for ($i = 0; $i < count($asyncResult->readResponseList->readResponse); $i++)
                {
                    $response[] = new nsReadResponse( $asyncResult->readResponseList->readResponse[$i] );
                }
            }
        }
        elseif (isset($asyncResult->searchResult))
        {
            $response = new nsSearchResponse( $asyncResult->searchResult );
        }

        return $response;
    }

    private function makeCall ($function_name, $arguments=array()) {

        $headers = $this->getRequestHeaders();

        $response = $this->client->__soapCall(  $function_name,
                                                $arguments,
                                                NULL,
                                                $headers,
                                                $this->soapHeadersResponse
        );

        if ( file_exists(dirname(__FILE__) . '/nslog') ) {

            // REQUEST
            $req = dirname(__FILE__) . '/nslog' . "/" . date("Ymd.His") . "." . milliseconds() . "-" . $function_name . "-request.xml";
            $Handle = fopen($req, 'w');
            $Data = $this->client->__getLastRequest();

            $Data = cleanUpNamespaces($Data);

            $xml = simplexml_load_string($Data, 'SimpleXMLElement', LIBXML_NOCDATA);

            $passwordFields = &$xml->xpath("//password | //password2 | //currentPassword | //newPassword | //newPassword2 | //ccNumber | //ccSecurityCode | //socialSecurityNumber");

            foreach ($passwordFields as &$pwdField) {

                (string)$pwdField[0] = "[Content Removed for Security Reasons]";

            }

            $stringCustomFields = &$xml->xpath("//customField[@xsitype='StringCustomFieldRef']");

            foreach ($stringCustomFields as $field) {

                (string)$field->value = "[Content Removed for Security Reasons]";

            }

            $xml_string = str_replace('xsitype', 'xsi:type', $xml->asXML());

            fwrite($Handle, $xml_string);
            fclose($Handle);

            // RESPONSE
            $resp = dirname(__FILE__) . '/nslog' . "/" . date("Ymd.His") . "." . milliseconds() . "-" . $function_name . "-response.xml";
            $Handle = fopen($resp, 'w');
            $Data = $this->client->__getLastResponse();
            fwrite($Handle, $Data);
            fclose($Handle);

        }

        return $response;

    }

    function setSearchPreferences ($bodyFieldsOnly = true, $pageSize = 50)
    {
        $this->soapHeaders["searchPreferences"] = new SoapHeader(   getNameSpace("SearchPreferences"),
                                                                    "searchPreferences",
                                                                    new SoapVar(    array(  "bodyFieldsOnly" => $bodyFieldsOnly, "pageSize" => $pageSize),
                                                                                    SOAP_ENC_OBJECT),
                                                                    false,
                                                                    "http://schemas.xmlsoap.org/soap/actor/next");
    }

    function clearSearchPreferences () {

        unset($this->soapHeaders["searchPreferences"]);

    }

    function setPreferences ($ignoreReadOnlyFields=null, $warningAsError=null, $disableMandatoryCustomFieldValidation=null, $disableSystemNotesForCustomFields=null, $useConditionalDefaultsOnAdd=null, $useConditionalDefaultsOnUpdate=null)
    {
        $prefs = array();

        if (!is_null($ignoreReadOnlyFields))
        {
            $prefs["ignoreReadOnlyFields"] = $ignoreReadOnlyFields;
        }
        if (!is_null($warningAsError))
        {
            $prefs["warningAsError"] = $warningAsError;
        }
        if (!is_null($disableMandatoryCustomFieldValidation))
        {
            $prefs["disableMandatoryCustomFieldValidation"] = $disableMandatoryCustomFieldValidation;
        }
        if (!is_null($disableSystemNotesForCustomFields))
        {
            $prefs["disableSystemNotesForCustomFields"] = $disableSystemNotesForCustomFields;
        }
        if (!is_null($useConditionalDefaultsOnAdd))
        {
            $prefs["useConditionalDefaultsOnAdd"] = $useConditionalDefaultsOnAdd;
        }
        if (!is_null($useConditionalDefaultsOnUpdate))
        {
            $prefs["useConditionalDefaultsOnUpdate"] = $useConditionalDefaultsOnUpdate;
        }

        $this->soapHeaders["preferences"] = new SoapHeader( getNameSpace("Preferences"),
                                                            "preferences",
                                                            new SoapVar($prefs, SOAP_ENC_OBJECT),
                                                            false,
                                                            "http://schemas.xmlsoap.org/soap/actor/next");
    }

    function clearPreferences () {

        unset($this->soapHeaders["preferences"]);

    }

    function setApplicationInfo($applicationId)
    {
        $this->soapHeaders["applicationInfo"] = new SoapHeader( getNameSpace("ApplicationInfo"),
                                                                "applicationInfo",
                                                                $applicationId,
                                                                false,
                                                                "http://schemas.xmlsoap.org/soap/actor/next");
    }

    function clearApplicationInfo () {

        unset($this->soapHeaders["applicationInfo"]);

    }

    function setSessionInfo($userId)
    {
        $this->soapHeaders["sessionInfo"] = new SoapHeader( getNameSpace("SessionInfo"),
                                                            "sessionInfo",
                                                            $userId,
                                                            false,
                                                            "http://schemas.xmlsoap.org/soap/actor/next");
    }

    function clearSessionInfo () {

        unset($this->soapHeaders["sessionInfo"]);

    }

    function getUserId()
    {
        return $this->soapHeadersResponse["sessionInfo"]->userId;
    }

    function setPartnerInfo($partnerId)
    {
        $this->soapHeaders["partnerInfo"] = new SoapHeader( getNameSpace("PartnerInfo"),
                                                            "partnerInfo",
                                                            $partnerId,
                                                            false,
                                                            "http://schemas.xmlsoap.org/soap/actor/next");
    }

    function clearPartnerInfo () {

        unset($this->soapHeaders["partnerInfo"]);

    }

    function setDocumentInfo($nsId)
    {
        $this->soapHeaders["documentInfo"] = new SoapHeader(getNameSpace("DocumentInfo"),
                                                            "documentInfo",
                                                            $nsId,
                                                            false,
                                                            "http://schemas.xmlsoap.org/soap/actor/next");
    }

    function clearDocumentInfo () {

        unset($this->soapHeaders["documentInfo"]);

    }

    function setPassport($email, $password, $account, $role)
    {
        $passport = array();
        $passport['email'] = $email;
        $passport['password'] = $password;
        $passport['account'] = $account;
        $role = new nsRecordRef(array('internalId' => $role));
        $passport['role'] = $role->getSoapVar();

        $this->soapHeaders["passport"] = new SoapHeader(getNameSpace("Passport"),
                                                        "passport",
                                                        new SoapVar($passport,SOAP_ENC_OBJECT),
                                                        false,
                                                        "http://schemas.xmlsoap.org/soap/actor/next");
    }

    function clearPassport () {

        unset($this->soapHeaders["passport"]);

    }

    private function getRequestHeaders() {

        if (count($this->soapHeaders) == 0) {

            return null;

        }

        $headers = array();

        foreach ($this->soapHeaders as $header) {

            $headers[] = $header;

        }

        if (count($headers) == 1) {

            return $headers[0];

        } else {

            return $headers;

        }

    }

}


########### DESERIALIZER ##############

function deserializeSimpleXML (SimpleXMLElement $record_element, $parent="", $namespaces=null)
{
    if ( $parent == "" )
    {
        foreach($record_element->attributes() as $attributeName => $attributeValue)
        {
            if ($attributeName == 'xsitype')
            {
                $parent = (string) $attributeValue;
            }
        }
    }

    if      ( $parent == "ItemAvailability" )       $record = new nsItemAvailability();
    elseif  ( $parent == "PricingMatrix")           $record = new nsPricingMatrix();
    elseif  ( $parent == "")                        return;
    else $record = new nsComplexObject($parent,null, $namespaces);

    foreach($record_element->attributes() as $attributeName => $attributeValue)
    {
        if ($attributeName == 'xsitype')
        {
            continue;
        }
        else
        {
            $record->setFields(array($attributeName => (string)$attributeValue));
        }
    }

    foreach ($record_element->children() as $fieldName => $fieldValue)
    {
        if ($fieldValue->children())
        {
            // e.g. RecordRef field or a list

            $fieldType = getFieldType($parent . "/" . $fieldName,$namespaces);

            if      ( $fieldType == "PricingMatrix")              $nsField = new nsPricingMatrix();
            elseif  ( $fieldType == "PriceList")                  $nsField = new nsPriceList();
            elseif  ( $fieldType == "CustomFieldList")            $nsField = new nsCustomFieldList();
            elseif  ( $fieldType == $parent . "AddressbookList")  $nsField = new nsAddressbookList($parent . "AddressbookList");
            else {$nsField = new nsComplexObject(!is_null(getTypeFromXML($fieldValue)) ? getTypeFromXML($fieldValue) : $fieldType,null,$namespaces);}

            foreach ($fieldValue as $fieldValue_name => $fieldValue_value)
            {
                if (count($fieldValue->$fieldValue_name) == 1)
                {
                    // e.g. RecordRef field, or a list where the field is not an array

                    if ($fieldValue_value->children())
                    {
                        // e.g. $fieldValue_value is a SalesOrderItem, Addressbook, etc

                        // deserializeSimpleXML function will return a nsComplexObject
                        $nsField->setFields(array($fieldValue_name => deserializeSimpleXML(
                                    $fieldValue_value,
                                    !is_null(getTypeFromXML($fieldValue_value))
                                        ? getTypeFromXML($fieldValue_value) : getFieldType($nsField->nsComplexObject_type . "/" . $fieldValue_name,$namespaces),
                                    $namespaces)));

                    }
                    else
                    {
                        // e.g. name field on a RecordRef

                        $nsField->setFields(array($fieldValue_name => (string)$fieldValue_value));
                    }

                }
                else
                {
                    // e.g. more than one item on an item list, or contacts in a contact list
                    if ($fieldValue_value->children())
                    {
                        $u[] = deserializeSimpleXML($fieldValue_value, !is_null(getTypeFromXML($fieldValue_value)) ? getTypeFromXML($fieldValue_value) : getFieldType($nsField->nsComplexObject_type . "/" . $fieldValue_name,$namespaces),$namespaces);
                    }
                    else
                    {
                        $u[] = (string)$fieldValue_value;
                    }
                }
            }

            if (isset($u))
            {
                // if $u has a value and it has not been set

                $nsField->setFields(array($fieldValue_name => $u));
                // clear field so values will not be reused
                unset($u);
            }

            foreach ($fieldValue->attributes() as $z => $d)
            {
                if ($z == 'xsitype')
                {
                    continue;
                }
                else
                {
                    $nsField->setFields(array($z => (string)$d));
                }
            }

            if (count($record_element->$fieldName) == 1)
            {
                // e.g. name on a RecordRef

                unset($t);
                $record->setFields(array($fieldName => $nsField));
            }
            else
            {
                $t[] = $nsField;
                $record->setFields(array($fieldName => $t));
            }

        }
        else
        {
            // e.g. a string field

            if (count($record_element->$fieldName) == 1) 
            {
                // if not a list or array of one element

                unset($j); // reset array $j
                $record->setFields(array($fieldName => (string)$fieldValue));
            }
            else
            {
                // if $record_element->$fieldName is array with more than one element (e.g. item on ItemList)

                $j[] = (string)$fieldValue;
                $record->setFields(array($fieldName => $j));
            }
        }
    }

    return $record;
}


########### DIRECTORY FUNCTIONS ##############

/**
 * Returns the namespace of a particular object
 *
 * @param $complexTypeName. The name of the object. e.g. "SalesOrder"
 * @return the namespace of a particular object
 */

function getNameSpace($complexTypeName, $namespaces=null) {
    if (strpos($complexTypeName, '/') === TRUE) {
        throw new Exception('ComplexTypeName cannot have "/"');
    }

    $namespace = searchDirectory($complexTypeName, $namespaces);

    if (!empty($namespace)) {
      return $namespace;
    } else {
        foreach ($myDirectory as $key => $value) {
            if (strtolower($key) == strtolower($complexTypeName)) {
                return $value;
            }
        }
    }

    throw new Exception ('ComplexType reference was not found in directory. ComplexTypeName = ' . $complexTypeName);
}

/**
 * Returns the type of the object that a field references to
 *
 * @param $fieldPath the path of the field. e.g. "InventoryItem/itemId"
 * @return the type of the object that a field references to
 */

function getFieldType ($fieldPath, $namespaces) {


    if (strpos($fieldPath, '/') === FALSE) {

        throw new Exception('Missing "/". Value passed should be <objectName>/<fieldName>');

    }

    list($parent, $field) = explode("/", $fieldPath);

    $compTypeName = searchDirectory($parent . '/' . $field, $namespaces);

    if ($compTypeName == null) {

        throw new Exception ("<ComplexType/field> was not found in directory. Argument = $fieldPath");

    } else {

        return $compTypeName;

    }

}


/**
 * Bottleneck access to the directory. There are some cases where there is both a sublist and a record with the same name,
 * eg the SiteCategory sublist on the Item records and the SiteCategory record. In these cases we have to disambiguate via context
 * and return the correctly prefixed object.
 *
 * @param $toFind - field path or namespace to find
 * @param $namespaces - an array of namespace prefixes like 'listsaccounting' or 'listswebsite' that are sometimes needed to disambiguate
 * records vs list types. These get passed to every child object from the parent. This isn't a true formal namespace stack, but it will do
 * thanks to the known structure of the NetSuite SOAP*/
function searchDirectory($toFind, $namespaces)
{
    global $myDirectory;
    $toRet = isset($myDirectory[$toFind]) ? $myDirectory[$toFind] : '';
    if (empty($toRet) && !empty($namespaces))
    {
        foreach ($namespaces as $i => $ns)
        {
            $toRet = $myDirectory[$ns . ":" . $toFind];
            if ($toRet !=null)
                break;
        }
    }
    return $toRet;
}

########### UTIL FUNCTIONS ##############

function array_is_associative ($array)
{
    if ( is_array($array) && ! empty($array) )
    {
        for ( $iterator = count($array) - 1; $iterator; $iterator-- )
        {
            if ( ! array_key_exists($iterator, $array) ) { return true; }
        }
        return ! array_key_exists(0, $array);
    }
    return false;
}


function arrayValuesAreEmpty ($array)
{
    if (!is_array($array))
    {
        return false;
    }

    foreach ($array as $key => $value)
    {
        if ( $value === false || ( !is_null($value) && $value != "" && !arrayValuesAreEmpty($value)))
        {
            return false;
        }
    }

    return true;
}


function milliseconds()
{
    $m = explode(' ',microtime());
    return (int)round($m[0]*10000,4);
}


function getStatusDetail ($sDetail) {

    if ( count($sDetail) == 1 ) {

        $statusDetail[] = new nsStatusDetail($sDetail->code, $sDetail->message, $sDetail->type);

    } else {

        foreach ($sDetail as $statDetail) {

            $statusDetail[] = new nsStatusDetail($statDetail->code, $statDetail->message, $statDetail->type);

        }

    }

    return $statusDetail;

}

function getTypeFromXML(SimpleXMLElement $extract_from) {

    foreach($extract_from->attributes() as $a => $b)
    {
        if ($a == 'xsitype') {

            return (string)$b;

        }

    }

    return NULL;

}

?>
