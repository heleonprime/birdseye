<?php

namespace Heleonprime\Birdseye\Models;

use Illuminate\Database\Eloquent\Model;

use GuzzleHttp\Client as HttpClient;
use Intervention\Image\ImageManager;
use GuzzleHttp\Pool;
use GuzzleHttp\Event\CompleteEvent;
use GuzzleHttp\Event\ErrorEvent;
use GuzzleHttp\Subscriber\Retry\RetrySubscriber;

class Birdseye extends Model
{

    protected $data = null;
    protected $image = null;
    protected $meta = null;
    public $debug = null;

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
            $client = new HttpClient([ 'defaults' =>
                [
                    //'debug' => true,
                    'verify' => false
                ]
            ]);
            $requests = [];
            $params = [];
            $left = 0;
            $top = 0;

            $debug = [
                'completed' => [],
                'errors' => [],
                'ended' => 0,
                //'test' => 5,
                'meta' => $this->meta
            ];

            $zoomK = 1; //($this->meta['zoomMin'] === 19) ? 2 : 1;
            for($i = 0; $i < /*48 */$this->meta['count']; $i++)
            {
                if ($i % ($this->meta['tilesX']/*8*/ / $zoomK) === 0 && $i !== 0) {
                    $top ++;
                    $left = 0;
                }
                $ImgSrcNow = str_replace("{tileId}", $i, $ImagesUrl);
                $requests[] = $client->createRequest('GET', $ImgSrcNow);
                $params[$ImgSrcNow] = [ 'top' => $top, 'left' => $left ];
                $left++;
            }
            $haveError = false;

            //dd($params);
            //dd($requests);
            $pool = new Pool($client, $requests, [
                'complete' => function (CompleteEvent $event) use (&$params, &$haveError, $manager, &$debug)
                {
                    if($event->getResponse()->getStatusCode() !== 200)
                    {
                        $haveError = true;
                        $debug['haveError'] = true;
                    }
                    $url = $event->getRequest()->getUrl();
                    $debug['completed'][$url] = $event->getResponse()->getStatusCode();
                    $data = $event->getResponse()->getBody()->getContents();
                    $image = $manager->make($data);
                    //dd($image);
                    $params[$url]['image'] = $image;
                },
                'error' => function (ErrorEvent $event) use (&$debug) {
                    $debug['errors'][$event->getRequest()->getUrl()] = $event->getResponse()->getStatusCode();
                },
                'end' => function() use (&$debug) {
                    $debug['ended']++;
                }
            ]);
            $pool->wait();
            $this->debug = json_encode($debug);
            if($haveError)
            {
                return false;
            }
            $isFirst = true;
            foreach($params as $paramArr)
            {
                if(!empty($paramArr['image']))
                {
                    if($isFirst)
                    {
                        //dd($paramArr);
                        $canvas = $manager->canvas($this->meta['tilesX'] /*8*/ * $paramArr['image']->width() / $zoomK, $this->meta['tilesY'] /*6*/ * $paramArr['image']->height() / $zoomK);
                        $isFirst = false;
                    }
                    $canvas->insert($paramArr['image'], 'top-left', $paramArr['left'] * $paramArr['image']->width()/* 512*/, $paramArr['top'] * $paramArr['image']->height()/*512*/);
                }
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

    public function encode()
    {
        return (string) $this->image->encode('data-url');
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
