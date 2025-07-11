<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Filex Validation Language Lines (Japanese)
    |--------------------------------------------------------------------------
    |
    | These language lines contain the default error messages used by
    | the Filex validation rules. Some of these rules have multiple versions
    | such as the size rules. Feel free to tweak each of these messages here.
    |
    */

    'filex_mimes' => ':attribute は次のファイル形式である必要があります：:values。',
    'filex_mimetypes' => ':attribute ファイルは次の形式である必要があります：:values。',
    'filex_min' => ':attribute は最低 :min キロバイトである必要があります。',
    'filex_max' => ':attribute は :max キロバイト以下である必要があります。',
    'filex_size' => ':attribute は正確に :size キロバイトである必要があります。',
    'filex_dimensions' => ':attribute の画像サイズが無効です。',
    'filex_image' => ':attribute は画像である必要があります。',
    'filex_file' => ':attribute は有効なファイルアップロードである必要があります。',

    // Additional Filex validation rule messages
    'temp_file' => ':attribute は有効な Filex 一時ファイルである必要があります。',
    'file_not_found_or_expired' => ':attribute ファイルが見つからないか、期限切れです。',
    'file_not_found' => ':attribute ファイルが見つかりません。',
    'file_not_readable' => ':attribute ファイルが読み取れません。',
    'must_be_image' => ':attribute は画像である必要があります。',
    'invalid_image_dimensions' => ':attribute の画像サイズが無効です。',
    'min_width' => ':attribute の画像サイズが無効です。最小幅は :value px です。',
    'max_width' => ':attribute の画像サイズが無効です。最大幅は :value px です。',
    'min_height' => ':attribute の画像サイズが無効です。最小高さは :value px です。',
    'max_height' => ':attribute の画像サイズが無効です。最大高さは :value px です。',
    'exact_width' => ':attribute の画像サイズが無効です。幅は正確に :value px である必要があります。',
    'exact_height' => ':attribute の画像サイズが無効です。高さは正確に :value px である必要があります。',
    'aspect_ratio' => ':attribute の画像サイズが無効です。アスペクト比は :value である必要があります。',
    'file_size_exact' => ':attribute は正確に :size である必要があります。',
    'file_too_large' => ':attribute は :max 以下である必要があります。',
    'file_too_small' => ':attribute は最低 :min である必要があります。',
    'invalid_mime_type' => ':attribute は次のファイル形式である必要があります：:values。',
    'invalid_mimetypes' => ':attribute ファイルは次の形式である必要があります：:values。',
    'invalid_upload' => ':attribute は有効なファイルアップロードである必要があります。',

    // Error messages that might be used in validation context
    'file_content_mismatch' => ':attribute ファイルの内容が予期される形式と一致しません。',
    'file_signature_validation_failed' => ':attribute ファイル署名の検証に失敗しました。',
    'file_content_validation_failed' => ':attribute ファイル内容の検証に失敗しました。',
    'file_security_validation_failed' => ':attribute ファイルのセキュリティ検証に失敗しました。',
    'file_validation_failed' => ':attribute ファイルの検証に失敗しました。',
    'invalid_file_path' => ':attribute の無効なファイルパス。ファイルの削除は許可されていません。',

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
