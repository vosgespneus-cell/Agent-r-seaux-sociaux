<?php
declare(strict_types=1);

if(PHP_SAPI!=='cli') exit(1);
$config=require __DIR__.'/vp_config.php';
$db=new PDO($config['db_dsn'],$config['db_user'],$config['db_password'],[
 PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
 PDO::ATTR_EMULATE_PREPARES=>false
]);

function counts(PDO $db,string $table,string $column): array {
 $allowed=[
   'vp_product_intake'=>['processing_status'],
   'vp_product_drafts'=>['status'],
   'vp_actions'=>['status']
 ];
 if(!isset($allowed[$table]) || !in_array($column,$allowed[$table],true)){
   throw new InvalidArgumentException('invalid status query');
 }
 $out=[];
 foreach($db->query("SELECT ".$column." AS s, COUNT(*) AS n FROM ".$table." GROUP BY ".$column) as $row){
   $out[(string)$row['s']]=(int)$row['n'];
 }
 ksort($out);
 return $out;
}

$report=[
 'intake'=>counts($db,'vp_product_intake','processing_status'),
 'drafts'=>counts($db,'vp_product_drafts','status'),
 'actions'=>counts($db,'vp_actions','status')
];
echo 'VP_PIPELINE_STATUS '.json_encode($report,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
