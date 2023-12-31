<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use RealRashid\SweetAlert\Facades\Alert;
use Illuminate\Support\Facades\DB;
use PhpCfdi\SatEstadoCfdi\Soap\SoapConsumerClient;
use PhpCfdi\SatEstadoCfdi\Soap\SoapClientFactory;
use PhpCfdi\SatEstadoCfdi\Consumer;
use ZipArchive;
use Carbon\Carbon;

class ValidadorController extends Controller
{
    public function Form(){
        session_start();
        if(isset($_SESSION['usuario'])){
            if(isset($_SESSION['usuario'])){

            }
        return view('facturas.factura-indiv.form');
    }else {
        return redirect()->route('login', app()->getLocale());
    }
    }
    public function FormZip(){
        session_start();
        if(isset($_SESSION['usuario'])){
            if(isset($_SESSION['usuario'])){

            }
        return view('facturas.factura-zip.form');
    }else {
        return redirect()->route('login', app()->getLocale());
    }
    }
    public function Individual($Request){
        session_start();
        if(isset($_FILES['xmlFile']) && $_FILES['xmlFile']['error'] === UPLOAD_ERR_OK) {
        $xmlFile = $_FILES['xmlFile']['tmp_name'];
        $pdfFile1 = $_FILES['pdfFile1']['tmp_name'];
        $pdfFile2 = $_FILES['pdfFile2']['tmp_name'];
        $nombreXml = $_FILES['xmlFile']['name'];
        $nombrePdf1 = $_FILES['pdfFile1']['name'];
        $nombrePdf2 = $_FILES['pdfFile2']['name'];
        $now = Carbon::now();
        //$destinationFolder = 'D:\PRV/'.$nombreXml.'/';
        $destinationFolder = 'E:\PRV/'.$nombreXml.'/';
        $xmlContent = file_get_contents($_FILES['xmlFile']['tmp_name']);
        $xml = simplexml_load_string($xmlContent);
        $ns = $xml->getNamespaces(true);
        if(isset($ns['cfdi'])){
        $xml->registerXPathNamespace('c', $ns['cfdi']);
        $xml->registerXPathNamespace('t', $ns['tfd']);
        $uuid='';
        $emisor='';
        $receptor='USI970814616';
        $total='';
        $sello='';

        //EMPIEZO A LEER LA INFORMACION DEL CFDI E IMPRIMIRLA
        foreach ($xml->xpath('//cfdi:Comprobante') as $cfdiComprobante){
            $total= (string)$cfdiComprobante['Total'];
        }
        foreach ($xml->xpath('//cfdi:Comprobante//cfdi:Emisor') as $Emisor){
        $emisor= (string)$Emisor['Rfc'];
        }

        // foreach ($xml->xpath('//cfdi:Comprobante//cfdi:Receptor') as $Receptor){
        // $receptor= (string)$Receptor['Rfc'];

        // }

        //ESTA ULTIMA PARTE ES LA QUE GENERABA EL ERROR
        foreach ($xml->xpath('//t:TimbreFiscalDigital') as $tfd) {
        $sello= (string)$tfd['SelloCFD'];
        $uuid= (string)$tfd['UUID'];
        }

        $udsello=substr($sello,-8);

        // creamos la fábrica dándole los parámetros de los objetos \SoapClient que fabricará
        $factory = new SoapClientFactory([
            'user_agent' => 'Mozilla/5.0 (X11; Linux x86_64; rv:66.0) Gecko/20100101 Firefox/66.0'
        ]);

        // le pasamos la fábrical al cliente
        $client = new SoapConsumerClient($factory);

        // creamos el consumidor del servicio para poder hacer las consultas
        $consumer = new Consumer($client);

        // consumimos el webservice!
        $response = $consumer->execute('https://verificacfdi.facturaelectronica.sat.gob.mx/default.aspx?&id='.$uuid.'&re='.$emisor.'&rr='.$receptor.'&tt='.$total.'&fe='.$udsello);
        $encontrada = (string)$response->document();
        if(str_contains($encontrada, "active")){
            $archivoXML = pathinfo($nombreXml, PATHINFO_FILENAME);
            $archivoPDF1 = pathinfo($nombrePdf1, PATHINFO_FILENAME);
            $archivoPDF2 = pathinfo($nombrePdf2, PATHINFO_FILENAME);

            if(pathinfo($nombreXml, PATHINFO_FILENAME) == pathinfo($nombrePdf1, PATHINFO_FILENAME)){
                //BUSCAMOS LA FACTURA EN LA CARPETA
                $factura = DB::select("SELECT TOP 1 * FROM PRVfacturas WHERE uuid = '$uuid'");

                if(count($factura) == 0){
                    if (!is_dir($destinationFolder)) {
                        mkdir($destinationFolder, 777, true);
                    }
                    move_uploaded_file($xmlFile, $destinationFolder . $nombreXml);
                    move_uploaded_file($pdfFile1, $destinationFolder . $nombrePdf1);
                    move_uploaded_file($pdfFile2, $destinationFolder . $nombrePdf2);
                    $data = array('ID_usuario'=>$_SESSION['usuario']->ID,'factura'=>$archivoXML,'estado' =>(string)$response->document(), 'total' => $total, 'uuid'=> $uuid, 'emisor'=>$emisor, 'sello'=> $udsello,'descripcion' => 'Subido con exito', 'fecha_ingreso' => $now, 'fecha_modificacion'=> $now, 'PDF'=> $archivoPDF1 , 'PDFsello'=> $archivoPDF2 );
                    DB::table('PRVfacturas')->insert($data);
                    return view('facturas.factura-indiv.confirmacion')->with('response',$response)->with('uuid',$uuid)->with('emisor',$emisor)->with('receptor',$receptor)->with('total',$total);
                }

                Alert::error(__('Factura Repetida'), __('Esta factura ya ha sido subida anteriormente'));
                return redirect()->back();
            }
            Alert::error(__('Error'), __('Su PDF debe tener el mismo nombre que el XML'));
            return redirect()->back();
         }

         Alert::error(__('No se encontro'), __('Su factura no es valida para este portal'));
         return redirect()->back();



    } else{
        Alert::error(__('No es una factura valida'), __('Su factura no es valida para este portal'));
        return redirect()->back();
    }

    }

}
public function ZIP(){
    session_start();
    $archivos_xml = [];
    $archivos_pdf = [];
    $archivos_rechazados= [];
    $now = Carbon::now();

    if (isset($_FILES['zipFile']) && $_FILES['zipFile']['error'] === UPLOAD_ERR_OK) {
        $zipFile = $_FILES['zipFile']['tmp_name'];
        $zip = new ZipArchive();

        if ($zip->open($zipFile) === true) {

            // Extraer los archivos del ZIP a un directorio temporal
            $tempDir = 'temp/';
            $zip->extractTo($tempDir);
            $zip->close();
            $nombreArchivo = $_FILES['zipFile']['name'];
            $nombreSinExtension = pathinfo($nombreArchivo, PATHINFO_FILENAME);

            //$destinationFolder = 'D:\PRV/'.$nombreSinExtension.'/';
            $destinationFolder = 'E:\PRV/'.$nombreSinExtension.'/';

            // Crear la carpeta de destino si no existe, la carpeta es por Usuario
            if (!is_dir($destinationFolder)) {
                mkdir($destinationFolder, 777, true);
            }


            // Leer los archivos extraídos
            $files = scandir($tempDir.$nombreSinExtension);

            foreach ($files as $file) {
                if ($file !== '.' && $file !== '..') {
                    $filePath = $tempDir .$nombreSinExtension.'/'. $file;
                    $TFilePath = $destinationFolder. $file;
                    $fileExtension = pathinfo($filePath, PATHINFO_EXTENSION);


                    if ($fileExtension === 'xml') {
                        // Acciones específicas para archivos XML
                        $xmlContent = file_get_contents($filePath);
                        // Realizar operaciones con el XML
                        $xml = simplexml_load_string($xmlContent);
                        $ns = $xml->getNamespaces(true);
                        if( isset($ns['cfdi'])){
                        $xml->registerXPathNamespace('c', $ns['cfdi']);
                        $xml->registerXPathNamespace('t', $ns['tfd']);
                        $uuid='';
                        $emisor='';
                        $receptor='USI970814616';
                        $total='';
                        $sello='';

                        //EMPIEZO A LEER LA INFORMACION DEL CFDI E IMPRIMIRLA
                        foreach ($xml->xpath('//cfdi:Comprobante') as $cfdiComprobante){
                            $total= (string)$cfdiComprobante['Total'];
                        }

                        // Se deja el Receptor fijo de URVINA
                        // foreach ($xml->xpath('//cfdi:Comprobante//cfdi:Receptor') as $Receptor){
                        //     $receptor= (string)$Receptor['Rfc'];
                        //     }

                        foreach ($xml->xpath('//cfdi:Comprobante//cfdi:Emisor') as $Emisor){
                        $emisor= (string)$Emisor['Rfc'];
                        }

                        //ESTA ULTIMA PARTE ES LA QUE GENERABA EL ERROR
                        foreach ($xml->xpath('//t:TimbreFiscalDigital') as $tfd) {
                        $sello= (string)$tfd['SelloCFD'];
                        $uuid= (string)$tfd['UUID'];
                        }

                        $udsello=substr($sello,-8);

                        // creamos la fábrica dándole los parámetros de los objetos \SoapClient que fabricará
                        $factory = new SoapClientFactory([
                            'user_agent' => 'Mozilla/5.0 (X11; Linux x86_64; rv:66.0) Gecko/20100101 Firefox/66.0'
                        ]);

                        // le pasamos la fábrical al cliente
                        $client = new SoapConsumerClient($factory);

                        // creamos el consumidor del servicio para poder hacer las consultas
                        $consumer = new Consumer($client);

                        // consumimos el webservice!
                        $response = $consumer->execute('https://verificacfdi.facturaelectronica.sat.gob.mx/default.aspx?&id='.$uuid.'&re='.$emisor.'&rr='.$receptor.'&tt='.$total.'&fe='.$udsello);
                        $encontrada = (string)$response->document();
                        if(str_contains($encontrada, "active")){
                           //Crear registro en la base de datos

                           //Guardar en array para comprobar PDF
                           $factura = DB::select("SELECT TOP 1 * FROM PRVfacturas WHERE uuid = '$uuid'");


                           if(count($factura) == 0){

                            $nombre_archivo = pathinfo($file, PATHINFO_FILENAME);
                            $archivos_xml[] = $nombre_archivo;
                            $data = array('ID_usuario'=>$_SESSION['usuario']->ID,'factura'=>$nombre_archivo,'estado' =>(string)$response->document(), 'total' => $total, 'uuid'=> $uuid, 'emisor'=>$emisor, 'sello'=> $udsello,'descripcion' => 'Subido con exito', 'fecha_ingreso' => $now, 'fecha_modificacion'=> $now, 'PDF'=> '' , 'PDFsello'=> '' );
                            DB::table('PRVfacturas')->insert($data);

                           }
                           $archivos_rechazados[$file]["razon"] = "Factura Repetida";
                            $archivos_rechazados[$file]["nombre"] = $file;

                           //Copiar y mover el archivo a carpeta de facturas
                           rename($filePath, $TFilePath);
                        }else if(str_contains($encontrada, "cancelled")){
                            $archivos_rechazados[$file]["razon"] = "Factura Cancelada";
                            $archivos_rechazados[$file]["nombre"] = $file;
                            chmod($filePath, 0777 );
                            unlink($filePath);
                        }else{
                            $archivos_rechazados[$file]["razon"] = "Factura no encontrada";
                            $archivos_rechazados[$file]["nombre"] = $file;
                            chmod($filePath, 0777 );
                            unlink($filePath);
                        }

                    } else{
                        $archivos_rechazados[$file]["razon"] = "No se puede leer el CDFI";
                        $archivos_rechazados[$file]["nombre"] = $file;
                        chmod($filePath, 0777 );
                        unlink($filePath);
                    }


                    }
                }
            }

            $files = scandir($tempDir.$nombreSinExtension);
            foreach ($files as $file) {
                $extension = pathinfo($file, PATHINFO_EXTENSION);
                if ($file !== '.' && $file !== '..') {
                    $filePath = $tempDir .$nombreSinExtension.'/'. $file;
                    $TFilePath = $destinationFolder. $file;
                    $fileExtension = pathinfo($filePath, PATHINFO_EXTENSION);

                    if ($fileExtension === 'pdf') {
                        // Acciones específicas para archivos PDF

                        $objetoxml = array_search(basename($filePath, ".pdf"),$archivos_xml);

                        if(isset($archivos_xml[$objetoxml])){
                        if (basename($filePath, ".pdf") !== $archivos_xml[$objetoxml]) {

                            // Eliminar el archivo PDF que no coincide con el nombre del XML
                            $archivos_rechazados[$file]["razon"] = "Relacion con XML no encontrada";
                            $archivos_rechazados[$file]["nombre"] = $file;
                            chmod($filePath, 0777 );
                            unlink($filePath);

                        }else{

                            $nombre_archivo = pathinfo($file, PATHINFO_FILENAME);
                            $archivos_pdf[] = $nombre_archivo;
                            rename($filePath, $TFilePath);
                            DB::table('PRVfacturas')->where('factura',$archivos_xml[$objetoxml])->update(array('PDF'=>$nombre_archivo,));

                        }
                    }else{
                        // Eliminar el archivo PDF que no coincide con el nombre del XML
                        $archivos_rechazados[$file]["razon"] = "Relacion con XML no encontrada";
                        $archivos_rechazados[$file]["nombre"] = $file;
                        chmod($filePath, 0777 );
                        unlink($filePath);
                    }
                    }
                    else {
                        // Otros tipos de archivos no deseados
                        // Eliminar el archivo no deseado
                        $archivos_rechazados[$file]["razon"] = "Formato de archivo no admitido";
                        $archivos_rechazados[$file]["nombre"] = $file;
                        chmod($filePath, 0777 );
                        unlink($filePath);
                    }


            }
        }

            // Eliminar el directorio temporal
             rmdir($tempDir.$nombreSinExtension);
        } else {
            echo 'No se pudo abrir el archivo ZIP.';
        }
    }
    return view('facturas.factura-zip.confirmacion')->with('archivos_xml',$archivos_xml)->with('archivos_pdf',$archivos_pdf)->with('archivos_rechazados',$archivos_rechazados);

}
}
