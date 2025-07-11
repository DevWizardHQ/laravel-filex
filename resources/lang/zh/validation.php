<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Filex Validation Language Lines (Chinese)
    |--------------------------------------------------------------------------
    |
    | These language lines contain the default error messages used by
    | the Filex validation rules. Some of these rules have multiple versions
    | such as the size rules. Feel free to tweak each of these messages here.
    |
    */

    'filex_mimes' => ':attribute 必须是以下类型的文件：:values。',
    'filex_mimetypes' => ':attribute 文件必须是以下类型：:values。',
    'filex_min' => ':attribute 最小为 :min 千字节。',
    'filex_max' => ':attribute 不能大于 :max 千字节。',
    'filex_size' => ':attribute 必须恰好为 :size 千字节。',
    'filex_dimensions' => ':attribute 图片尺寸无效。',
    'filex_image' => ':attribute 必须是图片。',
    'filex_file' => ':attribute 必须是有效的文件上传。',

    // Additional Filex validation rule messages
    'temp_file' => ':attribute 必须是有效的 Filex 临时文件。',
    'file_not_found_or_expired' => ':attribute 文件未找到或已过期。',
    'file_not_found' => ':attribute 文件未找到。',
    'file_not_readable' => ':attribute 文件不可读。',
    'must_be_image' => ':attribute 必须是图片。',
    'invalid_image_dimensions' => ':attribute 图片尺寸无效。',
    'min_width' => ':attribute 图片尺寸无效。最小宽度为 :value 像素。',
    'max_width' => ':attribute 图片尺寸无效。最大宽度为 :value 像素。',
    'min_height' => ':attribute 图片尺寸无效。最小高度为 :value 像素。',
    'max_height' => ':attribute 图片尺寸无效。最大高度为 :value 像素。',
    'exact_width' => ':attribute 图片尺寸无效。宽度必须恰好为 :value 像素。',
    'exact_height' => ':attribute 图片尺寸无效。高度必须恰好为 :value 像素。',
    'aspect_ratio' => ':attribute 图片尺寸无效。纵横比必须为 :value。',
    'file_size_exact' => ':attribute 必须恰好为 :size。',
    'file_too_large' => ':attribute 不能大于 :max。',
    'file_too_small' => ':attribute 最小为 :min。',
    'invalid_mime_type' => ':attribute 必须是以下类型的文件：:values。',
    'invalid_mimetypes' => ':attribute 文件必须是以下类型：:values。',
    'invalid_upload' => ':attribute 必须是有效的文件上传。',

    // Error messages that might be used in validation context
    'file_content_mismatch' => ':attribute 文件内容与预期类型不匹配。',
    'file_signature_validation_failed' => ':attribute 文件签名验证失败。',
    'file_content_validation_failed' => ':attribute 文件内容验证失败。',
    'file_security_validation_failed' => ':attribute 文件安全验证失败。',
    'file_validation_failed' => ':attribute 文件验证失败。',
    'invalid_file_path' => ':attribute 的文件路径无效。不允许删除文件。',

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
