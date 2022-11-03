<?php

use App\Console\Commands\DestroyCache;
use App\Http\Controllers\Api\Admin\CsvImportController;
use App\Http\Controllers\Api\Hardware\AmazonServerController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::get('health', function () {
    // TODO: Do actual health check
    return ['status' => 'healthy'];
});

Route::group(array('prefix' => 'images'), function() {
    Route::get('uploads/projects/{id}/{path}', '\App\Http\Controllers\Api\Project\LogoUploadController@download');
    Route::get('company/uploads/{id}/{path}', '\App\Http\Controllers\Api\Project\CompanyController@download');
});

Route::get('/user', function (Request $request) {
    return Auth::user()->user;
})->middleware('auth:api');

//Project Routes
Route::group(array('prefix' => 'admin', 'middleware' => 'auth'), function() {
    Route::group(['middleware' => 'auth.project'], function() {
        Route::get('pdfAnalysis/{id}', '\App\Http\Controllers\Api\Project\AnalysisController@pdfAnalysis');
        Route::get('wordAnalysis/{id}', '\App\Http\Controllers\Api\Project\AnalysisController@wordAnalysis');
        Route::get('reportAnalysis/{id}', '\App\Http\Controllers\Api\Project\AnalysisController@analysis');
        Route::get('spreadsheetAnalysis/{id}', '\App\Http\Controllers\Api\Project\AnalysisController@spreadsheetAnalysis');
        Route::get('consolidationAnalysisSpreadsheet/{id}', '\App\Http\Controllers\Api\Project\AnalysisController@consolidationAnalysisSpreadsheet');
        Route::post('userproject/{id}/clone', '\App\Http\Controllers\Api\Project\UserProjectController@cloneProject');

        Route::resource('project', '\App\Http\Controllers\Api\Project\ProjectController', ['only' => ['destroy', 'update', 'show']]);
        Route::put('project/{id}/currency/{code}', '\App\Http\Controllers\Api\Project\ProjectController@updateCurrency');
        Route::post('project/{id}/computeOptimalTarget', '\App\Http\Controllers\Api\OptimalTarget\ComputeOptimalTarget@computeOptimalTarget');
    });

    Route::group(['middleware' => 'auth.user'], function() {
        Route::get('projectGraphs/{id}', '\App\Http\Controllers\Api\Project\UserProjectController@projectGraphs');
    });

    Route::resource('project', '\App\Http\Controllers\Api\Project\ProjectController', ['only' => ['store', 'index']]);
    Route::post('environment/project/', '\App\Http\Controllers\Api\Project\EnvironmentController@store');

    Route::group(['middleware' => 'auth.environment'], function() {
        Route::get('environment/{id}/project/{projectId}', '\App\Http\Controllers\Api\Project\EnvironmentController@show');
        Route::put('environment/{id}/project/', '\App\Http\Controllers\Api\Project\EnvironmentController@update');
        Route::resource('environment', '\App\Http\Controllers\Api\Project\EnvironmentController', ['only' => ['destroy', 'update', 'show']]);
        Route::get('environment/{id}/constraints', '\App\Http\Controllers\Api\Project\EnvironmentController@getEnvironmentConstraints');
        Route::post('environment/{id}/cacheTargetAnalysis', '\App\Http\Controllers\Api\Project\EnvironmentController@cacheTargetAnalysis');
        Route::resource('project.environment', '\App\Http\Controllers\Api\Project\ProjectEnvironmentController', ['only' => ['destroy', 'update', 'show']]);
    });

    Route::resource('environment', '\App\Http\Controllers\Api\Project\EnvironmentController', ['only' => ['index', 'store']]);
    Route::resource('project.environment', '\App\Http\Controllers\Api\Project\ProjectEnvironmentController', ['only' => ['index', 'store']]);

    Route::post('pricing/azure', '\App\Http\Controllers\Api\Admin\PricingController@azure');
    Route::post('pricing/azure-sql', '\App\Http\Controllers\Api\Admin\PricingController@azureSql');
    Route::post('pricing/google', '\App\Http\Controllers\Api\Admin\PricingController@google');
    Route::post('pricing/ibmpvs', '\App\Http\Controllers\Api\Admin\PricingController@ibmpvs');
    Route::get("pricing/aws/raw", "\App\Http\Controllers\Api\Admin\PricingController@aws_raw");
    Route::get("pricing/aws/ec2_trimmed", "\App\Http\Controllers\Api\Admin\PricingController@aws_ec2_trimmed");
    Route::get("pricing/aws/rds_trimmed", "\App\Http\Controllers\Api\Admin\PricingController@aws_rds_trimmed");
    Route::get("pricing/aws/load", "\App\Http\Controllers\Api\Admin\PricingController@aws_load");
    Route::get("pricing/aws/test", "\App\Http\Controllers\Api\Admin\PricingController@aws_test");
    Route::get('revenue-report', '\App\Http\Controllers\Api\Admin\RevenueReportController');
    Route::get('revenue-filters', '\App\Http\Controllers\Api\Admin\RevenueFilterController');


    Route::post('import-cpm', '\App\Http\Controllers\Api\Admin\CpmImportController');
    Route::post('imports/optimal-target', [CsvImportController::class, 'uploadOptimalTarget']);

    Route::resource('environmentType', '\App\Http\Controllers\Api\Project\EnvironmentTypeController');

    Route::resource('provider', '\App\Http\Controllers\Api\Project\ProviderController');
    Route::resource('region', '\App\Http\Controllers\Api\Project\RegionController');
    Route::resource('currency', '\App\Http\Controllers\Api\Project\CurrencyController');
    Route::resource('faq', '\App\Http\Controllers\Api\Project\FaqController');
    Route::post('company/send-account-email/{id}', '\App\Http\Controllers\Api\Project\CompanyController@sendAccountEmail');
    Route::resource('company', '\App\Http\Controllers\Api\Project\CompanyController');

    Route::get('customers/{id}', '\App\Http\Controllers\Api\Project\UserProjectController@customers');
    Route::resource('userproject', '\App\Http\Controllers\Api\Project\UserProjectController');
    Route::get('projectDashboard', '\App\Http\Controllers\Api\Project\UserProjectController@dashboardInfo');
    Route::get('dashboardStats', '\App\Http\Controllers\Api\Project\UserProjectController@dashboardStats');
    Route::get('userCompanies', '\App\Http\Controllers\Api\Project\UserProjectController@userCompanies');

    Route::get('userProfiles', '\App\Http\Controllers\Api\Project\UserProfileController@getUserProfiles');
    Route::get('userProfiles/{id}', '\App\Http\Controllers\Api\Project\UserProfileController@getUserProfile');
    Route::get('user/{id}/emails', '\App\Http\Controllers\Api\Project\UserProfileController@getSavedEmails');

    Route::get('environment/{existingId}/targetAnalysis/{targetId}/',
        '\App\Http\Controllers\Api\Project\TargetAnalysisController@analysis');

    Route::get('email', '\App\Http\Controllers\Api\Project\EmailController@sendSupportEmail');
    Route::post('emailReport', '\App\Http\Controllers\Api\Project\EmailController@sendReportEmail');
    Route::post('dataRequirement', '\App\Http\Controllers\Api\Project\EmailController@sendRequirementEmail');

    Route::get('alive', '\App\Http\Controllers\Api\Project\KeepAliveController@index');
    Route::get('acceptEula/{user}', '\App\Http\Controllers\Api\Project\EulaController@acceptEula');


    Route::post('logoUpload', '\App\Http\Controllers\Api\Project\LogoUploadController@upload');
    Route::get('getSoftwareVariables/', '\App\Http\Controllers\Api\Project\EnvironmentVariableController@getSoftwareVariables');
    Route::get('getHardwareVariables/', '\App\Http\Controllers\Api\Project\EnvironmentVariableController@getHardwareVariables');
    Route::get('getEnvVariables/', '\App\Http\Controllers\Api\Project\EnvironmentVariableController@getEnvVariables');
    Route::get('getAllVariables/', '\App\Http\Controllers\Api\Project\EnvironmentVariableController@getAllVariables');

    // Currency controllers
    Route::get('currencies', '\App\Http\Controllers\Api\CurrenciesController@index');
    Route::get('currency-rates', '\App\Http\Controllers\Api\CurrenciesController@rates');
});

