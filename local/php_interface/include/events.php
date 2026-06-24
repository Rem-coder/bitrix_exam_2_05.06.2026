<?
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\UserTable;
use Bitrix\Main\Mail\Event;
use ORM\Fields\ExpressionField; 
use \Bitrix\Iblock\ElementTable;
use \Bitrix\Main\Loader;

$eventManager = \Bitrix\Main\EventManager::getInstance();

AddEventHandler("iblock", "OnBeforeIBlockElementUpdate", Array("MyRewiesEventHandlers", "OnBeforeIBlockElementUpdateHandler"));
AddEventHandler("iblock", "OnBeforeIBlockElementAdd", Array("MyRewiesEventHandlers", "OnBeforeIBlockElementAddHandler"));
AddEventHandler("iblock", "OnAfterIBlockElementUpdate", Array("MyRewiesEventHandlers", "OnAfterIBlockElementUpdateHandler"));
#AddEventHundler("main", "OnBuildGlobalMenu", ["MyGlobalMenuHundlers", "MyOnBuildGlobalMenuHindler"]);

$eventManager->addEventHandler("main", "OnBeforeUserUpdate", ["MyUserEventHandlers", "OnBeforeUserUpdateHandler"]);
$eventManager->addEventHandler("main", "OnAfterUserUpdate", ["MyUserEventHandlers", "OnAfterUserUpdateHandler"]);
$eventManager->addEventHandler("main", "OnSendUserInfo", ["MyMailEventHundlers", "MyOnSendUserInfoHandler"]);
$eventManager->addEventHandler("search", "BeforeIndex", ["MySearchHundlers", "MyBeforeIndexHandler"]);
$eventManager->addEventHandler("main", "OnBuildGlobalMenu", ["MyGlobalMenuHundlers", "MyOnBuildGlobalMenuHindler"]);

class MyRewiesEventHandlers{

    private static ?int $oldAuthor = null;

    private static function GetReviewData(&$arFields){

        $arFilter = [
            "IBLOCK_ID" => ID_IBLOCK_RECENZ,
            "ID" => $arFields["ID"]
        ];

        $res = CIBlockElement::GetList([],$arFilter, false, false, ["ID", "PROPERTY_AUTHOR"]);
        return $res->GetNext();    
    }

    private static function CheckTextAnonse(&$arFields){

        if($arFields["IBLOCK_ID"] <> ID_IBLOCK_RECENZ){
            return true;
        }

        $newValuePreviewText = str_replace("#del#", "", $arFields["PREVIEW_TEXT"]);
        $lengthAnonse = strlen($newValuePreviewText);
        $res = $lengthAnonse >= 5;

        if($res){
            $arFields["PREVIEW_TEXT"] = $newValuePreviewText;
        }else{
            global $APPLICATION;
            $APPLICATION->ThrowException(Loc::getMessage("MIN_LENGHT_EXCEPTION", ["#ANONSE_LENGTH#"=>$lengthAnonse]));   
        }

        return $res;
    }

    private static function SetOldAuthor($arFields){
        
        if($arFields["IBLOCK_ID"] <> ID_IBLOCK_RECENZ){
            return true;
        }

        $oldReviewData = self::GetReviewData($arFields);
        self::$oldAuthor = $oldReviewData["PROPERTY_AUTHOR_VALUE"] == "" ? null : $oldReviewData["PROPERTY_AUTHOR_VALUE"];
    }
    
	public static function OnBeforeIBlockElementUpdateHandler(&$arFields){
        
        $res = self::CheckTextAnonse($arFields);
        if($res){
            self::SetOldAuthor($arFields);
        }

        return $res;
	}

    public static function OnBeforeIBlockElementAddHandler(&$arFields){
        return self::CheckTextAnonse($arFields);
	}

