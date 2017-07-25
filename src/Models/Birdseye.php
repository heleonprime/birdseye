<?php

namespace Heleonprime\Birdseye\Models;

use Illuminate\Database\Eloquent\Model;
use GuzzleHttp\Client as HttpClient;

class Birdseye extends Model
{
    
    public $data = null;
    
    public function __construct($latitude = null, $longitude = null)
    {
        if(is_numeric($latitude) && is_numeric($longitude))
        {
            $this->latitude = $latitude;
            $this->longitude = $longitude;
            $reqUrl = config('birdseye.url') . $longitude . ',' . $latitude ;
            try {
                $this->data = json_decode((new HttpClient)->get($reqUrl, ['query' =>
                    [
                        'o' => 'json',
                        'key'    => config('birdseye.api_key'), 
                    ],
                    'headers'=> [
                        'Content-Type' => 'undefined'
                    ],
                ])->getBody()->getContents(), true);
            }
            catch (\Exception $ex) {
                $this->data = null;
            }
        }
    }

    public function getImage()
    {
        if ($this->data) {
            $scope = [];
            //try {
                $data = $this->data;
                $scope['error']  = '';
                $dataNeed = isset($data['resourceSets'][0]['resources'][0]['__type']) && strpos($data['resourceSets'][0]['resources'][0]['__type'], 'BirdseyeMetadata') !== false;
                dump($dataNeed);
                if (!$dataNeed) {
                    $scope['error'] = " Sorry NO XML";
                }

                $meta = $data['resourceSets'][0]['resources'][0];

                $tileWidth = intval($meta['tilesX']);
                $tileHeight = intval($meta['tilesY']);
                $left = 0;
                $top = 0;
                $src = [];
                $count = $tileWidth * $tileHeight;

                $ImagesUrl = $meta['imageUrl'];
                $ImagesUrl = str_replace("http", "https", $ImagesUrl);
                $ImagesUrl = str_replace("{subdomain}", "t0", $ImagesUrl);
                $ImagesUrl = str_replace("{zoom}", "20", $ImagesUrl);

                for($i = 0; $i < $count; $i++){
                    $ImgSrcNow = str_replace("{tileId}", $i, $ImagesUrl);
                    $src[] = $ImgSrcNow;
                }

                for($i = 0; $i < count($src); $i++){
                    $img = new Image();
                    if ($i % $tileWidth === 0 && $i !== 0) {
                        $top += $tileHeight;
                        $left = 0;
                    }
                }
                
                //return self::parseHttpJson($json);
            /*} catch (\Exception $ex) {
                //Log::error('Mapquest Error: ' . $ex->getMessage());
                dd('jopa');
                //return self::parseHttpError($ex);
            }*/
        }
    }
}
/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

