<?
use Bitrix\Main\Loader;
use \Bitrix\Iblock\ElementTable;
use \Bitrix\Main\Localization\Loc;
use \Bitrix\Main\Type\DateTime as BitrixDateTime;

class MyAgent{

    public static function Agent_ex_610($lastRunDate = false){

        $lastDate = $lastRunDate ? $lastRunDate : (new BitrixDateTime("2000-01-01 00:00:00", "Y-m-d H:i:s"))->toString();
        $currentDate = (new BitrixDateTime())->toString();

        if(Loader::includeModule("iblock")){
            $res = ElementTable::getList([
                'select' => [
                    'ID'
                ],
                'filter' => [
                    "ACTIVE" => "Y",
                    "IBLOCK_ID" => ID_IBLOCK_RECENZ,
                    "<=TIMESTAMP_X" => $currentDate,
                    ">=TIMESTAMP_X" => $lastDate,
                ]
            ]);
    
            CEventLog::Add([
                "AUDIT_TYPE_ID" => AGENT_NAME,
                "DESCRIPTION" => Loc::getMessage("MESS_COUNT_REVIEWS", [
                    "#AGENT_NAME#" => AGENT_NAME,
                    "#LAST_DATE#" => $lastDate,
                    "#COUNT#" => $res->getSelectedRowsCount()
                ])
            ]);
        }

        return "MyAgent::Agent_ex_610('".$currentDate."');";

    }

}