Route::get('faq', '\App\Http\Controllers\Api\Project\FaqController@index');

Route::group(array('prefix' => 'admin'), function() {
    Route::get('eula/{user}', '\App\Http\Controllers\Api\Project\EulaController@hasAccepted');

});
Route::post('updatePassword', '\App\Http\Controllers\Api\Project\UserProfileController@updatePassword');
Route::post('resource/company/{id}/upload',     '\App\Http\Controllers\Api\Project\CompanyController@upload');
Route::group(array('prefix' => 'resource', 'middleware' => 'auth'), function() {
    Route::resource('precisionUser', '\App\Http\Controllers\Api\Project\PrecisionUserController');
    Route::resource('user', '\App\Http\Controllers\Api\Project\PrecisionUserController');
    Route::resource('precisionProfile', '\App\Http\Controllers\Api\Project\PrecisionProfileController');
});

Route::post('/auth/login', '\App\Http\Controllers\Api\Project\PrecisionAuthController@authenticate');

//Hardware Routes
Route::group(array('prefix' => 'admin', 'middleware' => 'auth'), function() {
    Route::get('software-types', [AmazonServerController::class, 'softwareType']);
    Route::resource('processor', '\App\Http\Controllers\Api\Hardware\ProcessorController');
    Route::resource('manufacturer', '\App\Http\Controllers\Api\Hardware\ManufacturerController');
    Route::resource('server', '\App\Http\Controllers\Api\Hardware\ServerController');
    Route::resource('server-config-processor', '\App\Http\Controllers\Api\Hardware\ServerConfigurationProcessorController');
    Route::get('server-config/{serverConfigId}/environment/{id}', '\App\Http\Controllers\Api\Hardware\ServerConfigurationController@showByEnvironment');
    Route::post('server-config/{serverConfigId}/environment/', '\App\Http\Controllers\Api\Hardware\ServerConfigurationController@destroy');
    Route::put('server-config/{serverConfigId}/environment/', '\App\Http\Controllers\Api\Hardware\ServerConfigurationController@update');
    Route::resource('server-config', '\App\Http\Controllers\Api\Hardware\ServerConfigurationController');
    Route::resource('server-processor', '\App\Http\Controllers\Api\Hardware\ServerProcessorController');
    Route::resource('rpm-lookup', '\App\Http\Controllers\Api\Hardware\RpmLookupController');
    Route::get('user/{id}/serverConfigs', '\App\Http\Controllers\Api\Hardware\ServerConfigurationController@getUserConfigs');
    Route::resource('interconnectchassis', '\App\Http\Controllers\Api\Hardware\InterconnectChassisController');
    Route::get('hardware/interconnect-chassis', '\App\Http\Controllers\Api\Hardware\InterconnectChassisController@index');
    Route::delete('hardware/chassis/{id}', '\App\Http\Controllers\Api\Hardware\InterconnectChassisController@destroy');
    Route::delete('hardware/interconnect/{id}', '\App\Http\Controllers\Api\Hardware\InterconnectChassisController@destroy');
    Route::put('hardware/chassis/{id}', '\App\Http\Controllers\Api\Hardware\InterconnectChassisController@update');
    Route::put('hardware/interconnect/{id}', '\App\Http\Controllers\Api\Hardware\InterconnectChassisController@update');
    Route::post('hardware/chassis', '\App\Http\Controllers\Api\Hardware\InterconnectChassisController@store');
    Route::post('hardware/interconnect', '\App\Http\Controllers\Api\Hardware\InterconnectChassisController@store');
    Route::get('hardware/{type}/model', '\App\Http\Controllers\Api\Hardware\InterconnectChassisModelController@index');
});

