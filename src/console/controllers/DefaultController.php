<?php

namespace alotacents\blueonesoft\console\controllers;

use Yii;
use yii\console\Controller;
use yii\helpers\Console;

use alotacents\videcom\common\components\VRSSoap;

/**
 * Default controller for the `Videcom` module
 */
class DefaultController extends Controller
{
    /**
     * Renders the index view for the module
     * @return string
     */
    public function actionIndex($args=array()) {
        echo "use help\n";

        $module = \alotacents\blueonesoft\Module::getInstance();


        var_dump($module->id);
    }

    public function actionVrs($args=array()) {

        $success = false;
        $result = null;

        $email = isset($args[2]) && strpos($args[2], '@') !== false ? $args[2] : false;
        $ftp = isset($args[2]) && strtolower($args[2]) == 'ftp' ? true : false;

        $file_name = isset($args[1]) ? $args[1].'.csv' : 'vrs.txt';
        if(!!$email){
            $command = $args[0]."[c/{$file_name},email={$email},ZIP=1]";
        } elseif($ftp){
            $command = $args[0]."[c/{$file_name},ftp=1]";
        } else {
            $command = $args[0]."[c/{$file_name}]";
        }

        try{
            $client=@(new VRSSoap('https://customer3.videcom.com/seaborneairlines/vrsxmlservice/VRSXMLService.asmx?WSDL', array(
                'connection_timeout'=>240,
                'timeout'=>600,
                'sine'=>Yii::$app->params['videcom']['sine'],
                'pass'=>Yii::$app->params['videcom']['pass'],
                //'cache_wsdl' =>  WSDL_CACHE_NONE, // WSDL_CACHE_BOTH in production
            )));



            $runVRSCommandResponse = $client->RunVRSCommand(array('VRSCommand'=>$command));

            if(isset($runVRSCommandResponse->RunVRSCommandResult)){
                $result = $runVRSCommandResponse->RunVRSCommandResult;

                if(isset($result->VRSResponse)){
                    $result = (string) $result->VRSResponse;
                    $result = ltrim($result);

                    if(!!$email) {
                        if (strpos($result, 'Report file sent via email attachment') === 0) {
                            $success = true;
                        }
                        $result = '';
                    } elseif($ftp) {
                        if (strpos($result, 'Report file stored on server') === 0) {
                            $success = true;

                            $url = substr($result, 32);

                            $uri = parse_url($url);

                            $scheme = $uri['scheme'];
                            $host = $uri['host'];
                            $port = isset($uri['port']) ? $uri['port'] : '21';

                            $user = isset($uri['user']) ? $uri['user'] : 'BBFTP';
                            $pass = isset($uri['pass']) ? $uri['pass'] : 'se8BOrn3fTp';
                            $auth = (!isset($user) || !isset($pass));
                            $path = isset($uri['path']) ? $uri['path'] : '/';
                            //$file	= basename($path);
                            $path = dirname($path);
                            $path = $path == '' ? '/' : $path;

                            //$host = '194.128.159.141';
                            if ($scheme == 'ftp') {
                                $conn_id = ftp_connect($host, $port, 120);
                                if (!!$conn_id) {
                                    if (!$auth) {
                                        $auth = @ftp_login($conn_id, $user, $pass);
                                    }

                                    if ($auth) {
                                        if ($path != '/') {
                                            @ftp_chdir($conn_id, $path);
                                        }
                                        if ($path == '/' || $path == ftp_pwd($conn_id)) {
                                            $fh = tmpfile();

                                            if (ftp_fget($conn_id, $fh, $file_name, FTP_BINARY, 0)) {
                                                fseek($fh, 0);
                                                $result = stream_get_contents($fh);
                                                $success = true;
                                            } else {
                                                Yii::getLogger()->log("There was a problem while downloading file {$file_name} from FTP", CLogger::LEVEL_INFO, 'application.commands.' . strtolower(get_class($this) . '.' . __FUNCTION__));
                                            }

                                            fclose($fh);
                                        }
                                    }

                                    ftp_close($conn_id);
                                } else {
                                    Yii::getLogger()->log('Failed to connect to FTP ' . $url, CLogger::LEVEL_INFO, 'application.commands.' . strtolower(get_class($this) . '.' . __FUNCTION__));
                                }
                            }
                        }
                    } else {
                        if (strpos($result, 'STOREREPORTFILE') === 0 || $result == '') {
                            if (strpos($result, 'STOREREPORTFILE' . $file_name) === 0 || $result == '') {
                                if (strlen($result) >= 65) {
                                    $result = substr($result, 65);
                                } else {
                                    $result = '';
                                }
                                $success = true;
                            } else {
                                $result = substr($result, 0, 65);
                            }
                        } elseif (strpos($result, 'ERROR') === false) {
                            $success = true;
                        }
                    }

                    /*
					if(strpos($result, 'ERROR') === 0){
						$result = $result;
					}*/
                } elseif(isset($result->ERRORS)){
                    $result = (string) $result->ERRORS->ERROR->attributes()->Description;
                } else {
                    $result = 'VRSResponse ' . var_dump($result);
                }
            } else {
                $result = "SOAP Result Invalid: \n" . $client->__getLastResponse();
            }
        } catch(SoapFault $e) {
            $result = "SOAP Fault: (faultcode: {$e->faultcode}, faultstring: {$e->faultstring})";
        } catch(Exception $e) {
            $result = "Exception (code: {$e->getCode()}, message: {$e->getMessage()})";
        }

        echo $result;

        if($success === false){
            Yii::getLogger()->log('Report ' . $command . ' Failed ' . $result, CLogger::LEVEL_ERROR, 'application.commands.'.strtolower(get_class($this).'.'.__FUNCTION__));
            //Yii::getLogger()->log('Report ' . $command . ' Failed', CLogger::LEVEL_ERROR, 'application.commands.'.strtolower(get_class($this).'.'.__FUNCTION__));
            return 1;
        }

        return 0;
    }
}
