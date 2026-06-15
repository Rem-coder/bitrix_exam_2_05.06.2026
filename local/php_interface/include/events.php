<?
use Bitrix\Main\Localization\Loc;
AddEventHandler("iblock", "OnBeforeIBlockElementUpdate", Array("MyRewiesEventHandlers", "OnBeforeIBlockElementUpdateHandler"));
AddEventHandler("iblock", "OnBeforeIBlockElementAdd", Array("MyRewiesEventHandlers", "OnBeforeIBlockElementAddHandler"));
AddEventHandler("iblock", "OnAfterIBlockElementUpdate", Array("MyRewiesEventHandlers", "OnAfterIBlockElementUpdateHandler"));


class MyRewiesEventHandlers
{
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

        #echo "<pre>".htmlspecialchars(print_r($arFields["PROPERTY_VALUES"][PROPERTY_AUTHOR_ID], true))."</pre>";   
        #echo "<pre>".htmlspecialchars(print_r($arFields, true))."</pre>";
        #die();

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