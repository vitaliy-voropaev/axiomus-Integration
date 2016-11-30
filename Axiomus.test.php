<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


/* Init Setup Axiomus CLASSES */
require __DIR__ . '/src/Axiomus/Axiomus.php';

use \Axiomus;

$query = new Axiomus\Query("new");

$query->setEnvironment(Axiomus\Query::DEMO);
$query->setOptions("type", "delivery");

//var_dump( $query );

$order = new Axiomus\Order();

$order->setOptions("inner_id", "16454");
$order->setOptions("name", "Петр Петров Федорович");
$order->setOptions("address", "Москва, Живописная, д4 корп1, кв 16");
$order->setOptions("d_date", date('Y-m-d', strtotime("+1 day")));
$order->setOptions("b_time", date('H:i', strtotime("+1 day")));
$order->setOptions("e_time", "23:59");
$order->setOptions("incl_deliv_sum", "0");
$order->setOptions("places", "1");
$order->setOptions("contacts", "тел. (499) 222-33-22");
$order->setOptions("description", "");

$items = new Axiomus\Items();

$items->add(new Axiomus\Item("товар 1", 0.7, 2, 1000));
$items->add(new Axiomus\Item("товар 2", 0.7, 1, 2000));
$items->add(new Axiomus\Item("товар 3", 0.7, 1, 200));

//добавляем товары в заказ
$order->setItems($items);
$query->setOrder($order);

$res = $query->send(Axiomus\SendQuery::DBG);

echo "<h3>Отправляем xml следующего содержания</h3>";
echo "<pre>";
    var_dump(htmlspecialchars($res));
echo "</pre>";

echo "<h3>Получаем ответ</h3>";
$res = $query->send();

echo "<pre>";
    var_dump(htmlspecialchars($res));
echo "</pre>";

?>