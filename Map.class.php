<?php

class Map_model extends model
{
    private static $instance = null;

    private static $file_name = 'markers.csv';
    private static $file_max_size = 5242880;
    private static $file_root = 'uploads/docs/';
    private static $geocode_url = 'http://geocode-maps.yandex.ru/1.x';


    private function isExistMarkerByMd5Key($md5key)
    {
        $this->db->select();
        $this->db->tables('`map_markers`');
        $this->db->fields();
        $this->db->where('md5_key = "' . $md5key . '"');
        $response = $this->db->execute(true);

        if ($response) {
            return true;
        } else {
            return false;
        }
    }

    private function isExistMarkerByMd5KeyExcludeById($md5key, $id)
    {
        $this->db->select();
        $this->db->tables('`map_markers`');
        $this->db->fields();
        $this->db->where('md5_key = "' . $md5key . '" and id <> "' . $id . '"');
        $response = $this->db->execute(true);

        if ($response) {
            return true;
        } else {
            return false;
        }
    }

    public function findDiff($a,$b)
    {
        if ($a['md5_key']===$b['md5_key'])
        {
            return 0;
        }
        return ($a>$b)?1:-1;
    }

    public function uploadFileMarkers($parameters)
    {
        if (isset($parameters['files']['upload'])) {
            $filename = self::uploadFile($parameters['files']['upload']);

            if (!$filename) {
                return array(
                    'type' => 'error',
                    'code' => 'upload_file_false',
                    'namespace' => 'default',
                    'text' => 'Upload file false'
                );
            }

            $this->db->select();
            $this->db->fields(array('county', 'region', 'city', 'address', 'name', 'description', 'phone', 'md5_key'));
            $this->db->tables('`map_markers`');
            $currentData = $this->db->execute(true);

            $csv = new parsecsv();
            $csv->delimiter = ";";
            $csv->fields = array('id', 'county', 'region', 'city', 'address', 'name', 'description', 'phone');
            $csv->parse($filename);

            $utf8_data = array();

            foreach($csv->data as $k=>$v){
                if(empty($v['county']) || empty($v['region']) || empty($v['city']) || empty($v['address']) || empty($v['name'])) {
                    continue;
                }

                $v['county'] = mb_convert_encoding($v['county'], "utf-8", "windows-1251");
                $v['region'] = mb_convert_encoding($v['region'], "utf-8", "windows-1251");
                $v['city'] = mb_convert_encoding($v['city'], "utf-8", "windows-1251");
                $v['address'] = mb_convert_encoding($v['address'], "utf-8", "windows-1251");
                $v['name'] = mb_convert_encoding($v['name'], "utf-8", "windows-1251");
                $v['description'] = mb_convert_encoding($v['description'], "utf-8", "windows-1251");

                $utf8_data[$k]['county'] = Functions::clearString(trim($v['county']));
                $utf8_data[$k]['region'] = Functions::clearString(trim($v['region']));
                $utf8_data[$k]['city'] = Functions::clearString(trim($v['city']));
                $utf8_data[$k]['address'] = Functions::clearString(trim($v['address']));
                $utf8_data[$k]['name'] = Functions::clearString(trim($v['name']));
                $utf8_data[$k]['description'] = Functions::clearString(trim($v['description']));
                $utf8_data[$k]['phone'] = $v['phone'];
                $utf8_data[$k]['md5_key'] = md5($utf8_data[$k]['county'] . $utf8_data[$k]['region'] . $utf8_data[$k]['city'] . $utf8_data[$k]['address'] . $utf8_data[$k]['name']);
            }

            $result = array_udiff($utf8_data, $currentData, array($this, 'findDiff'));

            if(empty($result)) {
                unlink($filename);

                return array(
                    'type' => 'error',
                    'code' => 'not_new_markers',
                    'namespace' => 'upload_file_markers',
                    'text' => 'Not new markers'
                );
            }

            $insertString = '';
            $active_marker = 0;
            foreach($result as $k=>$v) {
                $insertString .= '(';
                $insertString .= '"' . $v['county'] . '",';
                $insertString .= '"' . $v['region'] . '",';
                $insertString .= '"' . $v['city'] . '",';
                $insertString .= '"' . $v['address'] . '",';
                $insertString .= '"' . $v['name'] . '",';
                $insertString .= '"' . $v['description'] . '",';
                $insertString .= '"' . $v['phone'] . '",';
                $insertString .= '"' . $active_marker . '",';
                $insertString .= '"' . $v['md5_key'] . '"';
                $insertString .= '),';
            }

            $insertString = trim($insertString, ',');

            $this->db->prepare('INSERT INTO `map_markers` (`county`, `region`, `city`, `address`, `name`, `description`, `phone`, `active`, `md5_key`) VALUES ' . $insertString);
            $result = $this->db->execute(true);

            if (!$result) {
                return array(
                    'type' => 'error',
                    'code' => 'query_error',
                    'namespace' => 'default',
                    'text' => 'Insert fails to execute'
                );
            }

            return array(
                'type' => 'success',
                'code' => 'upload_file_true',
                'namespace' => 'default',
                'text' => 'Upload file true'
            );
        }
    }

