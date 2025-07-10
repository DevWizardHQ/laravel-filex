<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Filex Validation Language Lines (Bengali)
    |--------------------------------------------------------------------------
    |
    | These language lines contain the default error messages used by
    | the Filex validation rules. Some of these rules have multiple versions
    | such as the size rules. Feel free to tweak each of these messages here.
    |
    */

    'filex_mimes' => ':attribute অবশ্যই এই ধরনের ফাইল হতে হবে: :values।',
    'filex_mimetypes' => ':attribute ফাইল অবশ্যই এই ধরনের হতে হবে: :values।',
    'filex_min' => ':attribute অবশ্যই কমপক্ষে :min কিলোবাইট হতে হবে।',
    'filex_max' => ':attribute :max কিলোবাইটের বেশি হতে পারবে না।',
    'filex_size' => ':attribute অবশ্যই ঠিক :size কিলোবাইট হতে হবে।',
    'filex_dimensions' => ':attribute এর ভুল ছবির মাত্রা রয়েছে।',
    'filex_image' => ':attribute অবশ্যই একটি ছবি হতে হবে।',
    'filex_file' => ':attribute অবশ্যই একটি বৈধ ফাইল আপলোড হতে হবে।',

    // Additional Filex validation rule messages
    'temp_file' => ':attribute অবশ্যই একটি বৈধ Filex অস্থায়ী ফাইল হতে হবে।',
    'file_not_found_or_expired' => ':attribute ফাইল পাওয়া যায়নি বা মেয়াদ শেষ।',
    'file_not_found' => ':attribute ফাইল পাওয়া যায়নি।',
    'file_not_readable' => ':attribute ফাইলটি পড়া যায় না।',
    'must_be_image' => ':attribute অবশ্যই একটি ছবি হতে হবে।',
    'invalid_image_dimensions' => ':attribute এর ভুল ছবির মাত্রা রয়েছে।',
    'min_width' => ':attribute এর ভুল ছবির মাত্রা রয়েছে। ন্যূনতম প্রস্থ :value px।',
    'max_width' => ':attribute এর ভুল ছবির মাত্রা রয়েছে। সর্বোচ্চ প্রস্থ :value px।',
    'min_height' => ':attribute এর ভুল ছবির মাত্রা রয়েছে। ন্যূনতম উচ্চতা :value px।',
    'max_height' => ':attribute এর ভুল ছবির মাত্রা রয়েছে। সর্বোচ্চ উচ্চতা :value px।',
    'exact_width' => ':attribute এর ভুল ছবির মাত্রা রয়েছে। প্রস্থ অবশ্যই ঠিক :value px হতে হবে।',
    'exact_height' => ':attribute এর ভুল ছবির মাত্রা রয়েছে। উচ্চতা অবশ্যই ঠিক :value px হতে হবে।',
    'aspect_ratio' => ':attribute এর ভুল ছবির মাত্রা রয়েছে। অনুপাত অবশ্যই :value হতে হবে।',
    'file_size_exact' => ':attribute অবশ্যই ঠিক :size হতে হবে।',
    'file_too_large' => ':attribute :max এর বেশি হতে পারবে না।',
    'file_too_small' => ':attribute অবশ্যই কমপক্ষে :min হতে হবে।',
    'invalid_mime_type' => ':attribute অবশ্যই এই ধরনের ফাইল হতে হবে: :values।',
    'invalid_mimetypes' => ':attribute ফাইল অবশ্যই এই ধরনের হতে হবে: :values।',
    'invalid_upload' => ':attribute অবশ্যই একটি বৈধ ফাইল আপলোড হতে হবে।',

    // Error messages that might be used in validation context
    'file_content_mismatch' => ':attribute ফাইলের বিষয়বস্তু প্রত্যাশিত ধরনের সাথে মেলে না।',
    'file_signature_validation_failed' => ':attribute ফাইলের স্বাক্ষর যাচাইকরণ ব্যর্থ হয়েছে।',
    'file_content_validation_failed' => ':attribute ফাইলের বিষয়বস্তু যাচাইকরণ ব্যর্থ হয়েছে।',
    'file_security_validation_failed' => ':attribute ফাইল নিরাপত্তা যাচাইকরণে ব্যর্থ হয়েছে।',
    'file_validation_failed' => ':attribute ফাইল যাচাইকরণ ব্যর্থ হয়েছে।',
    'invalid_file_path' => ':attribute এর জন্য ভুল ফাইল পথ। ফাইল মুছে ফেলার অনুমতি নেই।',

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
