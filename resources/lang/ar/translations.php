<?php

declare(strict_types=1);

return [
    // UI Messages
    'drop_files' => 'اسحب الملفات هنا أو انقر للرفع',
    'file_too_big' => 'الملف كبير جداً (:filesize ميجابايت). الحد الأقصى للحجم: :maxFilesize ميجابايت.',
    'invalid_file_type' => 'لا يمكنك رفع ملفات من هذا النوع.',
    'server_responded' => 'الخادم استجاب بالرمز :statusCode.',
    'max_files_exceeded' => 'لا يمكنك رفع المزيد من الملفات.',
    'accepted_files' => 'الملفات المقبولة',
    'max_file_size' => 'الحد الأقصى لحجم الملف',
    'min_file_size' => 'الحد الأدنى لحجم الملف',
    'max_files' => 'الحد الأقصى للملفات',
    'validation' => 'التحقق',
    'uploading_files' => 'جاري رفع الملفات...',
    'all_files_uploaded' => 'تم رفع جميع الملفات بنجاح',
    'retry_failed_uploads' => 'إعادة محاولة الرفع الفاشل',
    'upload_success' => 'تم رفع الملف بنجاح',
    'chunk_upload_failed' => 'فشل في رفع الجزء :chunk. يرجى المحاولة مرة أخرى.',
    'validation_failed_after_upload' => 'فشل التحقق من الملف بعد الرفع',
    'chunk_merge_failed' => 'فشل في دمج الأجزاء المرفوعة. يرجى رفع الملف مرة أخرى.',
    'chunk_uploaded' => 'تم رفع الجزء :chunk من :total بنجاح',
    'invalid_file_path' => 'مسار الملف غير صحيح. حذف الملف غير مسموح.',
    'no_permission_delete' => 'ليس لديك صلاحية لحذف هذا الملف.',
    'file_not_found_or_deleted' => 'الملف غير موجود أو تم حذفه بالفعل.',
    'file_deleted' => 'تم حذف الملف بنجاح',
    'file_delete_failed' => 'فشل في حذف الملف',
    'delete_error' => 'حدث خطأ أثناء حذف الملف. يرجى المحاولة مرة أخرى.',
    'invalid_file_path_requested' => 'مسار الملف المطلوب غير صحيح.',
    'file_not_found_or_expired' => 'الملف غير موجود أو انتهت صلاحيته.',
    'not_available_production' => 'غير متاح في الإنتاج',
    'file_too_large_single' => 'حجم الملف (:size ميجابايت) يتجاوز حد الرفع الواحد :max ميجابايت. يرجى استخدام ميزة الرفع بالأجزاء للملفات الكبيرة.',
    'no_file_selected' => 'لم يتم تحديد أي ملف للرفع. يرجى اختيار ملف.',
    'unknown' => 'غير معروف',
    'exceeds_server_max' => 'الملف يتجاوز الحجم الأقصى المسموح به في الخادم',
    'exceeds_form_max' => 'الملف يتجاوز الحجم الأقصى المسموح به لهذا النموذج',
    'partial_upload' => 'تم رفع الملف جزئياً فقط. يرجى المحاولة مرة أخرى',
    'no_file_provided' => 'لم يتم تقديم أي ملف للرفع',
    'no_tmp_folder' => 'خطأ في إعدادات الخادم: مجلد مؤقت مفقود',
    'cannot_write_disk' => 'خطأ في إعدادات الخادم: لا يمكن كتابة الملف على القرص',
    'upload_stopped_extension' => 'تم إيقاف الرفع بواسطة امتداد PHP',
    'unknown_upload_error' => 'فشل رفع الملف بخطأ غير معروف',
    'file_too_large_limit' => 'حجم الملف (:size ميجابايت) يتجاوز الحجم الأقصى المسموح به :max ميجابايت',
    'help_file_too_large' => 'جرب رفع ملف أصغر أو استخدم الرفع بالأجزاء للملفات الكبيرة.',
    'help_validation_error' => 'يرجى التحقق من نوع الملف والحجم والمتطلبات الأخرى.',
    'help_rate_limit' => 'يرجى الانتظار قليلاً قبل المحاولة مرة أخرى.',
    'help_server_error' => 'يرجى المحاولة لاحقاً أو الاتصال بالدعم إذا استمرت المشكلة.',
    'upload_error' => 'حدث خطأ أثناء رفع الملف. يرجى المحاولة مرة أخرى.',
    'disk_space_error' => 'مساحة القرص غير كافية. يرجى المحاولة لاحقاً.',
    'memory_error' => 'الملف كبير جداً للمعالجة. جرب رفع ملف أصغر.',
    'timeout_error' => 'انتهت مهلة الرفع. يرجى المحاولة مرة أخرى أو رفع ملف أصغر.',
    'permission_error' => 'خطأ في صلاحيات الخادم. يرجى الاتصال بالدعم.',
    'security_check_failed' => 'فشل فحص الأمان. قد يحتوي الملف على محتوى ضار أو غير مسموح به.',
    'no_files_selected' => 'لم يتم تحديد أي ملفات للرفع. يرجى اختيار الملفات.',
    'too_many_files' => 'تم تحديد ملفات كثيرة جداً. الحد الأقصى المسموح: :max ملف.',
    'bulk_upload_completed' => 'تم إكمال الرفع المجمع بنجاح',
    'invalid_file' => 'تم تقديم ملف غير صحيح',
    'directory_deleted' => 'تم حذف المجلد بنجاح',
    'directory_delete_failed' => 'فشل في حذف المجلد',
    'directory_not_found' => 'المجلد غير موجود',
    'invalid_directory' => 'مسار المجلد غير صحيح',
    'security_validation_failed' => 'فشل التحقق الأمني',
    'rate_limit_exceeded' => 'تم تجاوز حد المعدل. يرجى المحاولة لاحقاً.',
    'invalid_content_type' => 'نوع المحتوى غير صحيح',
    'invalid_headers' => 'رؤوس الطلب غير صحيحة',
    'suspicious_activity_detected' => 'تم اكتشاف نشاط مشبوه',

    // Error Messages
    'errors' => [
        'runtime' => 'خطأ في وقت التشغيل: :message',
        'validation_failed' => 'فشل التحقق',
        'file_not_found' => 'الملف غير موجود',
        'file_type_not_allowed' => 'نوع الملف غير مسموح',
        'invalid_file_type_detected' => 'تم اكتشاف نوع ملف غير صحيح',
        'file_validation_failed' => 'فشل التحقق من الملف',
        'file_signature_validation_failed' => 'فشل التحقق من توقيع الملف',
        'file_content_validation_failed' => 'فشل التحقق من محتوى الملف',
        'file_security_validation_failed' => 'فشل الملف في التحقق الأمني',
        'file_content_mismatch' => 'محتوى الملف لا يطابق النوع المتوقع',
        'file_size_exceeds_limit' => 'حجم الملف يتجاوز الحد الأقصى المسموح به',
        'some_files_failed_to_process' => 'فشل في معالجة بعض الملفات',
        'memory_limit_exceeded' => 'تم تجاوز حد الذاكرة في السياق: :context',
        'chunk_file_error' => 'لا يمكن فتح ملف الجزء: :file',
        'output_file_error' => 'لا يمكن فتح ملف الإخراج للكتابة',
        'chunk_streaming_error' => 'لا يمكن فتح الملفات لتدفق الأجزاء',
        'invalid_file_path' => 'مسار الملف غير صحيح',
    ],

];