    public static function OnAfterIBlockElementUpdateHandler(&$arFields){

        if($arFields["IBLOCK_ID"] <> ID_IBLOCK_RECENZ){
            return true;
        }

        $newReviewData = self::GetReviewData($arFields);
        
        if($newReviewData["PROPERTY_AUTHOR_VALUE"] != self::$oldAuthor){           
            CEventLog::Add([
                "AUDIT_TYPE_ID" => FIX_AUDIT_TYPE_ID, 
                "DESCRIPTION" => Loc::getMessage("MESSAGE_CHANGE_AUTHOR", [
                    "#ID_REVIEW#" => $arFields["ID"],
                    "#OLD_AUTHOR_ID#" => self::$oldAuthor,
                    "#CURRENT_AUTHOR_ID#" => $newReviewData["PROPERTY_AUTHOR_VALUE"]
                ])
            ]);
        }

        return true;
    }

}

class MyUserEventHandlers{

    private static ?string $oldUserClass = null; 

    private static function GetValueUserClass($userClassID){

        $userClass = EMPTY_USER_CLASS;

        if(empty($userClassID)){
            return $userClass;
        }

        $res = CUserFieldEnum::GetList([], ["ID" => $userClassID])->fetch();
            if($res && $res["VALUE"]){
                $userClass = $res["VALUE"];
            }      

        return $userClass;

    }

    public static function OnBeforeUserUpdateHandler(&$arFields){

        if(!array_key_exists("UF_USER_CLASS", $arFields)){
            return true;
        }

        $res = UserTable::GetList([
            'select' => ["ID", "UF_USER_CLASS"],
            'filter' => ["ID" => $arFields["ID"]]
        ])->fetch();

        self::$oldUserClass = self::GetValueUserClass($res["UF_USER_CLASS"]);  

        return true;
    }

    public static function OnAfterUserUpdateHandler(&$arFields){

        if(self::$oldUserClass === null){
            return true;
        }

        $currentUserClass = self::GetValueUserClass($arFields["UF_USER_CLASS"]);
        
        if($currentUserClass != self::$oldUserClass){
            Event::send([
                "EVENT_NAME" => Loc::getMessage("MESS_EVENT_NAME"),
                "LID" => MY_SITE_ID,
                "C_FIELDS" => [
                    "OLD_USER_CLASS" => self::$oldUserClass,
                    "NEW_USER_CLASS" => $currentUserClass,
                    "MAIN_EMAIL" => MAIN_EMAIL
                ],
            ]);    
        };

        echo "<pre>".htmlspecialchars(print_r($currentUserClass, true))."</pre>";
        echo "<pre>".htmlspecialchars(print_r("OLD".self::$oldUserClass, true))."</pre>";
        #die();

        return true;
    }
}

class MyMailEventHundlers{

    public static function MyOnSendUserInfoHandler(&$arFields){

        $userID = $arFields["FIELDS"]["USER_ID"];
        if(!$userID){
            return true;
        };

        $userClassId = UserTable::getList([
            'select' => ["ID", "UF_USER_CLASS"],
            'filter' => ["ID" => $arFields["FIELDS"]["USER_ID"]]
        ])->fetch();

        $userClassId = $res ? $res["UF_USER_CLASS"] : null;

        if(!$userClassId){
            $userClassValue = EMPTY_USER_CLASS;
        }else{
            $userClassValue = CUserFieldEnum::GetList([], ["ID" => $userClassId])->fetch()["VALUE"];
        };

        $arFields["FIELDS"]["CLASS"] = $userClassValue;

        return true;
    }

}

class MySearchHundlers{

    private static $arrayReviewUsersClass = null;

