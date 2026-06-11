<?php
if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();

use \Bitrix\Main\UserTable;

$arIDValidUsers = [];

$arSelect = ["ID", "LOGIN", "EMAIL", UF_AUTHOR_STATUS];
$arFilter = [
	"ACTIVE" => "Y",
	UF_AUTHOR_STATUS => ID_UF_AUTHOR_STATUS_PUBLIC,
	"GROUPS.GROUP_ID" => ID_USER_GROUP_AUTHOR
];

$usersData = UserTable::getList([
	'select' => $arSelect,
	'filter' => $arFilter
]);

while($res = $usersData->Fetch()){
	$arIDValidUsers[] = $res["ID"];
}

$reviewsByProduct = [];
$mainRecenzName = "";
$countRecenz = 0;

if(!empty($arIDValidUsers)){
	$arSelect = ["ID", "NAME", "PROPERTY_AUTHOR", "PROPERTY_PRODUCT"];
	$arFilter = [
		"IBLOCK_ID" => ID_IBLOCK_RECENZ,
		"ACTIVE" => "Y",
		"PROPERTY_AUTHOR" => $arIDValidUsers
	];
	$result = CIBlockElement::GetList(array(),$arFilter, false, false, $arSelect);

	while($res = $result->GetNext()){
		$productID = $res["PROPERTY_PRODUCT_VALUE"];
		$reviewsByProduct[$productID][] = $res;
		if($mainRecenzName == ""){
			$mainRecenzName = $res["NAME"];
		}
		$countRecenz = $countRecenz + 1;
	}
}

$arResult["MAIN_RECENZ_NAME"] = $mainRecenzName;
$arResult['COUNT_RECENZ'] = $countRecenz;

foreach ($arResult['ITEMS'] as $key => $arItem)
{
	$arItem['PRICES']['PRICE']['PRINT_VALUE'] = number_format((float)$arItem['PRICES']['PRICE']['PRINT_VALUE'], 0, '.', ' ');
	$arItem['PRICES']['PRICE']['PRINT_VALUE'] .= ' '.$arItem['PROPERTIES']['PRICECURRENCY']['VALUE_ENUM'];

	if($reviewsByProduct[$arItem["ID"]]){
		$arItem["RECENZS"] = $reviewsByProduct[$arItem["ID"]];
	}

	$arResult['ITEMS'][$key] = $arItem;
}

$this->__component->SetResultCacheKeys(['COUNT_RECENZ', 'MAIN_RECENZ_NAME']);
