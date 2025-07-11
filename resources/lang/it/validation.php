<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Filex Validation Language Lines (Italian)
    |--------------------------------------------------------------------------
    |
    | These language lines contain the default error messages used by
    | the Filex validation rules. Some of these rules have multiple versions
    | such as the size rules. Feel free to tweak each of these messages here.
    |
    */

    'filex_mimes' => 'Il campo :attribute deve essere un file di tipo: :values.',
    'filex_mimetypes' => 'Il file :attribute deve essere di tipo: :values.',
    'filex_min' => 'Il campo :attribute deve essere almeno :min kilobyte.',
    'filex_max' => 'Il campo :attribute non può essere maggiore di :max kilobyte.',
    'filex_size' => 'Il campo :attribute deve essere esattamente :size kilobyte.',
    'filex_dimensions' => 'Il campo :attribute ha dimensioni immagine non valide.',
    'filex_image' => 'Il campo :attribute deve essere un\'immagine.',
    'filex_file' => 'Il campo :attribute deve essere un caricamento file valido.',

    // Additional Filex validation rule messages
    'temp_file' => 'Il campo :attribute deve essere un file temporaneo Filex valido.',
    'file_not_found_or_expired' => 'Il file :attribute non è stato trovato o è scaduto.',
    'file_not_found' => 'Il file :attribute non è stato trovato.',
    'file_not_readable' => 'Il file :attribute non è leggibile.',
    'must_be_image' => 'Il campo :attribute deve essere un\'immagine.',
    'invalid_image_dimensions' => 'Il campo :attribute ha dimensioni immagine non valide.',
    'min_width' => 'Il campo :attribute ha dimensioni immagine non valide. La larghezza minima è :value px.',
    'max_width' => 'Il campo :attribute ha dimensioni immagine non valide. La larghezza massima è :value px.',
    'min_height' => 'Il campo :attribute ha dimensioni immagine non valide. L\'altezza minima è :value px.',
    'max_height' => 'Il campo :attribute ha dimensioni immagine non valide. L\'altezza massima è :value px.',
    'exact_width' => 'Il campo :attribute ha dimensioni immagine non valide. La larghezza deve essere esattamente :value px.',
    'exact_height' => 'Il campo :attribute ha dimensioni immagine non valide. L\'altezza deve essere esattamente :value px.',
    'aspect_ratio' => 'Il campo :attribute ha dimensioni immagine non valide. Il rapporto di aspetto deve essere :value.',
    'file_size_exact' => 'Il campo :attribute deve essere esattamente :size.',
    'file_too_large' => 'Il campo :attribute non può essere maggiore di :max.',
    'file_too_small' => 'Il campo :attribute deve essere almeno :min.',
    'invalid_mime_type' => 'Il campo :attribute deve essere un file di tipo: :values.',
    'invalid_mimetypes' => 'Il file :attribute deve essere di tipo: :values.',
    'invalid_upload' => 'Il campo :attribute deve essere un caricamento file valido.',

    // Error messages that might be used in validation context
    'file_content_mismatch' => 'Il contenuto del file :attribute non corrisponde al tipo previsto.',
    'file_signature_validation_failed' => 'La convalida della firma del file :attribute è fallita.',
    'file_content_validation_failed' => 'La convalida del contenuto del file :attribute è fallita.',
    'file_security_validation_failed' => 'Il file :attribute ha fallito la convalida di sicurezza.',
    'file_validation_failed' => 'La convalida del file :attribute è fallita.',
    'invalid_file_path' => 'Percorso file non valido per :attribute. Eliminazione file non consentita.',

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
