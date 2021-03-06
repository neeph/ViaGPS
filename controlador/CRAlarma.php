<?php
require_once 'modelo/DeviceGroupMP.php';
require_once 'modelo/DeviceMP.php';
require_once 'modelo/EventDataMP.php';
require_once 'modelo/AlertaLogMP.php';
require_once 'modelo/PoligonoMP.php';
require_once 'modelo/PInteresMP.php';
require_once 'modelo/SensorDeviceMP.php';
require_once 'modelo/SensorMP.php';

class CRAlarma {
    protected $cp;
    protected $dgMP;
    protected $deMP;
    protected $poMP;
    protected $piMP;
    protected $alMP;

    function  __construct($cp) {
        $this->cp = $cp;
        $this->dgMP = new DeviceGroupMP();
        $this->deMP = new DeviceMP();
        $this->edMP = new EventDataMP();
        $this->alMP = new AlertaLogMP();
        $this->poMP = new PoligonoMP();
        $this->piMP = new PInteresMP();
        $this->sdMP = new SensorDeviceMP();
        $this->seMP = new SensorMP();
        $this->setGet();
        $this->setOp();
    }

    function getLayout() {
        return $this->layout;
    }

    function setGet() {
        if(isset($_GET["get"])) {
            $this->cp->cp->showLayout = false;
            $this->get = mysql_escape_string($_GET["get"]);
            $attr = array("accountID");
            switch($this->get) {
                case 'mapa':
                    include 'vista/ralarma_mapa.phtml';
                    $info = array("par"=>$_GET["par"], "lat"=>$_GET["lat"], "lon"=>$_GET["lon"]);
                    echo "<div id='info' style='display:none;'>".json_encode($info)."</div>";
                    switch ($_GET["par"]) {
                        case "1": //vel
                            break;
                        case "2": //time
                            break;
                        case "3": //geoz
                            $this->poligono = $this->poMP->find($_GET["pol"]);
                            $this->puntos = $this->poMP->fetchPuntos($this->poligono->ID_POLIGONO);
                            echo "<div id='pol' style='display:none;'>";
                            echo json_encode($this->puntos);
                            echo "</div>";
                            break;
                        case "4": //geof
                            break;
                        case "5": //pint
                            $this->obj = $this->piMP->find($_GET["pol"]);
                            echo "<div id='pint' style='display:none;'>";
                            echo json_encode($this->obj);
                            echo "</div>";
                            break;
                    }
                    break;
                case 'descargar':
                    $ini = strtotime($_GET["fecha_ini"]." ".$_GET["hrs_ini"].":".$_GET["min_ini"].":00");
                    $fin = strtotime($_GET["fecha_fin"]." ".$_GET["hrs_fin"].":".$_GET["min_fin"].":00");
                    $fini = $_GET["fecha_ini"]." ".$_GET["hrs_ini"].":".$_GET["min_ini"].":00";
                    $ffin = $_GET["fecha_fin"]." ".$_GET["hrs_fin"].":".$_GET["min_fin"].":00";
                    $rep = null;
                    if($_GET["id_device"] == "0") {
                        $gr = $this->dgMP->find($_GET["id_grupo"], $attr);
                        if($gr->accountID == $this->cp->getSession()->get("accountID")) {
                            $de = $this->deMP->fetchByGrupo($_GET["id_grupo"]);
                            $dev = array();
                            $license = array();
                            foreach($de as $d) {
                                $dev[] = $d->deviceID;
                                $license[$d->deviceID] = $d->licensePlate;
                                $nombre[$d->deviceID] = $d->displayName;
                            }
                            $rep = $this->alMP->reporte($ini, $fin, $dev);
                        }
                    } else {
                        $dev = $this->deMP->find($_GET["id_device"], array("accountID", "licensePlate", "displayName"));
                        $license[$_GET["id_device"]] = $dev->licensePlate;
                        $nombre[$_GET["id_device"]] = $dev->displayName;
                        if($dev->accountID == $this->cp->getSession()->get("accountID")) {
                            $rep = $this->alMP->reporte($ini, $fin, array($_GET["id_device"]));
                        }
                    }
                    if($rep != null) {
                        require_once 'modelo/DireccionMP.php';
                        require_once 'Classes/PHPExcel.php';
                        $this->diMP = new DireccionMP();
                        $cacheMethod = PHPExcel_CachedObjectStorageFactory:: cache_to_phpTemp;
                        $cacheSettings = array( ' memoryCacheSize ' => '8MB');
                        PHPExcel_Settings::setCacheStorageMethod($cacheMethod, $cacheSettings);
                        $objPHPExcel = new PHPExcel();
                        $objPHPExcel->getProperties()->setCreator("ViaGPS")
                                ->setTitle("Reporte de Alarmas " . $ini . " - " . $fin)
                                ->setSubject("Reporte de Alarmas " . $ini . " - " . $fin)
                                ->setDescription("Reporte de Alarmas " . $ini . " - " . $fin);

                        $objPHPExcel->setActiveSheetIndex(0);
                        $objPHPExcel->getActiveSheet()->setTitle('Alarmas');
                        $objReader = PHPExcel_IOFactory::createReader('Excel5');
                        $objPHPExcel = $objReader->load("plantilla.xls");

                        $objPHPExcel->getActiveSheet()
                                ->setCellValueByColumnAndRow(5, 2, 'Reporte de Alarmas')
                                ->setCellValueByColumnAndRow(5, 3, utf8_encode('Periodo de tiempo: ') . $fini . " / ".$ffin);
                        $columnas = array("Fecha", "Vehiculo", "Patente", "Direccion", "Comuna", "Region", "Velocidad", "Encendido", "Alarma", "Regla");
                        $nCol = count($columnas);
                        $rowIni = 7;
                        for($i=0; $i<$nCol; $i++) {
                            $objPHPExcel->getActiveSheet()
                                ->setCellValueByColumnAndRow($i+1, $rowIni-1, utf8_encode($columnas[$i]));
                        }
                        $km = 0;
                        $i = 0;
                        foreach ($rep as $r) {
                            $dir = $this->getDireccion($r->latitude, $r->longitude);
                            $objPHPExcel->getActiveSheet()
                                ->setCellValueByColumnAndRow(1, $rowIni+$i, $r->fecha)
                                ->setCellValueByColumnAndRow(2, $rowIni+$i, $nombre[$r->deviceID])
                                ->setCellValueByColumnAndRow(3, $rowIni+$i, $license[$r->deviceID])
                                ->setCellValueByColumnAndRow(4, $rowIni+$i, $dir->DIRECCION)
                                ->setCellValueByColumnAndRow(5, $rowIni+$i, $dir->COMUNA)
                                ->setCellValueByColumnAndRow(6, $rowIni+$i, $dir->REGION)
                                ->setCellValueByColumnAndRow(7, $rowIni+$i, round($r->speedKPH))
                                ->setCellValueByColumnAndRow(8, $rowIni+$i, ($r->encendido=="1")?"Si":"No")
                                ->setCellValueByColumnAndRow(9, $rowIni+$i, $r->NOM_ALERTA)
                                ->setCellValueByColumnAndRow(10, $rowIni+$i, utf8_encode(strip_tags($this->traduceRegla($r))));
                            $i++;
                        }
                        
                        header('Content-Type: application/vnd.ms-excel');
                        header('Content-Disposition: attachment;filename="reporte_alarma_'.$ini.'_'.$fin.'.xls"');
                        header('Cache-Control: max-age=0');
                        $objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
                        $objWriter->save('php://output');
                    }
                    break;
                case 'reporte':
                    $ini = strtotime($_POST["fecha_ini"]." ".$_POST["hrs_ini"].":".$_POST["min_ini"].":00");
                    $fin = strtotime($_POST["fecha_fin"]." ".$_POST["hrs_fin"].":".$_POST["min_fin"].":00");
                    $rep = null;
                    if($_POST["id_device"] == "0") {
                        $gr = $this->dgMP->find($_POST["id_grupo"], $attr);
                        if($gr->accountID == $this->cp->getSession()->get("accountID")) {
                            $de = $this->deMP->fetchByGrupo($_POST["id_grupo"]);
                            $dev = array();
                            $license = array();
                            foreach($de as $d) {
                                $dev[] = $d->deviceID;
                                $license[$d->deviceID] = $d->licensePlate;
                                $nombre[$d->deviceID] = $d->displayName;
                                $vehicle[$d->deviceID] = $d->vehicleID;
                            }
                            $rep = $this->alMP->reporte($ini, $fin, $dev);
                            $this->sensor = $this->sdMP->fetchByDevices($dev);
                        }
                    } else {
                        $dev = $this->deMP->find($_POST["id_device"], array("accountID", "licensePlate", "vehicleID","displayName"));
                        $this->sensor = $this->sdMP->fetchByDevice($_POST["id_device"], $dev->accountID);
                        $license[$_POST["id_device"]] = $dev->licensePlate;
                        $nombre[$_POST["id_device"]] = $dev->displayName;
                        $vehicle[$_POST["id_device"]] = $dev->vehicleID;
                        if($dev->accountID == $this->cp->getSession()->get("accountID")) {
                            $rep = $this->alMP->reporte($ini, $fin, array($_POST["id_device"]));
                        }
                    }
//                    print_r($this->sensor);
                    if($rep != null) {
                        foreach ($rep as $r) {
                            $out[] = array(
                                "licensePlate"=>$license[$r->deviceID],
                                "vehicleID"=>$vehicle[$r->deviceID],
                                "displayName"=>$nombre[$r->deviceID],
                                "fecha"=>$r->fecha,
                                "latitude"=>$r->latitude,
                                "longitude"=>$r->longitude,
                                "encendido"=>$r->encendido,
                                "alarma"=>$r->NOM_ALERTA,
                                "regla"=>utf8_encode($this->traduceRegla($r)),
                                "velocidad"=>round($r->speedKPH),
                                "heading"=>$r->heading
                            );
                        }
                        echo json_encode($out);
                    }
                    break;
            }
        }
    }

