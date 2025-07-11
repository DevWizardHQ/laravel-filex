<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Filex Validation Language Lines (French)
    |--------------------------------------------------------------------------
    |
    | These language lines contain the default error messages used by
    | the Filex validation rules. Some of these rules have multiple versions
    | such as the size rules. Feel free to tweak each of these messages here.
    |
    */

    'filex_mimes' => 'Le :attribute doit être un fichier de type : :values.',
    'filex_mimetypes' => 'Le fichier :attribute doit être de type : :values.',
    'filex_min' => 'Le :attribute doit faire au moins :min kilooctets.',
    'filex_max' => 'Le :attribute ne peut pas dépasser :max kilooctets.',
    'filex_size' => 'Le :attribute doit faire exactement :size kilooctets.',
    'filex_dimensions' => 'Le :attribute a des dimensions d\'image invalides.',
    'filex_image' => 'Le :attribute doit être une image.',
    'filex_file' => 'Le :attribute doit être un téléchargement de fichier valide.',

    // Additional Filex validation rule messages
    'temp_file' => 'Le :attribute doit être un fichier temporaire Filex valide.',
    'file_not_found_or_expired' => 'Le fichier :attribute est introuvable ou a expiré.',
    'file_not_found' => 'Le fichier :attribute est introuvable.',
    'file_not_readable' => 'Le fichier :attribute n\'est pas lisible.',
    'must_be_image' => 'Le :attribute doit être une image.',
    'invalid_image_dimensions' => 'Le :attribute a des dimensions d\'image invalides.',
    'min_width' => 'Le :attribute a des dimensions d\'image invalides. Largeur minimale : :value px.',
    'max_width' => 'Le :attribute a des dimensions d\'image invalides. Largeur maximale : :value px.',
    'min_height' => 'Le :attribute a des dimensions d\'image invalides. Hauteur minimale : :value px.',
    'max_height' => 'Le :attribute a des dimensions d\'image invalides. Hauteur maximale : :value px.',
    'exact_width' => 'Le :attribute a des dimensions d\'image invalides. La largeur doit être exactement :value px.',
    'exact_height' => 'Le :attribute a des dimensions d\'image invalides. La hauteur doit être exactement :value px.',
    'aspect_ratio' => 'Le :attribute a des dimensions d\'image invalides. Le ratio d\'aspect doit être :value.',
    'file_size_exact' => 'Le :attribute doit faire exactement :size.',
    'file_too_large' => 'Le :attribute ne peut pas dépasser :max.',
    'file_too_small' => 'Le :attribute doit faire au moins :min.',
    'invalid_mime_type' => 'Le :attribute doit être un fichier de type : :values.',
    'invalid_mimetypes' => 'Le fichier :attribute doit être de type : :values.',
    'invalid_upload' => 'Le :attribute doit être un téléchargement de fichier valide.',

];
