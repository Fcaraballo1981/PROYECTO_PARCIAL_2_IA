<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
startSecureSession();
$method=$_SERVER['REQUEST_METHOD']??'GET';
if(!sameOrigin())jsonResponse(403,['error'=>'Solicitud no permitida.','code'=>'forbidden']);

if($method==='POST'){
    $data=requestJson();$action=(string)($data['action']??'');
    if($action==='login'){
        rateLimit('admin_login',8,900);$expectedUser=trim((string)getenv('ADMIN_USERNAME'));$expectedPassword=(string)getenv('ADMIN_PASSWORD');
        if($expectedUser===''||strlen($expectedPassword)<12)jsonResponse(503,['error'=>'El acceso administrativo no está configurado.','code'=>'configuration']);
        if(!hash_equals($expectedUser,(string)($data['username']??''))||!hash_equals($expectedPassword,(string)($data['password']??'')))jsonResponse(401,['error'=>'El usuario o la contraseña no coinciden.','code'=>'credentials']);
        session_regenerate_id(true);$_SESSION['admin']=true;$_SESSION['csrf']=randomToken(16);jsonResponse(200,['authenticated'=>true,'csrf'=>$_SESSION['csrf']]);
    }
    requireAdmin();requireCsrf($data);
    if($action==='logout'){$_SESSION=[];session_destroy();jsonResponse(200,['authenticated'=>false]);}
    if($action==='update_submission')updateSubmission($data);
    if($action==='save_schedule')saveSchedule($data);
    jsonResponse(400,['error'=>'Acción no válida.','code'=>'invalid_action']);
}
if($method!=='GET'){header('Allow: GET, POST');jsonResponse(405,['error'=>'Método no permitido.']);}
requireAdmin();$pdo=database();
$rows=$pdo->query("SELECT s.*,c.full_name,c.email,c.phone,c.preferred_contact,v.status valuation_status,v.result_json,a.id appointment_id,a.booking_code,a.starts_at,a.status appointment_status FROM watch_submissions s JOIN customers c ON c.id=s.customer_id LEFT JOIN valuations v ON v.id=(SELECT id FROM valuations WHERE submission_id=s.id ORDER BY id DESC LIMIT 1) LEFT JOIN appointments a ON a.submission_id=s.id ORDER BY s.created_at DESC")->fetchAll();
$img=$pdo->prepare('SELECT id,original_name FROM watch_images WHERE submission_id=? ORDER BY id');$history=$pdo->prepare('SELECT event_type,detail,created_at FROM submission_history WHERE submission_id=? ORDER BY id DESC');
foreach($rows as &$row){$row['valuation']=$row['result_json']?json_decode($row['result_json'],true):null;unset($row['result_json'],$row['public_token']);$img->execute([$row['id']]);$row['images']=$img->fetchAll();$history->execute([$row['id']]);$row['history']=$history->fetchAll();}
$schedule=$pdo->query('SELECT * FROM availability_rules ORDER BY weekday')->fetchAll();
jsonResponse(200,['authenticated'=>true,'csrf'=>$_SESSION['csrf'],'submissions'=>$rows,'schedule'=>$schedule]);

