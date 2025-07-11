<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Filex Validation Language Lines (Hindi)
    |--------------------------------------------------------------------------
    |
    | These language lines contain the default error messages used by
    | the Filex validation rules. Some of these rules have multiple versions
    | such as the size rules. Feel free to tweak each of these messages here.
    |
    */

    'filex_mimes' => ':attribute निम्नलिखित प्रकार की फ़ाइल होनी चाहिए: :values।',
    'filex_mimetypes' => ':attribute फ़ाइल निम्नलिखित प्रकार की होनी चाहिए: :values।',
    'filex_min' => ':attribute कम से कम :min किलोबाइट होनी चाहिए।',
    'filex_max' => ':attribute :max किलोबाइट से अधिक नहीं हो सकती।',
    'filex_size' => ':attribute वास्तव में :size किलोबाइट होनी चाहिए।',
    'filex_dimensions' => ':attribute की छवि आयाम अमान्य हैं।',
    'filex_image' => ':attribute एक छवि होनी चाहिए।',
    'filex_file' => ':attribute एक वैध फ़ाइल अपलोड होनी चाहिए।',

    // Additional Filex validation rule messages
    'temp_file' => ':attribute एक वैध Filex अस्थायी फ़ाइल होनी चाहिए।',
    'file_not_found_or_expired' => ':attribute फ़ाइल नहीं मिली या समाप्त हो गई है।',
    'file_not_found' => ':attribute फ़ाइल नहीं मिली।',
    'file_not_readable' => ':attribute फ़ाइल पढ़ने योग्य नहीं है।',
    'must_be_image' => ':attribute एक छवि होनी चाहिए।',
    'invalid_image_dimensions' => ':attribute की छवि आयाम अमान्य हैं।',
    'min_width' => ':attribute की छवि आयाम अमान्य हैं। न्यूनतम चौड़ाई :value px है।',
    'max_width' => ':attribute की छवि आयाम अमान्य हैं। अधिकतम चौड़ाई :value px है।',
    'min_height' => ':attribute की छवि आयाम अमान्य हैं। न्यूनतम ऊंचाई :value px है।',
    'max_height' => ':attribute की छवि आयाम अमान्य हैं। अधिकतम ऊंचाई :value px है।',
    'exact_width' => ':attribute की छवि आयाम अमान्य हैं। चौड़ाई वास्तव में :value px होनी चाहिए।',
    'exact_height' => ':attribute की छवि आयाम अमान्य हैं। ऊंचाई वास्तव में :value px होनी चाहिए।',
    'aspect_ratio' => ':attribute की छवि आयाम अमान्य हैं। पहलू अनुपात :value होना चाहिए।',
    'file_size_exact' => ':attribute वास्तव में :size होनी चाहिए।',
    'file_too_large' => ':attribute :max से अधिक नहीं हो सकती।',
    'file_too_small' => ':attribute कम से कम :min होनी चाहिए।',
    'invalid_mime_type' => ':attribute निम्नलिखित प्रकार की फ़ाइल होनी चाहिए: :values।',
    'invalid_mimetypes' => ':attribute फ़ाइल निम्नलिखित प्रकार की होनी चाहिए: :values।',
    'invalid_upload' => ':attribute एक वैध फ़ाइल अपलोड होनी चाहिए।',

    // Error messages that might be used in validation context
    'file_content_mismatch' => ':attribute फ़ाइल सामग्री अपेक्षित प्रकार से मेल नहीं खाती।',
    'file_signature_validation_failed' => ':attribute फ़ाइल हस्ताक्षर सत्यापन असफल।',
    'file_content_validation_failed' => ':attribute फ़ाइल सामग्री सत्यापन असफल।',
    'file_security_validation_failed' => ':attribute फ़ाइल सुरक्षा सत्यापन असफल।',
    'file_validation_failed' => ':attribute फ़ाइल सत्यापन असफल।',
    'invalid_file_path' => ':attribute के लिए अमान्य फ़ाइल पथ। फ़ाइल हटाने की अनुमति नहीं है।',

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
