# Router
Router - Fast, flexible routing for PHP, enabling you to quickly and easily build RESTful web applications.<br>
Version : 1.0.0
___
### Installation
You can download it and using it without any changes.

OR use Composer.

It's recommended that you use Composer to install Route.
```
$ composer require eylmzrouter
```
___
### Simple Usage 
```php
require 'vendor/autoload.php';
// or
// require 'src/eylmz/Router.php';

use eylmz/Router;

Router::setControllerNamespace("App\\Controllers\\");
Router::setMiddlewareNamespace("App\\Middlewares\\");

// Routers
Router::any("/url","Controller@Method");
// or
Router::any("/url2",function() {

});
// #Routers

Router::routeNow();
```
___
### Available Router Methods
```php
Router::get($url, $callback);
Router::post($url, $callback);
Router::put($url, $callback);
Router::patch($url, $callback);
Router::delete($url, $callback);
Router::options($url, $callback);
```

#### Usage More Than One Routers
```php
Router::match("GET|POST",$url,$callback);
//or
Router::match(["GET","POST"],$url,$callback);
```
#### Usage The Any Methods
```php
Router::any($url,$callback);
``` 
___
### Route Parameters
#### Required Parameters
```php
Router::get("url\{id}",function($myID){
  echo "Hello " . $myID;
});
```

#### Optional Parameters
```php
Router::get("url\{id?}",function($myID=0){
  echo $myID;
});
```
___
### Controller and Method Parameters
```php 
// Controller -> First Parameter
// Method -> Second Parameter
Router::get("admin\{controller}\{method}","{?}@{?}");

// or

// Custom
Router::get("admin\{method}\{controller}","{controller}@{method}");

```
___
### Regular Expression Constraints
```php
Router::get('url/{id}', function ($myID) {
    
})->where('id', '[0-9]+');

Router::get('user/{id}/{name}', function ($myID, $name) {
    
})->where(['id' => '[0-9]+', 'name' => '[a-zA-Z]+']);
```
___
### Named Routes
```php
Router::get('user/profile', function () {
    //
})->name('profile');
```

#### Generating URLs To Named Routes
```php
$url = Router::route("profile");

// Usage with parameters
Router::get('url/{id}/profile', function ($id) {
    
})->name('profile');

$url = Router::route('profile', ['id' => 1]);
```
___
### Route Groups
#### Prefix URL
```php 
Router::prefix('admin')->group(function () {
    Router::get('users', function () {
        // new url -> /admin/users
    });
});
```

#### Middleware
```php
Router::middleware("middleware")->group(function () {
    Router::get('/', function () {
        
    });

    Router::get('url/profile', function () {
        
    });
});
```

#### Usage More Than One Middlewares
```php
Router::middleware(["middleware","middleware2"])->group(function () {
    Router::get('/', function () {
        
    });

    Router::get('url/profile', function () {
        
    });
});
```
___
### License
The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
