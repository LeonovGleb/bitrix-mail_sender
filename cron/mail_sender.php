<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');

use Bitrix\Main\Mail\Event;
\Bitrix\Main\Loader::includeModule('sale');

// Достанем всех активных пользователей
$filter = [
    "ACTIVE" => 'Y'
];
$rsUsers = CUser::GetList(($by = "NAME"), ($order = "desc"), $filter);

while ($arUser = $rsUsers->Fetch()) {

    // Достанем отложенные товары
    // TODO: Переписать на D7
    $basketRes = CSaleBasket::GetList(array('NAME' => 'ASC', 'ID' => 'ASC'), array(
        'USER_ID' => $arUser['ID'],
        'DELAY' => 'Y',
    ));

    $whishList  = [];
    while ($basketItem = $basketRes->Fetch()) {
        // Проверим присутствует ли товар в заказх этого пользователя за последние 30 дней
        // Если нет, то добавим его в массив для письма
        // TODO: Переписать на D7 и исключить запросы в цикле
        if (CSaleOrder::GetList(
            array('ID' => 'DESC'),
            array(
                'USER_ID' => $arUser['ID'],
                'BASKET_PRODUCT_ID' => $basketItem['PRODUCT_ID'],
                '>=DATE_FROM' => date($DB->DateFormatToPHP(CSite::GetDateFormat("FULL")), strtotime(' -30 days'))
            )
        )->fetch()) continue;

        $whishList[] = $basketItem['NAME'];
    }

    if ($whishList) {
        // Создаем шаблон письма с нужным описанием и метками которые необходимо заменить
        // Список товаров напишем просто через запятую
        // TODO: Обернуть название товара в ссылку на сайт, лучше утвердить новый шаблон письма
        Event::send(array(
            "EVENT_NAME" => "SEND_WHISH_LIST",
            "LID" => "s1",
            "C_FIELDS" => array(
                "USER_NAME" => $arUser['NAME'] ?? $arUser['LOGIN'],
                "ITEMS" => implode('", "', $whishList),
                "EMAIL" => $arUser['EMAIL'],
            ),
        ));
    }
}

require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_after.php');