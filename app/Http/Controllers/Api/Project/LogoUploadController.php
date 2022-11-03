<?php

namespace App\Http\Controllers\Api\Project;

use App\Http\Controllers\Controller;
use App\Services\Filesystems;
use Illuminate\Http\Request;
use App\Models\Project\Project;

class LogoUploadController extends Controller
{

    public function download($id, $path) {
        return Filesystems::imagesFilesystem()->download("images/uploads/projects/{$id}/{$path}");
    }

    protected function upload(Request $request)
    {
        $file = $request->file('file');
        $projectId = $request->input('projectId');
        $imagePath = 'images/uploads/projects/' . $projectId;

        //Set project logo field
        $project = Project::find($projectId);

        //Remove the old image
        if ($project->logo && Filesystems::imagesFilesystem()->exists($project->logo)) {
            Filesystems::imagesFilesystem()->delete($project->logo);
        }

        $project->logo = null;
        if ($file) {
            $fileName = 'logo.' . $file->getClientOriginalExtension();
            Filesystems::imagesFilesystem()->putFileAs($imagePath, $file, $fileName);
            Filesystems::imagesFilesystem()->setVisibility($imagePath,'public');
            $project->logo = "$imagePath/$fileName";
        }
        $project->save();

        return response()->json('Uploaded');
    }
}