    public function getMarkers($order_name = 'address', $order_mode = 'asc', $active = null, $limit = null, $offset = null)
    {
        $this->db->select();
        $this->db->tables('`map_markers`');
        $this->db->fields();

        if (isset($active)) {
            $this->db->where('active = "' . $active . '"');
        }

        if (isset($order_name) && isset($order_mode)) {
            $this->db->order($order_name, $order_mode);
        }

        if (isset($limit) && isset($offset)) {
            $this->db->limit($offset, $limit);

            $response['limit'] = $limit;
            $response['offset'] = $offset;
        }

        $response['content'] = $this->db->execute(true);
        $response['count'] = count($response['content']);

        return $response;
    }

    public function getMarkerItem($id)
    {
        $this->db->select();
        $this->db->tables('`map_markers`');
        $this->db->fields();

        $this->db->where('id = "' . $id . '"');

        $response = $this->db->execute(true);

        if ($response) {
            return $response[0];
        } else {
            return false;
        }
    }

    public function addMarkerAdmin($parameters)
    {
        if (       empty($parameters['name'])
            || empty($parameters['region'])
            || empty($parameters['county'])
            || empty($parameters['city'])
            || empty($parameters['address'])
        ) {
            return array(
                'type' => 'error',
                'code' => 'require',
                'namespace' => 'default',
                'text' => 'Not valid parameters'
            );
        }

        if(empty($parameters['latlng'])) {
            $parameters['is_exist_latlng'] = 0;
        } else {
            $parameters['is_exist_latlng'] = 1;
        }

        if (isset($parameters['active']) && $parameters['active'] == 'on') {
            $parameters['active'] = 1;
        } else {
            $parameters['active'] = 0;
        }

        $parameters['md5_key'] = md5($parameters['county'] . $parameters['region'] . $parameters['city'] . $parameters['address'] . $parameters['name']);

        if($this->isExistMarkerByMd5Key($parameters['md5_key'])) {
            return array(
                'type' => 'error',
                'code' => 'marker_already_exist',
                'namespace' => 'edit_marker',
                'text' => 'Marker already exist'
            );
        }

        $parameters['id'] = $this->getAutoincrement('map_markers');
        $this->db->insert();
        $this->db->tables('`map_markers`');
        $this->db->fields($parameters);
        $result = $this->db->execute(true);

        if (!$result) {
            return array(
                'type' => 'error',
                'code' => 'query_error',
                'namespace' => 'default',
                'text' => 'Insert fails to execute'
            );
        }

        $this->saveToJson();

        Functions::dropAllCache();

        return array(
            'type' => 'success',
            'parameters' => $parameters,
            'code' => 'add_marker_true',
            'namespace' => 'add_marker',
            'text' => 'Marker was added'
        );
    }

    public function editMarkerAdmin($parameters)
    {
        if (       empty($parameters['name'])
            || empty($parameters['region'])
            || empty($parameters['county'])
            || empty($parameters['city'])
            || empty($parameters['address'])
        ) {
            return array(
                'type' => 'error',
                'code' => 'require',
                'namespace' => 'default',
                'text' => 'Not valid parameters'
            );
        }

        if(empty($parameters['latlng'])) {
            $parameters['is_exist_latlng'] = 0;
        } else {
            $parameters['is_exist_latlng'] = 1;
        }

        if (isset($parameters['active']) && $parameters['active'] == 'on') {
            $parameters['active'] = 1;
        } else {
            $parameters['active'] = 0;
        }

        $parameters['md5_key'] = md5($parameters['county'] . $parameters['region'] . $parameters['city'] . $parameters['address'] . $parameters['name']);

        if($this->isExistMarkerByMd5KeyExcludeById($parameters['md5_key'], $parameters['id'])) {
            return array(
                'type' => 'error',
                'code' => 'marker_already_exist',
                'namespace' => 'edit_marker',
                'text' => 'Marker already exist'
            );
        }

        $this->db->update();
        $this->db->tables('`map_markers`');
        $this->db->fields($parameters);
        $this->db->where('id = "' . $parameters['id'] . '"');
        $result = $this->db->execute(true);

        if (!$result) {
            return array(
                'type' => 'error',
                'code' => 'query_error',
                'namespace' => 'default',
                'text' => 'Update fails to execute'
            );
        }

        $this->saveToJson();

        Functions::dropAllCache();

        return array(
            'type' => 'success',
            'parameters' => $parameters,
            'code' => 'edit_marker_true',
            'namespace' => 'edit_marker',
            'text' => 'Marker was edited'
        );
    }