    function getDireccion($lat, $lon) {
        $url = new stdClass();
        $url->LATITUD = round($lat, 5);
        $url->LONGITUD = round($lon, 5);
        $res = $this->diMP->find($url->LATITUD, $url->LONGITUD);
        if($res != null) {
            $res->fuente = "InternalBD";
            return $res;
        } else {
            $delay = 0;
            $geocode_pending = true;
            $urlBase = "http://maps.google.com/maps/api/geocode/json?";
            while ($geocode_pending) {
                $urlRequest = $urlBase . "latlng=$lat,$lon&sensor=true&region=CL&language=ES";
                $dir = json_decode(file_get_contents($urlRequest));
                $status = $dir->status;
                if (strcmp($status, "OK") == 0) {
                    $geocode_pending = false;
                    $url->DIRECCION = $dir->results[0]->formatted_address."";
                    $n = count($dir->results[0]->address_components);
                    for($i=0; $i<$n; $i++) {
                        $d = $dir->results[0]->address_components[$i];
                        switch($d->types[0]) {
                            case 'administrative_area_level_3': //comuna
                                $url->COMUNA = $d->long_name."";
                                break;
                            case 'administrative_area_level_1': //region
                                $url->REGION = $d->long_name."";
                                break;
                            case 'locality':
                                $url->CIUDAD = $d->long_name."";
                                break;
                            case 'country': //pais
                                $url->PAIS = $d->long_name."";
                                break;
                        }
                    }
                    $this->diMP->insert($url);
                } else if (strcmp($status, "620") == 0) {
                    $delay += 100000;
                } else {
                    $geocode_pending = false;
                }
                usleep($delay);
            }
            $url->fuente = "GoogleMapsApi";
            return $url;
        }
    }