//Software Routes
Route::group(array('prefix' => 'admin', 'middleware' => 'auth'), function() {
    Route::resource('software', '\App\Http\Controllers\Api\Software\SoftwareController');
    Route::resource('feature', '\App\Http\Controllers\Api\Software\FeatureController');
    Route::resource('softwareModifier', '\App\Http\Controllers\Api\Software\SoftwareModifierController');
    Route::get('user/{id}/software', '\App\Http\Controllers\Api\Software\SoftwareController@getUserSoftware');
    Route::get('software-cost/{id}/environment/{environmentID}', '\App\Http\Controllers\Api\Software\SoftwareCostController@showByEnvironment');
    Route::put('software-cost/{id}/environment/', '\App\Http\Controllers\Api\Software\SoftwareCostController@update');
    Route::post('software-cost/environment/', '\App\Http\Controllers\Api\Software\SoftwareCostController@store');
    Route::resource('software-cost', '\App\Http\Controllers\Api\Software\SoftwareCostController');
    Route::resource('software-type', '\App\Http\Controllers\Api\Software\SoftwareTypeController');
});

//Language
Route::group(array('prefix' => 'resource', 'middleware' => 'auth'), function() {
    Route::resource('languageKey', '\App\Http\Controllers\Api\Language\LanguageKeyController');
});

