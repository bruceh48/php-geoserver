<?php
ini_set("display_errors", "On");
error_reporting(E_ALL);

$gsurl = 'http://geoserver.io/geoserver/';
$username = 'admin';
$password = 'halley48';

class GSWrapper {
        var $gsurl ='';
        var $username = '';
        var $password = '';


        public function __construct($gsurl, $username = '', $password = '') {
          if (substr($gsurl, -1) !== '/') $gsurl .= '/';
          $this->gsurl = $gsurl;
          $this->username = $username;
          $this->password = $password;
        }



      function authGet($apiPath) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->gsurl.$apiPath);
        curl_setopt($ch, CURLOPT_USERPWD, $this->username.":".$this->password);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $rslt = curl_exec($ch);
        $info = curl_getinfo($ch);

        if ($info['http_code'] == 401) {
          return 'Access denied. Check login credentials.';
        } else {
          return $rslt;
        }
      }

      function runApi($apiPath='', $method = 'GET', $data = '', $contentType = 'application/xml') {
        $apiPath = trim($apiPath);
        $ch = curl_init();
        $url = $apiPath;
        $data = trim($data);

        if (!strpos($apiPath,'/rest/')) {
          $url = $this->gsurl.'rest/'.$apiPath;
        }
        echo 'runApi_URL:'.$url.'<br>';
        echo 'Auth: '.$this->username.":".$this->password.'<br>';

        $cmd='curl -v -u '.$this->username.':'.$this->password.' -X'.$method.' -H "Content-type: '.$contentType.'" -d "'.$data.'" '.$url;
        // $a=array();
        // $rs='';
        // echo $cmd.'<br>';
        // $r=exec($cmd,$a,$rs);
        // echo '<pre>';print_r($a);echo '</pre>';
        // return $r;

        // $url = $this->gsurl.'rest/'.$apiPath;
      //	echo 'URL:'.$url.'<br>';die;
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_USERPWD, $this->username.":".$this->password);
        if ($method == 'POST') {
          curl_setopt($ch, CURLOPT_POST, true);
          curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        }
        if ($method == 'PUT') {
          curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
          curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        }
        if ($method == 'DELETE') {
          curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        }

        if ($data != '') {
          curl_setopt($ch, CURLOPT_HTTPHEADER,array("Content-Type: $contentType",
          "Content-Length: ".strlen($data)));
        }

        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $rslt = curl_exec($ch);

        echo 'curl_exec=<pre>';print_r($rslt);echo '</pre>';
        $info = curl_getinfo($ch);

        if ($info['http_code'] == 401) {
          return 'Access denied. Check login credentials.';
        } else {
          $j=json_decode($rslt,true);
          return $j;
        }
      }

      // --- Workspace functions---

      public function workspaceExists($workspaceName='') {
        $r = $this->listWorkspaces();
        $i=0;
        $a=$r['workspaces']['workspace'];
        // echo 'R=<pre>';print_r($a);echo '</pre>';
        $len=count($a);
        // echo 'Len = '.$len.'<br>';
        $found=false;
        if ($workspaceName!='') {
          while(!$found && $i < $len) {
            // echo 'name = '.$a[$i]['name'].', looking for '.$workspaceName.'<br>';
            $found = ($a[$i]['name']==$workspaceName);
            // echo ($found?'YES':'NO');
            $i++;
          }
        }
        return $found;
      }

      public function listAllWorkspaces() {
        return $this->runApi('workspaces.json');
      }

      public function createWorkspace($workspaceName='') {
        return $this->runApi('workspaces', 'POST',
        '<workspace><name>'.htmlentities($workspaceName, ENT_COMPAT).'</name><url>'.htmlentities('http:\/\/'.$workspaceName.'bruce.com', ENT_COMPAT).'</url></workspace>');
      }

      public function createCoverageStore($workspaceName='', $datastoreName='', $layerName='', $filePathName='') {

        $contentType='text/plain';
        $data = $filePathName;
        $url='workspaces/getsat/coveragestores/'.urlencode($datastoreName).'/external.geotiff?configure=first&coverageName='.urlencode($layerName);

        echo 'url='.$url.'<br>$filePathName='.$filePathName.'<br>';

        $res= $this->runApi($url, 'PUT', $filePathName, $contentType);
        if (stripos($res,'Error')===FALSE) {
          return (Object)(array('success'=>TRUE,'msg'=>$res));
        }  else {
          return (Object)(array('success'=>FALSE,'msg'=>$res));
        }
      }

      public function deleteLayer($layerName, $workspaceName, $datastoreName) {
        $this->runApi('layers/'.urlencode($layerName), 'DELETE');
        return $this->runApi('workspaces/'.urlencode($workspaceName).'/datastores/'.urlencode($datastoreName).'/featuretypes/'.urlencode($layerName), 'DELETE');
      }

      // Datastore APIs
      	public function listVectorDatastores($workspaceName) {
      		return $this->runApi('workspaces/'.urlencode($workspaceName).'/datastores.json');
      	}

        public function listCoverageStores($workspaceName) {
          $stores=$this->runApi('workspaces/'.urlencode($workspaceName).'/coveragestores.json');
          echo '<pre>';print_r($stores);echo '</pre>';
          $a = array();
          if (is_array($stores['coverageStores']) && array_key_exists('coverageStore',$stores['coverageStores'])) {
                $b = $stores['coverageStores']['coverageStore'];
                for ($i=0;$i< count($b);$i++) {
                  $url = $b[$i]['href'];
                  $c=$this->runApi($url);

                  $name = $c['coverageStore']['name'];
                  $description=$c['coverageStore']['description'];
                  $file = $c['coverageStore']['url'];
                  $enabled = $c['coverageStore']['enabled'];
                  $type = $c['coverageStore']['type'];

                  $a[] = (object)(array('name'=>$name,
                                        'url'=>$file,
                                        'description'=>$description,
                                        'enabled'=>$enabled,
                                        'type'=>$enabled));
                }
            }
          return $a;
        }

        public function setLayerWmsPath($workspaceName='',$layerName='',$wmsPath='' ) {
          $r = $this->runApi('layers/'.htmlentities($workspaceName, ENT_COMPAT).':'.
          htmlentities($layerName, ENT_COMPAT).'.xml','PUT',
          "<layer><path>".htmlentities($wmsPath, ENT_COMPAT)."</path></layer>",
          "application/xml");
        }

        public function assignSLDtoCoverage($sldName='',$workspaceName='',$dataStoreName='',$layerName='') {
          $r = $this->runApi('layers/'.htmlentities($workspaceName, ENT_COMPAT).':'.
          htmlentities($layerName, ENT_COMPAT).'.xml','PUT',
          "<layer><defaultStyle><name>".htmlentities($sldName, ENT_COMPAT)."</name></defaultStyle></layer>",
          "application/xml");

          // curl -v -u admin:geoserver -XPUT -H "Content-type: text/xml"
          // -d "<layer><defaultStyle><name>$SLD_NAME</name></defaultStyle></layer>"
          // http://geoserver.io/geoserver/rest/layers/$WORKSPACE:$TITLE

          return $r;
        }

  }

  $gs = new GSWrapper($gsurl, $username, $password);
  // $workspaces =  $gs->listAllWorkspaces();
  // echo '<pre>';print_r($workspaces);echo '</pre>';
  // Get list of datastores in getsat
  // $datastores =  $gs->listCoverageStores('getsat');
  // echo '<pre>';print_r($datastores);echo '</pre>';

  $status = $gs->createCoverageStore('getsat','rain_week','20170214',
  'file:///Users/bruce/Downloads/GIS_data/rain/week/20170214.tif');
  echo '<pre>';print_r($status);echo '</pre>';
  if (!$status->success) {
    echo 'ERROR: '.$r->message;die;
  }


  $r = $gs->assignSLDtoCoverage('raster_rain2','getsat','rain_week','20170214');
  echo '<pre>';print_r($status);echo '</pre>';
//  die;

  $r = $gs->setLayerWmsPath('getsat','20170214','bruce/rain/week');
  echo '<pre>';print_r($status);echo '</pre>';
  die;


  // $workspaceName = 'test3';
  // $e = $gs->workspaceExists($workspaceName);
  // echo 'Does '.$workspaceName.' exist: <pre>'.($e?'YES':'NO').'<br>';
  //
  // echo 'Going to try to create workspace :'.$workspaceName.'<br>';
  // $r = $gs->createWorkspace($workspaceName);
  // echo '<pre>';print_r($r);echo '</pre>';
