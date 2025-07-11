<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Filex Validation Language Lines (Russian)
    |--------------------------------------------------------------------------
    |
    | These language lines contain the default error messages used by
    | the Filex validation rules. Some of these rules have multiple versions
    | such as the size rules. Feel free to tweak each of these messages here.
    |
    */

    'filex_mimes' => 'Поле :attribute должно быть файлом типа: :values.',
    'filex_mimetypes' => 'Файл :attribute должен быть типа: :values.',
    'filex_min' => 'Поле :attribute должно быть не менее :min килобайт.',
    'filex_max' => 'Поле :attribute не может быть больше :max килобайт.',
    'filex_size' => 'Поле :attribute должно быть точно :size килобайт.',
    'filex_dimensions' => 'Поле :attribute имеет неверные размеры изображения.',
    'filex_image' => 'Поле :attribute должно быть изображением.',
    'filex_file' => 'Поле :attribute должно быть правильно загруженным файлом.',

    // Additional Filex validation rule messages
    'temp_file' => 'Поле :attribute должно быть правильным временным файлом Filex.',
    'file_not_found_or_expired' => 'Файл :attribute не найден или истек срок его действия.',
    'file_not_found' => 'Файл :attribute не найден.',
    'file_not_readable' => 'Файл :attribute не читается.',
    'must_be_image' => 'Поле :attribute должно быть изображением.',
    'invalid_image_dimensions' => 'Поле :attribute имеет неверные размеры изображения.',
    'min_width' => 'Поле :attribute имеет неверные размеры изображения. Минимальная ширина :value px.',
    'max_width' => 'Поле :attribute имеет неверные размеры изображения. Максимальная ширина :value px.',
    'min_height' => 'Поле :attribute имеет неверные размеры изображения. Минимальная высота :value px.',
    'max_height' => 'Поле :attribute имеет неверные размеры изображения. Максимальная высота :value px.',
    'exact_width' => 'Поле :attribute имеет неверные размеры изображения. Ширина должна быть точно :value px.',
    'exact_height' => 'Поле :attribute имеет неверные размеры изображения. Высота должна быть точно :value px.',
    'aspect_ratio' => 'Поле :attribute имеет неверные размеры изображения. Соотношение сторон должно быть :value.',
    'file_size_exact' => 'Поле :attribute должно быть точно :size.',
    'file_too_large' => 'Поле :attribute не может быть больше :max.',
    'file_too_small' => 'Поле :attribute должно быть не менее :min.',
    'invalid_mime_type' => 'Поле :attribute должно быть файлом типа: :values.',
    'invalid_mimetypes' => 'Файл :attribute должен быть типа: :values.',
    'invalid_upload' => 'Поле :attribute должно быть правильно загруженным файлом.',

    // Error messages that might be used in validation context
    'file_content_mismatch' => 'Содержимое файла :attribute не соответствует ожидаемому типу.',
    'file_signature_validation_failed' => 'Проверка подписи файла :attribute не удалась.',
    'file_content_validation_failed' => 'Проверка содержимого файла :attribute не удалась.',
    'file_security_validation_failed' => 'Файл :attribute не прошел проверку безопасности.',
    'file_validation_failed' => 'Проверка файла :attribute не удалась.',
    'invalid_file_path' => 'Неверный путь к файлу для :attribute. Удаление файла не разрешено.',

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
