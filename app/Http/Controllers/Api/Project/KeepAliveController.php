<?php
namespace App\Http\Controllers\Api\Project;

use App\Http\Controllers\Controller;

/*
    Used with ng-idle service to keep session Alive
    @link https://github.com/HackedByChinese/ng-idle.git
*/
class KeepAliveController extends Controller {
  protected function index() {
      return response()->json("Alive");
  }
}