    public function getMarkersForYandexMap()
    {
        $this->db->select();
        $this->db->tables('`map_markers`');
        $this->db->fields(array(
            'id',
            'region',
            'county',
            'city',
            'city_latlng',
            'address',
            'latlng',
            'name',
            'description',
            'phone'
        ));

        $this->db->where('is_exist_latlng = 1 and active = 1');
        $this->db->order('address');
        $data = $this->db->execute(true);

        return $data;
    }

    public function getCountMarkersByCity()
    {
        $this->db->select();
        $this->db->tables('`map_markers`');
        $this->db->fields(array(
            'count(*) as quantity',
            'id',
            'region',
            'county',
            'city',
            'address',
            'latlng',
            'name',
            'description',
            'phone'
        ));

        $this->db->where('is_exist_latlng = 1 and active = 1');
        $this->db->order('city');
        $this->db->group(array('`region`', '`city`'));
        $data = $this->db->execute(true);

        return $data;
    }

    public function getCountMarkersByUniqCity()
    {
        $this->db->select();
        $this->db->tables('`map_markers`');
        $this->db->fields(array(
            'count(DISTINCT `city`) as quantity'
        ));

        $this->db->where('is_exist_latlng = 1 and active = 1');
        $data = $this->db->execute(true);

        if (!$data) {
            return false;
        }

        return $data[0]['quantity'];
    }

    public static function uploadFile($file)
    {
        if($file['size'] > self::$file_max_size) {
            return false;
        }

        $allowedTypes = array('application/vnd.ms-excel','text/plain','text/csv','text/tsv');

        if (!in_array((string)strtolower($file['type']), $allowedTypes)) {
            return false;
        }

        $uploadfile = self::$file_root . date("Y-m-d_H-i-s") . '-' . self::$file_name ;

        if (move_uploaded_file($file['tmp_name'], $uploadfile)) {
            return $uploadfile;
        }

        return false;
    }