function updateSubmission(array $data): never{
    $id=filter_var($data['id']??null,FILTER_VALIDATE_INT);if(!$id)jsonResponse(422,['error'=>'Solicitud no válida.']);
    $status=(string)($data['status']??'recibida');$outcome=(string)($data['outcome']??'pendiente');
    if(!in_array($status,['recibida','en_revision','evaluada','cerrada'],true)||!in_array($outcome,['pendiente','comprado','rechazado','negociacion'],true))jsonResponse(422,['error'=>'Estado no válido.']);
    try{$notes=cleanText($data['technician_notes']??'',3000);$final=trim((string)($data['final_value']??''));$final=$final===''?null:filter_var($final,FILTER_VALIDATE_FLOAT);if($final===false||($final!==null&&$final<0))throw new InvalidArgumentException('Revisá la tasación definitiva.');
        $pdo=database();$pdo->beginTransaction();$pdo->prepare('UPDATE watch_submissions SET status=?,outcome=?,technician_notes=?,final_value=?,updated_at=? WHERE id=?')->execute([$status,$outcome,$notes,$final,nowIso(),$id]);
        $appointmentStatus=(string)($data['appointment_status']??'');if($appointmentStatus!==''&&!in_array($appointmentStatus,['solicitada','confirmada','cancelada'],true))throw new InvalidArgumentException('Estado de cita no válido.');
        $appointmentAt=trim((string)($data['appointment_at']??''));$appointmentIso=null;
        if($appointmentAt!==''){$zone=new DateTimeZone(APP_TIMEZONE);$appointment=new DateTimeImmutable($appointmentAt,$zone);if($appointment<=new DateTimeImmutable('now',$zone))throw new InvalidArgumentException('La cita debe quedar en el futuro.');$appointmentIso=$appointment->format(DateTimeInterface::ATOM);$conflict=$pdo->prepare("SELECT 1 FROM appointments WHERE starts_at=? AND submission_id<>? AND status IN ('solicitada','confirmada') UNION SELECT 1 FROM blocked_slots WHERE starts_at=?");$conflict->execute([$appointmentIso,$id,$appointmentIso]);if($conflict->fetch())throw new InvalidArgumentException('Ese horario ya está ocupado.');}
        if($appointmentStatus!==''&&$appointmentIso!==null)$pdo->prepare('UPDATE appointments SET status=?,starts_at=?,updated_at=? WHERE submission_id=?')->execute([$appointmentStatus,$appointmentIso,nowIso(),$id]);elseif($appointmentStatus!=='')$pdo->prepare('UPDATE appointments SET status=?,updated_at=? WHERE submission_id=?')->execute([$appointmentStatus,nowIso(),$id]);
        $pdo->prepare('INSERT INTO submission_history(submission_id,event_type,detail,created_at) VALUES(?,?,?,?)')->execute([$id,'actualizacion','Estado: '.$status.'. Resultado: '.$outcome.'.',nowIso()]);$pdo->commit();jsonResponse(200,['saved'=>true]);
    }catch(InvalidArgumentException $e){if(isset($pdo)&&$pdo->inTransaction())$pdo->rollBack();jsonResponse(422,['error'=>$e->getMessage()]);}catch(Throwable $e){if(isset($pdo)&&$pdo->inTransaction())$pdo->rollBack();error_log('[Admin update] '.$e->getMessage());jsonResponse(500,['error'=>'No se pudieron guardar los cambios.']);}
}
function saveSchedule(array $data): never{
    if(!isset($data['rules'])||!is_array($data['rules'])||count($data['rules'])!==7)jsonResponse(422,['error'=>'Completá los siete días de la agenda.']);
    try{$pdo=database();$pdo->beginTransaction();$stmt=$pdo->prepare('UPDATE availability_rules SET enabled=?,start_time=?,end_time=?,slot_minutes=? WHERE weekday=?');
        foreach($data['rules'] as $rule){$day=filter_var($rule['weekday']??null,FILTER_VALIDATE_INT);$start=(string)($rule['start_time']??'');$end=(string)($rule['end_time']??'');$minutes=(int)($rule['slot_minutes']??0);if(!$day||$day<1||$day>7||!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/',$start)||!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/',$end)||$start>=$end||$minutes<15||$minutes>240)throw new InvalidArgumentException('Revisá días, horas e intervalo.');$stmt->execute([!empty($rule['enabled'])?1:0,$start,$end,$minutes,$day]);}
        $pdo->commit();jsonResponse(200,['saved'=>true]);
    }catch(InvalidArgumentException $e){if(isset($pdo)&&$pdo->inTransaction())$pdo->rollBack();jsonResponse(422,['error'=>$e->getMessage()]);}catch(Throwable $e){if(isset($pdo)&&$pdo->inTransaction())$pdo->rollBack();jsonResponse(500,['error'=>'No se pudo guardar la agenda.']);}
}
