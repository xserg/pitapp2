<?php
namespace App\Http\Controllers\Api\Project;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\Project\Faq;

class FaqController extends Controller {

    protected $model = "App\Models\Project\Faq";

    protected $activity = 'Faq Management';
    protected $table = 'faqs';

    // Private Method to set the data
    private function setData(&$faq) {
        $validator = Validator::make(
            Request::all(),
            array(
                'question' => 'required|string',
                'answer' => 'required|string'
        ));

        if($validator->fails()) {
            $this->messages = $validator->messages();
            return false;
        }

        // Set all the data here (fill in all the fields)
        $faq->question = Request::input('question');
        $faq->answer = Request::input('answer');


        $faq->save();

        return $faq;
    }

    protected function index() {
        $faqs = Faq::all();

        return response()->json($faqs);
    }

    /**
     * Show Method
     * Retrieve - a specifc item.
     */
    protected function show($id) {
        $faq = Faq::find($id);
        return response()->json($faq->toArray());
    }

    /**
     * Store Method
     */
    protected function store() {
        // Create item and set the data
        $faq = new Faq;
        if(!$this->setData($faq)) {
            return response()->json($this->messages, 500);
        }
        return response()->json($faq->toArray());
        //return response()->json("Create Successful");
    }

    /**
     * Update Method
     */
    protected function update($id) {
        // Retrieve the item and set the data
        $faq = Faq::find($id);
        if(!$this->setData($faq)) {
            return response()->json($this->messages, 500);
        }
        return response()->json($faq->toArray());
        //return response()->json("Update Successful");
    }

    /**
     * Destroy Method
     */
    protected function destroy($id) {
        // Make the deletion
        Faq::destroy($id);

        // return a success
        return response()->json("Delete Successful");
    }
}
