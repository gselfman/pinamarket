<?php
declare(strict_types=1);

function gc_audit_list(array $q):array {
 $scope=(string)($q['archive']??'live');if(!in_array($scope,['live','archive','all'],true))throw new ApiError('Выберите текущий журнал или архив');
 $source=match($scope){
  'live'=>'pm_audit a LEFT JOIN pg_audit_context x ON x.audit_id=a.id',
  'archive'=>'pg_audit_archive a LEFT JOIN pg_audit_archive_context x ON x.audit_id=a.id',
  'all'=>'(SELECT id,actor_id,action,object_ref,details_json,created_at FROM pm_audit UNION ALL SELECT id,actor_id,action,object_ref,details_json,created_at FROM pg_audit_archive) a LEFT JOIN (SELECT * FROM pg_audit_context UNION ALL SELECT * FROM pg_audit_archive_context) x ON x.audit_id=a.id'
 };
 $where=["a.action LIKE 'gift_%'"];$args=[];
 $search=trim((string)($q['search']??''));if(mb_strlen($search)>200)throw new ApiError('Слишком длинный поиск');
 if($search!==''){$where[]='(a.action LIKE ? OR a.object_ref LIKE ? OR p.email LIKE ?)';array_push($args,'%'.$search.'%','%'.$search.'%','%'.$search.'%');}
 $kind=(string)($q['actorKind']??'');if($kind!==''){if(!in_array($kind,['customer','merchant','staff','system','unknown'],true))throw new ApiError('Неизвестный участник');$where[]="COALESCE(x.actor_kind,'unknown')=?";$args[]=$kind;}
 $transport=(string)($q['source']??'');if($transport!==''){if(!in_array($transport,['web','cli','unknown'],true))throw new ApiError('Неизвестный источник');$where[]="COALESCE(x.source,'unknown')=?";$args[]=$transport;}
 if(($q['clientId']??'')!==''){$id=filter_var($q['clientId'],FILTER_VALIDATE_INT);if($id===false||$id<1)throw new ApiError('Проверьте ID клиента');$where[]='(a.actor_id=? OR x.target_client_id=?)';array_push($args,$id,$id);}
 if(($q['issuerId']??'')!==''){$where[]='x.issuer_id=?';$args[]=(string)$q['issuerId'];}
 foreach(['from'=>'>=','to'=>'<'] as $key=>$op)if(($q[$key]??'')!==''){$date=gc_date((string)$q[$key]);$where[]='a.created_at'.$op.'?';$args[]=$key==='to'?gmdate('Y-m-d',strtotime($date.' +1 day')):$date;}
 $sql=' FROM '.$source.' LEFT JOIN pm_clients p ON p.id=a.actor_id WHERE '.implode(' AND ',$where);
 $total=(int)one('SELECT COUNT(*) n'.$sql,$args)['n'];$limit=min(100,max(1,(int)($q['limit']??50)));$page=max(1,min(100000,(int)($q['page']??1)));$offset=($page-1)*$limit;
 $items=rows("SELECT a.*,p.email,COALESCE(x.actor_kind,'unknown') actor_kind,COALESCE(x.source,'unknown') source,x.target_client_id,x.issuer_id,x.user_agent,x.ip_hash".$sql.' ORDER BY a.created_at DESC,a.id DESC LIMIT '.$limit.' OFFSET '.$offset,$args);
 return ['items'=>$items,'total'=>$total,'page'=>$page,'pages'=>max(1,(int)ceil($total/$limit)),'archive'=>$scope];
}
function gc_archive_audit(int $limit=1000,?array $actor=null):array {
 $limit=min(1000,max(1,$limit));$cutoff=gmdate('Y-m-d H:i:s',time()-30*86400);
 return transact(function()use($limit,$cutoff,$actor){
  $ids=array_column(rows("SELECT id FROM pm_audit WHERE action LIKE 'gift_%' AND created_at<? ORDER BY id LIMIT ".$limit.' FOR UPDATE',[$cutoff]),'id');
  if(!$ids)return ['archived'=>0,'cutoff'=>$cutoff];
  $params=implode(',',array_fill(0,count($ids),'?'));
  query('INSERT INTO pg_audit_archive(id,actor_id,action,object_ref,details_json,created_at,archived_at) SELECT id,actor_id,action,object_ref,details_json,created_at,? FROM pm_audit WHERE id IN ('.$params.')',array_merge([now()],$ids));
  query('INSERT INTO pg_audit_archive_context SELECT * FROM pg_audit_context WHERE audit_id IN ('.$params.')',$ids);
  $bad=(int)one('SELECT COUNT(*) n FROM pm_audit s JOIN pg_audit_archive a ON a.id=s.id WHERE s.id IN ('.$params.') AND NOT((a.actor_id<=>s.actor_id) AND BINARY a.action=BINARY s.action AND BINARY a.object_ref=BINARY s.object_ref AND BINARY a.details_json=BINARY s.details_json AND a.created_at=s.created_at)',$ids)['n'];
  if($bad)throw new RuntimeException('AUDIT_ARCHIVE_VERIFICATION_FAILED');
  query('DELETE FROM pg_audit_context WHERE audit_id IN ('.$params.')',$ids);query('DELETE FROM pm_audit WHERE id IN ('.$params.')',$ids);
  audit($actor?(int)$actor['id']:null,'gift_audit_archived','journal',['rows'=>count($ids),'cutoff'=>$cutoff]);
  return ['archived'=>count($ids),'cutoff'=>$cutoff];
 });
}