// No auth required here
Route::get("language/translations/{language}.json", "\App\Http\Controllers\Api\Language\TranslationsController");

Route::group(array('prefix' => 'resource'), function() {
    //We don't use show, leave it out so we can make language/publish
    Route::resource('language', '\App\Http\Controllers\Api\Language\LanguageController', ['except' => ['show']]);

    Route::get('language/publish', [
        'as' => 'language.publish', 'uses' => '\App\Http\Controllers\Api\Language\LanguageController@publish'
    ]);

    Route::get('language/{id}/keys', [
        'as' => 'language.keys', 'uses' => '\App\Http\Controllers\Api\Language\LanguageController@getKeys'
    ]);

    Route::get('language/{id}/keysWithEnglish', [
        'as' => 'language.keys', 'uses' => '\App\Http\Controllers\Api\Language\LanguageController@getKeysWithEnglish'
    ]);
});


//StandardModule

Route::group(array('prefix' => 'resource', 'middleware' => 'auth'), function() {

    Route::resource('activity',         '\App\Http\Controllers\Api\StandardModule\ActivityController');

    Route::resource('component',        '\App\Http\Controllers\Api\StandardModule\ComponentController');

    Route::get('activityLog/filter',      '\App\Http\Controllers\Api\StandardModule\ActivityLogController@get');
    Route::resource('activityLog',      '\App\Http\Controllers\Api\StandardModule\ActivityLogController');
    Route::resource('activityLogType',  '\App\Http\Controllers\Api\StandardModule\ActivityLogTypeController');

    // Defined inline, clears the activtiyLog's cache
    Route::delete('activityLog/cache/clear', ['as' => 'resource.activityLog.cache', 'uses' => function() {
        // Delete the activity Log file if it exists
        $path = public_path() . "/cache/activity_logs";

        // Loop through the directories and remove the files.
        foreach(scandir($path) as $dir) {
            if($dir !== "." && $dir !== "..") {
                Bus::dispatch(new DestroyCache($path . '/'. $dir));
            }
        }

        // Remove the base directory
        rmdir($path);

        // Return Success
        return response()->json('success');
    }]);

    // Set up a global route to delete cached files
    Route::delete('cache/{path}', ['as' => 'cache.clear', 'uses' => function($path) {
        $dir = public_path() . "/cache/" . $path;
        if(is_dir($dir)) {
            foreach(scandir($dir) as $file) {
                if($file !== "." && $file !== "..") {
                    Bus::dispatch(new DestroyCache($dir . "/" . $file));
                }
            }

            return response()->json("Cache files successfully deleted.", 200);
        }

        return response()->json("Invalid path", 500);
    }]);

});