    function traduceRegla($regla) {
        if($regla->ID_TIPO_REGLA != 4) {
            switch($regla->ID_PARAMETRO) {
                case 1: //velocidad
                    switch($regla->ID_OPERADOR) {
                        case 1:
                            return "Velocidad (".round($regla->speedKPH).") > ".$regla->VALOR_REGLA." (Km/h)";
                            break;
                        case 2:
                            return "Velocidad (".round($regla->speedKPH).") < ".$regla->VALOR_REGLA." (Km/h)";
                            break;
                    }
                    break;
                case 2: //tiempo
                    switch($regla->ID_OPERADOR) {
                        case 1:
                            return "Detencion > ".$regla->VALOR_REGLA." (Min.)";
                            break;
                        case 2:
                            return "Detencion > ".$regla->VALOR_REGLA." (Min.)";
                            break;
                    }
                    break;
                case 3: //geozona
                    $pol = $this->poMP->find($regla->ID_POLIGONO, array("NOM_POLIGONO"));
                    switch($regla->ID_OPERADOR) {
                        case 4:
                            return "Entro a la Geozona <b>".$pol->NOM_POLIGONO."</b>";
                            break;
                        case 5:
                            return "Salio de la Geozona <b>".$pol->NOM_POLIGONO."</b>";
                            break;
                    }
                    break;
                case 4: //geofrontera
                    $pol = $this->poMP->find($regla->ID_POLIGONO, array("NOM_POLIGONO"));
                    switch($regla->ID_OPERADOR) {
                        case 6:
                            return "Cruzo la Geofrontera <b>".$pol->NOM_POLIGONO."</b>";
                            break;
                    }
                    break;
                case 5: //punto de interes
                    $pi = $this->piMP->find($regla->ID_POLIGONO, array("name"));
                    switch($regla->ID_OPERADOR) {
                        case 4:
                            return "Entro al Punto de interes <b>".$pi->name."</b>";
                            break;
                        case 5:
                            return "Salio del Punto de interes <b>".$pi->name."</b>";
                            break;
                    }
                    break;
            }
        } else { //sensores
            $this->sensorAl = $this->getSensor($regla->ID_PARAMETRO);
            if($this->sensorAl != null) {
                switch($this->sensorAl->TIPO_PROCESO_SENSOR) {
                    case 1: //eventual
                        $this->opSenAl = $this->seMP->fetchOpcion($regla->ID_PARAMETRO, $regla->VALOR_REGLA);
                        return $this->sensorAl->NOM_SENSOR." <b>".$this->opSenAl->SENSOR_OPCION."</b>";
                        break;
                    case 2: //variacion
                        switch($regla->ID_OPERADOR) {
                            case 1:
                                return $this->sensorAl->NOM_SENSOR." > ".$regla->VALOR_REGLA." (".$this->sensorAl->UNIDAD_SENSOR.")";
                                break;
                            case 2:
                                return $this->sensorAl->NOM_SENSOR." < ".$regla->VALOR_REGLA." (".$this->sensorAl->UNIDAD_SENSOR.")";
                                break;
                        }
                        break;
                    case 3://independiente
                        $col = $this->sensorAl->COLUMNA_SENSOR;
                        switch($regla->ID_OPERADOR) {
                            case 1:
                                return "Sensor ".$this->sensorAl->NOM_SENSOR.": ".$regla->$col." > ".$regla->VALOR_REGLA." (".utf8_decode($this->sensorAl->UNIDAD_SENSOR).")";
                                break;
                            case 2:
                                return "Sensor ".$this->sensorAl->NOM_SENSOR.": ".$regla->$col." < ".$regla->VALOR_REGLA." (".utf8_decode($this->sensorAl->UNIDAD_SENSOR).")";
                                break;
                        }
                        break;
                }
            }
        }
    }
    
    function getSensor($idSe) {
        $n = count($this->sensor);
        $encontrado = 0;
        $i = 0;
        while($encontrado == 0 && $i<$n) {
            $auxSe = $this->sensor[$i];
            if($auxSe->ID_SENSOR == $idSe) {
                $encontrado = 1;
            } else {
                $i++;
            }
        }
        if($encontrado == 1) return $auxSe;
        else return null;
    }
    
    

    function setOp() {
        if (isset($_GET["op"])) {
        } else {
            $this->layout = "vista/reporte_base.phtml";
            if($this->cp->cp->isAdmin() || $this->cp->cp->isSuperAdmin()) {
                $this->grupos = $this->dgMP->fetchByCuenta($this->cp->getSession()->get("accountID"));
            } else {
                $this->grupos = $this->dgMP->fetchUserGrupo($this->cp->getSession()->get("userID"));
            }
            $this->min = range(0,59,15);
            $this->hrs = range(0,23,1);
        }
    }

    function getGrupoName($id) {
        $n = count($this->grupos);
        $i = 0;
        while($i<$n && $this->grupos[$i]->groupID != $id) { $i++; }
        return $this->grupos[$i]->displayName;
    }
}
?>