<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Filex Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | These language lines contain the default error messages used by
    | the Filex validation rules. Some of these rules have multiple versions
    | such as the size rules. Feel free to tweak each of these messages here.
    |
    */

    'filex_mimes' => 'The :attribute must be a file of type: :values.',
    'filex_mimetypes' => 'The :attribute file must be of type: :values.',
    'filex_min' => 'The :attribute must be at least :min kilobytes.',
    'filex_max' => 'The :attribute may not be greater than :max kilobytes.',
    'filex_size' => 'The :attribute must be exactly :size kilobytes.',
    'filex_dimensions' => 'The :attribute has invalid image dimensions.',
    'filex_image' => 'The :attribute must be an image.',
    'filex_file' => 'The :attribute must be a valid file upload.',

    // Additional Filex validation rule messages
    'temp_file' => 'The :attribute must be a valid Filex temp file.',
    'file_not_found_or_expired' => 'The :attribute file not found or expired.',
    'file_not_found' => 'The :attribute file not found.',
    'file_not_readable' => 'The :attribute file is not readable.',
    'must_be_image' => 'The :attribute must be an image.',
    'invalid_image_dimensions' => 'The :attribute has invalid image dimensions.',
    'min_width' => 'The :attribute has invalid image dimensions. Minimum width is :value px.',
    'max_width' => 'The :attribute has invalid image dimensions. Maximum width is :value px.',
    'min_height' => 'The :attribute has invalid image dimensions. Minimum height is :value px.',
    'max_height' => 'The :attribute has invalid image dimensions. Maximum height is :value px.',
    'exact_width' => 'The :attribute has invalid image dimensions. Width must be exactly :value px.',
    'exact_height' => 'The :attribute has invalid image dimensions. Height must be exactly :value px.',
    'aspect_ratio' => 'The :attribute has invalid image dimensions. Aspect ratio must be :value.',
    'file_size_exact' => 'The :attribute must be exactly :size.',
    'file_too_large' => 'The :attribute may not be greater than :max.',
    'file_too_small' => 'The :attribute must be at least :min.',
    'invalid_mime_type' => 'The :attribute must be a file of type: :values.',
    'invalid_mimetypes' => 'The :attribute file must be of type: :values.',
    'invalid_upload' => 'The :attribute must be a valid file upload.',

    // Error messages that might be used in validation context
    'file_content_mismatch' => 'The :attribute file content does not match expected type.',
    'file_signature_validation_failed' => 'The :attribute file signature validation failed.',
    'file_content_validation_failed' => 'The :attribute file content validation failed.',
    'file_security_validation_failed' => 'The :attribute file failed security validation.',
    'file_validation_failed' => 'The :attribute file validation failed.',
    'invalid_file_path' => 'Invalid file path for :attribute. File deletion not allowed.',

    /*
    |--------------------------------------------------------------------------
    | Custom Attribute Names
    |--------------------------------------------------------------------------
    |
    | These language lines are used to swap our attribute placeholder
    | with something more reader friendly such as "E-Mail Address" instead
    | of "email". This simply helps us make our message more expressive.
    |
    */

    'attributes' => [],

];
