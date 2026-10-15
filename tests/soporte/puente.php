<?php
// Instrumentación de pruebas local. No se publica como ruta HTTP.
declare(strict_types=1);
$raiz = dirname(__DIR__, 2) . '/menu08_app';
spl_autoload_register(static function(string $clase) use ($raiz): void {
    $partes = explode('\\', substr($clase, strlen('Menu08\\')));
    $mapa = ['Nucleo'=>'nucleo','Modelos'=>'modelos','Controladores'=>'controladores'];
    $espacio = array_shift($partes);
    if (isset($mapa[$espacio])) require $raiz.'/aplicacion/'.$mapa[$espacio].'/'.implode('/',$partes).'.php';
});
use Menu08\Nucleo\Configuracion;
use Menu08\Nucleo\ConexionBD;
use Menu08\Modelos\Ubicacion;
use Menu08\Modelos\Orden;
Configuracion::cargar($raiz.'/configuracion/configuracion.php');
date_default_timezone_set('America/Bogota');
$args=json_decode(stream_get_contents(STDIN),true);
$pdo=ConexionBD::obtener();
switch($args['accion']) {
    case 'sql':
        $s=$pdo->prepare($args['sql']); $s->execute($args['params']??[]);
        echo json_encode($s->columnCount() ? $s->fetchAll() : ['filas'=>$s->rowCount()],JSON_UNESCAPED_UNICODE); break;
    case 'vigente': echo json_encode(Ubicacion::vigente((int)$args['ft'],$args['momento']),JSON_UNESCAPED_UNICODE); break;
    case 'proxima': echo json_encode(Ubicacion::proxima((int)$args['ft'],$args['momento']),JSON_UNESCAPED_UNICODE); break;
    case 'reloj': echo json_encode(['php'=>date('c'),'zona'=>date_default_timezone_get(),'mysql'=>$pdo->query('SELECT NOW() ahora, @@session.time_zone zona')->fetch()]); break;
    case 'perfil':
        // El log general se activa únicamente para contar las consultas del modelo.
        $pdo->exec("SET GLOBAL log_output='TABLE'");
        $pdo->exec('SET GLOBAL general_log=ON');
        $thread=(int)$pdo->query('SELECT CONNECTION_ID()')->fetchColumn();
        $inicio=$pdo->query('SELECT NOW(6)')->fetchColumn();
        $t=microtime(true); $r=Orden::enCurso((int)$args['ft']); $ms=(microtime(true)-$t)*1000;
        $pdo->exec('SET GLOBAL general_log=OFF');
        $s=$pdo->prepare("SELECT argument FROM mysql.general_log WHERE thread_id=? AND event_time>=? AND command_type='Execute' AND (argument LIKE 'SELECT t.id AS turno_id%' OR argument LIKE 'SELECT i.orden_id%') ORDER BY event_time");
        $s->execute([$thread,$inicio]);
        echo json_encode(['ms_modelo'=>$ms,'ordenes'=>count($r['ordenes']),'consultas'=>$s->fetchAll(PDO::FETCH_COLUMN)],JSON_UNESCAPED_UNICODE); break;
}