    public function getCoord()
    {
        $this->db->select();
        $this->db->tables('`map_markers`');
        $this->db->fields();
        $this->db->where('is_exist_latlng = 0 or is_exist_latlng = -1');
        $data = $this->db->execute(true);

        if (!$data) {
            return false;
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $count_errors = 0;

        foreach($data as $k=>$v) {
            $params = array();
            curl_setopt($ch, CURLOPT_URL, self::$geocode_url . '/?format=json&kind=house' . '&geocode=' . urlencode('Россия, ' . $v['region'] . ', ' . $v['city'] . ', ' . $v['address']));

            $content = curl_exec($ch);

            if (curl_error($ch)) {
                break;
            }

            $json = json_decode($content);

            if (   $json->response->GeoObjectCollection->metaDataProperty->GeocoderResponseMetaData->found != 0
                && $json->response->GeoObjectCollection->featureMember[0]->GeoObject->metaDataProperty->GeocoderMetaData->precision == 'exact'
            )
            {
                $coords = $json->response->GeoObjectCollection->featureMember[0]->GeoObject->Point->pos;
                list($lng, $lat) = explode(' ', $coords);
                $coords = $lat . ', ' . $lng;
                $params['latlng'] = $coords;
                $params['is_exist_latlng'] = 1;
                $params['active'] = 1;
            } else {
                $params['is_exist_latlng'] = -1;
                $params['active'] = 0;
                $count_errors++;
            }

            if($v['city'] == 'г. Москва') {
                curl_setopt($ch, CURLOPT_URL, self::$geocode_url . '/?format=json&kind=locality' . '&geocode=' . urlencode('Россия, ' . ', ' . $v['city']));
            } else {
                curl_setopt($ch, CURLOPT_URL, self::$geocode_url . '/?format=json&kind=locality' . '&geocode=' . urlencode('Россия, ' . $v['region'] . ', ' . $v['city']));
            }

            $content = curl_exec($ch);

            if (curl_error($ch)) {
                break;
            }

            $json = json_decode($content);

            if ($json->response->GeoObjectCollection->metaDataProperty->GeocoderResponseMetaData->found != 0)
            {
                $coords = $json->response->GeoObjectCollection->featureMember[0]->GeoObject->Point->pos;
                list($lng, $lat) = explode(' ', $coords);
                $coords = $lat . ', ' . $lng;
                $params['city_latlng'] = $coords;
            }

            $this->db->update();
            $this->db->tables('`map_markers`');
            $this->db->fields($params);
            $this->db->where('id = "' . $v['id'] . '"');
            $this->db->execute(true);
        }

        $this->saveToJson();

        Functions::dropAllCache();

        return array(
            'type' => 'success',
            'code' => 'get_coord_true',
            'namespace' => 'get_coord',
            'parameters' => array(
                'not_found' => $count_errors
            ),
            'text' => 'Get coords finish'
        );
    }

    public function changeActiveMarkersAdmin($id, $active)
    {
        $needleCheck = array(
            'id' => true,
            'active' => true
        );

        if (!$this->dataHandler->isValidParams(array('id' => $id, 'active' => $active), $needleCheck)) {
            $response = $this->dataHandler->getLastDataHandlerError();
            return $response;
        }

        if ($active == 'on') {
            $active = 1;
        } elseif ($active == 'off') {
            $active = 0;
        }

        $this->db->update();
        $this->db->tables('`map_markers`');
        $this->db->fields(array('active' => $active));
        $this->db->where('id = "' . $id . '"');
        $result = $this->db->execute(true);

        $this->saveToJson();

        Functions::dropAllCache();

        if (!$result) {
            return array(
                'type' => 'error',
                'code' => 'query_error',
                'namespace' => 'default',
                'text' => 'Update fails to execute'
            );
        }

        return array(
            'type' => 'success',
            'parameters' =>
                array(
                    'id' => $id,
                    'active' => $active
                ),
            'namespace' => 'default',
            'code' => 'change_active_true',
            'text' => 'Marker status was changed'
        );
    }

    public function deleteMarkersAdmin($id)
    {
        $needleCheck = array(
            'id' => true
        );

        if (!$this->dataHandler->isValidParams(array('id' => $id), $needleCheck)) {
            $response = $this->dataHandler->getLastDataHandlerError();
            return $response;
        }

        $this->db->delete();
        $this->db->tables('`map_markers`');
        $this->db->where('id = "' . $id . '"');
        $result = $this->db->execute(true);

        if (!$result) {
            return array(
                'type' => 'error',
                'code' => 'query_error',
                'namespace' => 'default',
                'text' => 'Delete fails to execute'
            );
        }

        $this->saveToJson();

        Functions::dropAllCache();

        return array(
            'type' => 'success',
            'code' => 'delete_marker_true',
            'parameters' => array('id' => $id),
            'namespace' => 'delete_marker',
            'text' => 'Marker was deleted'
        );
    }

    public function getMapCSV()
    {
        $dir_list = scandir(self::$file_root);
        $files = array();
        foreach ($dir_list as $key => $value) {
            if(is_file(self::$file_root . $value) && end(explode('.', self::$file_root . $value)) == 'csv') {
                $files[$key]['path'] = self::$file_root . $value;
                $files[$key]['name'] = basename(self::$file_root . $value);
            }
        }

        return $files;
    }

    public function saveToJson(){
        $markers = $this->getMarkersForYandexMap();
        $json = array();
        $json['type'] = 'FeatureCollection';

        foreach($markers as $k=>$item) {
            $json['features'][$k]['type'] = 'Feature';
            $json['features'][$k]['id'] = $item['id'];
            $json['features'][$k]['geometry']['type'] = 'Point';
            $item['latlng'] = str_replace(' ', '', $item['latlng']);
            list($lng, $lat) = explode(',', $item['latlng']);
            $json['features'][$k]['geometry']['coordinates'] = array($lng, $lat);
            $json['features'][$k]['properties']['id'] = $item['id'];
            $json['features'][$k]['properties']['name'] = Functions::decodeParameters($item['name']);
            $json['features'][$k]['properties']['description'] = Functions::decodeParameters($item['description']);
            $json['features'][$k]['properties']['region'] = $item['region'];
            $json['features'][$k]['properties']['county'] = $item['county'];
            $json['features'][$k]['properties']['city'] = $item['city'];
            $item['city_latlng'] = str_replace(' ', '', $item['city_latlng']);
            list($city_lng, $city_lat) = explode(',', $item['city_latlng']);
            $json['features'][$k]['properties']['city_coordinates'] = array($city_lng, $city_lat);
            $json['features'][$k]['properties']['address'] = $item['address'];
            $json['features'][$k]['properties']['phone'] = $item['phone'];
        }

        file_put_contents('json/data.json', json_encode($json));
    }
}