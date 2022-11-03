<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| This file is where you may define all of the routes that are handled
| by your application. Just tell Laravel the URIs it should respond
| to using a Closure or controller method. Build something great!
|
*/

// http://preit.test/api/images/uploads/projects/2114/logo.png
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

Route::any('/storage/{any}', function ($path) {
    $file = storage_path('app/public/'.$path);
    //\Illuminate\Support\Facades\File::files(storage_path('app/public/'))
//    echo "Fetching file $file";
//    echo Storage::exists($file) ? 'true' : 'false';
//    $files = collect(File::files(File::dirname($file)));
//    echo $files->toJson();
    return File::get($file);
})->where('any', '.*');


Route::any('/deploymentKey', '\App\Http\Controllers\Api\DeploymentKeyController');

Route::any('api/password/email', "\App\Http\Controllers\Api\PasswordController@postEmail");
Route::post('api/password/email', "\App\Http\Controllers\Api\PasswordController@postEmail");
Route::post('api/password/reset', "\App\Http\Controllers\Api\PasswordController@postReset");

//Route::group(['prefix' => 'password'], function() {
//    Route::any('/{any}', '\App\Http\Controllers\Web\PasswordRedirectController')->where('any', '.*');
//});

// Proxy paths for client side scraping
Route::get('/azure-instance-pricing/{azurePath}', '\App\Http\Controllers\Web\PricingProxyController@getAzurePricingPage')->where('azurePath', '.*');

Route::any('/{any}', '\App\Http\Controllers\Web\PrecisionController')->where('any', '[^.]*');

Route::any('/api/core/{any}', '\App\Http\Controllers\Web\StaticController')->where('any', '.*');
Route::any('/api//core/{any}', '\App\Http\Controllers\Web\StaticController')->where('any', '.*');