    private static function GenerateArrayUsersClass(){
        
        $reviewsAuthors = [];

        $authorsClass = [];
        $classesName = [];

        if(!Loader::includeModule('iblock')){
            return $reviewsAuthors;
        }

        $reviews = CIBlockElement::GetList(
            [],
            ["ACTIVE"=>"Y", "IBLOCK_ID" => REVIEWS_IBLOCK_ID],
            false,
            false,
            ["IBLOCK_ID", "ID", "PROPERTY_AUTHOR"]
        );

        while ($review = $reviews->GetNext()){
            $reviewsAuthors[$review["ID"]] = $review["PROPERTY_AUTHOR_VALUE"]; 
        }

        if(empty($reviewsAuthors)){
            return $reviewsAuthors;
        }

        $authorsRes = UserTable::getList([
            'select' => ["ID", "UF_USER_CLASS"],
            'filter' => ["ID" => array_filter(array_unique(array_values($reviewsAuthors)))]
        ]);
        
        while($author = $authorsRes->fetch()){
            $authorsClass[$author["ID"]] = $author["UF_USER_CLASS"];
        }

        if(empty($authorsClass)){
            return $reviewsAuthors;
        }

        $classesRes = CUserFieldEnum::GetList(
            [],
            ["USER_FIELD_ID" => ID_ENUM_CLASS]
        );

        while($class = $classesRes->GetNext()){
            $classesName[$class["ID"]] = $class["VALUE"];
            #file_put_contents($_SERVER["DOCUMENT_ROOT"]."/local/logSearch.txt", print_r($class, true), FILE_APPEND);
        }

        foreach($authorsClass as &$value){
            $value = $classesName[$value];
        }
        unset($value);
        
        foreach ($reviewsAuthors as &$value) {
            $value = $authorsClass[$value];
        }
        unset($value);

        #file_put_contents($_SERVER["DOCUMENT_ROOT"]."/local/logSearch.txt", print_r($reviewsAuthors, true),FILE_APPEND);

        return $reviewsAuthors;

    }

    public static function MyBeforeIndexHandler($Fields){

        if(self::$arrayReviewUsersClass === null){
            self::$arrayReviewUsersClass = self::GenerateArrayUsersClass();
        }

        #file_put_contents($_SERVER["DOCUMENT_ROOT"]."/local/logSearch.txt", print_r($Fields, true),FILE_APPEND);

        if($Fields["MODULE_ID"] == MODULE_IBLOCK_ID 
        && $Fields["PARAM2"] == ID_IBLOCK_RECENZ
        && substr($Fields["ITEM_ID"], 0, 1) != 'S'){
            
            $clasUserRewie = self::$arrayReviewUsersClass[$Fields["ITEM_ID"]] ? self::$arrayReviewUsersClass[$Fields["ITEM_ID"]] : EMPTY_USER_CLASS;

            $Fields["TITLE"] = $Fields["TITLE"].Loc::getMessage("MESS_CLASS").$clasUserRewie;
        };

        return $Fields;
    } 

}

class MyGlobalMenuHundlers{

    public static function MyOnBuildGlobalMenuHindler(&$aGlobalMenu, &$aModuleMenu){

        global $USER;
        if(!isset($USER) || !is_object($USER)){
            return;
        }
        $currentUserGroups = $USER->GetUserGroupArray();
      
        if(!in_array(GROUP_CONTENT_REDACTOR_ID, $currentUserGroups)){
            return;
        }
            
        foreach ($aGlobalMenu as $key => $val) {
            if($val["menu_id"] == 'content'){
                continue;
            }
            unset($aGlobalMenu[$key]);        
        }
 
        foreach ($aModuleMenu as $key => $val) {
            if($val["parent_menu"] == PARENT_MENU_CONTENT_VALUE){
                continue;
            }
            unset($aModuleMenu[$key]); 
        }

        $aGlobalMenu["fast"] = [     
            "menu_id" =>  "fast",
            "text" => Loc::getMessage("TITLE_MAIN_MENU_FAST"),
            "title" => Loc::getMessage("TITLE_MAIN_MENU_FAST"),
            "items_id" => "global_menu_fast",
            "sort" => 500,
            "items" => [
                [
                    "text" => Loc::getMessage("SRC_NAME_1"),
                    "url" => "https://test1",
                    "title" => Loc::getMessage("SRC_NAME_1")
                ],
                [
                    "text" => Loc::getMessage("SRC_NAME_2"),
                    "url" => "https://test2",
                    "title" => Loc::getMessage("SRC_NAME_2")
                ]
            ]   
        ]; 
    }
    
}