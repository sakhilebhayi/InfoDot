<?php

/*
|--------------------------------------------------------------------------
| Attachments on questions, solutions, answers and comments (R-15)
|--------------------------------------------------------------------------
| What may be uploaded and how much. Types are decided by sniffing the file's content (fileinfo), never by its name,
| and the extension must agree with the sniffed type. SVG is deliberately absent (scriptable). Video has no server-side
| processing on this host: only formats browsers play natively are accepted, and the PHP limits on the host
| (upload_max_filesize / post_max_size) must be at least the video cap for it to work.
*/

return [

    'kinds' => [
        'image' => [
            'max_bytes' => (int) env('ATTACHMENTS_IMAGE_MAX_MB', 10) * 1024 * 1024,
            'mimes' => [
                'image/jpeg' => ['jpg', 'jpeg'],
                'image/png' => ['png'],
                'image/webp' => ['webp'],
                'image/gif' => ['gif'],
            ],
        ],
        'pdf' => [
            'max_bytes' => (int) env('ATTACHMENTS_PDF_MAX_MB', 20) * 1024 * 1024,
            'mimes' => [
                'application/pdf' => ['pdf'],
            ],
        ],
        'video' => [
            'max_bytes' => (int) env('ATTACHMENTS_VIDEO_MAX_MB', 100) * 1024 * 1024,
            'mimes' => [
                'video/mp4' => ['mp4', 'm4v'],
                // Some libmagic builds call an MP4 container this; it is stored and served as video/mp4.
                'application/mp4' => ['mp4', 'm4v'],
                'video/webm' => ['webm'],
            ],
        ],
    ],

    // Attachments per question / solution / step / answer / comment.
    'max_per_item' => (int) env('ATTACHMENTS_MAX_PER_ITEM', 10),

    // A person's uploads per day (count and bytes), across everything. Abuse brake, not a product limit.
    'daily' => [
        'files' => (int) env('ATTACHMENTS_DAILY_FILES', 40),
        'bytes' => (int) env('ATTACHMENTS_DAILY_MB', 300) * 1024 * 1024,
    ],

    // How long an uploaded file may sit unbound before attachments:prune removes it.
    'unbound_ttl_hours' => 24,

    // Longest edge of the image thumbnails the lists and lightbox strip use.
    'thumb_max' => 640,

    // Decompression-bomb guard: images with more pixels than this are refused outright.
    'max_image_pixels' => 50_000_000,
];
