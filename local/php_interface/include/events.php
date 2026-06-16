<?
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\UserTable;
use Bitrix\Main\Mail\Event;

$eventManager = \Bitrix\Main\EventManager::getInstance();

AddEventHandler("iblock", "OnBeforeIBlockElementUpdate", Array("MyRewiesEventHandlers", "OnBeforeIBlockElementUpdateHandler"));
AddEventHandler("iblock", "OnBeforeIBlockElementAdd", Array("MyRewiesEventHandlers", "OnBeforeIBlockElementAddHandler"));
AddEventHandler("iblock", "OnAfterIBlockElementUpdate", Array("MyRewiesEventHandlers", "OnAfterIBlockElementUpdateHandler"));

#$eventManager->addEventHandler("mymodule", "OnMacrosProductCreate", "OnMacrosProductCreate");
$eventManager->addEventHandler("main", "OnBeforeUserUpdate", ["MyUserEventHandlers", "OnBeforeUserUpdateHandler"]);
$eventManager->addEventHandler("main", "OnAfterUserUpdate", ["MyUserEventHandlers", "OnAfterUserUpdateHandler"]);

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

        $userClass = Loc::getMessage("EMPTY_USER_CLASS");

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