<?php
declare(strict_types=1);
function gc_issuer_brand_name(array $issuer):string {
 if(array_key_exists('brand_name',$issuer))return (string)($issuer['brand_name']??'');
 return one('SELECT brand_name FROM pg_issuer_presentation WHERE issuer_id=?',[$issuer['id']])['brand_name']??'';
}
function gc_save_issuer_brand(string $id,array $data,array $actor):array {
 $brand=$data['brandName']??'';if(!is_string($brand)||mb_strlen($brand)>80||preg_match('/[\x00-\x1F\x7F]/u',$brand))throw new ApiError('Укажите название бренда одной строкой, не более 80 символов');$brand=trim($brand);
 return transact(function()use($id,$brand,$actor){
  $issuer=one('SELECT id,name FROM pg_issuers WHERE id=? FOR UPDATE',[$id]);if(!$issuer)throw new ApiError('Компания не найдена',404);
  if(gc_issuer_brand_name($issuer)!==$brand){
   query('INSERT INTO pg_issuer_presentation(issuer_id,brand_name,updated_by,updated_at) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE brand_name=VALUES(brand_name),updated_by=VALUES(updated_by),updated_at=VALUES(updated_at)',[$id,$brand,$actor['id'],now()]);
   audit((int)$actor['id'],'gift_supplier_brand_saved',$id,['brandName'=>$brand]);
  }
  return ['issuerId'=>$id,'brandName'=>$brand,'displayName'=>$brand!==''?$brand:$issuer['name'],'saved'=>true];
 });
}
