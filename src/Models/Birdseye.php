<?php

namespace Heleonprime\Birdseye\Models;

use Illuminate\Database\Eloquent\Model;

use GuzzleHttp\Client as HttpClient;
use Intervention\Image\ImageManager;
use GuzzleHttp\Pool;
use GuzzleHttp\Event\CompleteEvent;

class Birdseye extends Model
{
    
    protected $data = null;
    protected $image = null;
    protected $meta = null;   
    
    public function __construct($latitude = null, $longitude = null)
    {
        if(!is_numeric($latitude) || !is_numeric($longitude))
        {
            return false;
        }
        $reqUrl = config('birdseye.url') . $latitude . ',' . $longitude ;
        try {
            $this->data = json_decode((new HttpClient)->get($reqUrl, [
                'query' => [ 'o' => 'json', 'key' => config('birdseye.api_key') ],
                'headers'=> [ 'Content-Type' => 'undefined' ],
            ])->getBody()->getContents(), true);
            //dd($this->getMeta());
            if($this->getMeta())
            {
                $this->setAttributes();
            }
        }
        catch (Exception $ex) {
            return $ex;
        }
        parent::__construct();
    }
    
    public function getImage()
    {
        if (!($meta = $this->hasMeta())) {
            return false;
        }
        else
        {
            $ImagesUrl = $this->getUrlFromMeta();
            $manager = new ImageManager(array('driver' => 'imagick'));
            $client = new HttpClient;
            $requests = [];
            $params = [];
            $left = 0;
            $top = 0;
            
            $zoomK = 1; //($this->meta['zoomMin'] === 19) ? 2 : 1;
            for($i = 0; $i < $this->meta['count']; $i++)
            {
                if ($i % ($this->meta['tilesX'] / $zoomK) === 0 && $i !== 0) {
                    $top ++;
                    $left = 0;
                }
                $ImgSrcNow = str_replace("{tileId}", $i, $ImagesUrl);
                $requests[] = $client->createRequest('GET', $ImgSrcNow);
                $params[$ImgSrcNow] = [ 'top' => $top, 'left' => $left ];
                $left++;
            }
            $pool = new Pool($client, $requests, [
                'complete' => function (CompleteEvent $event) use (&$params, &$canvas, $manager) 
                    {
                        $url = $event->getRequest()->getUrl();
                        $data = $event->getResponse()->getBody()->getContents();
                        $image = $manager->make($data);
                        $params[$url]['image'] = $image;
                    }
            ]);
            $pool->wait();
            
            $isFirst = true;
            
            foreach($params as $paramArr)
            {
                if($isFirst)
                {
                    $canvas = $manager->canvas($this->meta['tilesX'] * $paramArr['image']->width() / $zoomK, $this->meta['tilesY'] * $paramArr['image']->height() / $zoomK);
                    $isFirst = false;
                }
                $canvas->insert($paramArr['image'], 'top-left', $paramArr['left'] * $paramArr['image']->width(), $paramArr['top'] * $paramArr['image']->height());
            }
            $canvas = $canvas->crop($canvas->width()/2,$canvas->height()/2, $canvas->width()/4, $canvas->height()/4);
            $this->image = $canvas;
            return $canvas;
        }
        return false;
    }
    
    public function resizeToWidth(int $width)
    {
        return $this->image->resize($width, null, function ($constraint) {
            $constraint->aspectRatio();
        });
    }
    
    public function response()
    {
        return $this->image->response();
    }
    
    protected function getUrlFromMeta()
    {
        return str_replace([
                "http", 
                "{subdomain}", 
                "{zoom}"
            ], 
            [
                "https", 
                "t0", 
                20 // $this->meta['zoomMin']
            ], $this->meta['imageUrl']);
    }
            
    protected function getMeta()
    {
        if($this->hasMeta())
        {
            $this->meta = $this->data['resourceSets'][0]['resources'][0];
            return $this->meta;
        }
        return false;
    }
    
    protected function hasMeta()
    {
        return isset($this->data['resourceSets'][0]['resources'][0]['__type']) && 
                strpos($this->data['resourceSets'][0]['resources'][0]['__type'], 'BirdseyeMetadata') !== false;
    }
    
    protected function setAttributes() {
        $this->meta['tilesX'] = intval($this->meta['tilesX']);
        $this->meta['tilesY'] = intval($this->meta['tilesY']);
        $this->meta['imageHeight'] = intval($this->meta['imageHeight']);
        $this->meta['imageWidth'] = intval($this->meta['imageWidth']);
        $this->meta['count'] = $this->meta['tilesX'] * $this->meta['tilesY'];
    }
    
}

