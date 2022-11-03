<?php

return [
    // Skip checking if the project is stale in the analysis process
    'skip_stale_project_check' => false,

    'pdf' => [
        // Set the output type for the PDF. If you set this to "I" in .env, it will output directly to the browser vs being a download
        'output' => env('ANALYSIS_PDF_OUTPUT', 'D')
    ]
];
