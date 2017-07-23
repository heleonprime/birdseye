<?php  namespace Heleonprime\Birdseye\Controllers;

use Illuminate\Routing\Controller;
use GuzzleHttp\Client as HttpClient;

class BirdseyeController extends Controller
{

    public static function  getImage($latitude = null, $longitude = null)
    {
        if ($latitude && $longitude) {
            $scope = [];
            $scope['error'] = '';

            $reqUrl = config('birdseye.url') . $longitude . ',' . $latitude ;

            try {
                $json = (new HttpClient)->get($reqUrl, ['query' =>
                    [
                        'o' => 'json',
                        'key'    => config('birdseye.api_key'), 
                    ],
                ])->getBody();
                
                $scope['error']  = '';
                $dataNeed = isset($data['data']['Response']['ResourceSets']['ResourceSet']['Resources']['BirdseyeMetadata']);

                if (!$dataNeed) {
                    $scope['error'] = " Sorry NO XML";
                }


                $tileWidth = intval($dataNeed.TilesX);
                $tileHeight = intval($dataNeed.TilesY);
                $left = 0;
                $top = 0;
                $src = [];
                $count = $tileWidth * $tileHeight;

                $ImagesUrl = $dataNeed . ImageUrl;
                $ImagesUrl = str_replace("http", "https", $ImagesUrl);
                $ImagesUrl = str_replace("{subdomain}", "t0", $ImagesUrl);
                $ImagesUrl = str_replace("{zoom}", "20", $ImagesUrl);

                for($i = 0; $i < $count; $i++){
                    $ImgSrcNow = $ImagesUrl.replace("{tileId}", i);
                    $src[] = $ImgSrcNow;
                }

                for($i = 0; $i < count($src); $i++){
                    $img = new Image();
                    if ($i % $tileWidth === 0 && $i !== 0) {
                        $top += $tileHeight;
                        $left = 0;
                    }
                }
                
                return self::parseHttpJson($json);
            } catch (\Exception $ex) {
                Log::error('Mapquest Error: ' . $ex->getMessage());
                return self::parseHttpError($ex);
            }
        }
    }
}