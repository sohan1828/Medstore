<?php
require_once __DIR__ . '/lib.php';

function sample_products(): array {
    $r = fn($id,$n,$note,$cat,$p,$rx) => ['id'=>$id,'name'=>$n,'note'=>$note,'cat'=>$cat,'price'=>$p,'rx'=>$rx,'controlled'=>false,'stock'=>100];
    return [
      $r(1,'Paracetamol 500 mg','Strip of 10 tablets','Pain & fever',20,false),
      $r(2,'ORS sachets','Pack of 5','First aid',60,false),
      $r(3,'Cetirizine 10 mg','Strip of 10 tablets','Cold & allergy',30,false),
      $r(4,'Antiseptic solution','100 ml bottle','First aid',110,false),
      $r(5,'Crepe bandage roll','10 cm','First aid',90,false),
      $r(6,'Digital thermometer','Fast-read','Devices',250,false),
      $r(7,'Blood pressure monitor','Upper-arm, automatic','Devices',3200,false),
      $r(8,'Ibuprofen 400 mg','Strip of 10 tablets','Pain & fever',45,false),
      $r(9,'Amoxicillin 500 mg','Strip of 10 capsules','Prescription',140,true),
      $r(10,'Atorvastatin 20 mg','Strip of 10 tablets','Prescription',180,true),
      $r(11,'Salbutamol inhaler','200 doses','Prescription',350,true),
      $r(12,'Cough syrup','100 ml','Cold & allergy',95,false),
    ];
}

function catalog(): array {
    if (USE_SAMPLE_DATA) return sample_products();
    $rows = db()->query("SELECT id,name,note,category AS cat,price,stock,requires_rx AS rx,controlled FROM products WHERE active = 1 ORDER BY id")->fetchAll();
    foreach ($rows as &$r) { $r['id']=(int)$r['id']; $r['price']=(float)$r['price']; $r['stock']=(int)$r['stock']; $r['rx']=(bool)$r['rx']; $r['controlled']=(bool)$r['controlled']; }
    return $rows;
}
