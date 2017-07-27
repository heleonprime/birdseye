# BirdsEye Imagine view for Laravel 5

## Requirements

- PHP 5.6.x or higher
- intervention/image v2.3
- guzzlehttp/guzzle v5.*
- BirdsEye API key

## Usage

### Step 1: Install Through Composer

```
composer require heleonprime/birdseye:dev-master
```

### Step 2: Include BirdsEye model class

```php
use Heleonprime\Birdseye\Models\Birdseye;
```

### Step 3: Create class instance and get image

```php
$birdseye = (new Birdseye($latitude, $longitude))->getImage();
```

Also you can resize image and return it's response

```php
$birdseye->resizeToWidth($resizeWidth);

return $birdseye->response();
```