//Configuration
Route::group(array('prefix' => 'configuration', 'middleware' => 'auth'), function() {

    // resource sets up some routes for you automatically.
    // This GET setting/{id} expects a component id and not a setting id
    Route::resource('setting', '\App\Http\Controllers\Api\SettingController');

    // Update by accepting multiple setting objects as an array
    Route::post('bulkUpdate', '\App\Http\Controllers\Api\SettingController@bulkUpdate');

    // get Settings List Data with matching component_id
    Route::get('setting/component/{component_id}', '\App\Http\Controllers\Api\SettingController@getSettingListData');

    // get Settings with matching activity_id
    Route::get('setting/activity/{activity_id}', '\App\Http\Controllers\Api\SettingController@getSettingData');
});

//UserManagement
Route::group(array('prefix' => 'resource', 'middleware' => 'auth'), function() {
    Route::resource('profile',          '\App\Http\Controllers\Api\ProfileController');

    Route::resource('activity.user',    '\App\Http\Controllers\Api\ActivityUserController');
    Route::resource('activity.group',   '\App\Http\Controllers\Api\ActivityGroupController');

    Route::resource('user',             '\App\Http\Controllers\Api\UserController');
    Route::resource('user.group',       '\App\Http\Controllers\Api\UserGroupController');
    Route::resource('user.activity',    '\App\Http\Controllers\Api\UserActivityController');

    Route::resource('group',            '\App\Http\Controllers\Api\GroupController');
    Route::resource('group.user',       '\App\Http\Controllers\Api\GroupUserController');
    Route::resource('group.activity',   '\App\Http\Controllers\Api\GroupActivityController');

    Route::get('user/{id}/profiles', [
        'as' => 'user.profiles', 'uses' => '\App\Http\Controllers\Api\UserController@getProfiles'
    ]);
});

Route::post('resource/user/upload',     '\App\Http\Controllers\Api\UserController@upload');
Route::post('resource/user/{id}/activities',     '\App\Http\Controllers\Api\UserActivityController@checkActivity');
Route::get('resource/user/{id}/activities',     '\App\Http\Controllers\Api\UserActivityController@userActivities');

Route::group(array('middleware' => 'auth'), function() {
    Route::get('/user/activities/all/{id}', function($id) {
        return App\Http\Controllers\Api\UserActivityController::allActivities($id);
    });

    Route::get('/user/active', '\App\Http\Controllers\Api\UserController@authuser');

});
Route::get('/user/passwordComplexityRules', '\App\Http\Controllers\Api\ProfileController@passwordComplexityRules');

// Log in routes
//Route::post('/auth/login', '\App\Http\Controllers\Api\AuthController@authenticate');
Route::post('/userComplete', '\App\Http\Controllers\Api\Project\UserProfileController@userComplete');
Route::post('auth/check',  '\App\Http\Controllers\Api\AuthController@check');
Route::get('/auth/logout', '\App\Http\Controllers\Api\AuthController@logout');
Route::any('/auth/logout', array('before' => 'auth', function() {
    Auth::logout();
}));

Route::group(array('prefix'=>'api', 'middleware'=>'smartauth'), function() {
    Route::post('resource/customer/create',
        ['as'=>'resource.customer.create',
            'uses'=>'\App\Http\Controllers\Api\UserController@customerCreateUser']);
    Route::post('resource/customer/login',
        ['as'=>'resource.customer.login',
            'uses'=>'\App\Http\Controllers\Api\AuthController@authenticate']);
    Route::post('resource/customer/logout',
        ['as'=>'resource.customer.logout',
            'uses'=>'\App\Http\Controllers\Api\AuthController@customerLogout']);
});

//Route::controllers([
//    'auth' => '\App\Http\Controllers\Api\AuthController',
//    'password' => '\App\Http\Controllers\Api\PasswordController',
//]);

//Route::post('/password/email', "\App\Http\Controllers\Api\PasswordController@postEmail");
//Route::post('/password/reset', "\App\Http\Controllers\Api\PasswordController@postReset");

Route::group(['prefix' => 'cache', 'middelware' => 'auth'], function() {
    Route::any('/{any}', '\App\Http\Controllers\Api\CacheController')->where('any', '.*\.json');
});


Route::get(
    '/test-log',
    function () {
        var_dump(config('logging.default'));
        $foo = \Illuminate\Support\Facades\Log::info("Test Cloud Watch");
        dd($foo);
    }
);
